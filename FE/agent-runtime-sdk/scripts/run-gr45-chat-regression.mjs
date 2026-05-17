import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../../..",
);
const REPORT_MD = path.join(ROOT, "docs/CHAT_AGENT_FULL_TEST_REPORT.md");
const REPORT_JSON = path.join(ROOT, "docs/chat-agent-full-test-report.json");
const SKIP_LOGOUT = process.env.GR45_SKIP_LOGOUT === "1";

const TOKEN_KEY = "auth.client.token";
const USER_KEY = "auth.client.user";

function installBrowserShims() {
  const store = new Map();
  globalThis.localStorage = {
    getItem: (key) => store.get(String(key)) ?? null,
    setItem: (key, value) => store.set(String(key), String(value)),
    removeItem: (key) => store.delete(String(key)),
    clear: () => store.clear(),
  };
  globalThis.CustomEvent = class CustomEvent {
    constructor(type, init = {}) {
      this.type = type;
      this.detail = init.detail;
    }
  };
  globalThis.window = {
    location: { origin: "http://127.0.0.1:5173" },
    dispatchEvent: () => true,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
  };
}

const CASE_GROUPS = [
  {
    name: "Auth / Account / Customer",
    cases: [
      "đăng nhập email customer@gmail.com mật khẩu 123456",
      "đăng nhập email abc mật khẩu 123456",
      "đăng nhập customer@gmail.com",
      "đăng xuất",
      "xem hồ sơ của tôi",
      "cho tôi biết trạng thái tài khoản",
      "cập nhật họ tên thành Nguyễn Văn Test",
      "cập nhật email thành newcustomer@gmail.com",
      "cập nhật số điện thoại 0909123456",
      "đổi mật khẩu cũ 123456 sang mật khẩu mới 654321 xác nhận 654321",
      "quên mật khẩu email customer@gmail.com",
      "đặt lại mật khẩu email customer@gmail.com token 123456 mật khẩu mới 654321 xác nhận 654321",
      "đăng ký tài khoản tên Nguyễn Văn Test số điện thoại 0909123456 email test@gmail.com mật khẩu 123456 xác nhận 123456",
      "kích hoạt tài khoản email test@gmail.com mã 123456",
      "xem lịch sử đặt vé",
      "xem lịch sử đặt vé từ 2026-05-01 đến 2026-05-31",
      "xem điểm thưởng của tôi",
      "xem lịch sử điểm thưởng",
      "xem hạng thành viên của tôi",
    ],
  },
  {
    name: "Route / Trip / Seat",
    cases: [
      "tìm tuyến từ Đà Nẵng đến Huế",
      "tìm tuyến từ Sài Gòn đến Cần Thơ",
      "nhà xe Phương Trang có tuyến Sài Gòn đi Cần Thơ không",
      "nhà xe NX001 có tuyến Đà Nẵng đi Huế không",
      "tìm chuyến từ Đà Nẵng đến Huế ngày mai lúc 08:00",
      "tìm chuyến từ Đà Nẵng đến Huế ngày 2026-05-20 lúc 08:00",
      "tìm chuyến từ Sài Gòn đến Cần Thơ tối nay",
      "có chuyến nào từ Nha Trang đến Sài Gòn trước 18h không",
      "tìm chuyến limousine từ Đà Lạt về Sài Gòn ngày 2026-05-20 lúc 22:00",
      "tìm xe giường nằm từ Hà Nội đi Hải Phòng sáng mai",
      "xem chi tiết chuyến 123",
      "trạng thái chuyến 123",
      "giá vé chuyến 123 bao nhiêu",
      "xem sơ đồ ghế chuyến 123",
      "ghế A1 còn trống không chuyến 123",
      "kiểm tra ghế A1,A2 chuyến 123",
      "xem điểm đón của chuyến 123",
      "xem điểm trả của chuyến 123",
    ],
  },
  {
    name: "Booking / Ticket",
    cases: [
      "đặt vé chuyến 123 ghế A1",
      "đặt vé chuyến 123 ghế A1,A2",
      "đặt vé chuyến 123 ghế A1 tên Nguyễn Văn Test số điện thoại 0909123456",
      "đặt vé chuyến 123 ghế A1 thanh toán chuyển khoản",
      "đặt vé chuyến 123 ghế A1 mã voucher SUMMER2026",
      "xem vé của tôi",
      "xem chi tiết vé VX123456",
      "kiểm tra trạng thái vé VX123456",
      "hủy vé VX123456",
      "hủy đặt vé VX123456",
      "đổi vé VX123456 sang chuyến 456",
      "đổi ghế vé VX123456 sang A3",
      "xác nhận đặt vé VX123456 thanh toán chuyển khoản",
    ],
  },
  {
    name: "Payment / Refund / Voucher",
    cases: [
      "kiểm tra trạng thái thanh toán mã PT123456",
      "vé VX123456 đã thanh toán chưa",
      "kiểm tra thanh toán vé VX123456",
      "tạo thanh toán cho vé VX123456 bằng chuyển khoản",
      "thanh toán lại vé VX123456",
      "ước tính hoàn tiền vé VX123456",
      "tôi muốn hoàn tiền vé VX123456 vì bận việc",
      "kiểm tra trạng thái hoàn tiền mã RF123456",
      "áp dụng voucher SUMMER2026 cho đơn 500000",
      "kiểm tra voucher SUMMER2026 còn dùng được không",
      "voucher FREE50 còn hiệu lực không",
      "cho tôi danh sách voucher hiện có",
      "xem lịch sử dùng voucher",
    ],
  },
  {
    name: "Tracking / Map / Transport / Support",
    cases: [
      "xe chuyến 123 đang ở đâu",
      "theo dõi chuyến 123",
      "trạng thái hiện tại của chuyến 123",
      "tốc độ xe chuyến 123",
      "giờ đến dự kiến của chuyến 123",
      "xem bản đồ chuyến 123",
      "xem thông tin xe của chuyến 123",
      "xem tài xế chuyến 123",
      "xem nhà xe của chuyến 123",
      "liên hệ hỗ trợ",
      "tôi muốn gặp admin",
      "chat với nhà xe Phương Trang",
      "gặp nhân viên hỗ trợ về vé VX123456",
      "gửi tin nhắn hỗ trợ: tôi cần kiểm tra vé VX123456",
      "xem tin nhắn hỗ trợ phiên ABC123",
      "đóng phiên hỗ trợ ABC123",
    ],
  },
  {
    name: "Clarification / Negative / Regression",
    cases: [
      "tìm chuyến xe",
      "tôi muốn đi ngày mai",
      "có chuyến nào sáng không",
      "nhà xe nào chạy tuyến này",
      "tôi muốn đi từ Huế",
      "tôi muốn đến Đà Nẵng",
      "tôi muốn đi hôm qua từ Huế đến Đà Nẵng lúc 08:00",
      "tìm chuyến từ Huế đến Đà Nẵng ngày 2025-01-01 lúc 08:00",
      "trip abc",
      "xem chuyến abc",
      "đặt vé chuyến abc ghế A1",
      "đặt vé chuyến 123 ghế Z99",
      "đặt vé chuyến 123",
      "đặt vé ghế A1",
      "hủy vé",
      "kiểm tra thanh toán",
      "áp dụng voucher",
      "hoàn tiền",
      "đổi chuyến xe từ Huế đi Đà Nẵng",
      "chuyển đổi phương tiện công cộng ở Đà Nẵng",
      "máy bay từ Đà Nẵng đi Hà Nội",
      "tàu hỏa từ Huế đi Đà Nẵng",
      "cho tôi tìm chuyến bay",
    ],
  },
];

const FIELD_FLOW_GROUPS = [
  {
    name: "Field-by-field / Auth login",
    steps: ["đăng nhập", "customer@gmail.com", "123456"],
    allowAutoLogin: false,
  },
  {
    name: "Field-by-field / Auth invalid email",
    steps: ["đăng nhập", "abc", "123456"],
    allowAutoLogin: false,
  },
  {
    name: "Field-by-field / Trip search",
    steps: ["tìm chuyến xe", "Đà Nẵng", "Huế", "ngày mai", "08:00"],
    allowAutoLogin: true,
  },
  {
    name: "Field-by-field / Booking",
    steps: ["đặt vé", "chuyến 123", "ghế A1"],
    allowAutoLogin: true,
  },
  {
    name: "Field-by-field / Payment status",
    steps: ["kiểm tra thanh toán", "VX123456"],
    allowAutoLogin: true,
  },
  {
    name: "Field-by-field / Voucher apply",
    steps: ["áp dụng voucher", "SUMMER2026", "500000"],
    allowAutoLogin: true,
  },
  {
    name: "Field-by-field / Support",
    steps: [
      "liên hệ hỗ trợ",
      "gửi tin nhắn hỗ trợ: tôi cần kiểm tra vé VX123456",
      "kết thúc hỗ trợ",
    ],
    allowAutoLogin: false,
  },
];

function compact(value, max = 700) {
  const text = typeof value === "string" ? value : JSON.stringify(value);
  if (!text) return "";
  return text.length > max ? `${text.slice(0, max)}...` : text;
}

function collectCounts(data) {
  if (Array.isArray(data)) return data.length;
  if (!data || data.constructor !== Object) return null;
  for (const key of ["count", "total"]) {
    const n = Number(data[key]);
    if (Number.isFinite(n)) return n;
  }
  for (const key of [
    "data",
    "items",
    "rows",
    "tickets",
    "trips",
    "routes",
    "messages",
  ]) {
    const value = data[key];
    if (Array.isArray(value)) return value.length;
    if (value && value.constructor === Object) {
      const nested = collectCounts(value);
      if (nested !== null) return nested;
    }
  }
  return null;
}

function hasUsefulData(data) {
  if (!data) return false;
  if (data.not_implemented === true) return false;
  if (data.auth_required === true || data.missing_password === true)
    return false;
  if (data.invalid_email_format === true || data.auth_failed === true)
    return false;
  if (data.clarification_needed === true) return true;
  const count = collectCounts(data);
  if (count !== null) return count > 0;
  if (data.success === true) return true;
  if (Array.isArray(data)) return data.length > 0;
  return data.constructor === Object && Object.keys(data).length > 0;
}

function rowStatus(toolResults) {
  if (!toolResults.length) return "NO_TOOL";
  if (toolResults.every((row) => !answerFromTool(row))) return "NO_BOT_ANSWER";
  if (toolResults.some((row) => String(row.error || "").includes("fetch failed")))
    return "API_FAIL";
  if (toolResults.every((row) => row.data?.clarification_needed === true))
    return "PASS";
  if (toolResults.every((row) => row.data?.auth_required === true))
    return "AUTH_REQUIRED";
  if (toolResults.some((row) => row.data?.not_implemented === true))
    return "STUB";
  if (toolResults.some((row) => row.ok === false)) {
    const handled = toolResults.every((row) => {
      const text = answerFromTool(row);
      return Boolean(text) || row.data?.success === false;
    });
    return handled ? "PASS" : "API_FAIL";
  }
  if (toolResults.some((row) => !hasUsefulData(row.data))) return "EMPTY_DATA";
  return "PASS";
}

function answerFromTool(row) {
  const data = row?.data ?? {};
  const questions = Array.isArray(data.suggested_questions_vi)
    ? data.suggested_questions_vi.filter(Boolean)
    : [];
  if (questions.length > 0) return questions.join(" ");
  const direct = String(
    data.message ??
      data.error ??
      data.data?.message ??
      data.data?.error ??
      row?.error ??
      "",
  ).trim();
  if (direct) return direct;

  const count = collectCounts(data);
  if (row?.ok === true) {
    if (count !== null) return `Tool trả dữ liệu thành công (${count} dòng).`;
    if (data.success === true) return "Tool thực hiện thành công.";
    if (data && data.constructor === Object && Object.keys(data).length > 0) {
      return "Tool trả dữ liệu thành công.";
    }
  }
  return "";
}

function suggestionsFromTool(row) {
  const data = row?.data ?? {};
  const suggestions = data.suggestions ?? data.data?.suggestions ?? [];
  if (Array.isArray(suggestions)) {
    return suggestions.join(", ");
  }
  return String(suggestions || "").trim();
}

function formatSuggestions(chips) {
  return (Array.isArray(chips) ? chips : [])
    .map((chip) => chip?.text)
    .filter(Boolean)
    .join(", ");
}

async function run() {
  installBrowserShims();

  const { createDefaultToolRegistry } =
    await import("../gr45-fe-chat-runtime/src/runtime/providers/tool-registry.js");
  const { gr45FastPlanner } =
    await import("../gr45-fe-chat-runtime/src/domain/planner/fast-planner.js");
  const { enhanceGr45TripSearchArgumentsRuleOnly } =
    await import("../gr45-fe-chat-runtime/src/catalog/slots/trip-search-slot-extractor.js");
  const { deriveGr45ToolSuggestions } =
    await import("../gr45-fe-chat-runtime/src/catalog/tools/index.js");

  const createTools = () =>
    createDefaultToolRegistry({
      defaultToolTimeoutMs: 10_000,
      circuitBreakerThreshold: 999,
      requireSensitiveToolConfirmation: false,
    });
  createTools();
  const allRows = [];
  const startedAt = new Date();
  const testContext = {
    tripId: "123",
    reverseTripId: "456",
    ticketCode: "VX123456",
    cancelTicketCode: "VXCANCEL",
    cancelBookingTicketCode: "VXCANCEL2",
    supportPublicId: "",
    capturedBookedTicket: false,
    bookingIndex: 0,
  };

  async function resolveSeededContext(tools) {
    const today = new Date(Date.now() + 7 * 60 * 60 * 1000)
      .toISOString()
      .slice(0, 10);
    const mainTrip = await tools.execute(
      {
        id: crypto.randomUUID(),
        callId: crypto.randomUUID(),
        toolName: "search_trips",
        arguments: {
          raw_message: "resolve regression main trip",
          diem_di: "Đà Nẵng",
          diem_den: "Huế",
          ngay_khoi_hanh: "2026-05-20",
          gio_khoi_hanh: "08:00",
        },
      },
      { confirmToolCall: async () => true },
    );
    const reverseTrip = await tools.execute(
      {
        id: crypto.randomUUID(),
        callId: crypto.randomUUID(),
        toolName: "search_trips",
        arguments: {
          raw_message: "resolve regression reverse trip",
          diem_di: "Huế",
          diem_den: "Đà Nẵng",
          ngay_khoi_hanh: new Date(Date.now() + 31 * 60 * 60 * 1000)
            .toISOString()
            .slice(0, 10),
          gio_khoi_hanh: "09:00",
        },
      },
      { confirmToolCall: async () => true },
    );
    const mainRows = Array.isArray(mainTrip.data?.data)
      ? mainTrip.data.data
      : [];
    const reverseRows = Array.isArray(reverseTrip.data?.data)
      ? reverseTrip.data.data
      : [];
    const mainId = mainRows.find((row) => String(row?.id ?? "").trim() !== "");
    const reverseId = reverseRows.find((row) => String(row?.id ?? "").trim() !== "");
    if (mainId) testContext.tripId = String(mainId.id);
    if (reverseId) testContext.reverseTripId = String(reverseId.id);

    const hcmToday = await tools.execute(
      {
        id: crypto.randomUUID(),
        callId: crypto.randomUUID(),
        toolName: "search_trips",
        arguments: {
          raw_message: "resolve regression today trip",
          diem_di: "TP Hồ Chí Minh",
          diem_den: "Cần Thơ",
          ngay_khoi_hanh: today,
          gio_khoi_hanh_tu: "18:00",
          gio_khoi_hanh_den: "23:59",
        },
      },
      { confirmToolCall: async () => true },
    );
    const hcmRows = Array.isArray(hcmToday.data?.data)
      ? hcmToday.data.data
      : [];
    const hcmId = hcmRows.find((row) => String(row?.id ?? "").trim() !== "");
    if (hcmId) console.log(`Resolved seeded HCM->Cần Thơ trip: ${hcmId.id}`);
    console.log(
      `Resolved seeded context: trip=${testContext.tripId}, reverseTrip=${testContext.reverseTripId}`,
    );
  }

  await resolveSeededContext(createTools());

  function materializeText(text) {
    let out = String(text);
    out = out.replace(/\bchuyến 123\b/gi, `chuyến ${testContext.tripId}`);
    out = out.replace(/\bchuyen 123\b/gi, `chuyen ${testContext.tripId}`);
    out = out.replace(
      /\bchuyến 456\b/gi,
      `chuyến ${testContext.reverseTripId}`,
    );
    out = out.replace(
      /\bchuyen 456\b/gi,
      `chuyen ${testContext.reverseTripId}`,
    );

    if (
      out.toLowerCase().includes("hủy vé") ||
      out.toLowerCase().includes("hủy đặt vé")
    ) {
      out = out.replace(
        /\bVX123456\b/g,
        out.toLowerCase().includes("hủy đặt vé")
          ? testContext.cancelBookingTicketCode
          : testContext.cancelTicketCode,
      );
    } else {
      out = out.replace(/\bVX123456\b/g, testContext.ticketCode);
    }
    if (testContext.supportPublicId) {
      out = out.replace(/\bABC123\b/g, testContext.supportPublicId);
    }

    if (/^đặt vé chuyến\s+\d+/i.test(out)) {
      const seats = ["A1", "A2,A3", "A4", "A5", "A6"];
      const seat =
        seats[testContext.bookingIndex] ?? `A${testContext.bookingIndex + 1}`;
      testContext.bookingIndex += 1;
      out = out.replace(/ghế\s+A1,A2/i, `ghế ${seat}`);
      out = out.replace(/ghế\s+A1/i, `ghế ${seat}`);
    }

    return out;
  }

  async function runOne({
    groupName,
    text,
    history,
    allowAutoLogin,
    flowStep,
    flowContext,
  }) {
    const tools = createTools();
    const actualText = materializeText(text);
    const state = {
      messages: [...history, { role: "user", content: actualText }],
    };
    const plan = gr45FastPlanner({ userMessage: actualText, state });
    const toolCalls = Array.isArray(plan?.toolCalls) ? plan.toolCalls : [];
    const toolResults = [];

    for (const call of toolCalls) {
      if (
        allowAutoLogin &&
        ![
          "auth_login",
          "auth_logout",
          "registration_register_account",
        ].includes(call.toolName)
      ) {
        await ensureLoggedIn(tools);
      }
      let args = {
        raw_message: actualText,
        ...(call.arguments && call.arguments.constructor === Object
          ? call.arguments
          : {}),
      };
      if (
        (flowContext?.supportPublicId || testContext.supportPublicId) &&
        [
          "support_send_support_message",
          "support_get_support_messages",
          "support_close_support_session",
        ].includes(call.toolName)
      ) {
        args.public_id =
          flowContext?.supportPublicId || testContext.supportPublicId;
      }
      args = await enhanceGr45TripSearchArgumentsRuleOnly({
        toolName: call.toolName,
        rawUserMessage: actualText,
        argumentsPayload: args,
      });
      call.arguments = args;
      const result = await tools.execute(
        {
          id: crypto.randomUUID(),
          callId: crypto.randomUUID(),
          toolName: call.toolName,
          arguments: args,
        },
        {
          confirmToolCall: async () => true,
        },
      );
      toolResults.push(result);
      if (call.toolName === "booking_create_booking" && result.ok === true) {
        const maVe = result.data?.data?.ma_ve ?? result.data?.ma_ve;
        if (maVe && !testContext.capturedBookedTicket) {
          testContext.ticketCode = String(maVe);
          testContext.capturedBookedTicket = true;
        }
      }
      if (call.toolName === "support_create_support_session" && result.ok) {
        const publicId =
          result.data?.data?.public_id == null
            ? result.data?.public_id
            : result.data.data.public_id;
        if (publicId) testContext.supportPublicId = String(publicId);
        if (flowContext && publicId)
          flowContext.supportPublicId = String(publicId);
      }
    }

    const answer = toolResults.map(answerFromTool).filter(Boolean).join(" | ");
    const derivedSuggestions = deriveGr45ToolSuggestions(toolResults);
    const suggestionText =
      formatSuggestions(derivedSuggestions) ||
      toolResults.map(suggestionsFromTool).filter(Boolean).join(" | ");
    const status = rowStatus(toolResults);
    const row = {
      group: groupName,
      flowStep,
      text: actualText,
      originalText: text,
      status,
      toolCalls,
      toolResults,
      answer,
      suggestions: suggestionText,
      suggestionChips: derivedSuggestions,
      tokenPresent: Boolean(localStorage.getItem(TOKEN_KEY)),
      userPresent: Boolean(localStorage.getItem(USER_KEY)),
    };
    allRows.push(row);

    const firstCall = toolCalls[0] ?? {};
    const argsStr = firstCall.arguments
      ? JSON.stringify(firstCall.arguments)
      : "{}";

    console.log(`[${status}] ${flowStep ? `${flowStep}. ` : ""}${actualText}`);
    console.log(`   => Tool: ${firstCall.toolName || "None"}`);
    console.log(`   => Args: ${argsStr}`);
    if (answer) console.log(`   => Bot: ${answer}`);
    if (suggestionText) console.log(`   => Suggestions: ${suggestionText}`);
    console.log(`--------------------------------------------------`);

    history.push({ role: "user", content: actualText });
    history.push({
      role: "assistant",
      content:
        answer || (status === "NO_TOOL" ? "Không chọn được tool." : status),
    });
    if (history.length > 16) history.splice(0, history.length - 16);
  }

  for (const group of CASE_GROUPS) {
    const allowAutoLogin = group.name !== "Auth / Account / Customer";
    for (const text of group.cases) {
      if (SKIP_LOGOUT && /^đăng xuất$/i.test(text.trim())) continue;
      const history = [];
      await runOne({
        groupName: group.name,
        text,
        history,
        allowAutoLogin,
      });
    }
  }

  const summary = allRows.reduce(
    (acc, row) => {
      acc.total += 1;
      acc[row.status] = (acc[row.status] ?? 0) + 1;
      return acc;
    },
    { total: 0 },
  );

  await fs.mkdir(path.dirname(REPORT_MD), { recursive: true });
  await fs.writeFile(
    REPORT_JSON,
    JSON.stringify({ startedAt, summary, rows: allRows }, null, 2),
  );
  await fs.writeFile(REPORT_MD, renderMarkdown(startedAt, summary, allRows));
  console.log(`Report: ${REPORT_MD}`);
  console.log(JSON.stringify(summary));
}

async function ensureLoggedIn(tools) {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  const candidates = [
    ["newcustomer@gmail.com", "654321"],
    ["newcustomer@gmail.com", "123456"],
    ["customer@gmail.com", "654321"],
    ["customer@gmail.com", "123456"],
  ];
  for (const [email, password] of candidates) {
    const result = await tools.execute(
      {
        id: crypto.randomUUID(),
        callId: crypto.randomUUID(),
        toolName: "auth_login",
        arguments: {
          email,
          password,
          raw_message: "silent login for regression",
        },
      },
      {
        confirmToolCall: async () => true,
      },
    );
    if (result.ok === true && localStorage.getItem(TOKEN_KEY)) return;
  }
}

function renderMarkdown(startedAt, summary, rows) {
  const lines = [];
  lines.push("# Chat Agent Full Regression Report");
  lines.push("");
  lines.push(`- Time: ${startedAt.toISOString()}`);
  lines.push(`- API base: http://127.0.0.1:8000/api/v1`);
  lines.push(`- Total: ${summary.total}`);
  for (const key of [
    "PASS",
    "CLARIFICATION",
    "AUTH_REQUIRED",
    "EMPTY_DATA",
    "API_FAIL",
    "STUB",
    "NO_TOOL",
    "NO_BOT_ANSWER",
  ]) {
    lines.push(`- ${key}: ${summary[key] ?? 0}`);
  }
  lines.push("");
  lines.push("## Failed / Needs Work");
  const badRows = rows.filter((row) => row.status !== "PASS");
  if (!badRows.length) {
    lines.push("");
    lines.push("All cases passed with usable tool data.");
  } else {
    lines.push("");
    lines.push(
      "| # | Group | Status | Message | Tool | Args | Error / Answer | Suggestions |",
    );
    lines.push("|---:|---|---|---|---|---|---|---|");
    badRows.forEach((row, idx) => {
      const firstCall = row.toolCalls[0] ?? {};
      const firstResult = row.toolResults[0] ?? {};
      const message = row.flowStep ? `${row.flowStep}. ${row.text}` : row.text;
      lines.push(
        `| ${idx + 1} | ${escapeMd(row.group)} | ${row.status} | ${escapeMd(message)} | ${escapeMd(firstCall.toolName ?? "")} | \`${escapeMd(compact(firstCall.arguments ?? {}, 240))}\` | ${escapeMd(compact(row.answer || firstResult.error || firstResult.data || "", 260))} | ${escapeMd(compact(row.suggestions, 150))} |`,
      );
    });
  }
  lines.push("");
  lines.push("## All Cases");
  lines.push("");
  lines.push(
    "| # | Group | Status | Message | Tool | Args | Bot Answer | Suggestions | Data Count |",
  );
  lines.push("|---:|---|---|---|---|---|---|---|---:|");
  rows.forEach((row, idx) => {
    const firstCall = row.toolCalls[0] ?? {};
    const toolNames = row.toolCalls.map((call) => call.toolName).join(", ");
    const argsStr = escapeMd(compact(firstCall.arguments ?? {}, 100));
    const message = row.flowStep ? `${row.flowStep}. ${row.text}` : row.text;
    const counts = row.toolResults
      .map((result) => collectCounts(result.data))
      .filter((n) => n !== null);

    lines.push(
      `| ${idx + 1} | ${escapeMd(row.group)} | ${row.status} | ${escapeMd(message)} | ${escapeMd(toolNames)} | \`${argsStr}\` | ${escapeMd(compact(row.answer, 200))} | ${escapeMd(compact(row.suggestions, 150))} | ${counts.length ? counts.join(", ") : ""} |`,
    );
  });
  lines.push("");
  lines.push("## Raw JSON");
  lines.push("");
  lines.push("See `docs/chat-agent-full-test-report.json`.");
  lines.push("");
  return lines.join("\n");
}

function escapeMd(value) {
  return String(value ?? "")
    .replace(/\|/g, "\\|")
    .replace(/\n/g, " ")
    .trim();
}

run().catch(async (error) => {
  await fs.mkdir(path.dirname(REPORT_MD), { recursive: true });
  const message =
    error instanceof Error ? error.stack || error.message : String(error);
  await fs.writeFile(
    REPORT_MD,
    `# Chat Agent Full Regression Report\n\nScript failed before completion.\n\n\`\`\`\n${message}\n\`\`\`\n`,
  );
  console.error(message);
  process.exitCode = 1;
});
