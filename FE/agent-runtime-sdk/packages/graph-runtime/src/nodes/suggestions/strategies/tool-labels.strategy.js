import { createSuggestionResult } from "../create-suggestion-result.js";
import { parseSuggestedReplyChipsFromToolResults } from "../parsers/tool-result-chips.parser.js";
import { SuggestionStrategy } from "./base.strategy.js";

/**
 * Map between user-intent regex (on normalized customer message + final answer)
 * and label-name regex (on normalized suggestionLabels of registered tools).
 * Score increments by 3 when both regexes hit for the same suggestion label.
 */
const INTENT_GROUPS = [
  // Trip/route search & schedule
  {
    re: /tim|kiem|chuyen|tuyen|lich|gio|nha xe|limousine|ve xe|xe khach/,
    label: /chuyen|tuyen|ve|ghe|nha xe|lich|gio|tim|loc|seat/,
  },
  // Ticket management (my tickets, cancel, transfer)
  {
    re: /ve cua toi|lich su|booking|dat ve|huy ve|doi ve/,
    label: /ve|huy|doi|hoan|booking|dat|lich su/,
  },
  // Payment
  {
    re: /thanh toan|payment|hoa don|chuyen khoan/,
    label: /thanh toan|payment|hoa don|voucher/,
  },
  // Refund
  { re: /hoan tien|refund|huy/, label: /hoan|refund|huy/ },
  // Voucher / promo
  {
    re: /voucher|ma giam|khuyen mai|giam gia/,
    label: /voucher|giam|khuyen mai/,
  },
  // Loyalty points
  {
    re: /diem (thuong|tich luy|hang)|loyalty/,
    label: /diem|thuong|loyalty|hang|doi diem/,
  },
  // Live support / human
  {
    re: /ho tro|nhan vien|admin|lien he|tu van|tong dai|live support/,
    label: /ho tro|admin|nhan vien|lien he|tu van/,
  },
  // Account / profile (only when user really asks about account)
  {
    re: /tai khoan|ho so|profile|dang nhap|dang xuat|cap nhat|email|sdt|so dien thoai|anh dai dien/,
    label: /tai khoan|ho so|email|sdt|profile|dang nhap|dang xuat|anh dai dien|avatar|cap nhat/,
  },
  // Vehicle tracking / location
  {
    re: /vi tri|tracking|xe (dang )?o dau|theo doi|xe da toi/,
    label: /tracking|vi tri|theo doi|toa do/,
  },
  // Map / pickup-dropoff
  {
    re: /diem don|diem tra|tram dung|gan day|nearest|nearby/,
    label: /diem don|diem tra|tram|gan|nearby/,
  },
];

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
const SUPPORT_LABEL = "Liên hệ hỗ trợ";

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

/** Luôn ưu tiên hiển thị nếu có trong catalog (tránh chỉ 5 nhãn đầu danh sách đăng ký). */
const PINNED_ROUTE_CHIPS = ["Tìm tuyến khác", "Đặt vé", "Chọn nhà xe"];

function mergePinnedRouteChips(suggestions, allLabels) {
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

  for (const p of PINNED_ROUTE_CHIPS) {
    push(p);
    if (out.length >= MAX_PICKED) return out;
  }

  for (const s of suggestions) {
    push(s);
    if (out.length >= MAX_PICKED) return out;
  }

  return out;
}

function withSupportFallback(labels, allLabels) {
  const support = allLabels.find((label) => normalize(label) === normalize(SUPPORT_LABEL));
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

    const scored = allLabels.map((label) => {
      const labelNormalized = normalize(label);
      let score = 0;
      for (const group of INTENT_GROUPS) {
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
      const merged = mergePinnedRouteChips(
        withSupportFallback(relevant, allLabels),
        allLabels,
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

    const merged = mergePinnedRouteChips(
      withSupportFallback(allLabels, allLabels),
      allLabels,
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
