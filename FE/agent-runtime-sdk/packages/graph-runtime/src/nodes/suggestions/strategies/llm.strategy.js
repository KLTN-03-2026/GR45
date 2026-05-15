import { createSuggestionResult } from "../create-suggestion-result.js";
import { generateLlmSuggestions } from "../services/llm-suggestion.service.js";
import { SuggestionStrategy } from "./base.strategy.js";

/** Freeform suggestions (≤3): LLM với transcript + tin nhắn gần + finalAnswer. */
export class LlmSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("llm_error");
  }

  /** @inheritdoc */
  async run(context) {
    const uniqueCappedSuggestions = await generateLlmSuggestions({
      languageModel: context.deps.llm,
      registeredToolDefinitions: context.registeredToolDefinitions,
      latestUserMessage: context.latestUserMessage,
      finalAnswer: context.finalAnswer,
      transcript: context.transcript,
    });

    const source = uniqueCappedSuggestions.length ? "llm" : "llm_empty";

    return createSuggestionResult({
      source,
      suggestions: uniqueCappedSuggestions,
    });
  }
}
