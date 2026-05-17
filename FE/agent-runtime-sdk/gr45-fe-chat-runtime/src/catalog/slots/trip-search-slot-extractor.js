import {
  extractDateFromText,
  extractTimeFilterFromText,
  findDirectionedProvinces,
} from "../../domain/planner/text-utils.js";

const TRIP_SEARCH_TOOL_NAMES = new Set(["search_trips", "search_routes"]);

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
    !pickFilled(out, ["ngay_khoi_hanh", "ngay_di", "ngay", "date"]) &&
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
  if (!pickFilled(out, ["gio_khoi_hanh_tu"]) && extracted.gio_khoi_hanh_tu) {
    out.gio_khoi_hanh_tu = extracted.gio_khoi_hanh_tu;
  }
  if (!pickFilled(out, ["gio_khoi_hanh_den"]) && extracted.gio_khoi_hanh_den) {
    out.gio_khoi_hanh_den = extracted.gio_khoi_hanh_den;
  }

  return out;
}

function extractTripSearchSlotsByRule(userMessage) {
  const text = String(userMessage ?? "").trim();
  if (!text) return {};

  const out = {};
  const [diemDi, diemDen] = findDirectionedProvinces(text);
  if (diemDi) out.diem_di = diemDi;
  if (diemDen) out.diem_den = diemDen;

  const ngay = extractDateFromText(text);
  if (ngay) out.ngay_khoi_hanh = ngay;

  Object.assign(out, extractTimeFilterFromText(text));

  return out;
}

export function enhanceGr45TripSearchArgumentsRuleOnly({
  toolName,
  rawUserMessage,
  argumentsPayload,
}) {
  if (
    !TRIP_SEARCH_TOOL_NAMES.has(toolName) ||
    !String(rawUserMessage ?? "").trim()
  ) {
    return argumentsPayload;
  }

  const extracted = extractTripSearchSlotsByRule(rawUserMessage);
  return mergeTripSearchExtractedSlots(argumentsPayload, extracted);
}
