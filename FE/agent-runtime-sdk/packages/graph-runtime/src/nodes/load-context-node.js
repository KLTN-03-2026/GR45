import { journalEntry } from "../journal.js";
import { errorText, textOrEmpty, valueOr } from "../value.js";

const DEFAULT_MAX_CONTEXT_MESSAGES = 20;

function dedupeMessages(messages = []) {
  const seen = new Set();

  return messages.filter((message) => {
    const key = message.id
      ? `id:${message.id}`
      : `${message.role}:${textOrEmpty(message.content).slice(0, 500)}`;

    if (seen.has(key)) return false;

    seen.add(key);
    return true;
  });
}

export function createLoadContextNode(graphDependencies) {
  return async function loadContextGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "load_context",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const maxMessages =
      valueOr(graphDependencies.config?.maxContextMessages, DEFAULT_MAX_CONTEXT_MESSAGES);

    let conversationMessages = Array.isArray(graphState.messages)
      ? graphState.messages
      : [];

    let loaded = false;
    let error = null;

    try {
      if (graphDependencies.sessions) {
        const snapshot = await graphDependencies.sessions.load(
          graphState.sessionId,
        );

        if (
          Array.isArray(snapshot.messages) &&
          snapshot.messages.length > 0 &&
          conversationMessages.length <= 1
        ) {
          conversationMessages = [
            ...snapshot.messages,
            ...conversationMessages,
          ];
          loaded = true;
        }
      }
    } catch (err) {
      error = err;
    }

    conversationMessages = dedupeMessages(conversationMessages).slice(
      -maxMessages,
    );

    graphDependencies.bus?.emit("stage", {
      stage: "load_context",
      status: "exit",
      correlationId: graphState.correlationId,
    });

    return {
      messages: conversationMessages,
      journal: [
        journalEntry("load_context", {
          loaded,
          count: conversationMessages.length,
          error: error
            ? errorText(error).slice(0, 300)
            : undefined,
        }),
      ],
    };
  };
}
