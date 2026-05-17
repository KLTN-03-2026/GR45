function clean(value) {
  return String(value == null ? "" : value).trim();
}

export function bearerHeadersFromStorage(storage, key) {
  if (!storage) return {};
  const token = clean(storage.getItem(key));
  return token ? { Authorization: `Bearer ${token}` } : {};
}
