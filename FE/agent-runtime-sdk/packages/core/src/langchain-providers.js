import { HuggingFaceInferenceEmbeddings } from "@langchain/community/embeddings/hf";
import { ChatGroq } from "@langchain/groq";
import { ChatOllama, OllamaEmbeddings } from "@langchain/ollama";

const DEFAULT_OLLAMA_KEEP_ALIVE = "30m";

function resolveOllamaKeepAlive(raw) {
  const t = typeof raw === "string" ? raw.trim() : "";
  return t === "" ? DEFAULT_OLLAMA_KEEP_ALIVE : t;
}

function clean(value) {
  return String(value ?? "").trim().replace(/^["']|["']$/g, "");
}

function messageText(message) {
  const content = message?.content;
  if (typeof content === "string") return content;
  if (Array.isArray(content)) {
    return content
      .map((part) => {
        if (typeof part === "string") return part;
        if (part && typeof part === "object" && typeof part.text === "string") {
          return part.text;
        }
        return "";
      })
      .join("");
  }
  return content == null ? "" : String(content);
}

function fallbackProviders(providers) {
  return async (input) => {
    let lastError;
    for (const provider of providers) {
      try {
        return await provider(input);
      } catch (error) {
        lastError = error;
      }
    }
    throw lastError instanceof Error ? lastError : new Error(String(lastError));
  };
}

function parseOptionalNumCtx(raw) {
  const n = Number.parseInt(String(raw ?? "").trim(), 10);
  if (!Number.isFinite(n) || n < 512) return undefined;
  /** Giới hạn trên để tránh nhập nhầm làm crash loader model; có thể nới tay trong Modelfile / chạy Ollama CLI. */
  return Math.min(n, 262144);
}

export function createLangChainLlmDeps(opts = {}) {
  const providers = [];
  const ollamaModel = clean(opts.ollamaModel ?? opts.ollamaChatModel ?? opts.chatModel);
  const ollamaBaseUrl = clean(opts.ollamaBaseUrl ?? opts.ollamaOrigin);
  const numCtx =
    typeof opts.ollamaNumCtx === "number"
      ? opts.ollamaNumCtx
      : parseOptionalNumCtx(opts.ollamaNumCtx);

  if (ollamaModel) {
    const ollamaFields = {
      model: ollamaModel,
      baseUrl: ollamaBaseUrl || undefined,
      format: "json",
      keepAlive: resolveOllamaKeepAlive(clean(opts.ollamaKeepAlive)),
      fetch: opts.fetchImpl,
    };
    if (typeof numCtx === "number" && Number.isFinite(numCtx)) {
      ollamaFields.numCtx = numCtx;
    }
    const ollama = new ChatOllama(ollamaFields);
    providers.push(async ({ prompt, signal }) =>
      messageText(await ollama.invoke([{ role: "user", content: String(prompt ?? "") }], { signal }))
    );
  }

  const groqKey = clean(opts.groqKey ?? opts.groqApiKey);
  const groqModel = clean(opts.groqModel);
  if (groqKey && groqModel) {
    const groq = new ChatGroq({
      apiKey: groqKey,
      model: groqModel,
      configuration: opts.groqBaseUrl
        ? { baseURL: clean(opts.groqBaseUrl).replace(/\/+$/, "") }
        : undefined,
      modelKwargs: { response_format: { type: "json_object" } },
    });
    providers.push(async ({ prompt, signal }) =>
      messageText(await groq.invoke([{ role: "user", content: String(prompt ?? "") }], { signal }))
    );
  }

  if (!providers.length) {
    throw new Error("No chat model configured. Provide ollamaModel or groqKey + groqModel.");
  }

  const complete = fallbackProviders(providers);
  return {
    completeJson: (prompt, callOpts = {}) =>
      complete({ prompt: String(prompt ?? ""), signal: callOpts.signal || opts.signal }),
  };
}

export function createLangChainRagEmbedderDeps(opts = {}) {
  const providers = [];
  const ollamaModel = clean(opts.ollamaEmbedModel ?? opts.embedModel);
  const ollamaBaseUrl = clean(opts.ollamaBaseUrl ?? opts.ollamaOrigin);

  if (ollamaModel) {
    const embeddings = new OllamaEmbeddings({
      model: ollamaModel,
      baseUrl: ollamaBaseUrl || undefined,
      fetch: opts.fetchImpl,
    });
    providers.push(async ({ text, signal }) =>
      embeddings.embedQuery(String(text ?? ""), { signal })
    );
  }

  if (clean(opts.huggingFaceKey ?? opts.huggingFaceApiKey) && clean(opts.huggingFaceModel ?? opts.huggingFaceEmbedModel)) {
    const embeddings = new HuggingFaceInferenceEmbeddings({
      apiKey: clean(opts.huggingFaceKey ?? opts.huggingFaceApiKey),
      model: clean(opts.huggingFaceModel ?? opts.huggingFaceEmbedModel),
      endpointUrl: clean(opts.huggingFaceEndpointUrl) || undefined,
      provider: opts.huggingFaceProvider,
    });
    providers.push(({ text }) =>
      embeddings.embedQuery(String(text ?? ""))
    );
  }

  if (!providers.length) {
    throw new Error(
      "No embedding model configured. Provide ollamaEmbedModel or huggingFaceKey + huggingFaceModel."
    );
  }

  const embedOne = fallbackProviders(providers);
  return {
    embedQuery: ({ text, signal }) => embedOne({ text, signal: signal || opts.signal }),
    embedTexts: ({ texts, signal }) =>
      Promise.all(
        (Array.isArray(texts) ? texts : []).map((text) =>
          embedOne({ text, signal: signal || opts.signal })
        )
      ),
  };
}
