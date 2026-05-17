import { bearerHeadersFromStorage } from "./auth-headers.js";
import { createAuthenticatedHttpVectorProvider } from "./authenticated-vector-provider.js";
import { createBrowserSessionProvider } from "./browser-session-provider.js";

export function defaultBrowserStorage() {
  return globalThis.localStorage == null ? null : globalThis.localStorage;
}

export function defaultBrowserFetch() {
  return globalThis.fetch.bind(globalThis);
}

function useProvidedValue(value, defaultValue) {
  return value == null ? defaultValue : value;
}

export function createBrowserAgentProviders({
  options = {},
  config,
  tokenStorageKey = "auth.client.token",
} = {}) {
  const fetchImpl = useProvidedValue(options.fetchImpl, defaultBrowserFetch());
  const storage = options.storage === undefined ? defaultBrowserStorage() : options.storage;
  const getAuthHeaders = options.getAuthHeaders == null
    ? () => bearerHeadersFromStorage(storage, tokenStorageKey)
    : options.getAuthHeaders;
  const sessions = options.sessions === undefined
    ? storage
      ? createBrowserSessionProvider(storage)
      : undefined
    : options.sessions;
  const vector = options.vector == null
    ? createAuthenticatedHttpVectorProvider({
        baseUrl: useProvidedValue(options.vectorBaseUrl, config.vectorBaseUrl),
        fetchImpl,
        getHeaders: getAuthHeaders,
      })
    : options.vector;

  return {
    fetchImpl,
    getAuthHeaders,
    sessions,
    storage,
    vector,
  };
}
