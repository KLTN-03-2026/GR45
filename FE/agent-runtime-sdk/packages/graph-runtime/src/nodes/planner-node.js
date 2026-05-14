import { journalEntry } from "@fe-agent/observability";
import { PlannerOutputSchema } from "@fe-agent/shared-zod-schemas";

function getLastUserMessage(messages = []) {
  return [...messages].reverse().find((m) => m?.role === "user")?.content ?? "";
}

function createFallbackPlan(userMessage) {
  return PlannerOutputSchema.parse({
    goal: userMessage || "deliver_best_effort_answer",
    steps: [],
    stopCondition: "deliver_best_effort_answer",
    confidence: 0.35,
    toolCalls: [],
    needs_grounding: true,
    needs_rag_fallback: true,
  });
}

function createPdfOnlyPlan(userMessage) {
  return PlannerOutputSchema.parse({
    goal: userMessage || "faq_from_pdf",
    steps: [],
    stopCondition: "rag_pdf_only",
    confidence: 0.95,
    toolCalls: [],
    needs_grounding: true,
    needs_rag_fallback: true,
  });
}

export function createPlannerNodeHandler({
  graphDependencies,
  plannerRunnable,
  isQuestionAnswerPdfOnly,
}) {
  return async function plannerGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "planner",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const userMessage = getLastUserMessage(graphState.messages);
    const plannerLoop = Number(graphState.signals?.planner_loop ?? 0) + 1;

    try {
      const plannerNodeOutput = isQuestionAnswerPdfOnly
        ? {
            plan: createPdfOnlyPlan(userMessage),
            signals: {
              ...graphState.signals,
              needs_grounding: true,
              confidence: 0.95,
            },
            journal: [
              journalEntry("planner", {
                qaPdfOnly: true,
                goal: userMessage.slice(0, 120),
              }),
            ],
          }
        : await plannerRunnable({
            ...graphState,
            messages: Array.isArray(graphState.messages)
              ? graphState.messages.map((m) => ({
                  role: m.role,
                  content: m.content,
                }))
              : [],
            signals: graphState.signals ?? {},
          });

      const plan = PlannerOutputSchema.parse(plannerNodeOutput.plan);

      return {
        plan,
        signals: {
          ...graphState.signals,
          ...plannerNodeOutput.signals,
          confidence: plan.confidence,
          needs_grounding: plan.needs_grounding,
          planner_loop: plannerLoop,
          needs_replan: false,
          rag_fallback: false,
          replan_terminal: false,
        },
        journal: [
          ...(Array.isArray(plannerNodeOutput.journal)
            ? plannerNodeOutput.journal
            : []),
        ],
      };
    } catch (error) {
      const fallbackPlan = createFallbackPlan(userMessage);

      return {
        plan: fallbackPlan,
        signals: {
          ...graphState.signals,
          confidence: fallbackPlan.confidence,
          needs_grounding: true,
          planner_loop: plannerLoop,
          needs_replan: false,
          rag_fallback: true,
          replan_terminal: false,
        },
        journal: [
          journalEntry("planner_error", {
            error: String(error?.message ?? error).slice(0, 300),
          }),
        ],
      };
    } finally {
      graphDependencies.bus?.emit("stage", {
        stage: "planner",
        status: "exit",
        correlationId: graphState.correlationId,
      });
    }
  };
}