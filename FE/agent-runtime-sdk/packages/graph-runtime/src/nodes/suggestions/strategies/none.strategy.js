import { createSuggestionResult } from "../create-suggestion-result.js";
import { SuggestionStrategy } from "./base.strategy.js";

/** `suggestionSource` none hoặc rỗng. */
export class NoneSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("none_error");
  }

  /** @inheritdoc */
  async run() {
    return createSuggestionResult({ source: "none", suggestions: [] });
  }
}
