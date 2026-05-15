<script setup>
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from "vue";
import {
  callChatAiMessage,
  coerceAssistantStructured,
  parseAssistantUiPayload,
  notifyChatAiUserClosing,
  notifyLiveSupportWidgetDisconnect,
  postLiveSupportCustomerClose,
  postLiveSupportCustomerMessage,
} from "@/api/chatAiApi.js";
import { useLiveSupportChannel } from "@/composables/useLiveSupportChannel";
// Force Vite HMR reload

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

/** Phiên chỉ khóa theo widget — không tải lịch sử DB khi mount; admin xem transcript qua message-log BE. */
const threadLocked = ref(false);
/** Đang trong phiên live support — tin nhắn khách gửi thẳng REST + Echo, không gọi chatbot. */
const liveSupportActivePublicId = ref("");
/** Tin nhận được khi khách đã đóng popup — hiện badge trên FAB. */
const unreadWhileClosed = ref(0);

const WELCOME_MESSAGE =
  "Dạ, em có thể hỗ trợ tra cứu tuyến xe, chuyến xe, giờ khởi hành, giá vé và điểm đón/trả.";

/** Chip gửi nguyên văn vào bot — planner/tool `support_create_support_session`. */
const WELCOME_QUICK_REPLIES = [
  "Tìm chuyến xe",
  "Kiểm tra tuyến xe",
  "Hỏi điểm đón/trả",
  "Liên hệ hỗ trợ",
];

function flushUserCloseBeacon() {
  const key = chatAiSessionId.value?.trim();
  if (!key) return;
  void notifyChatAiUserClosing(key);
  void notifyLiveSupportWidgetDisconnect(key);
}

const {
  subscribe: subscribeLiveSupport,
  unsubscribe: unsubscribeLiveSupport,
  unsubscribeAll,
  isSubscribed,
} = useLiveSupportChannel("customer_widget");

/**
 * Phiên đang được **chính khách** chủ động thoát qua nút "Thoát hỗ trợ".
 * Echo `customer_disconnected` cho các pid này sẽ bị **suppress** để không
 * trùng với bubble local "Bạn đã thoát chat trực tiếp" do `exitLiveSupportToBot`
 * push. Phiên kết thúc bởi admin (`resolved`) vẫn được hiển thị bình thường.
 */
const liveSupportPidsExitingByCustomer = new Set();

function liveSupportEndedAssistantText(kind) {
  if (kind === "resolved") {
    return "Phiên hỗ trợ trực tiếp đã kết thúc (nhân viên đã xử lý xong). Bạn vẫn có thể chat với bot; nếu cần người thật, hãy yêu cầu lại để mở phiên mới.";
  }
  return "Phiên hỗ trợ đã đóng (ví dụ bạn tải lại hoặc rời trang). Bạn vẫn có thể chat với bot; nếu cần người thật, hãy yêu cầu lại để mở phiên mới.";
}

function subscribeToLiveSupportPublicId(publicId) {
  const pid = String(publicId || "").trim();
  if (!pid || isSubscribed(pid)) return;
  subscribeLiveSupport(
    pid,
    (message) => {
      if (message.role === "admin") {
        const panelHidden = !open.value;
        messages.value.push({
          role: message.role,
          text: message.content,
          metadata: message.meta || {},
        });
        if (panelHidden) {
          unreadWhileClosed.value += 1;
        } else {
          scrollToEnd();
        }
      }
    },
    {
      onSessionEnded: (detail) => {
        if (String(detail?.public_id || "").trim() !== pid) return;
        if (liveSupportActivePublicId.value.trim() === pid) {
          liveSupportActivePublicId.value = "";
        }
        unsubscribeLiveSupport(pid);

        /**
         * Khách vừa bấm nút "Thoát hỗ trợ" — `exitLiveSupportToBot` đã (hoặc đang)
         * push bubble "Bạn đã thoát chat trực tiếp…". Bỏ qua bubble Echo để khỏi trùng.
         */
        const suppressBubble =
          detail.kind === "customer_disconnected" &&
          liveSupportPidsExitingByCustomer.has(pid);
        liveSupportPidsExitingByCustomer.delete(pid);
        if (suppressBubble) {
          return;
        }

        const panelHidden = !open.value;
        messages.value.push({
          role: "assistant",
          text: liveSupportEndedAssistantText(detail.kind),
          metadata: {
            live_support_session_ended: true,
            live_support_end_reason: detail.kind,
          },
        });
        if (panelHidden) {
          unreadWhileClosed.value += 1;
        } else {
          scrollToEnd();
        }
      },
    },
  );
}

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

/**
 * Chuẩn hoá chip gợi ý — hỗ trợ string[], object có text/action/payload hoặc params.
 */
function normalizeQuickReplies(items) {
  const out = [];
  const seen = new Set();
  for (const raw of Array.isArray(items) ? items : []) {
    let s = raw;
    if (typeof raw === "string") {
      const text = raw.trim();
      if (!text) continue;
      s = { text, action: "", payload: {} };
    }
    if (!s || typeof s !== "object") continue;
    const text = typeof s.text === "string" ? s.text.trim() : "";
    if (!text) continue;
    const payload =
      s.payload && typeof s.payload === "object"
        ? s.payload
        : s.params && typeof s.params === "object"
          ? s.params
          : {};
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

function ensureWelcomeState() {
  if (messages.value.length === 0) {
    messages.value.push({ role: "assistant", text: WELCOME_MESSAGE });
  }
  if (quickReplies.value.length === 0) {
    quickReplies.value = normalizeQuickReplies(WELCOME_QUICK_REPLIES);
  }
  void scrollToEnd();
}

onMounted(() => {
  if (typeof window !== "undefined") {
    window.addEventListener("pagehide", flushUserCloseBeacon);
    window.addEventListener("beforeunload", flushUserCloseBeacon);
  }

  chatAiSessionId.value = newChatSessionId();
});

function newChatSessionId() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return `sess-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function startNewChatSession() {
  if (streaming.value) return;

  unsubscribeAll();
  chatAiSessionId.value = newChatSessionId();
  messages.value = [];
  quickReplies.value = [];
  input.value = "";
  assistantBuffer.value = "";
  threadLocked.value = false;
  liveSupportActivePublicId.value = "";
  unreadWhileClosed.value = 0;
  if (open.value) {
    ensureWelcomeState();
  }
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
  () => input.value.trim().length > 0 && !streaming.value && !threadLocked.value,
);

const isLiveSupportActive = computed(
  () => liveSupportActivePublicId.value.trim().length > 0,
);

const unreadFabBadge = computed(() => {
  const n = unreadWhileClosed.value;
  if (n <= 0) return "";
  if (n > 99) return "99+";
  return String(n);
});

const fabAriaLabel = computed(() =>
  unreadWhileClosed.value > 0
    ? `Trợ lý BusSafe — ${unreadFabBadge.value} tin mới`
    : "Mở trợ lý BusSafe",
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

/** Gửi tin trong phiên live support — không invoke agent / LLM. */
async function submitLiveSupportOnlyMessage(displayText) {
  const pid = liveSupportActivePublicId.value.trim();
  if (!pid) return;

  quickReplies.value = [];
  pushUser(displayText);
  streaming.value = true;

  try {
    await postLiveSupportCustomerMessage(pid, displayText);
  } catch (e) {
    messages.value.push({
      role: "assistant",
      text: formatChatAiFetchError(e),
      metadata: { live_support_send_error: true },
    });
  } finally {
    streaming.value = false;
    scrollToEnd();
  }
}

async function exitLiveSupportToBot() {
  const pid = liveSupportActivePublicId.value.trim();
  if (!pid || streaming.value || threadLocked.value) return;

  /** Đánh dấu trước khi BE broadcast → onSessionEnded Echo sẽ suppress bubble trùng. */
  liveSupportPidsExitingByCustomer.add(pid);

  streaming.value = true;
  try {
    await postLiveSupportCustomerClose(pid, chatAiSessionId.value);
  } catch (e) {
    liveSupportPidsExitingByCustomer.delete(pid);
    messages.value.push({
      role: "assistant",
      text: formatChatAiFetchError(e),
      metadata: { live_support_exit_error: true },
    });
    streaming.value = false;
    await scrollToEnd();
    return;
  }

  unsubscribeLiveSupport(pid);
  liveSupportActivePublicId.value = "";
  streaming.value = false;

  messages.value.push({
    role: "assistant",
    text: "Bạn đã thoát chat trực tiếp — có thể tiếp tục chat với bot. Nếu cần người thật, hãy yêu cầu lại.",
    metadata: { live_support_customer_exited: true },
  });
  quickReplies.value = normalizeQuickReplies(WELCOME_QUICK_REPLIES);
  await scrollToEnd();
}

/** Gửi message + vị trí (lat/lon) + history + session_id — không gửi payload bổ sung. */
async function submitMessage(text) {
  const displayText = String(text || "").trim();
  if (!displayText || streaming.value || threadLocked.value) return;

  if (liveSupportActivePublicId.value.trim()) {
    await submitLiveSupportOnlyMessage(displayText);
    return;
  }
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

    const lsIds =
      body.metadata?.ai?.live_support_public_ids &&
      Array.isArray(body.metadata.ai.live_support_public_ids)
        ? body.metadata.ai.live_support_public_ids
        : [];
    const liveSupportDeferredOpening = Boolean(
      body.metadata?.ai?.live_support_deferred_opening,
    );
    for (const pid of lsIds) {
      subscribeToLiveSupportPublicId(pid);
    }
    const firstLivePid = lsIds
      .map((x) => String(x ?? "").trim())
      .find((s) => s.length > 0);

    waitingFirstToken.value = false;

    if (body.is_paused) {
      // Bỏ qua hiển thị AI (xóa bong bóng assistant vừa tạo)
      const last = messages.value[messages.value.length - 1];
      if (last && last.role === "assistant") {
        messages.value.pop();
      }
      return;
    }

    // Đã kết nối chat trực tiếp — không hiển thị phản hồi LLM (tránh lời chào/hướng dẫn thừa).
    if (firstLivePid) {
      liveSupportActivePublicId.value = firstLivePid;
      quickReplies.value = [];
      const lastBubble = messages.value[messages.value.length - 1];
      if (lastBubble && lastBubble.role === "assistant") {
        messages.value.pop();
      }
      if (liveSupportDeferredOpening && displayText.trim()) {
        try {
          await postLiveSupportCustomerMessage(firstLivePid, displayText);
        } catch (e) {
          messages.value.push({
            role: "assistant",
            text: formatChatAiFetchError(e),
            metadata: { live_support_send_error: true },
          });
        }
      }
      return;
    }

    const last = messages.value[messages.value.length - 1];
    if (last && last.role === "assistant") {
      if (body.metadata && typeof body.metadata === "object") {
        last.metadata = body.metadata;
      }

      const structured = coerceAssistantStructured(body.assistant);
      let suggestionsForQuick = [];

      if (structured) {
        const ans = String(structured.answer ?? "").trim();
        assistantBuffer.value = ans;
        last.text = ans;
        suggestionsForQuick = Array.isArray(structured.suggestions)
          ? structured.suggestions
          : [];
      } else {
        const rawForUi = String(body.assistant ?? "").trim();
        assistantBuffer.value = rawForUi;
        last.text = rawForUi;
        const parsed = parseAssistantJsonPayload(rawForUi);
        if (parsed?.answer?.trim()) {
          last.text = parsed.answer.trim();
          assistantBuffer.value = last.text;
        }
        suggestionsForQuick = parsed?.suggestions ?? [];
      }

      quickReplies.value = normalizeQuickReplies(suggestionsForQuick);
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

      const tail = messages.value[messages.value.length - 1];
      if (
        !open.value &&
        tail &&
        tail.role === "assistant" &&
        String(tail.text || "").trim().length > 0
      ) {
        unreadWhileClosed.value += 1;
      }
    }
  } catch (e) {
    waitingFirstToken.value = false;
    patchAssistant(`\n${formatChatAiFetchError(e)}`);
    const msg = String(e?.message || e || "");
    if (/đã kết thúc|thread_locked|chỉ xem lịch sử/i.test(msg)) {
      threadLocked.value = true;
    }
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
  if (streaming.value || threadLocked.value) return;
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
    unreadWhileClosed.value = 0;
    ensureWelcomeState();
    void scrollToEnd();
  }
});

/** Không watch toàn bộ nội dung tin — mỗi token SSE sẽ map lại cả mảng và scroll, gây đơ. Cuộn đã xử lý trong patchAssistant + finally. */

watch([quickReplies, streaming], () => {
  void scrollToEnd();
}, { deep: true, flush: "post" });

onUnmounted(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("pagehide", flushUserCloseBeacon);
    window.removeEventListener("beforeunload", flushUserCloseBeacon);
  }
});
</script>

<template>
  <div class="rag-chat">
    <button
      type="button"
      class="rag-chat__fab"
      :aria-label="fabAriaLabel"
      data-testid="chat-ai-fab"
      @click="toggle"
    >
      <span class="material-symbols-outlined">smart_toy</span>
      <span
        v-if="unreadWhileClosed > 0"
        class="rag-chat__fab-badge"
        aria-hidden="true"
        >{{ unreadFabBadge }}</span
      >
    </button>

    <Transition name="rag-fade">
      <div
        v-if="open"
        class="rag-chat__panel"
        role="dialog"
        aria-modal="true"
        data-testid="chat-ai-panel"
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
          v-if="quickReplies.length > 0 || isLiveSupportActive"
          class="rag-chat__quick"
        >
          <div class="rag-chat__quick-scroll" role="list">
            <button
              v-if="isLiveSupportActive"
              type="button"
              class="rag-chat__quick-chip rag-chat__quick-chip--exit-support"
              role="listitem"
              :disabled="streaming || threadLocked"
              @click="exitLiveSupportToBot"
            >
              Thoát hỗ trợ
            </button>
            <button
              v-for="(q, i) in quickReplies"
              :key="i"
              type="button"
              class="rag-chat__quick-chip"
              role="listitem"
              :disabled="streaming || threadLocked || isLiveSupportActive"
              @click="onQuickChip(q)"
            >
              {{ q.text }}
            </button>
          </div>
        </div>

        <footer class="rag-chat__foot">
          <p v-if="threadLocked" class="rag-chat__locked">
            Phiên đã kết thúc — chỉ xem lịch sử.
          </p>
          <p v-else-if="isLiveSupportActive" class="rag-chat__locked rag-chat__live-hint">
            Đang chat trực tiếp với hỗ trợ viên — tin gửi thẳng tới admin/nhà xe, bot tạm không trả lời.
          </p>
          <div class="rag-chat__composer">
            <textarea
              v-model="input"
              rows="1"
              class="rag-chat__input rag-chat__input--grow"
              :placeholder="
                isLiveSupportActive
                  ? 'Nhập tin nhắn cho hỗ trợ viên...'
                  : 'Nhập câu hỏi...'
              "
              data-testid="chat-ai-input"
              :disabled="threadLocked"
              @keydown.enter.exact.prevent="send"
            />
            <div class="rag-chat__composer-actions">
              <button
                type="button"
                class="rag-chat__btn rag-chat__btn--primary"
                data-testid="chat-ai-send"
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
  position: relative;
}
.rag-chat__fab-badge {
  position: absolute;
  top: -0.15rem;
  right: -0.15rem;
  min-width: 1.15rem;
  height: 1.15rem;
  padding: 0 5px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  line-height: 1.15rem;
  text-align: center;
  box-shadow: 0 1px 5px rgba(0, 0, 0, 0.22);
  pointer-events: none;
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

.rag-chat__quick-chip--exit-support {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.rag-chat__quick-chip--exit-support:hover:not(:disabled) {
  border-color: #f87171;
  background: #fee2e2;
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

.rag-chat__locked {
  margin: 0 0 0.45rem;
  padding: 0.35rem 0.45rem;
  font-size: 0.72rem;
  color: #92400e;
  background: #fef3c7;
  border-radius: 0.35rem;
  border: 1px solid #fcd34d;
}

.rag-chat__live-hint {
  color: #1e40af;
  background: #eff6ff;
  border-color: #93c5fd;
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
