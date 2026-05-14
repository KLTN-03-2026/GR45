import {
  ToolDefinitionSchema,
  RuntimeConfigSchema,
} from "@fe-agent/shared-zod-schemas";
import {
  emitToolEnd,
  emitToolStart,
  anySignal,
  confirmationRequiredResult,
  createTimeoutController,
  openAiToolFromDefinition,
  parseToolArguments,
  resolveCallId,
  toolFailureResult,
  toolSuccessResult,
  ToolCircuitBreaker,
} from "./tool-registry-internal.js";

export class ToolRegistry {
  tools = new Map();
  breakers = new Map();

  constructor(configPartial) {
    this.config = RuntimeConfigSchema.parse(configPartial ?? {});
  }

  register({ definition, argsSchema, execute }) {
    const def = ToolDefinitionSchema.parse(definition);
    if (this.tools.has(def.name)) {
      throw new Error(`Tool already registered: ${def.name}`);
    }
    this.tools.set(def.name, { definition: def, argsSchema, execute });
    this.breakers.set(
      def.name,
      new ToolCircuitBreaker(this.config.circuitBreakerThreshold, 30_000)
    );
  }

  unregister(name) {
    this.tools.delete(name);
    this.breakers.delete(name);
  }

  has(name) {
    return this.tools.has(name);
  }

  listDefinitions() {
    return [...this.tools.values()].map((t) => t.definition);
  }

  toOpenAIToolSubset(names) {
    const defs = [...this.tools.values()].map((t) => t.definition);
    const picked = names ? defs.filter((d) => names.includes(d.name)) : defs;
    return picked.map(openAiToolFromDefinition);
  }

  async execute(call, ctx) {
    const reg = this.tools.get(call.toolName);
    const startedAt = Date.now();
    const callId = resolveCallId(call);

    emitToolStart(ctx, {
      callId,
      toolName: call.toolName,
      arguments: call.arguments ?? {},
    });

    if (!reg) {
      emitToolEnd(ctx, {
        callId,
        toolName: call.toolName,
        startedAt,
        ok: false,
      });
      return toolFailureResult(
        callId,
        call.toolName,
        startedAt,
        `unknown tool: ${call.toolName}`
      );
    }

    const breaker = this.breakers.get(call.toolName);
    try {
      breaker?.beforeCall(call.toolName);
    } catch (circuitErr) {
      emitToolEnd(ctx, { callId, toolName: call.toolName, startedAt, ok: false });
      return toolFailureResult(
        callId,
        call.toolName,
        startedAt,
        circuitErr instanceof Error ? circuitErr.message : "circuit_open"
      );
    }

    try {
      const parsed = parseToolArguments(reg, call, breaker);
      if (!parsed.ok) {
        emitToolEnd(ctx, {
          callId,
          toolName: call.toolName,
          startedAt,
          ok: false,
        });
        return toolFailureResult(callId, call.toolName, startedAt, parsed.error);
      }

      if (ctx.signal?.aborted) throw new DOMException("", "AbortError");

      if (
        reg.definition.tier === "sensitive" &&
        this.config.requireSensitiveToolConfirmation
      ) {
        const confirmed =
          typeof ctx.confirmToolCall === "function"
            ? await ctx.confirmToolCall({
                call,
                definition: reg.definition,
                arguments: parsed.parsed,
              })
            : false;
        if (confirmed !== true) {
          emitToolEnd(ctx, {
            callId,
            toolName: call.toolName,
            startedAt,
            ok: false,
          });
          return confirmationRequiredResult(callId, call.toolName, startedAt);
        }
      }

      const timeout = createTimeoutController(this.config.defaultToolTimeoutMs);
      const signal = anySignal([ctx.signal, timeout.controller.signal]);
      let result;
      try {
        result = await Promise.race([
          reg.execute(parsed.parsed, { ...ctx, signal }),
          timeout.timeoutPromise,
        ]);
      } finally {
        timeout.clear();
      }

      if (result.ok) {
        breaker?.onSuccess();
        emitToolEnd(ctx, {
          callId,
          toolName: call.toolName,
          startedAt,
          ok: true,
        });
        return toolSuccessResult(
          callId,
          call.toolName,
          startedAt,
          result.data
        );
      }

      breaker?.onFailure();
      emitToolEnd(ctx, {
        callId,
        toolName: call.toolName,
        startedAt,
        ok: false,
      });
      return toolFailureResult(callId, call.toolName, startedAt, result.error);
    } catch (e) {
      breaker?.onFailure();
      emitToolEnd(ctx, {
        callId,
        toolName: call.toolName,
        startedAt,
        ok: false,
      });
      const message =
        e instanceof Error ? e.message : `exception: ${String(e)}`;
      return toolFailureResult(callId, call.toolName, startedAt, message);
    }
  }

  async executeMany(calls, ctx) {
    if (calls.length === 0) return [];
    if (!this.config.enableParallelTools) {
      const out = [];
      for (const c of calls) {
        out.push(await this.execute(c, ctx));
      }
      return out;
    }

    const out = [];
    let parallelBatch = [];

    const flushParallelBatch = async () => {
      if (!parallelBatch.length) return;
      out.push(
        ...(await Promise.all(parallelBatch.map((c) => this.execute(c, ctx))))
      );
      parallelBatch = [];
    };

    for (const call of calls) {
      const reg = this.tools.get(call.toolName);
      if (reg?.definition.parallelism === "parallel") {
        parallelBatch.push(call);
        continue;
      }

      await flushParallelBatch();
      out.push(await this.execute(call, ctx));
    }

    await flushParallelBatch();
    return out;
  }
}
