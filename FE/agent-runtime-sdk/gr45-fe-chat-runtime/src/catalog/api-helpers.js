/** @typedef {{ success?: boolean; data?: unknown; message?: string; errors?: unknown }} ApiJsonBody */

const DEFAULT_API_URL = "http://127.0.0.1:8000/api/";
const KHACH_TOKEN_KEY = "auth.client.token";

/** @returns {Record<string, string> | undefined} */
function getImportMetaEnv() {
  return typeof import.meta !== "undefined" && import.meta.env
    ? import.meta.env
    : undefined;
}

/** @param {string} value */
function trimTrailingSlashes(value) {
  return String(value || "").trim().replace(/\/+$/, "");
}

/** @param {string} rawUrl */
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

/** @returns {string} */
export function getKhachToken() {
  if (!canUseLocalStorage()) return "";
  return String(localStorage.getItem(KHACH_TOKEN_KEY) || "").trim();
}

/** @returns {Record<string, string>} */
export function getKhachBearerHeaders() {
  const token = getKhachToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

/** @param {unknown} body */
export function extractTokenFromApiBody(body) {
  if (!body || typeof body !== "object") return "";

  const payload = /** @type {{ data?: unknown; token?: unknown }} */ (body);
  const data = payload.data && typeof payload.data === "object"
    ? /** @type {{ token?: unknown }} */ (payload.data)
    : payload;

  return typeof data.token === "string" ? data.token.trim() : "";
}

/** @param {unknown} body */
export function persistKhachToken(body) {
  const token = extractTokenFromApiBody(body);
  if (token && canUseLocalStorage()) {
    localStorage.setItem(KHACH_TOKEN_KEY, token);
  }
}

export function clearKhachToken() {
  if (canUseLocalStorage()) {
    localStorage.removeItem(KHACH_TOKEN_KEY);
  }
}

/**
 * @param {string} text
 * @returns {ApiJsonBody}
 */
function parseJsonBody(text) {
  if (!text) return {};

  try {
    return JSON.parse(text);
  } catch {
    return { message: text.slice(0, 200) };
  }
}

/**
 * @param {HeadersInit | undefined} headers
 * @returns {Record<string, string>}
 */
function normalizeHeaders(headers) {
  if (!headers) return {};

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }

  if (Array.isArray(headers)) {
    return Object.fromEntries(headers);
  }

  return /** @type {Record<string, string>} */ (headers);
}

/**
 * @param {string} path
 * @param {RequestInit} [init]
 */
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

/**
 * @param {string} path
 * @param {Record<string, unknown>} query
 */
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
