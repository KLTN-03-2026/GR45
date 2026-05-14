export {
  createLangChainLlmDeps,
  createLangChainRagEmbedderDeps,
  unwrapSuggestionsEnvelope,
  unwrapSyntheticReplyEnvelope,
} from "@fe-agent/core";

export {
  compileAgentGraph,
  createInitialAgentState,
} from "@fe-agent/graph-runtime";
export { createRuntimeBus } from "@fe-agent/streaming";
export { ToolRegistry } from "@fe-agent/tools";
export { SessionFacades, ensureSessionSnapshot } from "@fe-agent/memory";

export {
  ingestPdfToVectorCollection,
  ingestPdfBufferToVectorCollection,
  extractPdfTextFromBuffer,
  retrieveContextForQuery,
  chunkText,
  materializeChunksFromHits,
} from "@fe-agent/rag";

export {
  PlannerOutputSchema,
  RuntimeConfigSchema,
  ChatMessageSchema,
  SessionSnapshotSchema,
} from "@fe-agent/shared-zod-schemas";

export { HttpSessionProvider } from "@fe-agent/adapters-session-http";
export { HttpVectorProvider } from "@fe-agent/adapters-vector-http";

export {
  bearerHeadersFromStorage,
  createAuthenticatedHttpVectorProvider,
  createBrowserSessionProvider,
  resolveBrowserAgentRuntimeConfig,
} from "./runtime-defaults.js";
