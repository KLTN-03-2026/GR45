export function journalEntry(type, payload) {
  return { type, timestamp: Date.now(), payload };
}

export function appendJournal(existing, type, payload) {
  return existing.concat(journalEntry(type, payload));
}
