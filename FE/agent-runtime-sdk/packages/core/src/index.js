export class AgentRuntimeError extends Error {
  constructor(message, code = "runtime_error", details = undefined, options = {}) {
    super(message, { cause: options.cause });

    this.name = "AgentRuntimeError";
    this.code = code;
    this.details = details;
    this.retryable = Boolean(options.retryable);
    this.status = options.status;
  }

  toJSON() {
    return {
      name: this.name,
      code: this.code,
      message: this.message,
      details: this.details,
      retryable: this.retryable,
      status: this.status,
    };
  }
}

export class CircuitOpenError extends AgentRuntimeError {
  constructor(toolName, options = {}) {
    super(
      `Circuit breaker open for tool: ${toolName}`,
      "circuit_open",
      { toolName },
      { ...options, retryable: true },
    );

    this.name = "CircuitOpenError";
  }
}

export class ValidationError extends AgentRuntimeError {
  constructor(message, details, options = {}) {
    super(message, "validation_failed", details, {
      ...options,
      retryable: false,
    });

    this.name = "ValidationError";
  }
}

export class ToolExecutionError extends AgentRuntimeError {
  constructor(toolName, message, details, options = {}) {
    super(message, "tool_execution_failed", { toolName, ...details }, options);

    this.name = "ToolExecutionError";
  }
}

export class TimeoutError extends AgentRuntimeError {
  constructor(message = "Operation timed out", details, options = {}) {
    super(message, "timeout", details, {
      ...options,
      retryable: true,
    });

    this.name = "TimeoutError";
  }
}

export function isAgentRuntimeError(error) {
  return error instanceof AgentRuntimeError;
}

export function normalizeRuntimeError(error) {
  if (isAgentRuntimeError(error)) return error;

  return new AgentRuntimeError(
    error?.message || String(error ?? "Unknown runtime error"),
    "unknown_error",
    undefined,
    { cause: error },
  );
}

export {
  createLangChainLlmDeps,
  createLangChainRagEmbedderDeps,
} from "./langchain-providers.js";

export {
  unwrapSuggestionsEnvelope,
  unwrapSyntheticReplyEnvelope,
} from "./model-json.js";