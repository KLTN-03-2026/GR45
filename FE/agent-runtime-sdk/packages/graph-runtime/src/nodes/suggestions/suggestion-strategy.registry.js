import { UnknownSuggestionStrategy } from "./strategies/unknown.strategy.js";
import { NoneSuggestionStrategy } from "./strategies/none.strategy.js";
import { ToolLabelsSuggestionStrategy } from "./strategies/tool-labels.strategy.js";
import { LlmSuggestionStrategy } from "./strategies/llm.strategy.js";
import { LlmFromToolLabelsSuggestionStrategy } from "./strategies/llm-from-tool-labels.strategy.js";
import { ReplyEnvelopeSuggestionStrategy } from "./strategies/reply-envelope.strategy.js";

const unknownSuggestionStrategySingleton = new UnknownSuggestionStrategy();

const registeredStrategiesBySource = new Map([
  ["none", new NoneSuggestionStrategy()],
  ["tool_labels", new ToolLabelsSuggestionStrategy()],
  ["llm", new LlmSuggestionStrategy()],
  ["llm_from_tool_labels", new LlmFromToolLabelsSuggestionStrategy()],
  ["reply_envelope", new ReplyEnvelopeSuggestionStrategy()],
]);

const sourceAliases = new Map([
  ["", "none"],
  ["off", "none"],
  ["false", "none"],
  ["disabled", "none"],

  ["tool-labels", "tool_labels"],
  ["tools", "tool_labels"],
  ["labels", "tool_labels"],

  ["llm-from-tool-labels", "llm_from_tool_labels"],
  ["llm_tool_labels", "llm_from_tool_labels"],

  ["reply-envelope", "reply_envelope"],
  ["options", "reply_envelope"],
]);

export function normalizeSuggestionSource(value) {
  const raw = String(value ?? "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "_");

  return sourceAliases.get(raw) ?? raw ?? "none";
}

/** @readonly */
export const suggestionStrategyRegistry = {
  resolve(suggestionSourceRaw) {
    const key = normalizeSuggestionSource(suggestionSourceRaw) || "none";

    return (
      registeredStrategiesBySource.get(key) ??
      unknownSuggestionStrategySingleton
    );
  },

  has(suggestionSourceRaw) {
    return registeredStrategiesBySource.has(
      normalizeSuggestionSource(suggestionSourceRaw),
    );
  },

  keys() {
    return [...registeredStrategiesBySource.keys()];
  },
};