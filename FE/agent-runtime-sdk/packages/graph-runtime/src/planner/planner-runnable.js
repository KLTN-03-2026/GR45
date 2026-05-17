import { PlannerOutputSchema } from "@fe-agent/shared-zod-schemas";

import { shouldPreferPdfAfterBarrenTools } from "../intent/intent-classifier.js";
import { errorText, isFunction, textOrEmpty, valueOr } from "../value.js";

export function createPlannerRunnable(deps) {
  return async (state) => {
    const lastUser = [...state.messages].reverse().find((m) => m.role === "user");
    const userMessage = textOrEmpty(lastUser?.content);

    let plan = null;

    if (isFunction(deps.prePlannerHook)) {
      try {
        const fastPlan = deps.prePlannerHook({ userMessage, state });
        if (fastPlan) {
          plan = PlannerOutputSchema.parse(fastPlan);
        }
      } catch (hookErr) {
        console.warn("[planner] prePlannerHook failed:", errorText(hookErr));
      }
    }

    if (!plan) {
      const ragHint = shouldPreferPdfAfterBarrenTools(
        userMessage,
        valueOr(deps.intentClassifierOptions, {}),
      );
      plan = PlannerOutputSchema.parse({
        goal: userMessage.slice(0, 200),
        steps: [],
        stopCondition: "ask_clarification",
        confidence: ragHint ? 0.55 : 0.2,
        toolCalls: [],
        needs_grounding: ragHint,
        needs_rag_fallback: ragHint,
      });
    }

    if (isFunction(deps.planPostProcessor)) {
      plan = PlannerOutputSchema.parse(
        deps.planPostProcessor({ userMessage, plan, state })
      );
    }

    deps.bus?.emit("stage", { stage: "planner", status: "exit" });

    return {
      plan,
      signals: {
        ...state.signals,
        needs_grounding: plan.needs_grounding,
        confidence: plan.confidence,
      },
      journal: [
        {
          type: "planner",
          timestamp: Date.now(),
          payload: { goal: plan.goal },
        },
      ],
    };
  };
}
