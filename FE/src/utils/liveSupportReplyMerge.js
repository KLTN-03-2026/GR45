
function messageIdsMatch(a, b) {
  if (a == null || b == null) return false;
  return Number(a) === Number(b);
}

/** API/list đôi khi trả id number hoặc string — tránh badge unread/Echo lệch khi so sánh === strict. */
export function sameLiveSupportSessionId(a, b) {
  if (a == null || b == null) return false;
  const na = Number(a);
  const nb = Number(b);
  return na === nb && !Number.isNaN(na);
}


export function appendLiveSupportReplyFromEnvelope(messagesRef, envelope) {
  const m = envelope?.data;
  if (!m || m.id == null) return false;
  const id = Number(m.id);
  const arr = messagesRef.value;
  if (!Array.isArray(arr)) return false;
  if (arr.some((x) => messageIdsMatch(x?.id, id))) return true;
  arr.push({
    id,
    role: m.role ?? "admin",
    content: String(m.content ?? ""),
    admin_name: m.admin_name ?? null,
    meta: m.meta ?? null,
    thread_type: m.thread_type ?? null,
    created_at: m.created_at,
  });
  return true;
}


export function appendOptimisticOutgoingMessage(messagesRef, payload) {
  const tempId = `opt-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
  const arr = messagesRef.value;
  if (!Array.isArray(arr)) return tempId;
  arr.push({
    id: tempId,
    client_temp_id: tempId,
    _optimistic: true,
    role: payload.role,
    content: String(payload.content ?? ""),
    admin_name: payload.admin_name ?? null,
    meta: null,
    thread_type: null,
    created_at: new Date().toISOString(),
  });
  return tempId;
}


export function finalizeOutgoingReply(messagesRef, tempId, envelope) {
  const arr = messagesRef.value;
  if (!Array.isArray(arr)) return false;
  const idx = arr.findIndex((x) => x?.client_temp_id === tempId);
  const m = envelope?.data;
  if (!m || m.id == null) {
    if (idx !== -1) arr.splice(idx, 1);
    return false;
  }
  const row = {
    id: Number(m.id),
    role: m.role ?? "admin",
    content: String(m.content ?? ""),
    admin_name: m.admin_name ?? null,
    meta: m.meta ?? null,
    thread_type: m.thread_type ?? null,
    created_at: m.created_at,
  };
  const dupIdx = arr.findIndex((x) => messageIdsMatch(x?.id, row.id));
  if (idx !== -1) {
    if (dupIdx !== -1 && dupIdx !== idx) {
      arr.splice(idx, 1);
      return true;
    }
    arr.splice(idx, 1, row);
    return true;
  }
  if (dupIdx !== -1) return true;
  arr.push(row);
  return true;
}


export function removeOptimisticMessage(messagesRef, tempId) {
  const arr = messagesRef.value;
  if (!Array.isArray(arr)) return;
  const i = arr.findIndex((x) => x?.client_temp_id === tempId);
  if (i !== -1) arr.splice(i, 1);
}


export function mergeEchoLiveSupportMessage(messagesRef, message) {
  const arr = messagesRef.value;
  if (!Array.isArray(arr)) return;
  const mid = message?.id;
  if (mid != null && arr.some((x) => messageIdsMatch(x?.id, mid))) return;

  const optIdx = arr.findIndex(
    (x) =>
      x?._optimistic &&
      String(x?.role ?? "") === String(message?.role ?? "") &&
      String(x?.content ?? "").trim() === String(message?.content ?? "").trim(),
  );
  if (optIdx !== -1) {
    arr.splice(optIdx, 1, message);
    return;
  }
  arr.push(message);
}


export function bumpLiveSupportSessionPreview(sessionsRef, sessionId, replyPayload) {
  if (sessionId == null || !replyPayload) return;
  const list = sessionsRef.value;
  if (!Array.isArray(list)) return;
  const s = list.find((x) => sameLiveSupportSessionId(x?.id, sessionId));
  if (!s) return;
  if (!s.messages) s.messages = [];
  s.messages[0] = {
    content: replyPayload.content,
    created_at: replyPayload.created_at,
    role: replyPayload.role,
    admin_name: replyPayload.admin_name ?? null,
  };
  sessionsRef.value = [
    s,
    ...list.filter((x) => !sameLiveSupportSessionId(x?.id, sessionId)),
  ];
}
