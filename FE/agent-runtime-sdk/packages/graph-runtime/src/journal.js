export function journalEntry(type, payload) {
  return { type, timestamp: Date.now(), payload };
}
