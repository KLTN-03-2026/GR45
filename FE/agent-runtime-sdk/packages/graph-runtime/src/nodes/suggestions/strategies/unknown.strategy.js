import { createSuggestionResult } from "../create-suggestion-result.js";
import { SuggestionStrategy } from "./base.strategy.js";

/** `suggestionSource` không được hỗ trợ. */
export class UnknownSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("unknown_suggestion_source_error");
  }

  /** @inheritdoc */
  async run() {
    return createSuggestionResult({
      source: "unknown_suggestion_source",
      suggestions: [],
    });
  }
}
