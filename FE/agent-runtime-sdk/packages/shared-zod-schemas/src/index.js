import { z } from "zod";

export const RoleEnum = z.enum(["system", "user", "assistant", "tool"]);

export const ChatMessageSchema = z.object({
  id: z.string().min(1),
  role: RoleEnum,
  content: z.string(),
  meta: z.record(z.unknown()).optional(),
});

export const ToolDefinitionSchema = z.object({
  name: z.string().regex(/^[a-zA-Z0-9_-]{1,64}$/),
  description: z.string().min(1),
  jsonSchema: z.record(z.unknown()),
  tier: z.enum(["safe", "sensitive"]).default("safe"),
  parallelism: z.enum(["serial", "parallel"]).default("serial"),
});

export const PlannerStepSchema = z.object({
  id: z.string(),
  intent: z.string(),
  rationale: z.string(),
  candidates: z.array(z.string()),
  fallback: z.enum(["rag", "ask_user", "abort", "retry"]).optional(),
});

export const PlannerOutputSchema = z.object({
  goal: z.string(),
  hypothesis: z.string().optional(),
  steps: z.array(PlannerStepSchema),
  stopCondition: z.string(),
  confidence: z.number().min(0).max(1),
  toolCalls: z
    .array(
      z.object({
        toolName: z.string(),
        rationale: z.string(),
        arguments: z.record(z.unknown()).default({}),
        confirmed: z.boolean().optional(),
      })
    )
    .default([]),
  needs_grounding: z.boolean().default(false),
  needs_rag_fallback: z.boolean().default(false),
});

export const SessionSnapshotSchema = z.object({
  sessionId: z.string(),
  updatedAt: z.string(),
  messages: z.array(ChatMessageSchema),
  workflow: z.record(z.unknown()).optional(),
});

export const RuntimeSignalsSchema = z
  .object({
    confidence: z.number().min(0).max(1).default(0.5),
    needs_grounding: z.boolean().default(false),
    tool_fail_streak: z.number().int().nonnegative().default(0),
    needs_replan: z.boolean().default(false),
    rag_fallback: z.boolean().default(false),
    replan_terminal: z.boolean().default(false),
    planner_loop: z.number().int().nonnegative().default(0),
    intent: z.string().optional(),
    last_error: z.string().optional(),
  })
  .default({});

export const ToolCallSchema = z.object({
  id: z.string(),
  toolName: z.string(),
  arguments: z.record(z.unknown()).default({}),
  confirmed: z.boolean().optional(),
});

export const ToolExecutionResultSchema = z.object({
  callId: z.string(),
  toolName: z.string(),
  ok: z.boolean(),
  data: z.unknown().optional(),
  error: z.string().optional(),
  startedAt: z.number(),
  finishedAt: z.number(),
});

export const RagChunkSchema = z.object({
  id: z.string(),
  text: z.string(),
  score: z.number(),
  source: z.string().optional(),
  metadata: z.record(z.unknown()).optional(),
});

export const ExecutionJournalEntrySchema = z.object({
  type: z.string(),
  timestamp: z.number(),
  payload: z.record(z.unknown()).optional(),
});

export const AgentGraphStateSnapshotSchema = z.object({
  sessionId: z.string(),
  correlationId: z.string(),
  messages: z.array(ChatMessageSchema),
  signals: RuntimeSignalsSchema,
  plan: PlannerOutputSchema.optional(),
  activeStepIndex: z.number().int().nonnegative().optional(),
  pendingToolCalls: z.array(ToolCallSchema).default([]),
  completedToolCalls: z.array(z.string()).default([]),
  toolResults: z.array(ToolExecutionResultSchema).default([]),
  ragContext: z.array(RagChunkSchema).default([]),
  observations: z.array(z.record(z.unknown())).default([]),
  finalAnswer: z.string().optional(),
  journal: z.array(ExecutionJournalEntrySchema).default([]),
});

export const ChatStateSchema = AgentGraphStateSnapshotSchema.partial({
  correlationId: true,
}).extend({
  sessionId: z.string(),
});

export const RuntimeConfigSchema = z.object({
  maxPlannerLoops: z.number().int().positive().default(6),
  maxToolRetries: z.number().int().nonnegative().default(2),
  defaultToolTimeoutMs: z.number().int().positive().default(15000),
  circuitBreakerThreshold: z.number().int().positive().default(5),
  enableParallelTools: z.boolean().default(false),
  requireSensitiveToolConfirmation: z.boolean().default(true),
  stream: z
    .object({
      sseUrl: z.string().url().optional(),
      websocketUrl: z.string().url().optional(),
    })
    .optional(),
});

export const VectorUpsertPayloadSchema = z.object({
  collection: z.string(),
  items: z.array(
    z.object({
      id: z.string(),
      vector: z.array(z.number()).min(1),
      metadata: z.record(z.unknown()).optional(),
    })
  ),
});

export const VectorQueryPayloadSchema = z.object({
  collection: z.string(),
  vector: z.array(z.number()).min(1),
  topK: z.number().int().positive().max(100),
  filter: z.record(z.unknown()).optional(),
});
