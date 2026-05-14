import { SessionSnapshotSchema } from "@fe-agent/shared-zod-schemas";

const DEFAULT_SESSION_LIMIT = 14;
const DEFAULT_TIMEOUT_MS = 15000;

function normalizeBaseUrl(baseUrl) {
  const normalized = String(baseUrl || "").trim().replace(/\/+$/, "");
  if (!normalized) throw new Error("HttpSessionProvider requires baseUrl");
  return normalized;
}

function normalizeSessionId(sessionId) {
  const value = String(sessionId || "").trim();
  if (!value) throw new Error("HttpSessionProvider requires sessionId");
  return value.slice(0, 128);
}

function getFetchImpl(fetchImpl) {
  if (fetchImpl) return fetchImpl;
  if (typeof globalThis.fetch === "function") {
    return globalThis.fetch.bind(globalThis);
  }
  throw new Error("HttpSessionProvider requires fetchImpl");
}

async function errorFromResponse(res, operation) {
  const text = await res.text().catch(() => "");
  const detail = text ? `: ${text.slice(0, 300)}` : "";
  return new Error(`session ${operation} failed: ${res.status}${detail}`);
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

function withQuery(path, query) {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(query || {})) {
    if (value === undefined || value === null || value === "") continue;
    params.set(key, String(value));
  }

  const qs = params.toString();
  return qs ? `${path}?${qs}` : path;
}

export class HttpSessionProvider {
  constructor(opts = {}) {
    this.opts = {
      ...opts,
      baseUrl: normalizeBaseUrl(opts.baseUrl),
      limit:
        Number.isFinite(opts.limit) && opts.limit > 0
          ? Math.trunc(opts.limit)
          : DEFAULT_SESSION_LIMIT,
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

  async request(path, init = {}) {
    const timeout = createTimeoutSignal(this.opts.timeoutMs);

    try {
      return await this.fetchImpl(`${this.opts.baseUrl}${path}`, {
        ...init,
        headers: await this.mergedHeaders(init.headers),
        signal: mergeSignals(init.signal, timeout.signal),
      });
    } finally {
      timeout.dispose();
    }
  }

  async load(sessionId) {
    const id = normalizeSessionId(sessionId);

    const path = withQuery(`/sessions/${encodeURIComponent(id)}`, {
      limit: this.opts.limit,
    });

    const res = await this.request(path, {
      method: "GET",
    });

    if (res.status === 404) {
      return SessionSnapshotSchema.parse({
        sessionId: id,
        messages: [],
        updatedAt: new Date().toISOString(),
      });
    }

    if (!res.ok) throw await errorFromResponse(res, "load");

    return SessionSnapshotSchema.parse(await res.json());
  }

  async save(snapshot) {
    const body = SessionSnapshotSchema.parse(snapshot);
    const id = normalizeSessionId(body.sessionId);

    const res = await this.request(`/sessions/${encodeURIComponent(id)}`, {
      method: "PUT",
      body: JSON.stringify(body),
    });

    if (!res.ok) throw await errorFromResponse(res, "save");
  }

  async patch(sessionId, patch) {
    const id = normalizeSessionId(sessionId);

    const res = await this.request(`/sessions/${encodeURIComponent(id)}`, {
      method: "PATCH",
      body: JSON.stringify(patch ?? {}),
    });

    if (!res.ok) throw await errorFromResponse(res, "patch");
  }
}