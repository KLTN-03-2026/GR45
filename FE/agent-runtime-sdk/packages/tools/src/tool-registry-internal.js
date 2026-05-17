import { CircuitOpenError } from "@fe-agent/core/errors";

export function anySignal(signals) {
  const usableSignals = signals.filter(Boolean);
  if (usableSignals.length === 0) return undefined;
  if (usableSignals.length === 1) return usableSignals[0];
  if (typeof AbortSignal !== "undefined" && typeof AbortSignal.any === "function") {
    return AbortSignal.any(usableSignals);
  }
  const controller = new AbortController();
  const abort = () => controller.abort();
  for (const signal of usableSignals) {
    if (signal.aborted) {
      controller.abort();
      return controller.signal;
    }
    signal.addEventListener("abort", abort, { once: true });
  }
  controller.signal.addEventListener(
    "abort",
    () => {
      for (const signal of usableSignals) {
        signal.removeEventListener("abort", abort);
      }
    },
    { once: true }
  );
  return controller.signal;
}

export function createTimeoutController(ms) {
  const controller = new AbortController();
  let rejectTimeout;
  const timeoutPromise = new Promise((_, reject) => {
    rejectTimeout = reject;
  });
  const timeoutId = setTimeout(() => {
    const error = new Error(`timeout after ${ms}ms`);
    controller.abort(error);
    rejectTimeout(error);
  }, ms);
  return {
    controller,
    timeoutPromise,
    clear: () => clearTimeout(timeoutId),
  };
}

export function resolveCallId(call) {
  return call.id || crypto.randomUUID();
}

export function emitToolStart(ctx, { callId, toolName, arguments: args }) {
  ctx.bus?.emit("tool:start", {
    callId,
    toolName,
    arguments: args ?? {},
  });
}

export function emitToolEnd(ctx, { callId, toolName, startedAt, ok }) {
  ctx.bus?.emit("tool:end", {
    callId,
    toolName,
    durationMs: Date.now() - startedAt,
    ok,
  });
}

export function toolFailureResult(callId, toolName, startedAt, error, data = undefined) {
  const finishedAt = Date.now();
  return {
    callId,
    toolName,
    ok: false,
    error,
    ...(data !== undefined ? { data } : {}),
    startedAt,
    finishedAt,
  };
}

export function confirmationRequiredResult(callId, toolName, startedAt) {
  return toolFailureResult(
    callId,
    toolName,
    startedAt,
    `confirmation_required: ${toolName}`
  );
}

export function toolSuccessResult(callId, toolName, startedAt, data) {
  const finishedAt = Date.now();
  return {
    callId,
    toolName,
    ok: true,
    data,
    startedAt,
    finishedAt,
  };
}

export function parseToolArguments(reg, call, breaker) {
  try {
    return { ok: true, parsed: reg.argsSchema.parse(call.arguments ?? {}) };
  } catch (e) {
    breaker?.onFailure();
    return {
      ok: false,
      error: e instanceof Error ? e.message : "argument validation failed",
    };
  }
}

export function openAiToolFromDefinition(d) {
  return {
    type: "function",
    function: {
      name: d.name,
      description: d.description,
      parameters: d.jsonSchema,
    },
  };
}

export class ToolCircuitBreaker {
  failures = 0;
  openedAt = null;

  constructor(threshold, resetMs) {
    this.threshold = threshold;
    this.resetMs = resetMs;
  }

  beforeCall(toolName) {
    if (this.openedAt != null && Date.now() - this.openedAt > this.resetMs) {
      this.failures = 0;
      this.openedAt = null;
    }
    if (this.failures >= this.threshold && this.openedAt != null) {
      throw new CircuitOpenError(toolName);
    }
  }

  onSuccess() {
    this.failures = 0;
    this.openedAt = null;
  }

  onFailure() {
    this.failures += 1;
    if (this.failures >= this.threshold) {
      this.openedAt = Date.now();
    }
  }
}
