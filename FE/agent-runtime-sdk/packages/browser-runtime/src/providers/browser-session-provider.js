function clean(value) {
  return String(value == null ? "" : value).trim();
}

function useProvidedValue(value, defaultValue) {
  return value == null ? defaultValue : value;
}

function normalizeStoredMessages(arr) {
  if (!Array.isArray(arr)) return [];
  return arr
    .map((message) => {
      if (message == null) return null;
      const id =
        String(useProvidedValue(message.id, "")).trim()
          ? String(message.id).trim()
          : crypto.randomUUID();
      const roleRaw = String(useProvidedValue(message.role, "user")).trim();
      const role = ["assistant", "tool", "system"].includes(roleRaw)
        ? roleRaw
        : "user";
      return {
        id,
        role,
        content: String(useProvidedValue(message.content, "")),
        meta: message.meta == null ? undefined : message.meta,
      };
    })
    .filter(Boolean);
}

export function createBrowserSessionProvider(storage, options = {}) {
  const prefix = useProvidedValue(options.prefix, "fe-agent-chat-ai:");
  const maxStoredMessages = useProvidedValue(options.maxStoredMessages, 24);
  const maxSerializedChars = useProvidedValue(options.maxSerializedChars, 120_000);

  function serializeSnapshot(snapshot, sid) {
    let messages = normalizeStoredMessages(snapshot.messages).slice(-maxStoredMessages);
    while (messages.length > 0) {
      const serialized = JSON.stringify({
        sessionId: sid,
        updatedAt: snapshot.updatedAt,
        messages,
        workflow: snapshot.workflow,
      });
      if (serialized.length <= maxSerializedChars) return serialized;
      messages = messages.slice(1);
    }
    return JSON.stringify({
      sessionId: sid,
      updatedAt: snapshot.updatedAt,
      messages: [],
      workflow: snapshot.workflow,
    });
  }

  return {
    async load(sessionId) {
      const sid = clean(sessionId).slice(0, 96);
      if (!sid) {
        return {
          sessionId: "anon",
          messages: [],
          updatedAt: new Date().toISOString(),
        };
      }

      try {
        const raw = storage.getItem(prefix + sid);
        const snapshot = raw ? JSON.parse(raw) : null;
        if (snapshot == null) {
          throw new Error("missing session");
        }
        const updatedAt = String(useProvidedValue(snapshot.updatedAt, "")).trim();
        return {
          sessionId: sid,
          updatedAt: updatedAt ? updatedAt : new Date().toISOString(),
          messages: normalizeStoredMessages(snapshot.messages),
          workflow: snapshot.workflow == null ? undefined : snapshot.workflow,
        };
      } catch {
        return {
          sessionId: sid,
          messages: [],
          updatedAt: new Date().toISOString(),
        };
      }
    },

    async save(snapshot) {
      const sid = clean(snapshot.sessionId).slice(0, 96);
      if (!sid) return;

      try {
        storage.setItem(prefix + sid, serializeSnapshot(snapshot, sid));
      } catch {
        try {
          storage.removeItem?.(prefix + sid);
        } catch {
          /* storage unavailable */
        }
      }
    },
  };
}
