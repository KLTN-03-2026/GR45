/**
 * Ngữ cảnh KV cache mặc định 32k để tránh cắt prompt khi planner/RAG có nhiều tool hoặc PDF.
 * Có thể giảm bằng VITE_OLLAMA_NUM_CTX nếu máy local yếu.
 */
const DEFAULT_OLLAMA_NUM_CTX = 32768;

function clean(value) {
  return String(value == null ? "" : value).trim();
}

function firstText(...values) {
  for (const value of values) {
    const text = clean(value);
    if (text) return text;
  }
  return "";
}

function firstRaw(...values) {
  for (const value of values) {
    if (value !== undefined && value !== null && value !== "") return value;
  }
  return undefined;
}

function stripTrailingSlashes(value) {
  return clean(value).replace(/\/+$/, "");
}

function parseBoundedInt(raw, fallback, min, max) {
  const n = Number.parseInt(clean(raw), 10);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(max, Math.max(min, n));
}

function parseOptionalOllamaNumCtx(raw) {
  const value = clean(raw);
  if (!value) return undefined;
  const n = Number.parseInt(value, 10);
  if (!Number.isFinite(n)) return undefined;
  if (n < 512) return undefined;
  return Math.min(n, 262144);
}

function resolveApiBaseUrl(env, defaults) {
  let raw = stripTrailingSlashes(
    firstText(env.VITE_API_URL, defaults.apiBaseUrl, "https://api.bussafe.io.vn/api/v1"),
  );
  if (/\/api$/i.test(raw)) raw += "/v1";
  return raw;
}

function resolveOllamaBaseUrl(env, defaults, locationOrigin) {
  const localBaseUrl = locationOrigin
    ? `${locationOrigin}/ollama-local`
    : "http://127.0.0.1:11434";
  return stripTrailingSlashes(
    firstText(
      env.VITE_AI_OLLAMA_URL,
      env.VITE_OLLAMA_BASE_URL,
      defaults.ollamaBaseUrl,
      localBaseUrl,
    ),
  );
}

function resolveVectorConfig(env, defaults, apiBaseUrl) {
  return {
    vectorBaseUrl: stripTrailingSlashes(
      firstText(env.VITE_AGENT_VECTOR_BASE_URL, defaults.vectorBaseUrl, `${apiBaseUrl}/agent`),
    ),
    adminVectorBaseUrl: stripTrailingSlashes(
      firstText(
        env.VITE_ADMIN_AGENT_VECTOR_BASE_URL,
        defaults.adminVectorBaseUrl,
        `${apiBaseUrl}/admin/agent`,
      ),
    ),
    collection: firstText(
      env.VITE_AGENT_VECTOR_COLLECTION,
      defaults.collection,
      "gr45_pdf_kb",
    ),
  };
}

function resolveLlmConfig(env, defaults, locationOrigin) {
  const ollamaBaseUrl = resolveOllamaBaseUrl(env, defaults, locationOrigin);
  return {
    ollamaModel: clean(
      firstText(
        env.VITE_AI_OLLAMA_CHAT_MODEL,
        env.VITE_OLLAMA_CHAT_MODEL,
        env.VITE_OLLAMA_MODEL,
        defaults.ollamaModel,
        "qwen2.5:3b",
      ),
    ),
    /** Chiều vector phải khớp `embedding_dim` trong bảng ai_chunks khi ingest PDF. */
    ollamaEmbedModel: clean(
      firstText(
        env.VITE_AI_OLLAMA_EMBED_MODEL,
        env.VITE_OLLAMA_EMBED_MODEL,
        defaults.ollamaEmbedModel,
        "nomic-embed-text",
      ),
    ),
    ollamaBaseUrl,
    ollamaKeepAlive: clean(firstText(env.VITE_OLLAMA_KEEP_ALIVE, defaults.ollamaKeepAlive)),
    ollamaNumCtx: parseOptionalOllamaNumCtx(
      firstRaw(
        env.VITE_AI_OLLAMA_NUM_CTX,
        env.VITE_OLLAMA_NUM_CTX,
        defaults.ollamaNumCtx,
        DEFAULT_OLLAMA_NUM_CTX,
      ),
    ),
    groqKey: clean(
      firstText(env.VITE_AI_GROQ_API_KEY, env.VITE_GROQ_API_KEY, defaults.groqKey),
    ),
    groqModel: clean(
      firstText(env.VITE_AI_GROQ_MODEL, env.VITE_GROQ_MODEL, defaults.groqModel),
    ),
    groqBaseUrl: stripTrailingSlashes(
      firstText(
        env.VITE_AI_GROQ_API_URL,
        env.VITE_GROQ_BASE_URL,
        defaults.groqBaseUrl,
        "https://api.groq.com/openai/v1",
      ),
    ),
    huggingFaceKey: clean(
      firstText(
        env.VITE_AI_HF_TOKEN,
        env.VITE_HF_API_KEY,
        env.VITE_HUGGINGFACE_API_KEY,
        defaults.huggingFaceKey,
      ),
    ),
    huggingFaceModel: clean(
      firstText(
        env.VITE_AI_HF_EMBED_MODEL,
        env.VITE_HF_EMBED_MODEL,
        env.VITE_HUGGINGFACE_EMBED_MODEL,
        defaults.huggingFaceModel,
      ),
    ),
    huggingFaceEndpointUrl: clean(
      firstText(
        env.VITE_AI_HF_EMBED_BASE_URL,
        env.VITE_HF_ENDPOINT_URL,
        env.VITE_HUGGINGFACE_ENDPOINT_URL,
        defaults.huggingFaceEndpointUrl,
      ),
    ),
    huggingFaceProvider: clean(
      firstText(
        env.VITE_HF_PROVIDER,
        env.VITE_HUGGINGFACE_PROVIDER,
        defaults.huggingFaceProvider,
      ),
    ),
  };
}

function resolvePlannerConfig(env, defaults) {
  return {
    chatAiMaxPlannerLoops: parseBoundedInt(
      firstRaw(env.VITE_CHAT_AI_MAX_PLANNER_LOOPS, defaults.chatAiMaxPlannerLoops),
      4,
      1,
      12,
    ),
    chatAiRagTopK: parseBoundedInt(
      firstRaw(env.VITE_CHAT_AI_RAG_TOP_K, defaults.chatAiRagTopK),
      5,
      1,
      16,
    ),
  };
}

export function resolveBrowserAgentRuntimeConfig({
  env = {},
  locationOrigin = "",
  defaults = {},
} = {}) {
  const apiBaseUrl = resolveApiBaseUrl(env, defaults);
  return {
    apiBaseUrl,
    ...resolveVectorConfig(env, defaults, apiBaseUrl),
    ...resolveLlmConfig(env, defaults, locationOrigin),
    ...resolvePlannerConfig(env, defaults),
  };
}
