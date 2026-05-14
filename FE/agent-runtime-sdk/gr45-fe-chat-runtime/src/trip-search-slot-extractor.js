import {
  DEFAULT_FAST_PLANNER_TIME_ZONE,
  localIsoDate,
} from "./gr45-fast-planner.js";

function safeLeadingJsonObject(raw) {
  const s = String(raw ?? "").trim();
  try {
    const i = s.indexOf("{");
    const j = s.lastIndexOf("}");
    if (i >= 0 && j > i) return JSON.parse(s.slice(i, j + 1));
  } catch {
    /* ignore */
  }
  return null;
}

function normSlot(v) {
  if (v == null) return "";
  return String(v).trim();
}

async function extractTripSearchSlotsFromUserMessage(llm, userMessage) {
  const text = String(userMessage ?? "").trim();
  if (!text || typeof llm?.completeJson !== "function") return {};

  const todayIso = localIsoDate(new Date(), DEFAULT_FAST_PLANNER_TIME_ZONE);
  const prompt = [
    "Bạn trích các trường tìm chuyến/xe khách Việt Nam từ TIN NHẮN khách.",
    "Chỉ trả một JSON object, không markdown, không giải thích.",
    "",
    `Ngày hôm nay để đối chiếu tin như "hôm nay", "mai" (ISO yyyy-mm-dd): ${todayIso}`,
    "",
    "Schema (thiếu / không chắc → bỏ key):",
    '{ "diem_di": string, "diem_den": string, "ngay_khoi_hanh": string, "gio_khoi_hanh": string, "nha_xe": string, "ma_nha_xe": string, "loai_xe": string }',
    "",
    "- diem_di / diem_den: tỉnh thành (vd: Huế, Đà Nẵng).",
    "- nha_xe: tên nhà xe được nhắc rõ (vd: Phương Trang, Sơn Tùng).",
    "- ma_nha_xe: mã nhà xe dạng NX001 nếu người dùng nói mã.",
    "- loai_xe: loại xe được nhắc rõ (vd: limousine).",
    "- ngay_khoi_hanh: yyyy-mm-dd. “Hôm nay”, “hom nay” → ngày ở trên; không nói ngày → bỏ key.",
    "",
    '- gio_khoi_hanh: **Luôn** chuẩn **HH:mm** (24 giờ, giờ 2 chữ số như "08"). Chỉ gửi key khi trong tin có **giờ gắn số**, hoặc nói "rưỡi" có gắn giờ (vd 8 giờ rưỡi = 08:30).',
    '  • "8h rưỡi", "8h rưỡi sáng", "8 giờ rưỡi sáng", "tám giờ rưỡi" → 08:30. «Rưỡi» = +30 phút; chữ "sáng"/"chiều" chỉ để hiểu ngữ cảnh, vẫn map đúng HH:mm khi đã có số giờ.',
    '  • "21h30", "6h15", "7h" (= 07:00) → chuẩn hoá tương ứng.',
    "  • Chiều/tối/sáng **không** được map sang HH:mm khi không kèm số (vd. chỉ \"chiều\" → không gửi gio_khoi_hanh).",
    "- **Cấm** đoán giờ khi trong tin không có dấu hiệu số giờ; **cấm** điền 08:00 mặc định.",
    "",
    "TIN NHẮN:",
    text.slice(0, 2000),
  ].join("\n");

  try {
    const raw = await llm.completeJson(prompt);
    const parsedPayload = safeLeadingJsonObject(raw);
    if (!parsedPayload || typeof parsedPayload !== "object") return {};

    const out = {};
    const diemDi = normSlot(parsedPayload.diem_di);
    if (diemDi) out.diem_di = diemDi;
    const diemDen = normSlot(parsedPayload.diem_den);
    if (diemDen) out.diem_den = diemDen;
    const ngay = normSlot(parsedPayload.ngay_khoi_hanh);
    if (ngay) out.ngay_khoi_hanh = ngay;
    const gio = normSlot(parsedPayload.gio_khoi_hanh);
    if (gio) out.gio_khoi_hanh = gio;
    const nhaXe = normSlot(parsedPayload.nha_xe);
    if (nhaXe) out.nha_xe = nhaXe;
    const maNhaXe = normSlot(parsedPayload.ma_nha_xe);
    if (maNhaXe) out.ma_nha_xe = maNhaXe;
    const loaiXe = normSlot(parsedPayload.loai_xe);
    if (loaiXe) out.loai_xe = loaiXe;
    return out;
  } catch {
    return {};
  }
}

function pickFilled(args, keys) {
  for (const key of keys) {
    const v = args[key];
    if (v !== undefined && v !== null && String(v).trim() !== "") {
      return String(v).trim();
    }
  }
  return "";
}

function mergeTripSearchExtractedSlots(baseArgs, extracted) {
  const out = { ...baseArgs };
  if (!extracted || typeof extracted !== "object") return out;

  if (
    !pickFilled(out, ["diem_di", "from", "xuat_phat", "diem_di_khoi_hanh"]) &&
    extracted.diem_di
  ) {
    out.diem_di = extracted.diem_di;
  }
  if (!pickFilled(out, ["diem_den", "to", "dich_den"]) && extracted.diem_den) {
    out.diem_den = extracted.diem_den;
  }
  if (
    !pickFilled(out, [
      "ngay_khoi_hanh",
      "ngay_di",
      "ngay",
      "date",
    ]) &&
    extracted.ngay_khoi_hanh
  ) {
    out.ngay_khoi_hanh = extracted.ngay_khoi_hanh;
  }
  if (
    !pickFilled(out, ["gio_khoi_hanh", "gio", "gio_chay"]) &&
    extracted.gio_khoi_hanh
  ) {
    out.gio_khoi_hanh = extracted.gio_khoi_hanh;
  }
  if (!pickFilled(out, ["nha_xe", "ten_nha_xe"]) && extracted.nha_xe) {
    out.nha_xe = extracted.nha_xe;
  }
  if (!pickFilled(out, ["ma_nha_xe"]) && extracted.ma_nha_xe) {
    out.ma_nha_xe = extracted.ma_nha_xe;
  }
  if (!pickFilled(out, ["loai_xe", "vehicle_type"]) && extracted.loai_xe) {
    out.loai_xe = extracted.loai_xe;
  }

  return out;
}

const TRIP_SEARCH_TOOL_NAMES = new Set(["search_trips", "search_routes"]);

export async function enhanceGr45TripSearchArguments({
  llm,
  toolName,
  rawUserMessage,
  argumentsPayload,
}) {
  if (
    !TRIP_SEARCH_TOOL_NAMES.has(toolName) ||
    !String(rawUserMessage ?? "").trim() ||
    typeof llm?.completeJson !== "function"
  ) {
    return argumentsPayload;
  }

  // Skip LLM extraction when the planner already filled the key route slots.
  const diemDiFilled = pickFilled(argumentsPayload, ["diem_di", "from", "xuat_phat", "diem_di_khoi_hanh"]);
  const diemDenFilled = pickFilled(argumentsPayload, ["diem_den", "to", "dich_den"]);
  if (diemDiFilled && diemDenFilled) {
    return argumentsPayload;
  }

  const extracted = await extractTripSearchSlotsFromUserMessage(
    llm,
    rawUserMessage
  );
  return mergeTripSearchExtractedSlots(argumentsPayload, extracted);
}
