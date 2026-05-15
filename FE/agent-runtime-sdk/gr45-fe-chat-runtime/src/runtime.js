import {
  bearerHeadersFromStorage,
  compileAgentGraph,
  createAuthenticatedHttpVectorProvider,
  createBrowserSessionProvider,
  createInitialAgentState,
  createRuntimeBus,
  resolveBrowserAgentRuntimeConfig,
  ToolRegistry,
} from '@fe-agent/sdk';

import { registerGr45CatalogTools } from './catalog/index.js';
import { gr45FastPlanner } from './gr45-fast-planner.js';
import {
  collectLiveSupportPublicIdsFromToolResults,
  GR45_PLANNER_DOMAIN_INSTRUCTIONS,
  GR45_REPLY_SURFACE_TRANSPORT_VI,
  postProcessGr45Plan,
} from './gr45-planner-policy.js';
import { enhanceGr45TripSearchArguments } from './trip-search-slot-extractor.js';

let defaultRuntime = null;

function defaultStorage() {
  return typeof localStorage !== 'undefined' ? localStorage : null;
}

function defaultFetch() {
  return globalThis.fetch.bind(globalThis);
}

function createDefaultToolRegistry(opts = {}) {
  const tools = new ToolRegistry({
    defaultToolTimeoutMs: opts.defaultToolTimeoutMs ?? 60_000,
    requireSensitiveToolConfirmation: opts.requireSensitiveToolConfirmation ?? true,
  });
  registerGr45CatalogTools(tools);
  if (typeof opts.registerTools === 'function') {
    opts.registerTools(tools);
  }
  return tools;
}

const VALID_ROLES = new Set(['user', 'assistant', 'tool', 'system']);

function normalizeRole(raw) {
  const r = String(raw ?? '').trim();
  return VALID_ROLES.has(r) ? r : 'user';
}

export function createFeChatAgentRuntime(opts = {}) {
  const collectLiveSupportIds =
    typeof opts.collectLiveSupportPublicIds === 'function'
      ? opts.collectLiveSupportPublicIds
      : collectLiveSupportPublicIdsFromToolResults;
  const fetchImpl = opts.fetchImpl || defaultFetch();
  const storage = opts.storage === undefined ? defaultStorage() : opts.storage;
  const config = resolveBrowserAgentRuntimeConfig({
    env: import.meta.env,
    locationOrigin:
      typeof window !== 'undefined' && window.location?.origin ? window.location.origin : '',
    defaults: opts,
  });
  const getAuthHeaders =
    opts.getAuthHeaders ||
    (() => bearerHeadersFromStorage(storage, 'auth.client.token'));
  const tools = opts.tools || createDefaultToolRegistry(opts);
  const sessions =
    opts.sessions !== undefined
      ? opts.sessions
      : storage
        ? createBrowserSessionProvider(storage)
        : undefined;

  // Vector provider and compiled graph are created once per runtime instance.
  const vector =
    opts.vector ||
    createAuthenticatedHttpVectorProvider({
      baseUrl: opts.vectorBaseUrl || config.vectorBaseUrl,
      fetchImpl,
      getHeaders: getAuthHeaders,
    });

  const compiledGraph = compileAgentGraph({
    llm: opts.llm,
    ollamaModel: config.ollamaModel,
    ollamaEmbedModel: config.ollamaEmbedModel,
    ollamaBaseUrl: config.ollamaBaseUrl,
    ollamaKeepAlive: config.ollamaKeepAlive,
    ollamaNumCtx: config.ollamaNumCtx,
    groqKey: config.groqKey,
    groqModel: config.groqModel,
    groqBaseUrl: config.groqBaseUrl,
    huggingFaceKey: config.huggingFaceKey,
    huggingFaceModel: config.huggingFaceModel,
    huggingFaceEndpointUrl: config.huggingFaceEndpointUrl,
    huggingFaceProvider: config.huggingFaceProvider,
    fetchImpl,
    tools,
    bus: opts.bus || createRuntimeBus(),
    sessions,
    // No compile-time signal; per-request signal is passed via graph state _signal.
    checkpointer: false,
    confirmToolCall: opts.confirmToolCall,
    domainInstructions: opts.domainInstructions ?? GR45_PLANNER_DOMAIN_INSTRUCTIONS,
    synthesizerDomainInstructions:
      opts.synthesizerDomainInstructions ?? GR45_REPLY_SURFACE_TRANSPORT_VI,
    planPostProcessor: opts.planPostProcessor ?? postProcessGr45Plan,
    prePlannerHook: opts.prePlannerHook ?? gr45FastPlanner,
    enhanceToolCallArguments:
      opts.enhanceToolCallArguments ?? enhanceGr45TripSearchArguments,
    suggestionSource: opts.suggestionSource || 'tool_labels',
    qaPdfOnly: opts.qaPdfOnly,
    restrictedAnswerSources: opts.restrictedAnswerSources,
    rag: {
      collection: opts.collection || config.collection,
      embedder: opts.embedder,
      vector,
    },
    config: opts.config || {},
  });

  async function invoke(message, invokeOpts = null, signal = undefined) {
    const text = String(message || '').trim();
    if (!text) {
      throw new Error('Thiếu nội dung tin nhắn.');
    }

    const requestOptions = invokeOpts && typeof invokeOpts === 'object' ? invokeOpts : null;
    const history = requestOptions && Array.isArray(requestOptions.history) ? requestOptions.history : [];
    const sessionId =
      requestOptions && typeof requestOptions.session_id === 'string' && requestOptions.session_id.trim()
        ? requestOptions.session_id.trim().slice(0, 64)
        : `fe-${crypto.randomUUID()}`;

    const prior = history.slice(-20).map((row) => ({
      id: crypto.randomUUID(),
      role: normalizeRole(row?.role),
      content: String(row?.content ?? ''),
    }));

    const correlationId = crypto.randomUUID();

    const out = await compiledGraph.invoke(
      createInitialAgentState({
        sessionId,
        correlationId,
        messages: [
          ...prior,
          {
            id: crypto.randomUUID(),
            role: 'user',
            content: text,
          },
        ],
        _signal: signal,
      }),
      // Use correlationId as thread_id so each invocation starts from a clean checkpoint.
      { configurable: { thread_id: correlationId } },
    );

    const suggestions = Array.isArray(out?.suggestions) ? out.suggestions : [];
    const answer = String(out?.finalAnswer ?? '').trim();
    const liveSupportPublicIds = collectLiveSupportIds(out?.toolResults);
    return {
      success: true,
      session_id: sessionId,
      assistant: JSON.stringify({
        answer:
          answer ||
          '(Trống) — kiểm tra provider LLM, embedding/RAG, và kết nối API.',
        suggestions: suggestions
          .map((s) => {
            const textValue = String(s ?? '').trim();
            return textValue ? { text: textValue, action: '', params: {} } : null;
          })
          .filter(Boolean)
          .slice(0, 5),
      }),
      metadata: {
        ai: {
          runtime: 'fe_agent',
          planner_loops_signal: out?.signals?.planner_loop,
          rag_hits: Array.isArray(out?.ragContext) ? out.ragContext.length : undefined,
          ...(liveSupportPublicIds.length
            ? { live_support_public_ids: liveSupportPublicIds }
            : {}),
        },
      },
    };
  }

  return { invoke, tools, sessions, compiledGraph };
}

export function createDefaultFeChatAgentRuntime(opts = {}) {
  return createFeChatAgentRuntime(opts);
}

export function ensureDefaultFeChatAgentRuntime() {
  if (!defaultRuntime) {
    defaultRuntime = createDefaultFeChatAgentRuntime();
  }
  return defaultRuntime;
}

export function resetDefaultFeChatAgentRuntime() {
  defaultRuntime = null;
}

export async function invokeFeChatAgent(message, opts = null, signal = undefined) {
  return ensureDefaultFeChatAgentRuntime().invoke(message, opts, signal);
}
