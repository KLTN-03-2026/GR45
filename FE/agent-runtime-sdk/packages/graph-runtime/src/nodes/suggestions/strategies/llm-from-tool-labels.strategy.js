import { createSuggestionResult } from "../create-suggestion-result.js";
import { executeLlmFromToolLabelsPipeline } from "../pipelines/llm-from-tool-labels.pipeline.js";
import { SuggestionStrategy } from "./base.strategy.js";

export class LlmFromToolLabelsSuggestionStrategy extends SuggestionStrategy {
  constructor() {
    super("llm_from_tool_labels_error");
  }

  /** @inheritdoc */
  async run(context) {
    const payload = await executeLlmFromToolLabelsPipeline({
      toolResults: context.toolResults,
      finalAnswer: context.finalAnswer,
      deps: context.deps,
      registeredToolDefinitions: context.registeredToolDefinitions,
      registeredSuggestionLabels: context.registeredSuggestionLabels,
      latestUserMessage: context.latestUserMessage,
      transcript: context.transcript,
    });

    return createSuggestionResult(payload);
  }
}
