import { createSuggestionResult } from "../create-suggestion-result.js";

/**
 * Fail-safe harness cho mọi strategy — không ném exception ra graph.
 */
export class SuggestionStrategy {
  /**
   * @param {string} errorJournalSource
   */
  constructor(errorJournalSource = "suggestion_strategy_error") {
    this.errorJournalSource = errorJournalSource;
  }

  /**
   * @param {unknown} context
   */
  async execute(context) {
    try {
      return await this.run(context);
    } catch (error) {
      return createSuggestionResult({
        source: this.errorJournalSource,
        suggestions: [],
        extra: {
          error_name: error?.name,
          error_message: String(error?.message ?? error ?? "").slice(0, 300),
        },
      });
    }
  }

  /**
   * @param {unknown} context
   */
  async run(context) {
    void context;
    throw new Error("SuggestionStrategy.run not implemented");
  }
}