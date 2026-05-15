import { createSuggestionResult } from "../create-suggestion-result.js";
import { parseSuggestionLabelsFromReplyEnvelope } from "../parsers/reply-envelope.parser.js";
import { SuggestionStrategy } from "./base.strategy.js";

/**
 * Chỉ lấy gợi ý từ JSON reply envelope trên `finalAnswer`.
 *
 * Chiến lược tùy chọn — đăng ký khi muốn `suggestionSource: "reply_envelope"` trong tương lai;
 * không bật mặc định trong graph hiện tại (backward compatible).
 */
export class ReplyEnvelopeSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("reply_options_envelope_error");
  }

  /** @inheritdoc */
  async run(context) {
    const labels = parseSuggestionLabelsFromReplyEnvelope(context.finalAnswer);
    const capped = labels.slice(0, 3);
    const source =
      capped.length > 0
        ? "reply_options_envelope"
        : "reply_options_envelope_empty";

    return createSuggestionResult({ source, suggestions: capped });
  }
}
