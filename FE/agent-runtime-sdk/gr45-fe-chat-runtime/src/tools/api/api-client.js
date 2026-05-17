const DEFAULT_API_URL = "http://127.0.0.1:8000/api/";
const KHACH_TOKEN_KEY = "auth.client.token";

function getImportMetaEnv() {
  return typeof import.meta !== "undefined" && import.meta.env
    ? import.meta.env
    : undefined;
}

function trimTrailingSlashes(value) {
  return String(value || "").trim().replace(/\/+$/, "");
}

function normalizeApiV1Base(rawUrl) {
  let base = trimTrailingSlashes(rawUrl || DEFAULT_API_URL);

  if (/\/api$/i.test(base)) {
    base += "/v1";
  }

  return base;
}

/** Chuẩn hoá `/api/v1` giống FE `chatAiApi`. */
export function getApiV1Base() {
  const env = getImportMetaEnv();
  return normalizeApiV1Base(env?.VITE_API_URL || DEFAULT_API_URL);
}

function canUseLocalStorage() {
  return typeof localStorage !== "undefined";
}

export function getKhachToken() {
  if (!canUseLocalStorage()) return "";
  return String(localStorage.getItem(KHACH_TOKEN_KEY) || "").trim();
}

export function getKhachBearerHeaders() {
  const token = getKhachToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export function extractTokenFromApiBody(body) {
  if (!body || typeof body !== "object") return "";

  const payload = body;
  const data = payload.data && typeof payload.data === "object"
    ? payload.data
    : payload;

  return typeof data.token === "string" ? data.token.trim() : "";
}

export function persistKhachToken(body) {
  const token = extractTokenFromApiBody(body);
  if (!token || !canUseLocalStorage()) return;

  localStorage.setItem(KHACH_TOKEN_KEY, token);

  const data = body?.data && typeof body.data === "object" ? body.data : body;
  const user = data?.khach_hang ?? data?.user ?? null;
  if (user && typeof user === "object") {
    localStorage.setItem("auth.client.user", JSON.stringify(user));
  }

  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent("chatbot:client:login", { detail: { token, user } }),
    );
  }
}

export function clearKhachToken() {
  if (canUseLocalStorage()) {
    localStorage.removeItem(KHACH_TOKEN_KEY);
    localStorage.removeItem("auth.client.user");
  }

  if (typeof window !== "undefined") {
    window.dispatchEvent(new CustomEvent("chatbot:client:logout"));
  }
}

function parseJsonBody(text) {
  if (!text) return {};

  try {
    return JSON.parse(text);
  } catch {
    return { message: text.slice(0, 200) };
  }
}

function normalizeHeaders(headers) {
  if (!headers) return {};

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }

  if (Array.isArray(headers)) {
    return Object.fromEntries(headers);
  }

  return headers;
}

export async function apiFetch(path, init = {}) {
  const base = getApiV1Base();
  const cleanPath = String(path || "").replace(/^\/+/, "");
  const url = `${base}/${cleanPath}`;

  const headers = {
    Accept: "application/json",
    ...normalizeHeaders(init.headers),
  };

  const res = await fetch(url, {
    ...init,
    headers,
  });

  const text = await res.text().catch(() => "");
  const body = parseJsonBody(text);

  return { res, body, text };
}

export function withQuery(path, query = {}) {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === "") continue;

    if (Array.isArray(value)) {
      for (const item of value) {
        if (item !== undefined && item !== null && item !== "") {
          params.append(key, String(item));
        }
      }
      continue;
    }

    params.append(key, String(value));
  }

  const qs = params.toString();
  return qs ? `${path}?${qs}` : path;
}
