import { HttpVectorProvider } from "@fe-agent/adapters-vector-http";

/**
 * Ngữ cảnh KV cache mặc định 32k để tránh cắt prompt khi planner/RAG có nhiều tool hoặc PDF.
 * Có thể giảm bằng VITE_OLLAMA_NUM_CTX nếu máy local yếu.
 */
const DEFAULT_OLLAMA_NUM_CTX = 32768;

const ALLOWED_CHAT_SUGGESTION_SOURCES = new Set([
  "none",
  "tool_labels",
  "llm",
  "llm_from_tool_labels",
  "reply_envelope",
]);

function clean(value) {
  return String(value ?? "").trim();
}

function parseChatAiSuggestionSource(raw, fallback) {
  const s = clean(raw).toLowerCase();
  if (ALLOWED_CHAT_SUGGESTION_SOURCES.has(s)) return s;
  return fallback;
}

function parseBoundedInt(raw, fallback, min, max) {
  const n = Number.parseInt(clean(raw), 10);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(max, Math.max(min, n));
}

/** @param {unknown} raw */
function parseOptionalOllamaNumCtx(raw) {
  const s = clean(raw);
  if (s === "") return undefined;
  const n = Number.parseInt(s, 10);
  if (!Number.isFinite(n) || n < 512) return undefined;
  return Math.min(n, 262144);
}

function stripTrailingSlashes(value) {
  return clean(value).replace(/\/+$/, "");
}

export function resolveBrowserAgentRuntimeConfig({
  env = {},
  locationOrigin = "",
  defaults = {},
} = {}) {
  const apiBase = (() => {
    let raw = stripTrailingSlashes(
      env.VITE_API_URL || defaults.apiBaseUrl || "https://api.bussafe.io.vn/api/v1"
    );
    if (/\/api$/i.test(raw)) raw += "/v1";
    return raw;
  })();

  const ollamaBaseUrl =
    stripTrailingSlashes(env.VITE_OLLAMA_BASE_URL || defaults.ollamaBaseUrl) ||
    (locationOrigin ? `${locationOrigin}/ollama-local` : "http://127.0.0.1:11434");

  return {
    apiBaseUrl: apiBase,
    vectorBaseUrl:
      stripTrailingSlashes(env.VITE_AGENT_VECTOR_BASE_URL || defaults.vectorBaseUrl) ||
      `${apiBase}/agent`,
    adminVectorBaseUrl:
      stripTrailingSlashes(env.VITE_ADMIN_AGENT_VECTOR_BASE_URL || defaults.adminVectorBaseUrl) ||
      `${apiBase}/admin/agent`,
    collection:
      clean(env.VITE_AGENT_VECTOR_COLLECTION || defaults.collection || "gr45_pdf_kb") ||
      "gr45_pdf_kb",
    ollamaModel:
      clean(env.VITE_OLLAMA_CHAT_MODEL || env.VITE_OLLAMA_MODEL || defaults.ollamaModel || "qwen2.5:3b"),
    ollamaEmbedModel:
      clean(env.VITE_OLLAMA_EMBED_MODEL || defaults.ollamaEmbedModel || "nomic-embed-text"),
    ollamaBaseUrl,
    ollamaKeepAlive: clean(env.VITE_OLLAMA_KEEP_ALIVE || defaults.ollamaKeepAlive),
    /** Mặc định `tool_labels` — không gọi thêm LLM cho chip (nhanh). `llm_from_tool_labels` = chip thông minh hơn, chậm hơn ~1 request model. */
    chatAiSuggestionSource: parseChatAiSuggestionSource(
      env.VITE_CHAT_AI_SUGGESTION_SOURCE ?? defaults.chatAiSuggestionSource,
      "tool_labels",
    ),
    ollamaNumCtx:
      parseOptionalOllamaNumCtx(
        env.VITE_OLLAMA_NUM_CTX ??
          defaults.ollamaNumCtx ??
          DEFAULT_OLLAMA_NUM_CTX,
      ),
    groqKey: clean(env.VITE_GROQ_API_KEY || defaults.groqKey),
    groqModel: clean(env.VITE_GROQ_MODEL || defaults.groqModel),
    groqBaseUrl:
      stripTrailingSlashes(env.VITE_GROQ_BASE_URL || defaults.groqBaseUrl) ||
      "https://api.groq.com/openai/v1",
    huggingFaceKey: clean(
      env.VITE_HF_API_KEY || env.VITE_HUGGINGFACE_API_KEY || defaults.huggingFaceKey
    ),
    huggingFaceModel: clean(
      env.VITE_HF_EMBED_MODEL ||
        env.VITE_HUGGINGFACE_EMBED_MODEL ||
        defaults.huggingFaceModel
    ),
    huggingFaceEndpointUrl: clean(
      env.VITE_HF_ENDPOINT_URL ||
        env.VITE_HUGGINGFACE_ENDPOINT_URL ||
        defaults.huggingFaceEndpointUrl
    ),
    huggingFaceProvider: clean(
      env.VITE_HF_PROVIDER ||
        env.VITE_HUGGINGFACE_PROVIDER ||
        defaults.huggingFaceProvider
    ),
    /** Giới hạn vòng replan — nhỏ hơn = ít LLM hơn khi tool lỗi (mặc định SDK graph = 6). */
    chatAiMaxPlannerLoops: parseBoundedInt(
      env.VITE_CHAT_AI_MAX_PLANNER_LOOPS ?? defaults.chatAiMaxPlannerLoops,
      4,
      1,
      12,
    ),
    /** Số chunk vector / RAG — nhỏ hơn = embed/query/tổng hợp nhanh hơn. */
    chatAiRagTopK: parseBoundedInt(
      env.VITE_CHAT_AI_RAG_TOP_K ?? defaults.chatAiRagTopK,
      5,
      1,
      16,
    ),
  };
}

export function bearerHeadersFromStorage(storage, key) {
  if (!storage) return {};
  const token = clean(storage.getItem(key));
  return token ? { Authorization: `Bearer ${token}` } : {};
}

function normalizeStoredMessages(arr) {
  if (!Array.isArray(arr)) return [];
  return arr
    .map((m) => {
      if (!m || typeof m !== "object") return null;
      const id = typeof m.id === "string" && m.id.trim() ? m.id : crypto.randomUUID();
      const roleRaw = String(m.role || "user").trim();
      const role =
        roleRaw === "assistant" || roleRaw === "tool" || roleRaw === "system"
          ? roleRaw
          : "user";
      return {
        id,
        role,
        content: String(m.content ?? ""),
        meta: typeof m.meta === "object" && m.meta !== null ? m.meta : undefined,
      };
    })
    .filter(Boolean);
}

export function createBrowserSessionProvider(storage, options = {}) {
  const prefix = options.prefix || "fe-agent-chat-ai:";
  const maxStoredMessages = options.maxStoredMessages ?? 24;
  const maxSerializedChars = options.maxSerializedChars ?? 120_000;

  function serializeSnapshot(snapshot, sid) {
    let messages = normalizeStoredMessages(snapshot.messages).slice(-maxStoredMessages);
    while (messages.length > 0) {
      const serialized = JSON.stringify({
        sessionId: sid,
        updatedAt: snapshot.updatedAt,
        messages,
        workflow: snapshot.workflow,
      });
      if (serialized.length <= maxSerializedChars) return serialized;
      messages = messages.slice(1);
    }
    return JSON.stringify({
      sessionId: sid,
      updatedAt: snapshot.updatedAt,
      messages: [],
      workflow: snapshot.workflow,
    });
  }

  return {
    async load(sessionId) {
      const sid = clean(sessionId).slice(0, 96);
      if (!sid) return { sessionId: "anon", messages: [], updatedAt: new Date().toISOString() };
      try {
        const raw = storage.getItem(prefix + sid);
        const snapshot = raw ? JSON.parse(raw) : null;
        if (!snapshot || typeof snapshot !== "object") throw new Error("missing session");
        return {
          sessionId: sid,
          updatedAt:
            typeof snapshot.updatedAt === "string"
              ? snapshot.updatedAt
              : new Date().toISOString(),
          messages: normalizeStoredMessages(snapshot.messages),
          workflow:
            typeof snapshot.workflow === "object" && snapshot.workflow !== null
              ? snapshot.workflow
              : undefined,
        };
      } catch {
        return { sessionId: sid, messages: [], updatedAt: new Date().toISOString() };
      }
    },
    async save(snapshot) {
      const sid = clean(snapshot.sessionId).slice(0, 96);
      if (!sid) return;
      try {
        storage.setItem(prefix + sid, serializeSnapshot(snapshot, sid));
      } catch {
        try {
          storage.removeItem?.(prefix + sid);
        } catch {
          /* storage unavailable */
        }
      }
    },
  };
}

export function createAuthenticatedHttpVectorProvider({
  baseUrl,
  fetchImpl,
  getHeaders,
  getSignal,
}) {
  return new HttpVectorProvider({
    baseUrl,
    getSignal,
    fetchImpl: (url, init) =>
      (fetchImpl || globalThis.fetch.bind(globalThis))(url, {
        ...(init ?? {}),
        headers: { ...(init?.headers ?? {}), ...(getHeaders?.() ?? {}) },
        signal: init?.signal || getSignal?.(),
      }),
  });
}
