<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import axiosClient from "@/api/axiosClient";
import { parseLaravelPaginatorEnvelope } from "@/utils/laravelPagination";
import {
  appendOptimisticOutgoingMessage,
  bumpLiveSupportSessionPreview,
  finalizeOutgoingReply,
  mergeEchoLiveSupportMessage,
  removeOptimisticMessage,
  sameLiveSupportSessionId,
} from "@/utils/liveSupportReplyMerge.js";
import { useLiveSupportChannel } from "@/composables/useLiveSupportChannel";
import { useAdminStore } from "@/stores/adminStore.js";
import SupportChatStatsChart from "@/components/ho-tro/SupportChatStatsChart.vue";
import {
  liveSupportBubbleSenderLabelBusafeThread,
  liveSupportBubbleSenderLineClassBusafe,
} from "@/composables/useLiveSupportBubbleLabels.js";

const adminStore = useAdminStore();
const optimisticStaffName = computed(
  () => adminStore.user?.ho_va_ten?.trim?.() ?? null,
);

const sessions = ref([]);
const sessionsMeta = ref({
  total: 0,
  last_page: 1,
  current_page: 1,
});
const sessionsPage = ref(1);
const loadingSessions = ref(false);
const loadingMoreSessions = ref(false);
const currentSessionId = ref(null);
const currentSessionDetails = ref(null);
const messages = ref([]);
const newMessage = ref("");
const isLoadingSessions = ref(true);
const isLoadingMessages = ref(false);
const isLoadingMore = ref(false);
const isSending = ref(false);
const resolving = ref(false);
const searchQuery = ref("");
const messagesContainer = ref(null);
const currentMessagePage = ref(1);
const hasMoreMessages = ref(true);

const showNewSessionModal = ref(false);
const nhaXes = ref([]);
const newSessionData = ref({ id_nha_xe: "", tieu_de: "", noi_dung: "" });
const isCreatingSession = ref(false);

const { subscribe, unsubscribeAll, isSubscribed } =
  useLiveSupportChannel("nhaxe_busafe");

/** Realtime nhận mọi phiên trong list — admin thấy ngay nhà xe trả lời trong sidebar. */
function handleEchoInboundForSession(sessionNumericId, message) {
  if (sameLiveSupportSessionId(currentSessionId.value, sessionNumericId)) {
    mergeEchoLiveSupportMessage(messages, message);
    scrollToBottom();
  }
  const s = sessions.value.find((x) =>
    sameLiveSupportSessionId(x.id, sessionNumericId),
  );
  if (s) {
    if (!s.messages) s.messages = [];
    s.messages[0] = message;
    sessions.value = [
      s,
      ...sessions.value.filter(
        (x) => !sameLiveSupportSessionId(x.id, sessionNumericId),
      ),
    ];
    if (
      message.role === "user" &&
      !sameLiveSupportSessionId(currentSessionId.value, sessionNumericId)
    ) {
      s.staff_unread_count = (Number(s.staff_unread_count) || 0) + 1;
    }
  }
}

function wireEchoForSessionsInList(rows) {
  if (!Array.isArray(rows)) return;
  for (const session of rows) {
    const pid = session?.public_id;
    if (!pid) continue;
    subscribe(pid, (message) => handleEchoInboundForSession(session.id, message));
  }
}

function bubbleSenderLabel(msg) {
  return liveSupportBubbleSenderLabelBusafeThread(
    currentSessionDetails.value,
    msg,
  );
}

function bubbleSenderLineClass(msg) {
  return liveSupportBubbleSenderLineClassBusafe(msg);
}

function unreadBadge(n) {
  const x = Number(n) || 0;
  if (x <= 0) return "";
  if (x > 99) return "99+";
  return String(x);
}

function sessionDisplayName(s) {
  return (
    s?.operator_display_name ||
    s?.nha_xe?.ten_nha_xe ||
    (s?.tieu_de ? String(s.tieu_de) : "") ||
    "Nhà xe"
  );
}

function lastPreview(s) {
  const last = s?.messages?.[0];
  const tail = last?.content ? String(last.content) : "Chưa có tin nhắn";
  if (s?.tieu_de) return `[${s.tieu_de}] ${tail}`;
  return tail;
}

function lastTime(s) {
  const last = s?.messages?.[0];
  return last?.created_at ? formatTime(last.created_at) : "";
}

async function fetchSessions(page = 1, append = false) {
  if (append) {
    loadingMoreSessions.value = true;
  } else {
    loadingSessions.value = true;
    isLoadingSessions.value = true;
  }
  try {
    const envelope = await axiosClient.get("/v1/admin/ho-tro/nha-xe/sessions", {
      params: {
        search: searchQuery.value,
        page,
      },
    });
    const { rows, total, last_page, current_page } =
      parseLaravelPaginatorEnvelope(envelope);
    if (append) {
      sessions.value = [...sessions.value, ...rows];
      wireEchoForSessionsInList(rows);
    } else {
      unsubscribeAll();
      sessions.value = rows;
      wireEchoForSessionsInList(rows);
    }
    sessionsMeta.value = {
      total,
      last_page,
      current_page,
    };
    sessionsPage.value = sessionsMeta.value.current_page;
  } catch (error) {
    console.error("Failed to fetch sessions", error);
  } finally {
    loadingSessions.value = false;
    loadingMoreSessions.value = false;
    isLoadingSessions.value = false;
  }
}

async function loadMoreSessions() {
  if (
    loadingMoreSessions.value ||
    loadingSessions.value ||
    sessionsPage.value >= sessionsMeta.value.last_page
  ) {
    return;
  }
  await fetchSessions(sessionsPage.value + 1, true);
}

async function fetchNhaXes() {
  try {
    const envelope = await axiosClient.get("/v1/admin/nha-xe/list-minimal");
    nhaXes.value = Array.isArray(envelope?.data) ? envelope.data : [];
  } catch (error) {
    console.error("Failed to fetch nha xes", error);
  }
}

async function createSession() {
  if (!newSessionData.value.id_nha_xe) return;
  isCreatingSession.value = true;
  try {
    const envelope = await axiosClient.post("/v1/admin/ho-tro/nha-xe/sessions", {
      id_nha_xe: newSessionData.value.id_nha_xe,
      tieu_de: newSessionData.value.tieu_de,
      noi_dung: newSessionData.value.noi_dung,
    });
    const newSession = envelope?.data;
    if (newSession && typeof newSession === "object") {
      if (newSessionData.value.noi_dung) {
        newSession.messages = [
          {
            content: newSessionData.value.noi_dung,
            created_at: new Date().toISOString(),
          },
        ];
      } else {
        newSession.messages = [];
      }
      sessions.value = [newSession, ...sessions.value];
    }
    newSessionData.value = { id_nha_xe: "", tieu_de: "", noi_dung: "" };
    showNewSessionModal.value = false;
    if (newSession) {
      await selectSession(newSession);
    }
  } catch (error) {
    console.error("Failed to create session", error);
    alert(error.response?.data?.message || "Có lỗi xảy ra khi tạo cuộc hội thoại");
  } finally {
    isCreatingSession.value = false;
  }
}

const selectSession = async (session) => {
  if (sameLiveSupportSessionId(currentSessionId.value, session.id)) {
    try {
      const envelope = await axiosClient.get(
        `/v1/admin/ho-tro/nha-xe/sessions/${session.id}`,
      );
      const payload = envelope?.data ?? {};
      currentSessionDetails.value = payload.session || currentSessionDetails.value;
      const idx = sessions.value.findIndex((x) =>
        sameLiveSupportSessionId(x.id, session.id),
      );
      if (idx !== -1) {
        sessions.value[idx].staff_unread_count = 0;
      }
    } catch (error) {
      console.error("Failed to sync session read state", error);
    }
    return;
  }

  currentSessionId.value = session.id;
  currentSessionDetails.value = session;
  messages.value = [];
  currentMessagePage.value = 1;
  hasMoreMessages.value = true;

  try {
    isLoadingMessages.value = true;
    const envelope = await axiosClient.get(
      `/v1/admin/ho-tro/nha-xe/sessions/${session.id}`,
    );
    const payload = envelope?.data ?? {};
    messages.value = payload.messages?.data?.slice().reverse() || [];
    currentMessagePage.value = payload.messages?.current_page || 1;
    hasMoreMessages.value =
      (payload.messages?.current_page || 1) < (payload.messages?.last_page || 1);
    currentSessionDetails.value = payload.session || session;

    const idx = sessions.value.findIndex((x) =>
      sameLiveSupportSessionId(x.id, session.id),
    );
    if (idx !== -1) {
      sessions.value[idx].staff_unread_count = 0;
      const pid = payload.session?.public_id ?? session.public_id;
      if (pid && !sessions.value[idx].public_id) {
        sessions.value[idx].public_id = pid;
      }
    }

    scrollToBottom();

    const publicId = payload.session?.public_id ?? session.public_id;
    if (publicId) {
      wireEchoForSessionsInList([{ id: session.id, public_id: publicId }]);
    }
  } catch (error) {
    console.error("Failed to fetch session messages", error);
  } finally {
    isLoadingMessages.value = false;
  }
};

const loadMoreMessages = async () => {
  if (!hasMoreMessages.value || isLoadingMore.value) return;

  const container = messagesContainer.value;
  const previousScrollHeight = container ? container.scrollHeight : 0;

  isLoadingMore.value = true;
  try {
    const envelope = await axiosClient.get(
      `/v1/admin/ho-tro/nha-xe/sessions/${currentSessionId.value}?page=${currentMessagePage.value + 1}`,
    );
    const payload = envelope?.data ?? {};
    const chunk = payload.messages?.data?.slice().reverse() || [];
    messages.value = [...chunk, ...messages.value];
    currentMessagePage.value = payload.messages?.current_page || 1;
    hasMoreMessages.value =
      (payload.messages?.current_page || 1) < (payload.messages?.last_page || 1);

    await nextTick();
    if (container) {
      container.scrollTop = container.scrollHeight - previousScrollHeight;
    }
  } catch (error) {
    console.error("Failed to load more messages", error);
  } finally {
    isLoadingMore.value = false;
  }
};

const onScroll = (e) => {
  if (e.target.scrollTop === 0) {
    loadMoreMessages();
  }
};

const staffCanReply = () =>
  currentSessionDetails.value?.staff_can_reply !== false &&
  !currentSessionDetails.value?.thread_archived;

const composerPlaceholder = computed(() => {
  if (!currentSessionId.value) return "Aa…";
  const d = currentSessionDetails.value;
  if (!d) return "Aa…";
  const closed =
    d.staff_can_reply === false ||
    d.thread_archived ||
    Boolean(d.resolved_at);
  return closed ? "Đã resolve — không thể chat thêm" : "Aa…";
});

const sendMessage = async () => {
  if (
    !newMessage.value.trim() ||
    !currentSessionId.value ||
    isSending.value ||
    !staffCanReply()
  )
    return;

  const content = newMessage.value.trim();
  newMessage.value = "";

  const previewPayload = {
    content,
    created_at: new Date().toISOString(),
    role: "admin",
    admin_name: optimisticStaffName.value,
  };
  const tempId = appendOptimisticOutgoingMessage(messages, {
    content,
    role: "admin",
    admin_name: optimisticStaffName.value,
  });
  bumpLiveSupportSessionPreview(sessions, currentSessionId.value, previewPayload);
  await scrollToBottom();

  isSending.value = true;
  try {
    const envelope = await axiosClient.post(
      `/v1/admin/ho-tro/nha-xe/sessions/${currentSessionId.value}/reply`,
      {
        content,
      },
    );
    finalizeOutgoingReply(messages, tempId, envelope);
    bumpLiveSupportSessionPreview(sessions, currentSessionId.value, envelope?.data);
    await scrollToBottom();
  } catch (error) {
    console.error("Failed to send message", error);
    removeOptimisticMessage(messages, tempId);
    newMessage.value = content;
  } finally {
    isSending.value = false;
  }
};

const resolveSession = async () => {
  if (!currentSessionId.value || resolving.value) return;
  resolving.value = true;
  try {
    const envelope = await axiosClient.post(
      `/v1/admin/ho-tro/nha-xe/sessions/${currentSessionId.value}/resolve`,
    );
    const updated = envelope?.data;
    if (updated && typeof updated === "object") {
      currentSessionDetails.value = {
        ...currentSessionDetails.value,
        ...updated,
      };
      const idx = sessions.value.findIndex((x) => x.id === currentSessionId.value);
      if (idx !== -1) {
        sessions.value[idx].thread_archived = updated.thread_archived;
      }
    }
  } catch (e) {
    console.error("resolve failed", e);
  } finally {
    resolving.value = false;
  }
};

const scrollToBottom = async () => {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
};

let searchTimeout = null;
const onSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    sessionsPage.value = 1;
    fetchSessions(1, false);
  }, 500);
};

onMounted(() => {
  fetchNhaXes();
  fetchSessions(1, false);
});

onUnmounted(() => {
  unsubscribeAll();
});

const getInitials = (name) => {
  const n = String(name || "").trim();
  if (!n) return "NX";
  return n
    .split(/\s+/)
    .map((p) => p[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
};

const formatTime = (isoString) => {
  if (!isoString) return "";
  const date = new Date(isoString);
  return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
};
</script>

<template>
  <div class="chat-support-container d-flex flex-column gap-3">
    <SupportChatStatsChart
      class="mx-chat-stats"
      api-path="/v1/admin/ho-tro/nha-xe/stats-daily"
    />

    <div class="msg-messenger-shell align-self-stretch">
      <!-- Sidebar Sessions -->
      <aside class="msg-messenger-sidebar border-end messenger-sidebar d-flex flex-column">
        <div
          class="messenger-sidebar-head px-3 py-3 border-bottom d-flex flex-column gap-2"
        >
          <div class="d-flex align-items-center justify-content-between gap-2">
            <h5 class="m-0 fw-bold text-primary d-flex align-items-center mb-0">
              <i class="bi bi-buildings me-2"></i>Hỗ Trợ Nhà Xe
            </h5>
            <span class="badge bg-secondary rounded-pill flex-shrink-0">{{
              isLoadingSessions ? "…" : sessionsMeta.total
            }}</span>
          </div>
          <button
            type="button"
            class="btn btn-sm btn-primary rounded-pill w-100"
            @click="showNewSessionModal = true"
          >
            <i class="bi bi-plus-circle me-1"></i> Tạo cuộc hội thoại mới
          </button>
        </div>

        <div class="px-3 py-2 border-bottom messenger-sidebar-search bg-white">
          <div
            class="input-group input-group-sm rounded-pill border messenger-search-inner overflow-hidden bg-white"
          >
            <span
              class="input-group-text bg-transparent border-end-0 text-muted"
            >
              <i class="bi bi-search"></i>
            </span>
            <input
              type="text"
              class="form-control border-start-0 ps-0 shadow-none"
              placeholder="Tìm nhà xe, tiêu đề..."
              v-model="searchQuery"
              @input="onSearch"
            />
          </div>
        </div>

        <div class="flex-grow-1 overflow-auto session-list">
          <div v-if="isLoadingSessions" class="text-center p-4">
            <div
              class="spinner-border text-primary spinner-border-sm"
              role="status"
            ></div>
            <div class="small mt-2 text-muted">Đang tải...</div>
          </div>

          <div
            v-else-if="sessions.length === 0"
            class="text-center p-4 text-muted small"
          >
            Không có dữ liệu hội thoại.
          </div>

          <div v-else class="list-group list-group-flush">
            <button
              v-for="session in sessions"
              :key="session.id"
              type="button"
              class="list-group-item list-group-item-action msg-row messenger-thread-row border-0 px-3 py-3 mx-2 my-1 rounded-3"
              :class="{ active: currentSessionId === session.id }"
              @click="selectSession(session)"
            >
              <div class="d-flex align-items-start gap-3">
                <div
                  class="avatar-circle flex-shrink-0"
                  :class="`bg-gradient-${(session.id % 4) + 1}`"
                >
                  {{ getInitials(sessionDisplayName(session)) }}
                </div>
                <div class="flex-grow-1 min-w-0 text-start">
                  <div class="d-flex justify-content-between gap-2 mb-1">
                    <span class="fw-semibold text-truncate title-text">{{
                      sessionDisplayName(session)
                    }}</span>
                    <span class="text-muted small flex-shrink-0 time-label">{{
                      lastTime(session)
                    }}</span>
                  </div>
                  <div class="d-flex justify-content-between gap-2 align-items-center">
                    <span class="preview-text text-muted small text-truncate">{{
                      lastPreview(session)
                    }}</span>
                    <span
                      v-if="unreadBadge(session.staff_unread_count)"
                      class="badge rounded-pill bg-danger unread-pill flex-shrink-0"
                      >{{ unreadBadge(session.staff_unread_count) }}</span
                    >
                    <span
                      v-else-if="isSubscribed(session.public_id)"
                      class="live-dot flex-shrink-0"
                      title="Realtime"
                    ></span>
                  </div>
                </div>
              </div>
            </button>
          </div>

          <div
            v-if="sessionsMeta.last_page > 1 && sessionsPage < sessionsMeta.last_page"
            class="p-2 border-top bg-white"
          >
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm w-100"
              :disabled="loadingMoreSessions"
              @click="loadMoreSessions"
            >
              <span
                v-if="loadingMoreSessions"
                class="spinner-border spinner-border-sm me-1"
                role="status"
              ></span>
              Tải thêm hội thoại
            </button>
          </div>
        </div>
      </aside>

      <!-- Chat Area -->
      <section
        class="msg-messenger-pane messenger-thread-pane bg-chat d-flex flex-column overflow-hidden"
      >
        <template v-if="currentSessionId">
          <div
            class="messenger-thread-header px-3 py-3 border-bottom d-flex align-items-center justify-content-between z-1 flex-wrap gap-2"
          >
            <div class="d-flex align-items-center">
              <div
                class="avatar-circle me-3"
                :class="`bg-gradient-${(currentSessionId % 4) + 1}`"
              >
                {{ getInitials(sessionDisplayName(currentSessionDetails)) }}
              </div>
              <div>
                <h5 class="m-0 fw-bold d-flex flex-wrap align-items-center gap-2">
                  {{ sessionDisplayName(currentSessionDetails) }}
                  <span
                    v-if="currentSessionDetails?.tieu_de"
                    class="badge bg-secondary fw-normal fs-6"
                    >{{ currentSessionDetails.tieu_de }}</span
                  >
                </h5>
                <small class="text-success d-flex align-items-center gap-2 flex-wrap">
                  <span class="live-indicator"></span>
                  Real-time
                  <span v-if="currentSessionDetails?.thread_archived" class="badge bg-secondary"
                    >Đã lưu trữ</span
                  >
                </small>
              </div>
            </div>

            <div
              class="messenger-thread-header-actions text-end d-flex flex-row flex-nowrap align-items-center justify-content-end gap-2 min-w-0"
            >
              <button
                type="button"
                class="btn-resolve-session flex-shrink-0"
                :class="{
                  'btn-resolve-session--done':
                    !!currentSessionDetails?.resolved_at,
                }"
                :disabled="
                  resolving || !!currentSessionDetails?.resolved_at
                "
                @click="resolveSession"
              >
                <template v-if="resolving">
                  <span
                    class="spinner-border spinner-border-sm"
                    role="status"
                  ></span>
                  <span>Đang xử lý…</span>
                </template>
                <template v-else-if="currentSessionDetails?.resolved_at">
                  <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                  <span>Đã resolve</span>
                </template>
                <template v-else>
                  <i class="bi bi-check2-circle" aria-hidden="true"></i>
                  <span>Resolve phiên</span>
                </template>
              </button>
              <span class="badge bg-light text-dark border flex-shrink-0"
                >ID:
                {{ currentSessionDetails?.session_key?.substring(0, 8) }}...</span
              >
              <div
                class="small text-muted messenger-thread-header-meta min-w-0 text-nowrap flex-shrink-0"
              >
                {{
                  currentSessionDetails?.nha_xe?.so_dien_thoai ||
                  currentSessionDetails?.nha_xe?.email
                }}
              </div>
            </div>
          </div>

          <div
            v-if="currentSessionDetails?.thread_archived"
            class="px-3 py-2 bg-warning-subtle border-bottom small text-dark"
          >
            Phiên đã resolve — chỉ xem lịch sử, không gửi tin được.
          </div>

          <div
            class="flex-grow-1 overflow-auto p-4 chat-messages-container"
            ref="messagesContainer"
            @scroll="onScroll"
          >
            <div v-if="isLoadingMessages" class="text-center mt-5">
              <div class="spinner-border text-primary" role="status"></div>
            </div>

            <template v-else>
              <div v-if="isLoadingMore" class="text-center mb-3">
                <div
                  class="spinner-border spinner-border-sm text-primary"
                  role="status"
                ></div>
              </div>
              <div
                v-for="msg in messages"
                :key="msg.id"
                class="mb-3 d-flex flex-column"
                :class="{
                  'align-items-end':
                    msg.role === 'admin' || msg.role === 'assistant',
                  'align-items-start': msg.role === 'user',
                }"
              >
                <div
                  class="message-bubble shadow-sm"
                  :class="{
                    'bg-primary text-white admin-bubble':
                      msg.role === 'admin',
                    'bg-white text-dark border user-bubble':
                      msg.role === 'user',
                    'bg-light text-dark border assistant-bubble':
                      msg.role === 'assistant',
                  }"
                  :title="bubbleSenderLabel(msg)"
                >
                  <div
                    class="bubble-sender-line small fw-semibold lh-sm"
                    :class="bubbleSenderLineClass(msg)"
                  >
                    {{ bubbleSenderLabel(msg) }}
                  </div>
                  <div class="content preserve-lines">{{ msg.content }}</div>
                  <div
                    class="timestamp text-end mt-1 small opacity-75"
                    :class="{ 'text-white': msg.role === 'admin' }"
                  >
                    {{ formatTime(msg.created_at) }}
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="messenger-composer px-3 py-3 border-top">
            <div
              class="messenger-composer-inner d-flex align-items-center gap-2 rounded-pill"
            >
              <input
                type="text"
                class="form-control border-0 bg-transparent px-3 py-2 shadow-none messenger-input"
                :placeholder="composerPlaceholder"
                v-model="newMessage"
                @keyup.enter="sendMessage"
                :disabled="isSending || !staffCanReply()"
              />
              <button
                class="btn btn-primary rounded-circle messenger-send-btn d-flex align-items-center justify-content-center flex-shrink-0"
                type="button"
                @click="sendMessage"
                :disabled="
                  isSending || !newMessage.trim() || !staffCanReply()
                "
              >
                <span
                  v-if="isSending"
                  class="spinner-border spinner-border-sm"
                  role="status"
                ></span>
                <i v-else class="bi bi-send-fill"></i>
                <span class="d-none">Gửi</span>
              </button>
            </div>
          </div>
        </template>

        <div
          v-else
          class="flex-grow-1 d-flex flex-column justify-content-center align-items-center text-muted bg-light px-3 py-5"
        >
          <h4 class="fw-semibold text-dark mb-2">Chưa chọn hội thoại</h4>
          <p class="text-center px-3 mb-0">
            Chọn hoặc tạo một cuộc hội thoại với nhà xe — tin mới sẽ lên đầu danh
            sách.
          </p>
        </div>
      </section>
    </div>

    <!-- Modal Tạo Cuộc Hội Thoại Mới -->
    <div v-if="showNewSessionModal" class="modal-backdrop fade show"></div>
    <div
      v-if="showNewSessionModal"
      class="modal fade show d-block"
      tabindex="-1"
      aria-modal="true"
      role="dialog"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light border-bottom-0">
            <h5 class="modal-title fw-bold text-primary">
              <i class="bi bi-plus-circle me-2"></i>Tạo hội thoại mới với nhà xe
            </h5>
            <button
              type="button"
              class="btn-close shadow-none"
              aria-label="Đóng"
              @click="showNewSessionModal = false"
            ></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-medium text-dark"
                >Chọn nhà xe <span class="text-danger">*</span></label
              >
              <select v-model="newSessionData.id_nha_xe" class="form-select shadow-none">
                <option value="">-- Chọn nhà xe --</option>
                <option v-for="nx in nhaXes" :key="nx.id" :value="nx.id">
                  {{ nx.ten_nha_xe }} ({{ nx.email }})
                </option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium text-dark">Chủ đề (tuỳ chọn)</label>
              <input
                v-model="newSessionData.tieu_de"
                type="text"
                class="form-control shadow-none"
                placeholder="Ví dụ: Vấn đề rút tiền…"
              />
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium text-dark">Tin mở đầu (tuỳ chọn)</label>
              <textarea
                v-model="newSessionData.noi_dung"
                rows="3"
                class="form-control shadow-none"
                placeholder="Xin chào…"
              ></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light border-top-0">
            <button
              type="button"
              class="btn btn-outline-secondary px-4 rounded-pill"
              @click="showNewSessionModal = false"
            >
              Hủy
            </button>
            <button
              type="button"
              class="btn btn-primary px-4 rounded-pill"
              :disabled="!newSessionData.id_nha_xe || isCreatingSession"
              @click="createSession"
            >
              <span
                v-if="isCreatingSession"
                class="spinner-border spinner-border-sm me-2"
                role="status"
              ></span>
              Tạo hội thoại
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-support-container {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 1rem;
  box-sizing: border-box;
  overflow: hidden;
}

.mx-chat-stats {
  margin-left: 0;
  margin-right: 0;
  flex-shrink: 0;
}

@media (max-width: 767.98px) {
  .chat-support-container {
    flex: 0 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
  }
}

.messenger-sidebar {
  background: #fff;
  border-color: #e4e6eb !important;
}

.messenger-sidebar-head {
  flex-shrink: 0;
  background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
}

.messenger-sidebar-search {
  flex-shrink: 0;
}

.messenger-sidebar-head h5 {
  font-size: 1.05rem;
  letter-spacing: -0.02em;
  color: #050505 !important;
}

.messenger-sidebar-head .badge {
  background: #e4e6eb !important;
  color: #65676b;
  font-weight: 600;
}

.messenger-sidebar-search .messenger-search-inner {
  border-color: #e4e6eb !important;
}

.messenger-thread-pane {
  min-width: 0;
  min-height: 0;
}

.chat-messages-container {
  min-height: 0;
  flex: 1 1 0%;
}

.session-list {
  min-height: 0;
}

.messenger-thread-header {
  background: #fff;
  border-color: #e4e6eb !important;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04);
}

.messenger-thread-row {
  transition:
    background 0.15s ease,
    transform 0.12s ease;
}

.messenger-thread-row:hover {
  background: #f2f3f5 !important;
}

.msg-row.active,
.messenger-thread-row.active {
  background: #e7f3ff !important;
  box-shadow: inset 3px 0 0 #0084ff;
}

.msg-row .title-text {
  color: #050505;
  font-weight: 600;
}

.msg-row.active .title-text {
  color: #0084ff;
}

.preview-text {
  max-width: 100%;
  color: #65676b !important;
}

.time-label {
  font-size: 0.72rem;
  color: #65676b !important;
}

.unread-pill {
  font-size: 0.65rem;
  min-width: 1.35rem;
}

.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #31a24c;
}

.cursor-pointer {
  cursor: pointer;
}

.bg-chat {
  background-color: #f0f2f5;
  background-image: none;
}

.messenger-composer {
  flex-shrink: 0;
  background: #fff;
  border-color: #e4e6eb !important;
}

.messenger-composer-inner {
  background: #f0f2f5;
  padding: 0.35rem 0.5rem 0.35rem 0.85rem;
  border: 1px solid transparent;
}

.messenger-composer-inner:focus-within {
  background: #fff;
  border-color: #0084ff;
  box-shadow: 0 0 0 1px rgba(0, 132, 255, 0.25);
}

.messenger-input {
  font-size: 0.95rem;
}

.messenger-send-btn {
  width: 2.25rem;
  height: 2.25rem;
  padding: 0 !important;
  background: #0084ff !important;
  border: none !important;
}

.messenger-send-btn:hover {
  filter: brightness(1.05);
}

.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: white;
  font-size: 0.95rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
}

.min-w-0 {
  min-width: 0;
}

.live-indicator {
  width: 8px;
  height: 8px;
  background-color: #31a24c;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 2px rgba(49, 162, 76, 0.25);
  animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(49, 162, 76, 0.45);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(49, 162, 76, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(49, 162, 76, 0);
  }
}

.message-bubble {
  max-width: 72%;
  padding: 0.55rem 0.85rem 0.45rem;
  border-radius: 1.15rem;
  position: relative;
  line-height: 1.45;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.admin-bubble,
.assistant-bubble {
  border-bottom-right-radius: 0.35rem;
}

.admin-bubble {
  background: #0084ff !important;
  border: none !important;
  color: #fff !important;
}

.user-bubble {
  border-bottom-left-radius: 0.35rem;
  background: #fff !important;
  border: 1px solid #e4e6eb !important;
  color: #050505 !important;
}

.assistant-bubble {
  background: #fff !important;
  border: 1px solid #e4e6eb !important;
}

.preserve-lines {
  white-space: pre-wrap;
  word-wrap: break-word;
}

.bg-purple {
  background-color: #8b5cf6;
}

.bg-gradient-1 {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.bg-gradient-2 {
  background: linear-gradient(135deg, #0084ff 0%, #006edc 100%);
}
.bg-gradient-3 {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.bg-gradient-4 {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.chat-messages-container::-webkit-scrollbar,
.session-list::-webkit-scrollbar {
  width: 8px;
}
.chat-messages-container::-webkit-scrollbar-track,
.session-list::-webkit-scrollbar-track {
  background: transparent;
}
.chat-messages-container::-webkit-scrollbar-thumb,
.session-list::-webkit-scrollbar-thumb {
  background-color: rgba(0, 0, 0, 0.12);
  border-radius: 10px;
}
.chat-messages-container::-webkit-scrollbar-thumb:hover,
.session-list::-webkit-scrollbar-thumb:hover {
  background-color: rgba(0, 0, 0, 0.2);
}
</style>
