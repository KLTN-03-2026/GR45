import {
  VectorQueryPayloadSchema,
  VectorUpsertPayloadSchema,
} from "@fe-agent/shared-zod-schemas";

const DEFAULT_TIMEOUT_MS = 20000;

function normalizeBaseUrl(baseUrl) {
  const normalized = String(baseUrl || "").trim().replace(/\/+$/, "");
  if (!normalized) throw new Error("HttpVectorProvider requires baseUrl");
  return normalized;
}

function getFetchImpl(fetchImpl) {
  if (fetchImpl) return fetchImpl;
  if (typeof globalThis.fetch === "function") {
    return globalThis.fetch.bind(globalThis);
  }
  throw new Error("HttpVectorProvider requires fetchImpl");
}

async function errorFromResponse(res, operation) {
  const text = await res.text().catch(() => "");
  const detail = text ? `: ${text.slice(0, 300)}` : "";
  return new Error(`vector ${operation} failed: ${res.status}${detail}`);
}

function createTimeoutSignal(timeoutMs) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  return {
    signal: controller.signal,
    dispose: () => clearTimeout(timer),
  };
}

function mergeSignals(signalA, signalB) {
  if (!signalA) return signalB;
  if (!signalB) return signalA;

  const controller = new AbortController();
  const abort = () => controller.abort();

  if (signalA.aborted || signalB.aborted) {
    controller.abort();
  } else {
    signalA.addEventListener("abort", abort, { once: true });
    signalB.addEventListener("abort", abort, { once: true });
  }

  return controller.signal;
}

function normalizeIds(ids) {
  return Array.isArray(ids)
    ? [...new Set(ids.map((id) => String(id || "").trim()).filter(Boolean))]
    : [];
}

export class HttpVectorProvider {
  constructor(opts = {}) {
    this.opts = {
      ...opts,
      baseUrl: normalizeBaseUrl(opts.baseUrl),
      timeoutMs:
        Number.isFinite(opts.timeoutMs) && opts.timeoutMs > 0
          ? Math.trunc(opts.timeoutMs)
          : DEFAULT_TIMEOUT_MS,
    };

    this.fetchImpl = getFetchImpl(opts.fetchImpl);
  }

  async mergedHeaders(extra = {}) {
    const headers = {
      "content-type": "application/json",
      accept: "application/json",
      ...extra,
    };

    if (this.opts.auth && typeof this.opts.auth.getHeaders === "function") {
      Object.assign(headers, await this.opts.auth.getHeaders());
    }

    return headers;
  }

  resolveSignal(initSignal) {
    if (initSignal) return initSignal;
    if (typeof this.opts.getSignal === "function") return this.opts.getSignal();
    return this.opts.signal;
  }

  async request(path, init = {}) {
    const timeout = createTimeoutSignal(this.opts.timeoutMs);

    try {
      return await this.fetchImpl(`${this.opts.baseUrl}${path}`, {
        ...init,
        headers: await this.mergedHeaders(init.headers),
        signal: mergeSignals(this.resolveSignal(init.signal), timeout.signal),
      });
    } finally {
      timeout.dispose();
    }
  }

  async upsert(payload) {
    const body = VectorUpsertPayloadSchema.parse(payload);

    const res = await this.request("/vectors/upsert", {
      method: "POST",
      body: JSON.stringify(body),
      signal: payload.signal,
    });

    if (!res.ok) throw await errorFromResponse(res, "upsert");

    return { ok: true };
  }

  async query(payload) {
    const body = VectorQueryPayloadSchema.parse(payload);

    const res = await this.request("/vectors/query", {
      method: "POST",
      body: JSON.stringify(body),
      signal: payload.signal,
    });

    if (!res.ok) throw await errorFromResponse(res, "query");

    const data = await res.json().catch(() => ({}));
    const rows = Array.isArray(data.items)
      ? data.items
      : Array.isArray(data.data)
        ? data.data
        : Array.isArray(data)
          ? data
          : [];

    return rows.map((hit) => ({
      id: String(hit?.id ?? ""),
      score: typeof hit?.score === "number" ? hit.score : 0,
      metadata:
        hit?.metadata && typeof hit.metadata === "object"
          ? hit.metadata
          : undefined,
    }));
  }

  async deleteByIds(collection, ids, opts = {}) {
    const cleanCollection = String(collection || "").trim();
    const cleanIds = normalizeIds(ids);

    if (!cleanCollection) {
      throw new Error("vector delete failed: missing collection");
    }

    if (cleanIds.length === 0) {
      return { ok: true, deleted: 0 };
    }

    const res = await this.request("/vectors/delete", {
      method: "POST",
      body: JSON.stringify({
        collection: cleanCollection,
        ids: cleanIds,
      }),
      signal: opts.signal,
    });

    if (!res.ok) throw await errorFromResponse(res, "delete");

    return { ok: true, deleted: cleanIds.length };
  }
}