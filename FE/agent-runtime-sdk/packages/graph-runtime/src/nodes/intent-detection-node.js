import { journalEntry } from "@fe-agent/observability";

import { classifyIntentText } from "./intent-classifier.js";

function getLastUserMessage(messages = []) {
  return (
    [...messages].reverse().find((m) => m?.role === "user")?.content ?? ""
  );
}

export function createIntentDetectionNode(graphDependencies) {
  return async function intentDetectionGraphNode(graphState) {
    const text = getLastUserMessage(graphState.messages);
    const { intent, isOperational, isPolicyQuestion, isMoneyRisk } =
      classifyIntentText(text, graphDependencies.intentClassifierOptions);

    graphDependencies.bus?.emit("stage", {
      stage: "intent_detection",
      status: "exit",
      correlationId: graphState.correlationId,
    });

    return {
      signals: {
        ...graphState.signals,
        intent,
        needs_grounding: Boolean(
          graphState.signals?.needs_grounding ||
            (isPolicyQuestion && !isOperational),
        ),
      },
      journal: [
        journalEntry("intent", {
          intent,
          isOperational,
          isPolicyQuestion,
          isMoneyRisk,
          preview: text.slice(0, 120),
        }),
      ],
    };
  };
}
