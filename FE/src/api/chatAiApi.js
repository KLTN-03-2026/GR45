/**
 * Chat AI API helpers.
 */

/** Timeout (ms) cho POST chat AI JSON — mặc định 10 phút (ReAct + LLM có thể rất lâu). */
function chatAiMessageTimeoutMs() {
  const raw = String(import.meta.env.VITE_CHAT_AI_TIMEOUT_MS || '').trim();
  const n = Number.parseInt(raw, 10);
  if (Number.isFinite(n) && n >= 120000) {
    return n;
  }
  return 600000;
}

function apiV1Base() {
  let raw = String(import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/v1').trim();
  raw = raw.replace(/\/+$/, '');
  if (/\/api$/i.test(raw)) {
    raw += '/v1';
  }
  return raw;
}

/** Gửi Bearer nếu khách đã đăng nhập (cùng key với axios client) — để tool "vé của tôi" hoạt động. */
function chatAiAuthHeaders() {
  if (typeof localStorage === 'undefined') return {};
  const t = localStorage.getItem('auth.client.token');
  if (t && String(t).trim().length > 0) {
    return { Authorization: `Bearer ${String(t).trim()}` };
  }
  return {};
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

function httpErrorMessage(status, bodyText, contentType) {
  const ct = (contentType || '').toLowerCase();
  if (status === 404 && (ct.includes('text/html') || /^\s*</.test(bodyText))) {
    return `HTTP ${status} — Không tìm thấy API. Kiểm tra VITE_API_URL (ví dụ https://api.bussafe.io.vn/api/v1), backend đang chạy, và route POST /api/v1/chat/ai/message.`;
  }
  if (bodyText && bodyText.length < 400 && !ct.includes('text/html')) {
    return bodyText;
  }
  return `HTTP ${status}`;
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
 * Lấy lịch sử chat dựa vào session_key hoặc chat_session_id.
 *
 * @param {{ session_key?: string, chat_session_id?: string }} params
 * @returns {Promise<{ success: boolean, data: { role: string, content: string, meta?: any }[] }>}
 */
export async function getChatAiHistory(params) {
  const query = new URLSearchParams();
  if (params.session_key) query.append('session_key', params.session_key);
  if (params.chat_session_id) query.append('chat_session_id', params.chat_session_id);

  const url = `${apiV1Base()}/chat/ai/history?${query.toString()}`;

  const res = await fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      ...chatAiAuthHeaders(),
    },
  });

  const text = await res.text().catch(() => '');
  let body;
  try {
    body = text ? JSON.parse(text) : {};
  } catch {
    body = {};
  }

  if (!res.ok) {
    const msg = (body && body.message) || `HTTP ${res.status}`;
    throw new Error(msg);
  }

  return body;
}



