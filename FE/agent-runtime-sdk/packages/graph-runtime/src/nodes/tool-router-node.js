import { journalEntry } from "../journal.js";
import { errorText, isFunction, isObject, textOrEmpty } from "../value.js";

function safeRandomId() {
  return globalThis.crypto?.randomUUID
    ? globalThis.crypto.randomUUID()
    : `call-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function getLastUserMessage(messages = []) {
  return textOrEmpty([...messages].reverse().find((m) => m?.role === "user")?.content);
}

export function createToolRouterNode(graphDependencies) {
  return async function toolRouterGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "tool_router",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const rawUserMsg = textOrEmpty(getLastUserMessage(graphState.messages));

    const toolCalls = Array.isArray(graphState.plan?.toolCalls)
      ? graphState.plan.toolCalls
      : [];

    const skipped = [];
    const pending = [];

    for (const call of toolCalls) {
      const toolName = textOrEmpty(call?.toolName).trim();

      if (!toolName) {
        skipped.push({ toolName: "", reason: "missing_tool_name" });
        continue;
      }

      if (!graphDependencies.tools?.has?.(toolName)) {
        skipped.push({ toolName, reason: "tool_not_registered" });
        continue;
      }

      let argumentsPayload = {
        raw_message: rawUserMsg,
        ...(isObject(call.arguments)
          ? call.arguments
          : {}),
      };

      if (isFunction(graphDependencies.enhanceToolCallArguments)) {
        try {
          argumentsPayload = await graphDependencies.enhanceToolCallArguments({
            llm: graphDependencies.llm,
            toolName,
            rawUserMessage: rawUserMsg,
            argumentsPayload,
            widgetChatSessionKey: graphDependencies.widgetChatSessionKey,
          });
        } catch (error) {
          skipped.push({
            toolName,
            reason: "argument_enhancer_error",
            error: errorText(error).slice(0, 300),
          });
          continue;
        }
      }

      pending.push({
        id: safeRandomId(),
        callId: safeRandomId(),
        toolName,
        arguments: argumentsPayload,
      });
    }

    graphDependencies.bus?.emit("stage", {
      stage: "tool_router",
      status: "exit",
      correlationId: graphState.correlationId,
    });

    return {
      pendingToolCalls: pending,
      journal: [
        journalEntry("tool_router", {
          pending: pending.map((row) => row.toolName),
          skipped,
        }),
      ],
    };
  };
}
