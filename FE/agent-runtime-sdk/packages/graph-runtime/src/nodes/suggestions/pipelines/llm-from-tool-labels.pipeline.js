import { createSuggestionResult, uniqueStrings } from "../create-suggestion-result.js";
import { parseSuggestedReplyChipsFromToolResults } from "../parsers/tool-result-chips.parser.js";
import { parseSuggestionLabelsFromReplyEnvelope } from "../parsers/reply-envelope.parser.js";
import { generateLlmSuggestions } from "../services/llm-suggestion.service.js";
import { rankRegisteredSuggestionLabelsWithLlm } from "../services/suggestion-ranking.service.js";

function result(source, suggestions, limit = 5) {
  return {
    source,
    suggestions: uniqueStrings(suggestions, limit),
  };
}

export async function executeLlmFromToolLabelsPipeline(ctx) {
  const fromTool = parseSuggestedReplyChipsFromToolResults(ctx.toolResults);
  if (fromTool.length) {
    return result("tool_suggested_reply_chips_vi", fromTool, 3);
  }

  const fromReply = parseSuggestionLabelsFromReplyEnvelope(ctx.finalAnswer);
  if (fromReply.length) {
    return result("reply_options_envelope", fromReply, 3);
  }

  if (typeof ctx.deps.llm?.completeJson !== "function") {
    return result("llm_from_tool_labels_no_llm", []);
  }

  try {
    if (!ctx.registeredSuggestionLabels.length) {
      const freeform = await generateLlmSuggestions({
        languageModel: ctx.deps.llm,
        registeredToolDefinitions: ctx.registeredToolDefinitions,
        latestUserMessage: ctx.latestUserMessage,
        finalAnswer: ctx.finalAnswer,
        transcript: ctx.transcript,
      });

      return result(
        freeform.length
          ? "llm_from_tool_labels_freeform"
          : "llm_from_tool_labels_freeform_empty",
        freeform,
        3,
      );
    }

    const ranked = await rankRegisteredSuggestionLabelsWithLlm({
      languageModel: ctx.deps.llm,
      registeredSuggestionLabels: ctx.registeredSuggestionLabels,
      latestUserMessage: ctx.latestUserMessage,
      finalAnswer: ctx.finalAnswer,
      transcript: ctx.transcript,
    });

    return result("llm_from_tool_labels", ranked, 5);
  } catch {
    return result("llm_from_tool_labels_error", []);
  }
}