/**
 * Chat AI API helpers.
 */

export function apiV1Base() {
  let raw = String(import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/v1').trim();
  raw = raw.replace(/\/+$/, '');
  if (/\/api$/i.test(raw)) {
    raw += '/v1';
  }
  return raw;
}

export function agentVectorHttpBaseUrl() {
  const explicit = String(import.meta.env.VITE_AGENT_VECTOR_BASE_URL || '').trim().replace(/\/+$/, '');
  return explicit || `${apiV1Base()}/agent`;
}

export function chatAiAuthHeaders() {
  if (typeof localStorage === 'undefined') return {};
  const token = String(localStorage.getItem('auth.client.token') ?? '').trim();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

/**
 * Mặc định Chat AI chạy trong trình duyệt (`@fe-agent/gr45-fe-chat-runtime` + Ollama).
 * Chỉ dùng `POST /chat/ai/message` Laravel khi đặt rõ `VITE_CHAT_AI_RUNTIME=laravel` (hoặc `server`|`backend`|`api`).
 */
export function isFeChatAgentRuntime() {
  const v = String(import.meta.env.VITE_CHAT_AI_RUNTIME || '').trim().toLowerCase();
  if (['laravel', 'server', 'backend', 'api'].includes(v)) {
    return false;
  }
  if (['false', '0', 'off', 'no'].includes(v)) {
    return false;
  }
  return true;
}

/** Timeout (ms) cho POST chat AI JSON — mặc định 10 phút (ReAct + LLM có thể rất lâu). */
function chatAiMessageTimeoutMs() {
  const raw = String(import.meta.env.VITE_CHAT_AI_TIMEOUT_MS || '').trim();
  const n = Number.parseInt(raw, 10);
  if (Number.isFinite(n) && n >= 120000) {
    return n;
  }
  return 600000;
}

/** JSON object cân ngoặc từ vị trí `{` (UTF-16 index). */
function balancedJsonSlice(text, brace) {
  const len = text.length;
  if (brace < 0 || brace >= len || text[brace] !== "{") return null;
  let depth = 0;
  let inStr = false;
  let esc = false;
  for (let i = brace; i < len; i++) {
    const c = text[i];
    if (inStr) {
      if (esc) {
        esc = false;
        continue;
      }
      if (c === "\\") {
        esc = true;
        continue;
      }
      if (c === '"') inStr = false;
      continue;
    }
    if (c === '"') {
      inStr = true;
      continue;
    }
    if (c === "{") depth++;
    else if (c === "}") {
      depth--;
      if (depth === 0) return text.slice(brace, i + 1);
    }
  }
  return null;
}

const UI_ANSWER_NEEDLE = '{"answer"';

/**
 * Giống BE AssistantUiJsonExtractor: prose + `{"answer":...}` hoặc answer lồng JSON.
 * @returns {{ answer: string, suggestions: unknown[] } | null}
 */
export function parseAssistantUiPayload(rawText) {
  const raw = String(rawText || "").trim();
  if (!raw) return null;
  let parsed = null;
  try {
    parsed = JSON.parse(raw);
  } catch {
    parsed = null;
  }
  if (
    parsed &&
    typeof parsed === "object" &&
    typeof parsed.answer !== "string"
  ) {
    parsed = null;
  }
  if (!parsed || typeof parsed !== "object") {
    let from = 0;
    while (true) {
      const pos = raw.indexOf(UI_ANSWER_NEEDLE, from);
      if (pos < 0) return null;
      const slice = balancedJsonSlice(raw, pos);
      if (!slice) {
        from = pos + 1;
        continue;
      }
      try {
        const o = JSON.parse(slice);
        if (o && typeof o === "object" && typeof o.answer === "string") {
          parsed = o;
          break;
        }
      } catch {
        /* continue */
      }
      from = pos + 1;
    }
  }
  if (!parsed || typeof parsed !== "object") return null;
  let answer = typeof parsed.answer === "string" ? parsed.answer.trim() : "";
  let suggestions = Array.isArray(parsed.suggestions) ? parsed.suggestions : [];
  const inner = answer.indexOf(UI_ANSWER_NEEDLE);
  if (inner >= 0) {
    const slice = balancedJsonSlice(answer, inner);
    if (slice) {
      try {
        const o2 = JSON.parse(slice);
        if (o2 && typeof o2 === "object" && typeof o2.answer === "string") {
          answer = o2.answer.trim();
          if (Array.isArray(o2.suggestions) && o2.suggestions.length > 0) {
            suggestions = o2.suggestions;
          }
        }
      } catch {
        /* keep */
      }
    }
  }
  return { answer, suggestions };
}

/**
 * Chuẩn hoá assistant: FE runtime trả `{ answer, suggestions }`, Laravel có thể trả JSON string hoặc prose + `{"answer":...}`.
 * @returns {{ answer: string, suggestions: unknown[] } | null}
 */
export function coerceAssistantStructured(assistant) {
  if (assistant == null || assistant === "") return null;
  if (typeof assistant === "object" && !Array.isArray(assistant)) {
    const answerRaw =
      typeof assistant.answer === "string"
        ? assistant.answer
        : String(assistant.answer ?? "").trim();
    const suggestions = Array.isArray(assistant.suggestions)
      ? assistant.suggestions
      : [];
    return { answer: answerRaw.trim(), suggestions };
  }
  const s = String(assistant ?? "").trim();
  if (!s || s === "[object Object]") return null;
  return parseAssistantUiPayload(s);
}

/** Chuỗi JSON lưu message-log (`answer` + `suggestions`) — không stringify object bằng `String(...)`. */
function assistantPayloadJsonForPersist(assistant) {
  const coerced = coerceAssistantStructured(assistant);
  if (!coerced) {
    const amRaw =
      typeof assistant === "string" ? assistant.trim() : String(assistant ?? "").trim();
    if (!amRaw || amRaw === "[object Object]") return "";
    return amRaw;
  }
  try {
    const answer =
      coerced.answer && coerced.answer.length > 0
        ? coerced.answer
        : "(Không có nội dung phản hồi)";
    const suggestions = Array.isArray(coerced.suggestions)
      ? coerced.suggestions
      : [];
    return JSON.stringify({ answer, suggestions });
  } catch {
    return "";
  }
}

function httpErrorMessage(status, bodyText, contentType) {
  const ct = (contentType || '').toLowerCase();
  if (status === 404 && (ct.includes('text/html') || /^\s*</.test(bodyText))) {
    return `HTTP ${status} — Không tìm thấy API Laravel chat (/chat/ai/message). Repo này mặc định xài agent-runtime trên FE: không đặt VITE_CHAT_AI_RUNTIME=laravel cho tới khi BE có route, hoặc kiểm tra VITE_API_URL.`;
  }
  if (bodyText && bodyText.length < 400 && !ct.includes('text/html')) {
    return bodyText;
  }
  return `HTTP ${status}`;
}

/** Gửi log lượt chat (FE agent-runtime → Laravel DB cho admin audit). */
async function persistFeChatAiMessageLog({
  session_id,
  user_message,
  assistant_message,
  metadata,
}) {
  const sid = String(session_id || "").trim().slice(0, 96);
  if (!sid || typeof fetch === "undefined") return;

  /** Laravel `required|string` rejects empty string — luôn gửi placeholder tối thiểu. */
  const umRaw = String(user_message ?? "").trim();
  const um = (umRaw.length > 0 ? umRaw : " ").slice(0, 48000);

  const persisted = assistantPayloadJsonForPersist(assistant_message).trim();
  const am = (
    persisted.length > 0
      ? persisted
      : '{"answer":"(Không có nội dung phản hồi)","suggestions":[]}'
  ).slice(0, 96000);

  try {
    const res = await fetch(`${apiV1Base()}/chat/ai/message-log`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...chatAiAuthHeaders(),
      },
      credentials: "omit",
      body: JSON.stringify({
        session_id: sid.slice(0, 64),
        user_message: um,
        assistant_message: am,
        metadata:
          metadata && typeof metadata === "object" ? metadata : {},
      }),
      keepalive: true,
    });
    if (!res.ok) {
      const errText = await res.text().catch(() => "");
      if (import.meta.env.DEV) {
        console.warn(
          "[chat/ai/message-log]",
          res.status,
          errText.slice(0, 500),
        );
      }
    }
  } catch (e) {
    if (import.meta.env.DEV) {
      console.warn("[chat/ai/message-log] fetch failed", e);
    }
  }
}

/**
 * Chat AI một lần (JSON).
 *
 * @param {string} message
 * @param {AbortSignal} [signal]
 * @param {{ latitude?: number, longitude?: number, history?: { role: string, content: string }[], session_id?: string } | null} [opts]
 * @returns {Promise<{ success: boolean, assistant?: string, metadata?: { ai?: Record<string, unknown> }, message?: string }>}
 */
export async function callChatAiMessage(message, signal, opts = null) {
  if (isFeChatAgentRuntime()) {
    const { invokeFeChatAgent } = await import('@fe-agent/gr45-fe-chat-runtime');
    const out = await invokeFeChatAgent(message, opts, signal);
    const sid =
      (opts && typeof opts.session_id === 'string' && opts.session_id.trim()) ||
      (out && typeof out.session_id === 'string' && out.session_id.trim()) ||
      '';
    if (sid && out && out.success !== false) {
      void persistFeChatAiMessageLog({
        session_id: sid,
        user_message: message,
        assistant_message: out?.assistant ?? "",
        metadata:
          out.metadata && typeof out.metadata === 'object'
            ? out.metadata
            : {},
      });
    }
    return out;
  }

  const url = `${apiV1Base()}/chat/ai/message`;
  const payload = { message };
  const geo = opts && typeof opts === 'object' ? opts : null;
  if (
    geo &&
    typeof geo.latitude === 'number' &&
    typeof geo.longitude === 'number' &&
    Number.isFinite(geo.latitude) &&
    Number.isFinite(geo.longitude)
  ) {
    payload.latitude = geo.latitude;
    payload.longitude = geo.longitude;
  }
  if (geo && Array.isArray(geo.history) && geo.history.length > 0) {
    payload.history = geo.history.slice(0, 20);
  }
  if (geo && typeof geo.session_id === 'string' && geo.session_id.trim().length > 0) {
    payload.session_id = geo.session_id.trim().slice(0, 64);
  }
  if (geo && geo.payload && typeof geo.payload === 'object') {
    payload.payload = geo.payload;
  }

  const timeoutMs = chatAiMessageTimeoutMs();
  const timeoutCtrl = new AbortController();
  const tid = setTimeout(() => timeoutCtrl.abort(), timeoutMs);
  let combinedSignal = timeoutCtrl.signal;
  if (signal) {
    combinedSignal =
      typeof AbortSignal.any === 'function'
        ? AbortSignal.any([signal, timeoutCtrl.signal])
        : timeoutCtrl.signal;
  }

  let res;
  try {
    res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...chatAiAuthHeaders(),
      },
      credentials: 'omit',
      body: JSON.stringify(payload),
      signal: combinedSignal,
    });
  } catch (e) {
    if (e && e.name === 'AbortError') {
      throw new Error(
        `Hết thời gian chờ sau ${Math.round(timeoutMs / 1000)}s (chat AI chậm). Tăng VITE_CHAT_AI_TIMEOUT_MS trong .env FE hoặc thử lại.`,
      );
    }
    throw e;
  } finally {
    clearTimeout(tid);
  }
  const text = await res.text().catch(() => '');
  let body;
  try {
    body = text ? JSON.parse(text) : {};
  } catch {
    body = {};
  }
  if (!res.ok) {
    const msg =
      (body && typeof body.message === 'string' && body.message) ||
      httpErrorMessage(res.status, text, res.headers.get('content-type'));
    throw new Error(msg);
  }
  if (!body || typeof body !== 'object') {
    throw new Error('Phản hồi không hợp lệ từ server.');
  }
  if (body.success !== true) {
    const msg =
      (typeof body.message === 'string' && body.message.trim()) ||
      'Chat AI thất bại.';
    throw new Error(msg);
  }
  return body;
}

/**
 * Gửi tin khách trong phiên live support (REST) — không qua chatbot.
 * Admin / nhà xe nhận qua WebSocket; widget đã subscribe `live-support.session.{publicId}`.
 *
 * @param {string} publicId
 * @param {string} bodyText
 * @param {AbortSignal} [signal]
 * @returns {Promise<{ success: boolean, data?: unknown, message?: string }>}
 */
export async function postLiveSupportCustomerMessage(publicId, bodyText, signal) {
  const pid = String(publicId || "").trim();
  const body = String(bodyText || "").trim();
  if (!pid || !body) {
    throw new Error("Thiếu nội dung hoặc mã phiên hỗ trợ.");
  }
  const url = `${apiV1Base()}/agent/support/sessions/${encodeURIComponent(pid)}/messages`;
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...chatAiAuthHeaders(),
    },
    credentials: "omit",
    body: JSON.stringify({ body, sender_type: "customer" }),
    signal,
  });
  const text = await res.text().catch(() => "");
  let json;
  try {
    json = text ? JSON.parse(text) : {};
  } catch {
    json = {};
  }
  if (!res.ok) {
    const msg =
      (json && typeof json.message === "string" && json.message.trim()) ||
      (res.status >= 500
        ? "Máy chủ hỗ trợ bận — thử lại sau."
        : `Gửi tin hỗ trợ thất bại (${res.status}).`);
    throw new Error(msg);
  }
  if (!json || typeof json !== "object" || json.success !== true) {
    const msg =
      (json && typeof json.message === "string" && json.message.trim()) ||
      "Gửi tin hỗ trợ thất bại.";
    throw new Error(msg);
  }
  return json;
}

/**
 * Khách chủ động thoát chat trực tiếp — đóng phiên (admin không reply tiếp).
 *
 * @param {string} publicId
 * @param {string} [chatWidgetSessionKey]
 * @param {AbortSignal} [signal]
 */
export async function postLiveSupportCustomerClose(
  publicId,
  chatWidgetSessionKey,
  signal,
) {
  const pid = String(publicId || "").trim();
  if (!pid) {
    throw new Error("Thiếu mã phiên hỗ trợ.");
  }
  const key =
    typeof chatWidgetSessionKey === "string"
      ? chatWidgetSessionKey.trim()
      : "";
  const url = `${apiV1Base()}/agent/support/sessions/${encodeURIComponent(pid)}/customer-close`;
  const body =
    key !== "" ? JSON.stringify({ chat_widget_session_key: key }) : "{}";
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...chatAiAuthHeaders(),
    },
    credentials: "omit",
    body,
    signal,
  });
  const text = await res.text().catch(() => "");
  let json;
  try {
    json = text ? JSON.parse(text) : {};
  } catch {
    json = {};
  }
  if (!res.ok) {
    const msg =
      (json && typeof json.message === "string" && json.message.trim()) ||
      (res.status >= 500
        ? "Máy chủ hỗ trợ bận — thử lại sau."
        : `Đóng phiên hỗ trợ thất bại (${res.status}).`);
    throw new Error(msg);
  }
  if (!json || typeof json !== "object" || json.success !== true) {
    const msg =
      (json && typeof json.message === "string" && json.message.trim()) ||
      "Đóng phiên hỗ trợ thất bại.";
    throw new Error(msg);
  }
  return json;
}

/**
 * Gửi khi khách reload / đóng tab để backend đặt user_closed_at.
 */
export function notifyChatAiUserClosing(sessionKey) {
  if (isFeChatAgentRuntime()) {
    return Promise.resolve(null);
  }
  const key = String(sessionKey || "").trim();
  if (!key || typeof fetch === "undefined") {
    return Promise.resolve(null);
  }
  const url = `${apiV1Base()}/chat/ai/session/user-close`;
  return fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...chatAiAuthHeaders(),
    },
    body: JSON.stringify({ session_key: key }),
    keepalive: true,
  }).then(async (res) => {
    try {
      return await res.json();
    } catch {
      return null;
    }
  });
}

/**
 * Reload / đóng tab: đánh dấu mọi phiên live support đang mở của widget key là closed (admin không reply tiếp).
 */
export function notifyLiveSupportWidgetDisconnect(chatWidgetSessionKey) {
  const key = String(chatWidgetSessionKey || "").trim();
  if (!key || typeof fetch === "undefined") {
    return Promise.resolve(null);
  }
  const url = `${apiV1Base()}/agent/support/sessions/widget-disconnect`;
  return fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...chatAiAuthHeaders(),
    },
    body: JSON.stringify({ chat_widget_session_key: key }),
    keepalive: true,
  }).then(async (res) => {
    try {
      return await res.json();
    } catch {
      return null;
    }
  });
}

// Force Vite HMR reload


