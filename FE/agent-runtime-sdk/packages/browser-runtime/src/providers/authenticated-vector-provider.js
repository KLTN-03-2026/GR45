import {
  VectorQueryPayloadSchema,
  VectorUpsertPayloadSchema,
} from "@fe-agent/shared-zod-schemas";

const DEFAULT_TIMEOUT_MS = 20000;

function normalizeBaseUrl(baseUrl) {
  const normalized = String(baseUrl == null ? "" : baseUrl).trim().replace(/\/+$/, "");
  if (!normalized) throw new Error("HttpVectorProvider requires baseUrl");
  return normalized;
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

async function errorFromResponse(res, operation) {
  const text = await res.text().catch(() => "");
  const detail = text ? `: ${text.slice(0, 300)}` : "";
  return new Error(`vector ${operation} failed: ${res.status}${detail}`);
}

function normalizeIds(ids) {
  return Array.isArray(ids)
    ? [...new Set(ids.map((id) => String(id == null ? "" : id).trim()).filter(Boolean))]
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
    this.fetchImpl = createFetch(opts.fetchImpl);
  }

  async mergedHeaders(extra = {}) {
    return {
      "content-type": "application/json",
      accept: "application/json",
      ...extra,
    };
  }

  resolveSignal(initSignal) {
    if (initSignal) return initSignal;
    if (this.opts.getSignal != null) return this.opts.getSignal();
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
      id: String(hit?.id == null ? "" : hit.id),
      score: hit?.score?.constructor === Number ? hit.score : 0,
      metadata: hit?.metadata?.constructor === Object ? hit.metadata : undefined,
    }));
  }

  async deleteByIds(collection, ids, opts = {}) {
    const cleanCollection = String(collection == null ? "" : collection).trim();
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

function createFetch(fetchImpl) {
  return fetchImpl == null ? globalThis.fetch.bind(globalThis) : fetchImpl;
}

function createInit(init) {
  return init == null ? {} : init;
}

function createHeaders(init, getHeaders) {
  const baseHeaders = init?.headers == null ? {} : init.headers;
  const authHeaders = getHeaders == null ? {} : getHeaders();
  return { ...baseHeaders, ...authHeaders };
}

function createSignal(init, getSignal) {
  if (init?.signal) return init.signal;
  return getSignal == null ? undefined : getSignal();
}

export function createAuthenticatedHttpVectorProvider({
  baseUrl,
  fetchImpl,
  getHeaders,
  getSignal,
}) {
  const requestFetch = createFetch(fetchImpl);
  return new HttpVectorProvider({
    baseUrl,
    getSignal,
    fetchImpl: (url, init) =>
      requestFetch(url, {
        ...createInit(init),
        headers: createHeaders(init, getHeaders),
        signal: createSignal(init, getSignal),
      }),
  });
}
