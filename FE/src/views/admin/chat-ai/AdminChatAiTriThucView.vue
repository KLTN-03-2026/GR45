<script setup>
import { ref, computed, onMounted } from "vue";
import {
  Sparkles,
  BarChart3,
  History,
  FolderOpen,
  RefreshCw,
  Trash2,
  Upload,
} from "lucide-vue-next";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from "chart.js";
import { Line } from "vue-chartjs";
import adminApi from "@/api/adminApi.js";
import { coerceAssistantStructured } from "@/api/chatAiApi.js";
import pdfjsWorkerSrc from "pdfjs-dist/build/pdf.worker.mjs?url";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
);

/** Đồng bộ với NO_KB_ANSWER backend (FaqService / AiChatController). */
const NO_KB_OUTCOME_LABEL =
  "Xin lỗi, mình chưa tìm được thông tin để trả lời câu này. Bạn thử hỏi lại giúp mình, hoặc gọi hotline nhà xe để được hỗ trợ trực tiếp nhé.";

const message = ref("");
const error = ref("");
const ingestUploading = ref(false);
const ingestUploadName = ref("");

const chatDateFrom = ref("");
const chatDateTo = ref("");
const ingestDateFrom = ref("");
const ingestDateTo = ref("");
const chatSearch = ref("");
const ingestSearch = ref("");

const chartKnowledgeFrom = ref("");
const chartKnowledgeTo = ref("");

const statsUnified = ref(null);
const statsError = ref("");
const loadingKnowledgeChart = ref(false);

const chatLogs = ref([]);
const ingestLogs = ref([]);
const loadingChatLogs = ref(false);
const loadingIngestLogs = ref(false);
const chatLogsLoadError = ref("");
const ingestLogsLoadError = ref("");

const chatPage = ref(1);
const chatPerPage = ref(15);
const chatMeta = ref({
  total: 0,
  last_page: 1,
  current_page: 1,
  per_page: 15,
});

const ingestPage = ref(1);
const ingestPerPage = ref(15);
const ingestMeta = ref({
  total: 0,
  last_page: 1,
  current_page: 1,
  per_page: 15,
});

function formatDt(v) {
  if (!v) return "—";
  try {
    return new Date(v).toLocaleString("vi-VN");
  } catch {
    return String(v);
  }
}

/** yyyy-mm-dd theo giờ địa phương (tránh lệch ngày do `toISOString()` UTC). */
function formatDateInputLocal(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

/** Mặc định tab chat: 7 ngày gần nhất. */
function defaultDateRangeStrings() {
  const to = new Date();
  const from = new Date(to);
  from.setDate(from.getDate() - 6);
  return { from: formatDateInputLocal(from), to: formatDateInputLocal(to) };
}

/** Mặc định tab PDF đã nhúng: 1 năm (vector vẫn trên DB; bảng chỉ lọc theo ngày). */
function defaultIngestDateRangeStrings() {
  const to = new Date();
  const from = new Date(to);
  from.setFullYear(from.getFullYear() - 1);
  return { from: formatDateInputLocal(from), to: formatDateInputLocal(to) };
}

function unwrapStats(res) {
  return res?.data != null ? res.data : res;
}

function adminBearerHeaders() {
  const token = String(localStorage.getItem("auth.admin.token") || "").trim();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function handlePdfUploadChange(event) {
  const input = event?.target;
  const file = input?.files?.[0];
  if (!file) return;

  message.value = "";
  error.value = "";
  ingestUploadName.value = file.name;

  if (!/\.pdf$/i.test(file.name) && file.type !== "application/pdf") {
    error.value = "Chỉ nhận file PDF.";
    input.value = "";
    return;
  }

  ingestUploading.value = true;
  try {
    const [
      { createAuthenticatedHttpVectorProvider },
      { resolveBrowserAgentRuntimeConfig },
      { createLangChainRagEmbedderDeps },
      { ingestPdfToVectorCollection },
    ] = await Promise.all([
      import("@fe-agent/browser-runtime/providers/authenticated-vector-provider"),
      import("@fe-agent/browser-runtime/config"),
      import("@fe-agent/core/langchain-providers"),
      import("@fe-agent/rag/pipeline"),
    ]);

    const config = resolveBrowserAgentRuntimeConfig({
      env: import.meta.env,
      locationOrigin: window.location.origin,
    });
    const fetchImpl = window.fetch.bind(window);
    const vector = createAuthenticatedHttpVectorProvider({
      baseUrl: config.adminVectorBaseUrl,
      fetchImpl,
      getHeaders: adminBearerHeaders,
    });
    const embedder = createLangChainRagEmbedderDeps({
      ollamaEmbedModel: config.ollamaEmbedModel,
      ollamaBaseUrl: config.ollamaBaseUrl,
      fetchImpl,
    });

    const result = await ingestPdfToVectorCollection({
      file,
      pdfjsWorkerSrc,
      collection: config.collection,
      docId: crypto.randomUUID(),
      documentFilename: file.name,
      embedder,
      vector,
      sessionId: `admin-pdf-${Date.now()}`,
      correlationId: crypto.randomUUID(),
      embeddingModel: config.ollamaEmbedModel,
    });

    message.value = `Đã nhúng xong ${file.name} (${result.chunks} chunk).`;
    await Promise.allSettled([loadKnowledgeChart(), loadIngestLogs()]);
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không nhúng được PDF.";
  } finally {
    ingestUploading.value = false;
    input.value = "";
  }
}

async function loadKnowledgeChart() {
  statsError.value = "";
  loadingKnowledgeChart.value = true;
  try {
    const res = await adminApi.getAiStats({
      date_from: chartKnowledgeFrom.value || undefined,
      date_to: chartKnowledgeTo.value || undefined,
    });
    statsUnified.value = unwrapStats(res);
  } catch (e) {
    statsUnified.value = null;
    statsError.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không tải được thống kê tri thức.";
  } finally {
    loadingKnowledgeChart.value = false;
  }
}

/**
 * axiosClient trả thẳng JSON body (interceptor `response => response.data`),
 * nên res = { success, data: [...], meta } — không bọc thêm lớp `.data`.
 */
function applyChatLogsResponse(res) {
  if (!res || typeof res !== "object") {
    chatLogs.value = [];
    return;
  }
  const rows = Array.isArray(res.data)
    ? res.data
    : Array.isArray(res?.data?.data)
      ? res.data.data
      : [];
  chatLogs.value = rows;
  const m = res.meta ?? res?.data?.meta;
  if (m && typeof m === "object") {
    const total = Number(m.total) || 0;
    const last = Math.max(1, Number(m.last_page) || 1);
    chatMeta.value = {
      total,
      last_page: last,
      current_page: Math.min(Math.max(1, Number(m.current_page) || 1), last),
      per_page: Number(m.per_page) || chatPerPage.value,
    };
    chatPage.value = chatMeta.value.current_page;
    chatPerPage.value = chatMeta.value.per_page;
  }
}

function applyIngestLogsResponse(res) {
  if (!res || typeof res !== "object") {
    ingestLogs.value = [];
    return;
  }
  const rows = Array.isArray(res.data)
    ? res.data
    : Array.isArray(res?.data?.data)
      ? res.data.data
      : [];
  ingestLogs.value = rows;
  const m = res.meta ?? res?.data?.meta;
  if (m && typeof m === "object") {
    const total = Number(m.total) || 0;
    const last = Math.max(1, Number(m.last_page) || 1);
    ingestMeta.value = {
      total,
      last_page: last,
      current_page: Math.min(Math.max(1, Number(m.current_page) || 1), last),
      per_page: Number(m.per_page) || ingestPerPage.value,
    };
    ingestPage.value = ingestMeta.value.current_page;
    ingestPerPage.value = ingestMeta.value.per_page;
  }
}

async function loadChatLogs() {
  chatLogsLoadError.value = "";
  loadingChatLogs.value = true;
  try {
    const res = await adminApi.getAiChatLogs({
      page: chatPage.value,
      per_page: chatPerPage.value,
      date_from: chatDateFrom.value || undefined,
      date_to: chatDateTo.value || undefined,
      q: chatSearch.value.trim() || undefined,
    });
    applyChatLogsResponse(res);
  } catch (e) {
    chatLogs.value = [];
    chatLogsLoadError.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không tải được lịch sử chat.";
  } finally {
    loadingChatLogs.value = false;
  }
}

async function loadIngestLogs() {
  ingestLogsLoadError.value = "";
  loadingIngestLogs.value = true;
  try {
    const res = await adminApi.getAiIngestLogs({
      page: ingestPage.value,
      per_page: ingestPerPage.value,
      date_from: ingestDateFrom.value || undefined,
      date_to: ingestDateTo.value || undefined,
      q: ingestSearch.value.trim() || undefined,
    });
    applyIngestLogsResponse(res);
  } catch (e) {
    ingestLogs.value = [];
    ingestLogsLoadError.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không tải được lịch sử ingest.";
  } finally {
    loadingIngestLogs.value = false;
  }
}

async function applyChatFilters() {
  chatPage.value = 1;
  await loadChatLogs();
}

async function applyIngestFilters() {
  ingestPage.value = 1;
  await loadIngestLogs();
}

async function refreshDashboard() {
  const r = defaultDateRangeStrings();
  const ri = defaultIngestDateRangeStrings();
  chartKnowledgeFrom.value = r.from;
  chartKnowledgeTo.value = r.to;
  chatDateFrom.value = r.from;
  chatDateTo.value = r.to;
  ingestDateFrom.value = ri.from;
  ingestDateTo.value = ri.to;
  chatSearch.value = "";
  ingestSearch.value = "";
  chatPage.value = 1;
  ingestPage.value = 1;
  await Promise.all([
    loadKnowledgeChart(),
    loadChatLogs(),
    loadIngestLogs(),
  ]);
}

const deletingIngestId = ref(null);

async function deleteIngestRow(id) {
  if (!id) {
    return;
  }
  error.value = "";
  deletingIngestId.value = id;
  try {
    const res = await adminApi.deleteAiIngestLog(id);
    const body = res?.data != null ? res.data : res;
    if (body?.success) {
      message.value = `Đã xóa (${body.deleted_chunks ?? 0} chunk).`;
      await Promise.all([
        loadKnowledgeChart(),
        loadIngestLogs(),
      ]);
    } else {
      error.value = body?.message || "Xóa thất bại.";
    }
  } catch (err) {
    error.value =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      err?.message ||
      "Lỗi khi xóa.";
  } finally {
    deletingIngestId.value = null;
  }
}

/**
 * Chuỗi trả lời hiển thị trong bảng (không dùng `String(object)` → [object Object]).
 */
function adminAssistantReplyText(row) {
  const fromDisp = coerceAssistantStructured(row?.assistant_display);
  if (fromDisp?.answer?.trim()) return fromDisp.answer.trim();
  const fromAm = coerceAssistantStructured(row?.assistant_message);
  if (fromAm?.answer?.trim()) return fromAm.answer.trim();
  const raw =
    typeof row?.assistant_message === "string" ? row.assistant_message.trim() : "";
  if (raw === "[object Object]" || raw === "") return "—";
  return raw || "—";
}

function hasSupportResult(row) {
  if (!row || typeof row !== "object") return false;
  if (row.outcome === "success") return true;
  if (row.outcome === "failed" || row.outcome === "clarification") return false;
  if (row.outcome === "unknown") {
    /** Legacy log thiếu cờ runtime → fallback nguồn tool. */
    const m = row.ai_meta;
    if (m && typeof m === "object") {
      const ai = m.ai && typeof m.ai === "object" ? m.ai : m;
      const sqlPreview =
        typeof ai.sql_result_preview === "string"
          ? ai.sql_result_preview.trim()
          : "";
      if (sqlPreview.length > 0) return true;
      if (Array.isArray(ai.result) && ai.result.length > 0) return true;
      if (Array.isArray(ai.results) && ai.results.length > 0) return true;
      const resultText =
        typeof ai.result_text === "string" ? ai.result_text.trim() : "";
      if (resultText.length > 0) return true;
      if (Array.isArray(ai.hits) && ai.hits.length > 0) return true;
    }
  }
  return false;
}

function outcomeLabel(row) {
  if (hasSupportResult(row)) return "Đã hỗ trợ";
  return "Chưa hỗ trợ";
}


function suggestionTextsFromAssistant(row) {
  const raw = row?.assistant_message ?? "";
  
  let source = raw;
  if ((!raw || String(raw).trim() === "") && row?.assistant_display) {
    source = row.assistant_display;
  }
  const coerced = coerceAssistantStructured(source);
  if (
    coerced &&
    typeof coerced === "object" &&
    Array.isArray(coerced.suggestions)
  ) {
    return coerced.suggestions
      .map((x) =>
        typeof x === "string"
          ? String(x).trim()
          : String(x?.text ?? "").trim(),
      )
      .filter((x) => x.length > 0);
  }
  if (!raw || typeof raw !== "string") return [];
  const t = raw.trim();
  if (!t.startsWith("{")) return [];
  try {
    const o = JSON.parse(t);
    if (!o || typeof o !== "object") return [];
    const s = o.suggestions;
    if (!Array.isArray(s)) return [];
    return s
      .map((x) => String(x?.text ?? "").trim())
      .filter((x) => x.length > 0);
  } catch {
    return [];
  }
}


function buildPaginationItems(current, last) {
  const L = Math.max(1, Math.floor(Number(last)) || 1);
  const c = Math.min(Math.max(1, Math.floor(Number(current)) || 1), L);
  if (L <= 11) {
    return Array.from({ length: L }, (_, i) => ({ kind: "p", n: i + 1 }));
  }
  const want = new Set(
    [1, 2, L - 1, L, c - 1, c, c + 1].filter((x) => x >= 1 && x <= L),
  );
  const sorted = [...want].sort((a, b) => a - b);
  
  const out = [];
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
      out.push({ kind: "g" });
    }
    out.push({ kind: "p", n: sorted[i] });
  }
  return out;
}

const chatPaginationItems = computed(() =>
  buildPaginationItems(chatPage.value, chatMeta.value.last_page),
);

const ingestPaginationItems = computed(() =>
  buildPaginationItems(ingestPage.value, ingestMeta.value.last_page),
);

async function goChatPage(next) {
  const t = chatPage.value + next;
  if (t < 1 || t > chatMeta.value.last_page) return;
  chatPage.value = t;
  await loadChatLogs();
}

async function selectChatPage(p) {
  const L = chatMeta.value.last_page;
  if (p < 1 || p > L || p === chatPage.value || loadingChatLogs.value) return;
  chatPage.value = p;
  await loadChatLogs();
}

async function goIngestPage(next) {
  const t = ingestPage.value + next;
  if (t < 1 || t > ingestMeta.value.last_page) return;
  ingestPage.value = t;
  await loadIngestLogs();
}

async function selectIngestPage(p) {
  const L = ingestMeta.value.last_page;
  if (p < 1 || p > L || p === ingestPage.value || loadingIngestLogs.value)
    return;
  ingestPage.value = p;
  await loadIngestLogs();
}

onMounted(() => {
  const r = defaultDateRangeStrings();
  const ri = defaultIngestDateRangeStrings();
  chartKnowledgeFrom.value = r.from;
  chartKnowledgeTo.value = r.to;
  chatDateFrom.value = r.from;
  chatDateTo.value = r.to;
  ingestDateFrom.value = ri.from;
  ingestDateTo.value = ri.to;
  void Promise.all([
    loadKnowledgeChart(),
    loadChatLogs(),
    loadIngestLogs(),
  ]);
});

function dailyFromStats(st) {
  const raw = st?.chat?.daily;
  return Array.isArray(raw) ? raw : [];
}

function labelForRows(rows) {
  return (items) => {
    const i = items[0]?.dataIndex;
    return rows[i]?.date != null ? String(rows[i].date) : "";
  };
}

function rowLabels(rows) {
  return rows.map((r) => {
    const parts = String(r.date || "").split("-");
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return String(r.date || "");
  });
}

const lastUpload = computed(() =>
  formatDt(statsUnified.value?.ingest?.last_upload_at),
);

const knowledgeRows = computed(() => dailyFromStats(statsUnified.value));

const knowledgeLineData = computed(() => ({
  labels: rowLabels(knowledgeRows.value),
  datasets: [
    {
      label: "Tổng tin nhắn theo ngày",
      data: knowledgeRows.value.map((r) => Number(r.total) || 0),
      borderColor: "rgba(37, 99, 235, 1)",
      backgroundColor: "rgba(37, 99, 235, 0.08)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
    {
      label: "Đã hỗ trợ",
      data: knowledgeRows.value.map((r) => Number(r.success) || 0),
      borderColor: "rgba(22, 163, 74, 1)",
      backgroundColor: "rgba(22, 163, 74, 0.06)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
    {
      label: "Chưa hỗ trợ",
      data: knowledgeRows.value.map(
        (r) => (Number(r.failed) || 0) + (Number(r.unknown) || 0),
      ),
      borderColor: "rgba(239, 68, 68, 1)",
      backgroundColor: "rgba(239, 68, 68, 0.06)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
  ],
}));

const knowledgeLineOptions = computed(() => {
  const rows = knowledgeRows.value;
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: true, position: "bottom", labels: { boxWidth: 12 } },
      title: { display: false },
      tooltip: {
        callbacks: { title: labelForRows(rows) },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { font: { size: 11 }, color: "#64748b" },
      },
      y: {
        beginAtZero: true,
        ticks: { font: { size: 11 }, color: "#64748b", precision: 0 },
        grid: { color: "rgba(148, 163, 184, 0.25)" },
      },
    },
  };
});
</script>

<template>
  <section class="chat-ai-page">
    <header class="page-head">
      <div class="head-icon-wrap">
        <Sparkles class="head-icon" />
      </div>
      <div>
        <h1>Tri thức Chat AI</h1>
        <p class="head-sub">
          Xem thống kê và lịch sử hội thoại / file đã nhúng vào corpus Chat AI.
        </p>
      </div>
    </header>

    <div class="stats-toolbar">
      <h2 class="stats-title">
        <BarChart3 class="inline-icon" /> Thống kê tin chat
      </h2>
      <button
        type="button"
        class="btn ghost btn-sm"
        :disabled="
          loadingKnowledgeChart ||
          loadingChatLogs ||
          loadingIngestLogs
        "
        @click="refreshDashboard"
      >
        <RefreshCw
          class="inline-icon"
          :class="{
            spin:
              loadingKnowledgeChart ||
              loadingChatLogs ||
              loadingIngestLogs,
          }"
        />
        Làm mới
      </button>
    </div>
    <p v-if="statsError" class="err">{{ statsError }}</p>

    <div class="charts-grid charts-grid--single">
      <article class="card card--chart">
        <div class="chart-head">
          <h2>
            <BarChart3 class="inline-icon" /> Thống kê tri thức chatbot (theo
            ngày)
          </h2>
          <div class="chart-controls">
            <label class="chart-date"
              ><span>Từ</span
              ><input v-model="chartKnowledgeFrom" type="date"
            /></label>
            <label class="chart-date"
              ><span>Đến</span><input v-model="chartKnowledgeTo" type="date"
            /></label>
            <button
              type="button"
              class="btn primary btn-sm"
              :disabled="loadingKnowledgeChart"
              @click="loadKnowledgeChart"
            >
              Xem
            </button>
          </div>
        </div>
        <div class="chart-wrap chart-wrap--tall">
          <Line
            v-if="knowledgeRows.length"
            :data="knowledgeLineData"
            :options="knowledgeLineOptions"
          />
          <p v-else class="chart-empty">
            Chưa có dữ liệu trong khoảng đã chọn.
          </p>
        </div>
      </article>
    </div>

    <article class="card">
      <h2><History class="inline-icon" /> Lịch sử tin nhắn (chatbot)</h2>
      <div class="table-toolbar">
        <label class="chart-date">
          <span>Từ ngày</span>
          <input v-model="chatDateFrom" type="date" />
        </label>
        <label class="chart-date">
          <span>Đến ngày</span>
          <input v-model="chatDateTo" type="date" />
        </label>
        <input
          v-model="chatSearch"
          type="search"
          class="search-input"
          placeholder="Tìm trong tin khách / trợ lý / session…"
          @keyup.enter="applyChatFilters"
        />
        <button
          type="button"
          class="btn ghost btn-sm"
          :disabled="loadingChatLogs"
          @click="applyChatFilters"
        >
          Tìm
        </button>
      </div>
      <p v-if="chatLogsLoadError" class="err err-tight">
        {{ chatLogsLoadError }}
      </p>
      <div class="table-wrap table-wrap--chat-logs">
        <table class="data-table data-table--chat-logs">
          <colgroup>
            <col class="chat-col chat-col--time" />
            <col class="chat-col chat-col--session" />
            <col class="chat-col chat-col--support" />
            <col class="chat-col chat-col--sug" />
            <col class="chat-col chat-col--customer" />
            <col class="chat-col chat-col--user" />
            <col class="chat-col chat-col--asst" />
          </colgroup>
          <thead>
            <tr>
              <th class="col-time">Thời gian</th>
              <th class="col-session">Session</th>
              <th class="col-support">Hỗ trợ</th>
              <th class="col-sug">Gợi ý</th>
              <th class="col-customer">Tên KH</th>
              <th class="col-user">Khách</th>
              <th class="col-asst">Trợ lý</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingChatLogs && !chatLogs.length">
              <td
                colspan="7"
                class="empty empty--loading"
                role="status"
                aria-busy="true"
              >
                <span class="table-loading-bar" aria-hidden="true"></span>
              </td>
            </tr>
            <tr v-else-if="!chatLogs.length">
              <td colspan="7" class="empty">Chưa có dữ liệu.</td>
            </tr>
            <template v-else>
              <tr v-for="row in chatLogs" :key="row.id">
                <td class="col-time nowrap">{{ formatDt(row.created_at) }}</td>
                <td class="mono sm col-session">
                  <div class="cell-scroll">{{ row.session_id || "—" }}</div>
                </td>
                <td class="col-support">
                  <span
                    class="outcome-pill"
                    :class="{
                      'outcome-pill--ok': hasSupportResult(row),
                      'outcome-pill--muted': !hasSupportResult(row),
                    }"
                  >
                    {{ outcomeLabel(row) }}
                  </span>
                </td>
                <td class="col-sug">
                  <template
                    v-for="lines in [suggestionTextsFromAssistant(row)]"
                    :key="'sug-' + row.id"
                  >
                    <ul v-if="lines.length" class="sug-ul">
                      <li v-for="(line, si) in lines" :key="si">{{ line }}</li>
                    </ul>
                    <span v-else class="sug-empty">—</span>
                  </template>
                </td>
                <td class="col-customer">
                  <div class="cell-customer-name">
                    {{ row.customer_name || "Ẩn danh" }}
                  </div>
                </td>
                <td class="col-user">
                  <div class="cell-scroll cell-scroll--wide">
                    {{ row.user_message }}
                  </div>
                </td>
                <td class="col-asst">
                  <div class="cell-scroll cell-scroll--wide">
                    {{ adminAssistantReplyText(row) }}
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div v-if="chatMeta.total > 0" class="pagination-bar">
        <div class="pagination-row">
          <div class="pagination-actions">
            <button
              type="button"
              class="btn ghost btn-sm"
              :disabled="chatPage <= 1 || loadingChatLogs"
              @click="goChatPage(-1)"
            >
              ← Trước
            </button>
            <div
              class="pagination-pages"
              role="navigation"
              aria-label="Phân trang lịch sử chat"
            >
              <template
                v-for="(it, idx) in chatPaginationItems"
                :key="'chat-pg-' + idx"
              >
                <span
                  v-if="it.kind === 'g'"
                  class="pagination-gap"
                  aria-hidden="true"
                  >…</span
                >
                <button
                  v-else
                  type="button"
                  class="btn ghost btn-sm pagination-page"
                  :class="{ 'pagination-page--active': it.n === chatPage }"
                  :disabled="loadingChatLogs"
                  :aria-current="it.n === chatPage ? 'page' : undefined"
                  @click="selectChatPage(it.n)"
                >
                  {{ it.n }}
                </button>
              </template>
            </div>
            <button
              type="button"
              class="btn ghost btn-sm"
              :disabled="chatPage >= chatMeta.last_page || loadingChatLogs"
              @click="goChatPage(1)"
            >
              Sau →
            </button>
          </div>
          <span class="pagination-meta">
            Trang {{ chatMeta.current_page }} / {{ chatMeta.last_page }}
            <span class="pagination-sub"
              >({{ chatMeta.total }} tin, {{ chatMeta.per_page }}/trang)</span
            >
          </span>
        </div>
      </div>
    </article>

    <article class="card">
      <h2><Upload class="inline-icon" /> Nhúng PDF vào corpus</h2>
      <p class="muted">
        Chọn file PDF để đưa vào kho tri thức Chat AI.
      </p>
      <label class="pdf-upload-box">
        <input
          type="file"
          accept="application/pdf,.pdf"
          :disabled="ingestUploading"
          @change="handlePdfUploadChange"
        />
        <span class="pdf-upload-main">
          {{ ingestUploading ? "Đang nhúng PDF..." : "Chọn file PDF" }}
        </span>
        <span v-if="ingestUploadName" class="pdf-upload-name">
          {{ ingestUploadName }}
        </span>
      </label>
    </article>

    <article class="card">
      <h2><FolderOpen class="inline-icon" /> Lịch sử file PDF đã nhúng</h2>
      <p class="last-upload-line ingest-last-upload">
        <span class="last-upload-label">Upload gần nhất (thống kê)</span>
        <span class="last-upload-value">{{ lastUpload }}</span>
      </p>
      <p v-if="message" class="ok ok-tight">{{ message }}</p>
      <p v-if="error" class="err err-tight">{{ error }}</p>
      <div class="table-toolbar">
        <label class="chart-date">
          <span>Từ ngày</span>
          <input v-model="ingestDateFrom" type="date" />
        </label>
        <label class="chart-date">
          <span>Đến ngày</span>
          <input v-model="ingestDateTo" type="date" />
        </label>
        <input
          v-model="ingestSearch"
          type="search"
          class="search-input"
          placeholder="Tìm theo tên file…"
          @keyup.enter="applyIngestFilters"
        />
        <button
          type="button"
          class="btn ghost btn-sm"
          :disabled="loadingIngestLogs"
          @click="applyIngestFilters"
        >
          Tìm
        </button>
      </div>
      <p v-if="ingestLogsLoadError" class="err err-tight">
        {{ ingestLogsLoadError }}
      </p>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-time">Thời gian</th>
              <th>Tên file</th>
              <th>Chunk</th>
              <th>Admin ID</th>
              <th class="col-act">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingIngestLogs && !ingestLogs.length">
              <td
                colspan="5"
                class="empty empty--loading"
                role="status"
                aria-busy="true"
              >
                <span class="table-loading-bar" aria-hidden="true"></span>
              </td>
            </tr>
            <tr v-else-if="!ingestLogs.length">
              <td colspan="5" class="empty">Chưa có dữ liệu.</td>
            </tr>
            <template v-else>
              <tr v-for="row in ingestLogs" :key="row.id">
                <td class="col-time nowrap">{{ formatDt(row.created_at) }}</td>
                <td class="mono sm">{{ row.original_filename }}</td>
                <td>{{ row.chunks_count }}</td>
                <td>{{ row.admin_id ?? "—" }}</td>
                <td class="col-act">
                  <button
                    type="button"
                    class="btn danger btn-sm btn-icon"
                    :disabled="deletingIngestId === row.id"
                    title="Xóa bản nhúng này"
                    @click="deleteIngestRow(row.id)"
                  >
                    <Trash2 class="inline-icon" />
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div v-if="ingestMeta.total > 0" class="pagination-bar">
        <div class="pagination-row">
          <div class="pagination-actions">
            <button
              type="button"
              class="btn ghost btn-sm"
              :disabled="ingestPage <= 1 || loadingIngestLogs"
              @click="goIngestPage(-1)"
            >
              ← Trước
            </button>
            <div
              class="pagination-pages"
              role="navigation"
              aria-label="Phân trang lịch sử PDF"
            >
              <template
                v-for="(it, idx) in ingestPaginationItems"
                :key="'ing-pg-' + idx"
              >
                <span
                  v-if="it.kind === 'g'"
                  class="pagination-gap"
                  aria-hidden="true"
                  >…</span
                >
                <button
                  v-else
                  type="button"
                  class="btn ghost btn-sm pagination-page"
                  :class="{ 'pagination-page--active': it.n === ingestPage }"
                  :disabled="loadingIngestLogs"
                  :aria-current="it.n === ingestPage ? 'page' : undefined"
                  @click="selectIngestPage(it.n)"
                >
                  {{ it.n }}
                </button>
              </template>
            </div>
            <button
              type="button"
              class="btn ghost btn-sm"
              :disabled="
                ingestPage >= ingestMeta.last_page || loadingIngestLogs
              "
              @click="goIngestPage(1)"
            >
              Sau →
            </button>
          </div>
          <span class="pagination-meta">
            Trang {{ ingestMeta.current_page }} / {{ ingestMeta.last_page }}
            <span class="pagination-sub"
              >({{ ingestMeta.total }} lần upload,
              {{ ingestMeta.per_page }}/trang)</span
            >
          </span>
        </div>
      </div>
    </article>
  </section>
</template>

<style scoped>
.chat-ai-page {
  width: 100%;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
  color: #0f172a;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 20px;
}

.charts-grid--single {
  grid-template-columns: 1fr;
}

.mono {
  font-size: 0.85rem;
  word-break: break-all;
}

.mono.sm {
  font-size: 0.8rem;
}

.page-head {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.head-icon-wrap {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.head-icon {
  width: 24px;
  height: 24px;
}

h1 {
  margin: 0 0 6px;
  font-size: 1.35rem;
  font-weight: 800;
}

.head-sub {
  margin: 0;
  color: #475569;
  font-size: 0.92rem;
  line-height: 1.5;
}

.card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 22px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
}

.card h2 {
  margin: 0 0 10px;
  font-size: 1.05rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.muted {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.9rem;
}

.pdf-upload-box {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  padding: 12px;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  background: #f8fafc;
  cursor: pointer;
}

.pdf-upload-box input {
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
  position: absolute;
  pointer-events: none;
}

.pdf-upload-main {
  font-weight: 700;
  color: #1d4ed8;
}

.pdf-upload-name {
  min-width: 0;
  color: #334155;
  word-break: break-word;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.9rem;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn-sm {
  padding: 8px 12px;
  font-size: 0.85rem;
}

.btn.primary {
  background: #2563eb;
  color: #fff;
  border-color: #1d4ed8;
}

.btn.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn.danger {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fecaca;
}

.btn.danger:hover:not(:disabled) {
  background: #fee2e2;
}

.btn.danger:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn.ghost {
  background: #fff;
  color: #334155;
  border-color: #cbd5e1;
}

.inline-icon {
  width: 18px;
  height: 18px;
}

.spin {
  animation: chat-ai-spin 0.85s linear infinite;
}

@keyframes chat-ai-spin {
  to {
    transform: rotate(360deg);
  }
}

.ok {
  margin: 14px 0 0;
  color: #15803d;
  font-size: 0.9rem;
}

.ok-tight {
  margin: 0 0 10px;
}

.err {
  margin: 14px 0 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.err-tight {
  margin: 0 0 10px;
}

.pagination-bar {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
}

.pagination-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.pagination-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.pagination-pages {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.pagination-page {
  min-width: 2.25rem;
  justify-content: center;
  font-variant-numeric: tabular-nums;
}

.pagination-page--active {
  background: #2563eb;
  color: #fff;
  border-color: #1d4ed8;
  font-weight: 700;
}

.pagination-page--active:hover:not(:disabled) {
  background: #1d4ed8;
  color: #fff;
}

.pagination-gap {
  padding: 0 4px;
  color: #94a3b8;
  font-size: 0.9rem;
  user-select: none;
}

.pagination-meta {
  font-size: 0.88rem;
  color: #475569;
  font-weight: 600;
}

.pagination-sub {
  font-weight: 500;
  color: #64748b;
  margin-left: 4px;
}

.stats-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.stats-title {
  margin: 0;
  font-size: 1.05rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

.stat-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 16px;
}

.stat-card--wide {
  grid-column: 1 / -1;
}

.card--chart .chart-wrap {
  position: relative;
  height: 240px;
  width: 100%;
  margin-top: 4px;
}

.card--chart .chart-wrap--tall {
  height: 320px;
}

.stat-label {
  font-size: 0.78rem;
  color: #64748b;
  margin-bottom: 6px;
  line-height: 1.35;
}

.stat-value {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
}

.stat-value--sm {
  font-size: 0.95rem;
  font-weight: 600;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
}

/** Bảng chat: colgroup + table-layout fixed → header và body cùng cột (không lệch khi đổi model). */
.table-wrap--chat-logs {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
}

.data-table--chat-logs {
  table-layout: fixed;
  width: max(100%, 1180px);
  min-width: 1180px;
}

.data-table--chat-logs col.chat-col--time {
  width: 10%;
}
.data-table--chat-logs col.chat-col--session {
  width: 12%;
}
.data-table--chat-logs col.chat-col--support {
  width: 10%;
}
.data-table--chat-logs col.chat-col--sug {
  width: 14%;
}
.data-table--chat-logs col.chat-col--customer {
  width: 12%;
}
.data-table--chat-logs col.chat-col--user {
  width: 14%;
}
.data-table--chat-logs col.chat-col--asst {
  width: 28%;
}

.table-wrap--chat-logs .data-table--chat-logs th {
  white-space: normal;
  line-height: 1.25;
  word-break: break-word;
}

.data-table.data-table--chat-logs .col-session {
  min-width: 13rem;
  max-width: 20rem;
  width: auto;
  box-sizing: border-box;
  vertical-align: top;
}

.data-table--chat-logs .col-session .cell-scroll {
  max-width: 100%;
  overflow-x: auto;
  word-break: break-all;
}

.data-table--chat-logs .col-support {
  min-width: 6.5rem;
}

.data-table--chat-logs .col-customer {
  min-width: 0;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  hyphens: auto;
  color: #475569;
}

.data-table--chat-logs .cell-customer-name {
  min-width: 0;
  max-width: 100%;
  line-height: 1.35;
}

.data-table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.data-table .col-time {
  width: 9.5rem;
}

.data-table.data-table--chat-logs .col-time {
  width: auto;
  min-width: 10.75rem;
  max-width: 12.5rem;
  box-sizing: border-box;
  overflow: hidden;
  text-overflow: ellipsis;
}

.data-table th,
.data-table td {
  border-bottom: 1px solid #e2e8f0;
  padding: 10px 12px;
  text-align: left;
  vertical-align: top;
}

.data-table th {
  background: #f8fafc;
  font-weight: 700;
  color: #334155;
  white-space: nowrap;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.nowrap {
  white-space: nowrap;
}

.cell-scroll {
  max-height: 7rem;
  overflow: auto;
  line-height: 1.45;
  word-break: break-word;
}

.cell-scroll--wide {
  max-height: 10rem;
  min-height: 2.5rem;
}

.empty {
  text-align: center;
  color: #64748b;
  padding: 20px !important;
}

.chart-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.chart-head h2 {
  margin: 0;
}

.chart-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px 10px;
}

.chart-date {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
}

.chart-date input {
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.82rem;
}

.chart-empty {
  margin: 0;
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-size: 0.9rem;
}

.data-table .col-act {
  width: 4.5rem;
  text-align: center;
}

.btn-icon {
  padding: 6px 10px;
  min-width: auto;
}

.last-upload-line {
  margin: 16px 0 0;
  padding-top: 14px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  align-items: baseline;
  font-size: 0.88rem;
}

.last-upload-label {
  color: #64748b;
  font-weight: 600;
}

.last-upload-value {
  color: #0f172a;
  font-weight: 600;
}

.ingest-last-upload {
  margin: 6px 0 12px;
  padding-top: 0;
  border-top: none;
}

.table-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 10px;
  align-items: flex-end;
  margin: 0 0 12px;
}

.table-toolbar .search-input {
  flex: 1;
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.88rem;
}

.data-table .col-out {
  width: 8.5rem;
}

.data-table .col-ai {
  min-width: 12rem;
  max-width: 24rem;
  vertical-align: top;
}

.data-table .col-sqlq {
  min-width: 10rem;
  max-width: 18rem;
  vertical-align: top;
}

.data-table .col-sug,
.data-table--chat-logs .col-sug {
  vertical-align: top;
}

.sug-ul {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.8rem;
  line-height: 1.45;
  color: #334155;
}

.sug-ul li {
  margin-bottom: 4px;
  word-break: break-word;
}

.sug-ul li:last-child {
  margin-bottom: 0;
}

.sug-empty {
  color: #94a3b8;
  font-size: 0.8rem;
}

.outcome-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #475569;
}

.outcome-pill--ok {
  background: #dcfce7;
  color: #166534;
}

.outcome-pill--muted {
  background: #e2e8f0;
  color: #64748b;
  max-width: 100%;
  white-space: normal;
  border-radius: 8px;
  line-height: 1.35;
  word-break: break-word;
  font-weight: 600;
  text-align: left;
}

.empty--loading {
  padding: 1.25rem 1rem;
  text-align: center;
  vertical-align: middle;
}

.table-loading-bar {
  display: inline-block;
  width: min(220px, 70%);
  height: 8px;
  border-radius: 4px;
  background: linear-gradient(90deg, #e2e8f0 0%, #f1f5f9 50%, #e2e8f0 100%);
  background-size: 200% 100%;
  animation: admin-chat-ai-table-shimmer 1.1s ease-in-out infinite;
}

@keyframes admin-chat-ai-table-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
</style>
