import { collectRegisteredSuggestionLabelsInOrder } from "../suggestion-language-model.js";

const MAX_TRANSCRIPT_MESSAGES = 4;
const MAX_MESSAGE_CHARS = 500;
const MAX_FINAL_ANSWER_CHARS = 1200;
const MAX_TOOL_DEFINITIONS = 30;

function compact(value, maxChars) {
  const text = String(value ?? "").replace(/\s+/g, " ").trim();
  return text.length > maxChars ? `${text.slice(0, maxChars)}…` : text;
}

function safeListDefinitions(deps) {
  try {
    const list = deps?.tools?.listDefinitions?.();
    return Array.isArray(list) ? list : [];
  } catch {
    return [];
  }
}

function isVisibleSuggestionTool(tool) {
  return !tool?.disabled && !tool?.hiddenFromCustomerPlanner;
}

function buildTranscript(messages) {
  return Array.isArray(messages)
    ? messages
        .slice(-MAX_TRANSCRIPT_MESSAGES)
        .map((m) => `${m.role}: ${compact(m.content, MAX_MESSAGE_CHARS)}`)
        .join("\n")
    : "";
}

function latestUserMessage(messages) {
  return (
    [...(Array.isArray(messages) ? messages : [])]
      .reverse()
      .find((m) => m?.role === "user")?.content ?? ""
  );
}

export function buildSuggestionContext({ deps = {}, state = {} } = {}) {
  const registeredToolDefinitions = safeListDefinitions(deps)
    .filter(isVisibleSuggestionTool)
    .slice(0, MAX_TOOL_DEFINITIONS);

  const registeredSuggestionLabels =
    collectRegisteredSuggestionLabelsInOrder(registeredToolDefinitions);

  return {
    deps,
    state,
    suggestionSource: String(deps.suggestionSource ?? "none").trim() || "none",
    registeredToolDefinitions,
    registeredSuggestionLabels,
    transcript: buildTranscript(state.messages),
    latestUserMessage: String(latestUserMessage(state.messages)),
    finalAnswer: compact(state.finalAnswer, MAX_FINAL_ANSWER_CHARS),
    toolResults: Array.isArray(state.toolResults) ? state.toolResults.slice(-6) : [],
  };
}