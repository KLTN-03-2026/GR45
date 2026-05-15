import { createSuggestionResult } from "../create-suggestion-result.js";
import { parseSuggestedReplyChipsFromToolResults } from "../parsers/tool-result-chips.parser.js";
import { SuggestionStrategy } from "./base.strategy.js";

function normalize(value) {
  return String(value ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

const MAX_PICKED = 5;

/** Chips từ tool/API (free-text) trước, rồi nhãn catalog — giữ tối đa `max`. */
function mergeChipsFirst(chipList, labelList, max) {
  const out = [];
  const seen = new Set();
  const push = (s) => {
    const t = String(s ?? "").trim();
    if (!t) return false;
    const key = normalize(t);
    if (seen.has(key)) return false;
    seen.add(key);
    out.push(t);
    return out.length >= max;
  };
  for (const c of chipList) {
    if (push(c)) return out;
  }
  for (const l of labelList) {
    if (push(l)) return out;
  }
  return out;
}

function mergePinnedChips(suggestions, allLabels, pinnedLabels = []) {
  const out = [];
  const seen = new Set();

  const push = (raw) => {
    const found = allLabels.find((l) => normalize(l) === normalize(raw));
    if (!found) return;
    const key = normalize(found);
    if (seen.has(key)) return;
    seen.add(key);
    out.push(found);
  };

  for (const p of pinnedLabels) {
    push(p);
    if (out.length >= MAX_PICKED) return out;
  }

  for (const s of suggestions) {
    push(s);
    if (out.length >= MAX_PICKED) return out;
  }

  return out;
}

function withSupportFallback(labels, allLabels, supportLabel) {
  const support = supportLabel
    ? allLabels.find((label) => normalize(label) === normalize(supportLabel))
    : null;
  if (!support) return labels.slice(0, MAX_PICKED);

  const picked = labels.slice(0, MAX_PICKED);
  if (picked.some((label) => normalize(label) === normalize(support))) {
    return picked;
  }

  if (picked.length < MAX_PICKED) {
    return [...picked, support];
  }

  return [...picked.slice(0, MAX_PICKED - 1), support];
}

/**
 * Context-aware label picker. Falls back to top-N when no intent group matches.
 */
export class ToolLabelsSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("tools_suggestionLabels_error");
  }

  /** @inheritdoc */
  async run(context) {
    const fromTool = parseSuggestedReplyChipsFromToolResults(
      context.toolResults ?? [],
    );

    const allLabels = [...context.registeredSuggestionLabels];
    if (!allLabels.length) {
      if (fromTool.length) {
        return createSuggestionResult({
          source: "tools_suggestionLabels_empty_tool_chips_only",
          suggestions: mergeChipsFirst(fromTool, [], MAX_PICKED),
        });
      }
      return createSuggestionResult({
        source: "tools_suggestionLabels_empty",
        suggestions: [],
      });
    }

    const contextText = normalize(
      `${context.latestUserMessage ?? ""}\n${context.finalAnswer ?? ""}`,
    );
    const intentGroups = Array.isArray(context.deps?.suggestionIntentGroups)
      ? context.deps.suggestionIntentGroups
      : [];

    /** Chưa có ngữ cảnh — không ghim chip live support để khởi động nhẹ hơn. */
    const userTurnCount = Array.isArray(context.state?.messages)
      ? context.state.messages.filter((m) => m?.role === "user").length
      : 0;
    const suppressSupportAtChatStart = userTurnCount <= 1;

    let pinnedLabels = Array.isArray(context.deps?.pinnedSuggestionLabels)
      ? [...context.deps.pinnedSuggestionLabels]
      : [];
    let supportLabel = context.deps?.supportSuggestionLabel;

    if (suppressSupportAtChatStart && supportLabel) {
      const sn = normalize(supportLabel);
      pinnedLabels = pinnedLabels.filter((p) => normalize(p) !== sn);
      supportLabel = undefined;
    }

    const scored = allLabels.map((label) => {
      const labelNormalized = normalize(label);
      let score = 0;
      for (const group of intentGroups) {
        if (group.re.test(contextText) && group.label.test(labelNormalized)) {
          score += 3;
        }
      }
      return { label, score };
    });

    const relevant = scored
      .filter((row) => row.score > 0)
      .sort((a, b) => b.score - a.score)
      .map((row) => row.label);

    if (relevant.length > 0) {
      const merged = mergePinnedChips(
        withSupportFallback(relevant, allLabels, supportLabel),
        allLabels,
        pinnedLabels,
      );
      const suggestions = fromTool.length
        ? mergeChipsFirst(fromTool, merged, MAX_PICKED)
        : merged;
      return createSuggestionResult({
        source: fromTool.length
          ? "tools_suggestionLabels_context_tool_chips"
          : "tools_suggestionLabels_context",
        suggestions,
      });
    }

    const merged = mergePinnedChips(
      withSupportFallback(allLabels, allLabels, supportLabel),
      allLabels,
      pinnedLabels,
    );
    const suggestions = fromTool.length
      ? mergeChipsFirst(fromTool, merged, MAX_PICKED)
      : merged;
    return createSuggestionResult({
      source: fromTool.length
        ? "tools_suggestionLabels_tool_chips"
        : "tools_suggestionLabels",
      suggestions,
    });
  }
}
