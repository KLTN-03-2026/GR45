import { PlannerOutputSchema } from "@fe-agent/shared-zod-schemas";

function normalizePlannerLlmJson(raw) {
  try {
    const o = JSON.parse(raw);
    if (!o || typeof o !== "object") return raw;

    if (!Array.isArray(o.toolCalls) && Array.isArray(o.tool_calls)) {
      o.toolCalls = o.tool_calls
        .map((x) => {
          if (!x || typeof x !== "object") return null;

          const name =
            x.toolName ??
            x.tool_name ??
            x.name ??
            (typeof x.function?.name === "string" ? x.function.name : null);
          let args =
            typeof x.arguments === "object" && x.arguments !== null
              ? x.arguments
              : {};
          if (typeof x.arguments === "string") {
            try {
              args = JSON.parse(x.arguments);
            } catch {
              args = {};
            }
          }
          const rationale = typeof x.rationale === "string" ? x.rationale : "";

          if (typeof name !== "string" || !name.trim()) return null;

          return { toolName: name.trim(), rationale, arguments: args };
        })
        .filter(Boolean);
      delete o.tool_calls;
    }

    return JSON.stringify(o);
  } catch {
    return raw;
  }
}

export function buildPlannerPrompt(input) {
  return [
    "You are the planner for a frontend enterprise agent runtime.",
    "Return STRICT JSON only — no markdown — matching:",
    JSON.stringify(
      {
        goal: "string",
        hypothesis: "string?",
        steps: [
          {
            id: "string",
            intent: "string",
            rationale: "string",
            candidates: ["tool_name"],
            fallback: "rag|ask_user|abort|retry?",
          },
        ],
        stopCondition: "string",
        confidence: "0-1 number",
        toolCalls: [
          { toolName: "string", rationale: "string", arguments: {} },
        ],
        needs_grounding: "boolean",
        needs_rag_fallback: "boolean",
      },
      null,
      2
    ),
    "",
    "Rules:",
    "- Only choose toolName values that exist in the tool catalog.",
    input.domainInstructions ? ["", input.domainInstructions].join("\n") : "",
    "",
    "Tool catalog:",
    input.toolCatalog,
    "",
    "Conversation summary:",
    input.historySummary,
    "",
    "Latest user message:",
    input.userMessage,
  ].join("\n");
}

function compactSchema(jsonSchema) {
  if (!jsonSchema || typeof jsonSchema !== "object") return {};
  const props = jsonSchema.properties;
  if (!props || typeof props !== "object") return {};
  const required = new Set(Array.isArray(jsonSchema.required) ? jsonSchema.required : []);
  const out = {};
  for (const [key, val] of Object.entries(props)) {
    const type = val?.type ?? "any";
    const req = required.has(key) ? "*" : "?";
    const desc = typeof val?.description === "string" ? ` // ${val.description.slice(0, 60)}` : "";
    out[key] = `${req}${type}${desc}`;
  }
  return out;
}

function parseCatalogSpec(description) {
  const raw = String(description ?? "");
  const marker = "Spec:\n";
  const start = raw.indexOf(marker);
  if (start < 0) return null;

  try {
    return JSON.parse(raw.slice(start + marker.length));
  } catch {
    return null;
  }
}

function compactToolDefinition(definition) {
  const spec = parseCatalogSpec(definition.description);
  const compactParams = compactSchema(definition.jsonSchema);

  if (!spec || typeof spec !== "object") {
    return {
      name: definition.name,
      description: String(definition.description ?? "").slice(0, 220),
      slots: Object.keys(compactParams).slice(0, 12),
    };
  }

  const requiredSlots = Array.isArray(spec.requiredSlots)
    ? spec.requiredSlots.slice(0, 8)
    : [];
  const optionalSlots = Array.isArray(spec.optionalSlots)
    ? spec.optionalSlots.slice(0, 10)
    : [];
  const conditionalRequiredSlots =
    spec.conditionalRequiredSlots && typeof spec.conditionalRequiredSlots === "object"
      ? Object.fromEntries(
          Object.entries(spec.conditionalRequiredSlots).slice(0, 5),
        )
      : undefined;

  return {
    name: definition.name,
    summary:
      typeof spec.shortDescription === "string"
        ? spec.shortDescription
        : String(definition.name).replace(/_/g, " "),
    authPolicy: spec.authPolicy,
    requiredSlots,
    optionalSlots,
    conditionalRequiredSlots,
    confirmationRequired: Boolean(spec.confirmationRequired ?? spec.confirm),
  };
}

export function createPlannerRunnable(deps) {
  return async (state) => {
    const lastUser = [...state.messages].reverse().find((m) => m.role === "user");
    const userMessage = lastUser?.content ?? "";

    let plan = null;

    // Fast-path: prePlannerHook (regex-based) can short-circuit the LLM call.
    if (typeof deps.prePlannerHook === "function") {
      try {
        const fastPlan = deps.prePlannerHook({ userMessage, state });
        if (fastPlan) {
          plan = PlannerOutputSchema.parse(fastPlan);
        }
      } catch (hookErr) {
        console.warn(
          "[planner] prePlannerHook failed, falling back to LLM:",
          hookErr?.message
        );
      }
    }

    if (!plan) {
      const catalog = JSON.stringify(
        deps.tools.listDefinitions().map(compactToolDefinition),
        null,
        2
      );

      // Exclude the current user message (last entry) to avoid duplication with userMessage field.
      const historySummary = state.messages
        .slice(-9, -1)
        .map((m) => `${m.role}: ${m.content}`)
        .join("\n");

      const prompt = buildPlannerPrompt({
        userMessage,
        toolCatalog: catalog,
        historySummary,
        domainInstructions: deps.domainInstructions,
      });

      const raw = await deps.llm.completeJson(prompt);
      const rawNormalized = normalizePlannerLlmJson(raw);

      try {
        plan = PlannerOutputSchema.parse(JSON.parse(rawNormalized));
      } catch (parseErr) {
        console.warn(
          "[planner] LLM output parse failed, using fallback plan:",
          parseErr?.message
        );
        plan = PlannerOutputSchema.parse({
          goal: userMessage,
          steps: [],
          stopCondition: "deliver_best_effort_answer",
          confidence: 0.35,
          toolCalls: [],
          needs_grounding: true,
          needs_rag_fallback: true,
        });
      }
    }

    if (typeof deps.planPostProcessor === "function") {
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
