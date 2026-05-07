<script setup>
import { ref, computed, nextTick, watch, onMounted } from "vue";
import { callChatAiMessage, parseAssistantUiPayload, getChatAiHistory } from "@/api/chatAiApi.js";

const open = ref(false);
const input = ref("");
const messages = ref([]);
const streaming = ref(false);
const quickReplies = ref([]);
/** Chờ byte đầu tiên từ bot (planner + SQL + LLM có thể vài giây) — hiện typing trong bubble. */
const waitingFirstToken = ref(false);
const assistantBuffer = ref("");
const scrollRef = ref(null);
/** Tọa độ lần lấy gần nhất (gửi kèm RAG để ưu tiên chi nhánh gần). */
const lastGeo = ref(null);
/** idle | loading | granted | denied | unavailable */
const geoState = ref("idle");
/** Một id cho cả phiên tab — ghi log admin (cột session). */
const chatAiSessionId = ref("");
/** Tránh scroll layout mỗi token — gộp theo frame. */
let scrollRaf = null;
const loginPath = String(import.meta.env.VITE_CHAT_LOGIN_PATH || "/auth/login").trim() || "/auth/login";

/** Link từ tool (tickets_url — tìm chuyến dùng chip suggestions open_search). */
function toolPayloadLinks(m) {
  const meta = m?.metadata && typeof m.metadata === "object" ? m.metadata : {};
  const tp =
    meta.tool_payload && typeof meta.tool_payload === "object"
      ? meta.tool_payload
      : {};
  const out = [];
  if (typeof tp.tickets_url === "string" && tp.tickets_url.startsWith("/")) {
    out.push({ label: "Vé của tôi", href: tp.tickets_url });
  }
  return out;
}

function goToolHref(href) {
  if (typeof window === "undefined" || !href) return;
  window.location.href = href.startsWith("/") ? href : `/${href}`;
}

function normalizeQuickReplies(items) {
  const out = [];
  const seen = new Set();
  for (const s of Array.isArray(items) ? items : []) {
    if (!s || typeof s !== "object") continue;
    const text = typeof s.text === "string" ? s.text.trim() : "";
    if (!text) continue;
    const payload = s.payload && typeof s.payload === "object" ? s.payload : {};
    const actionFromPayload =
      typeof payload.action === "string" ? payload.action.toLowerCase().trim() : "";
    const action =
      typeof s.action === "string" && s.action.trim().length > 0
        ? s.action.toLowerCase().trim()
        : actionFromPayload;
    const key = `${action}|${text.toLowerCase()}`;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push({ text, payload, action });
    if (out.length >= 6) break;
  }
  return out;
}

onMounted(async () => {
  let storedKey = localStorage.getItem("chat_session_key");
  if (!storedKey) {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
      storedKey = crypto.randomUUID();
    } else {
      storedKey = `sess-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    }
    localStorage.setItem("chat_session_key", storedKey);
  }
  chatAiSessionId.value = storedKey;

  try {
    const res = await getChatAiHistory({ session_key: storedKey });
    if (res && res.success && Array.isArray(res.data)) {
      messages.value = res.data.map(msg => {
        let text = msg.content;
        try {
          const payload = parseAssistantJsonPayload(msg.content);
          if (payload && payload.answer) {
            text = payload.answer;
          }
        } catch {
          // keep original
        }
        return {
          role: msg.role,
          text: text,
          metadata: msg.meta || {}
        };
      });
    }
  } catch (err) {
    console.error("Lỗi tải lịch sử chat AI:", err);
  }
});

function startNewChatSession() {
  if (streaming.value) return;
  
  let newKey;
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    newKey = crypto.randomUUID();
  } else {
    newKey = `sess-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  }
  
  localStorage.setItem("chat_session_key", newKey);
  chatAiSessionId.value = newKey;
  messages.value = [];
  quickReplies.value = [];
  input.value = "";
}

function refreshGeoOnce() {
  if (typeof navigator === "undefined" || !navigator.geolocation) {
    geoState.value = "unavailable";
    return Promise.resolve(false);
  }
  geoState.value = "loading";
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords?.latitude;
        const lon = pos.coords?.longitude;
        if (typeof lat === "number" && typeof lon === "number") {
          lastGeo.value = { latitude: lat, longitude: lon };
          geoState.value = "granted";
          resolve(true);
          return;
        }
        geoState.value = "unavailable";
        resolve(false);
      },
      (err) => {
        const code = Number(err?.code || 0);
        if (code === 1) {
          geoState.value = "denied";
        } else {
          geoState.value = "unavailable";
        }
        resolve(false);
      },
      { enableHighAccuracy: false, maximumAge: 120_000, timeout: 8_000 },
    );
  });
}

const canSend = computed(
  () => input.value.trim().length > 0 && !streaming.value,
);

function showAssistantBubble(m, idx) {
  if (m.role === "user") return true;
  if (String(m.text || "").length > 0) return true;
  const last = messages.value.length - 1;
  return (
    idx === last &&
    streaming.value &&
    waitingFirstToken.value &&
    m.role === "assistant"
  );
}

async function scrollToEnd() {
  await nextTick();
  await new Promise((r) => requestAnimationFrame(r));
  const el = scrollRef.value;
  if (!el) return;
  el.scrollTop = el.scrollHeight;
  await new Promise((r) => requestAnimationFrame(r));
  el.scrollTop = el.scrollHeight;
}

function pushUser(text) {
  messages.value.push({ role: "user", text });
  scrollToEnd();
}

function startAssistant() {
  assistantBuffer.value = "";
  messages.value.push({ role: "assistant", text: "" });
  void scrollToEnd();
}

function patchAssistant(delta) {
  assistantBuffer.value += delta;
  const last = messages.value[messages.value.length - 1];
  if (last && last.role === "assistant") {
    last.text = assistantBuffer.value;
  }
  if (scrollRaf == null) {
    scrollRaf = requestAnimationFrame(() => {
      scrollRaf = null;
      const el = scrollRef.value;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    });
  }
}

function historyForApi() {
  return messages.value
    .filter(
      (m) =>
        (m.role === "user" || m.role === "assistant") &&
        String(m.text || "").trim().length > 0,
    )
    .map((m) => {
      const row = {
        role: m.role,
        content: String(m.text || "").trim(),
      };
      if (
        m.metadata &&
        typeof m.metadata === "object" &&
        Object.keys(m.metadata).length > 0
      ) {
        row.metadata = m.metadata;
      }
      return row;
    })
    .slice(-14);
}

function formatChatAiFetchError(e) {
  const msg = String(e?.message || e || "").trim();
  if (!msg) return "Lỗi kết nối.";
  if (/hết thời gian chờ/i.test(msg)) return msg;
  if (/failed to fetch|network error|load failed|networkerror/i.test(msg)) {
    return `${msg} — Kiểm tra backend đang chạy; nếu chat rất lâu, tăng VITE_CHAT_AI_TIMEOUT_MS hoặc xem log Laravel.`;
  }
  return msg;
}

function parseAssistantJsonPayload(rawText) {
  const base = parseAssistantUiPayload(rawText);
  if (!base) return null;
  const answer = String(base.answer || "").trim();
  const suggestions = Array.isArray(base.suggestions)
    ? base.suggestions
        .map((s) => {
          if (typeof s === "string") {
            const text = s.trim();
            return text ? { text, action: "", params: {} } : null;
          }
          if (!s || typeof s !== "object") return null;
          const text = typeof s.text === "string" ? s.text.trim() : "";
          if (!text) return null;
          return {
            text,
            action: typeof s.action === "string" ? s.action : "",
            params: s.params && typeof s.params === "object" ? s.params : {},
          };
        })
        .filter(Boolean)
        .slice(0, 3)
    : [];
  return { answer, suggestions };
}

/** Gửi message + vị trí (lat/lon) + history + session_id — không gửi payload bổ sung. */
async function submitMessage(text) {
  const displayText = String(text || "").trim();
  if (!displayText || streaming.value) return;
  if (!lastGeo.value) {
    await refreshGeoOnce();
  }
  quickReplies.value = [];
  const history = historyForApi();
  pushUser(displayText);
  startAssistant();
  streaming.value = true;
  waitingFirstToken.value = true;

  const opts = {
    history,
    session_id: chatAiSessionId.value,
  };
  if (
    lastGeo.value &&
    typeof lastGeo.value.latitude === "number" &&
    typeof lastGeo.value.longitude === "number"
  ) {
    opts.latitude = lastGeo.value.latitude;
    opts.longitude = lastGeo.value.longitude;
  }

  try {
    const body = await callChatAiMessage(displayText, undefined, opts);

    waitingFirstToken.value = false;
    const last = messages.value[messages.value.length - 1];
    if (last && last.role === "assistant") {
      const rawForUi = String(body.assistant ?? "").trim();
      assistantBuffer.value = rawForUi;
      last.text = rawForUi;
      if (body.metadata && typeof body.metadata === "object") {
        last.metadata = body.metadata;
      }
      const payload = parseAssistantJsonPayload(rawForUi);
      if (payload) {
        const ans = String(payload.answer || "").trim();
        if (ans) {
          last.text = ans;
        }
        quickReplies.value = normalizeQuickReplies(payload.suggestions);
      }
      const meta = last.metadata && typeof last.metadata === "object" ? last.metadata : {};
      if (meta.login_required === true) {
        quickReplies.value = normalizeQuickReplies([
          { text: "Đăng nhập", action: "login", payload: { action: "login" } },
          ...quickReplies.value,
        ]);
      }
      const toolPayload =
        meta.tool_payload && typeof meta.tool_payload === "object"
          ? meta.tool_payload
          : {};
      const sugFromTool = toolPayload.suggestions;
      if (Array.isArray(sugFromTool) && sugFromTool.length > 0) {
        quickReplies.value = normalizeQuickReplies([
          ...sugFromTool,
          ...quickReplies.value,
        ]);
      }
    }
  } catch (e) {
    waitingFirstToken.value = false;
    patchAssistant(`\n${formatChatAiFetchError(e)}`);
    quickReplies.value = [];
  } finally {
    streaming.value = false;
    waitingFirstToken.value = false;
    scrollToEnd();
  }
}

async function send() {
  const text = input.value.trim();
  if (!text || streaming.value) return;
  input.value = "";
  await submitMessage(text);
}

function onQuickChip(label) {
  if (streaming.value) return;
  const action = String(label?.action || "").toLowerCase().trim();
  if (label && typeof label === "object" && action === "login") {
    if (typeof window !== "undefined") {
      window.location.href = loginPath;
    }
    return;
  }
  if (label && typeof label === "object" && action === "open_booking") {
    const id = label?.payload?.id_chuyen_xe;
    if (typeof window !== "undefined" && id != null && String(id).trim() !== "") {
      window.location.href = `/dat-ve?id_chuyen_xe=${encodeURIComponent(String(id).trim())}`;
    }
    return;
  }
  if (label && typeof label === "object" && action === "open_search") {
    const q = label?.payload?.query;
    const qs =
      typeof q === "string" && q.trim().length > 0
        ? (q.trim().startsWith("?") ? q.trim() : `?${q.trim()}`)
        : "";
    if (typeof window !== "undefined") {
      window.location.href = `/search${qs}`;
    }
    return;
  }
  if (label && typeof label === "object" && action === "open_tickets") {
    const p = label?.payload?.path;
    const path =
      typeof p === "string" && p.trim().startsWith("/") ? p.trim() : "/lich-su-dat-ve";
    if (typeof window !== "undefined") {
      window.location.href = path;
    }
    return;
  }
  const text = typeof label === "string" ? label : String(label?.text || "").trim();
  if (!text) return;
  void submitMessage(text);
}

function toggle() {
  open.value = !open.value;
  if (open.value) {
    void refreshGeoOnce();
  }
}

const geoLabel = computed(() => {
  if (geoState.value === "granted" && lastGeo.value) return "Định vị: đã bật";
  if (geoState.value === "loading") return "Định vị: đang lấy...";
  if (geoState.value === "denied") return "Định vị: bị từ chối";
  if (geoState.value === "unavailable") return "Định vị: không khả dụng";
  return "Định vị: chưa bật";
});

watch(open, (v) => {
  if (v) {
    void scrollToEnd();
  }
});

/** Không watch toàn bộ nội dung tin — mỗi token SSE sẽ map lại cả mảng và scroll, gây đơ. Cuộn đã xử lý trong patchAssistant + finally. */

watch([quickReplies, streaming], () => {
  void scrollToEnd();
}, { deep: true, flush: "post" });
</script>

<template>
  <div class="rag-chat">
    <button
      type="button"
      class="rag-chat__fab"
      aria-label="Mở trợ lý BusSafe"
      @click="toggle"
    >
      <span class="material-symbols-outlined">smart_toy</span>
    </button>

    <Transition name="rag-fade">
      <div
        v-if="open"
        class="rag-chat__panel"
        role="dialog"
        aria-modal="true"
      >
        <header class="rag-chat__head">
          <div>
            <strong>Trợ lý ảo</strong>
            <div class="rag-chat__geo">{{ geoLabel }}</div>
          </div>
          <div class="rag-chat__head-actions">
            <button
              type="button"
              class="rag-chat__icon"
              title="Tạo đoạn hội thoại mới"
              aria-label="Hội thoại mới"
              :disabled="streaming"
              @click="startNewChatSession"
            >
              <span class="material-symbols-outlined">add_comment</span>
            </button>
            <button
              type="button"
              class="rag-chat__icon"
              aria-label="Đóng"
              @click="open = false"
            >
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
        </header>

        <div ref="scrollRef" class="rag-chat__body">
          <div v-if="!messages.length" class="rag-chat__hint">
            Hỏi lịch trình, quy định đặt vé, hoặc thông tin trong tài liệu đã tải lên hệ thống.
          </div>
          <div
            v-for="(m, idx) in messages"
            v-show="m.role === 'user' || showAssistantBubble(m, idx)"
            :key="idx"
            class="rag-chat__turn"
            :class="
              m.role === 'user' ? 'rag-chat__turn--user' : 'rag-chat__turn--bot'
            "
          >
            <div
              class="rag-chat__msg"
              :class="
                m.role === 'user' ? 'rag-chat__msg--user' : 'rag-chat__msg--bot'
              "
            >
              <template v-if="m.role === 'assistant' && waitingFirstToken && idx === messages.length - 1 && !String(m.text || '').length">
                <span class="rag-chat__typing" role="status" aria-live="polite" aria-busy="true">
                  <span class="rag-chat__typing-dot" aria-hidden="true" />
                  <span class="rag-chat__typing-dot" aria-hidden="true" />
                  <span class="rag-chat__typing-dot" aria-hidden="true" />
                </span>
              </template>
              <template v-else>{{ m.text }}</template>
            </div>
            <div
              v-if="m.role === 'assistant' && toolPayloadLinks(m).length"
              class="rag-chat__tool-links"
            >
              <a
                v-for="(lnk, li) in toolPayloadLinks(m)"
                :key="li"
                href="#"
                class="rag-chat__tool-link"
                @click.prevent="goToolHref(lnk.href)"
                >{{ lnk.label }}</a>
            </div>
          </div>
        </div>

        <div
          v-if="quickReplies.length > 0"
          class="rag-chat__quick"
        >
          <div class="rag-chat__quick-scroll" role="list">
            <button
              v-for="(q, i) in quickReplies"
              :key="i"
              type="button"
              class="rag-chat__quick-chip"
              role="listitem"
              :disabled="streaming"
              @click="onQuickChip(q)"
            >
              {{ q.text }}
            </button>
          </div>
        </div>

        <footer class="rag-chat__foot">
          <div class="rag-chat__composer">
            <textarea
              v-model="input"
              rows="1"
              class="rag-chat__input rag-chat__input--grow"
              placeholder="Nhập câu hỏi..."
              @keydown.enter.exact.prevent="send"
            />
            <div class="rag-chat__composer-actions">
              <button
                type="button"
                class="rag-chat__btn rag-chat__btn--primary"
                :disabled="!canSend"
                @click="send"
              >
                Gửi
              </button>
            </div>
          </div>
        </footer>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.rag-chat {
  position: fixed;
  z-index: 12000;
  right: 1.25rem;
  bottom: 1.25rem;
  font-family: system-ui, -apple-system, sans-serif;
}

.rag-chat__fab {
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 999px;
  border: none;
  cursor: pointer;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 28px rgba(37, 99, 235, 0.35);
}
.rag-chat__fab:hover {
  filter: brightness(1.05);
}

.rag-chat__panel {
  position: absolute;
  right: 0;
  bottom: 3.75rem;
  width: min(100vw - 2rem, 22rem);
  height: min(70vh, 28rem);
  min-height: 0;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.rag-chat__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.62rem 0.9rem;
  background: linear-gradient(90deg, #f8fafc, #eff6ff);
  border-bottom: 1px solid #e2e8f0;
}
.rag-chat__head strong {
  font-size: 0.95rem;
  color: #0f172a;
}
.rag-chat__geo {
  margin-top: 0.1rem;
  font-size: 0.72rem;
  color: #64748b;
}
.rag-chat__head-actions {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.rag-chat__icon {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
  padding: 0.2rem;
  border-radius: 0.35rem;
}
.rag-chat__icon:hover {
  background: #e2e8f0;
}

.rag-chat__quick {
  flex-shrink: 0;
  border-top: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.45rem 0.55rem 0.35rem;
}
.rag-chat__quick-scroll {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.35rem;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 0.1rem;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}
.rag-chat__quick-scroll::-webkit-scrollbar {
  height: 4px;
}
.rag-chat__quick-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.rag-chat__quick-chip {
  flex: 0 0 auto;
  max-width: 14rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #0f172a;
  font-size: 0.72rem;
  line-height: 1.25;
  padding: 0.35rem 0.65rem;
  border-radius: 999px;
  cursor: pointer;
}
.rag-chat__quick-chip:hover:not(:disabled) {
  border-color: #93c5fd;
  background: #eff6ff;
}
.rag-chat__quick-chip:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.rag-chat__body {
  flex: 1 1 0%;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  overscroll-behavior: contain;
  padding: 0.75rem;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.rag-chat__hint {
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.45;
  padding: 0.35rem;
}

.rag-chat__turn {
  display: flex;
  width: 100%;
  align-items: flex-start;
  flex-shrink: 0;
}
.rag-chat__turn--user {
  justify-content: flex-end;
}
.rag-chat__turn--bot {
  justify-content: flex-start;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
}

.rag-chat__msg {
  box-sizing: border-box;
  display: block;
  width: fit-content;
  max-width: 100%;
  min-width: 0;
  padding: 0.5rem 0.65rem;
  border-radius: 0.65rem;
  font-size: 0.82rem;
  line-height: 1.45;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  word-break: break-word;
}
.rag-chat__msg--user {
  background: #2563eb;
  color: #fff;
  border-bottom-right-radius: 0.25rem;
}
.rag-chat__msg--bot {
  background: #fff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
  border-bottom-left-radius: 0.25rem;
}

.rag-chat__tool-links {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 0.65rem;
  padding: 0 0.05rem 0.15rem;
}
.rag-chat__tool-link {
  font-size: 0.75rem;
  font-weight: 500;
  color: #2563eb;
  text-decoration: underline;
  text-underline-offset: 2px;
  cursor: pointer;
}
.rag-chat__tool-link:hover {
  color: #1d4ed8;
}

.rag-chat__typing {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  vertical-align: middle;
  min-height: 1.1rem;
}
.rag-chat__typing-dot {
  width: 0.38rem;
  height: 0.38rem;
  border-radius: 999px;
  background: #64748b;
  animation: rag-chat-bounce 1.05s ease-in-out infinite;
}
.rag-chat__typing-dot:nth-child(2) {
  animation-delay: 0.15s;
}
.rag-chat__typing-dot:nth-child(3) {
  animation-delay: 0.3s;
}
@keyframes rag-chat-bounce {
  0%,
  80%,
  100% {
    transform: translateY(0);
    opacity: 0.45;
  }
  40% {
    transform: translateY(-0.28rem);
    opacity: 1;
  }
}

.rag-chat__foot {
  padding: 0.55rem 0.65rem 0.65rem;
  border-top: 1px solid #e2e8f0;
  background: #fff;
}

.rag-chat__composer {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.rag-chat__input {
  resize: none;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 0.5rem 0.55rem;
  font-size: 0.82rem;
  line-height: 1.35;
  outline: none;
  min-height: 2.35rem;
  max-height: 5rem;
}

.rag-chat__input--grow {
  flex: 1;
  min-width: 0;
  width: auto;
}

.rag-chat__input:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.rag-chat__composer-actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.35rem;
}
.rag-chat__btn {
  border-radius: 0.45rem;
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
}
.rag-chat__btn--primary {
  background: #2563eb;
  color: #fff;
}
.rag-chat__btn--primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.rag-chat__btn--ghost {
  background: #fff;
  border-color: #e2e8f0;
  color: #475569;
}

.rag-fade-enter-active,
.rag-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.rag-fade-enter-from,
.rag-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
