/**
 * Regex-based fast planner for GR45.
 * Maps common Vietnamese user intents directly to tool calls without LLM.
 * Returns a complete plan when matched, or null to fall back to LLM planner.
 *
 * Saves 1 LLM call per matched operational query (planner → 0 calls).
 */

function normalize(text) {
  return String(text ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

export const DEFAULT_FAST_PLANNER_TIME_ZONE = "Asia/Ho_Chi_Minh";

const VN_PROVINCES = [
  "ha noi", "ho chi minh", "hcm", "sai gon", "saigon", "da nang", "danang",
  "hue", "hai phong", "haiphong", "can tho", "cantho", "nha trang", "nhatrang",
  "vung tau", "vungtau", "da lat", "dalat", "quy nhon", "quynhon",
  "phan thiet", "phanthiet", "ha long", "halong", "bien hoa", "bienhoa",
  "buon ma thuot", "buonmathuot", "thanh hoa", "thanhhoa", "vinh", "pleiku",
  "kontum", "kon tum", "ban me thuot", "rach gia", "rachgia", "ca mau", "camau",
  "long xuyen", "longxuyen", "my tho", "mytho", "tay ninh", "tayninh",
  "binh duong", "binhduong", "dong nai", "dongnai", "ben tre", "bentre",
  "tien giang", "tiengiang", "vinh long", "vinhlong", "an giang", "angiang",
  "soc trang", "soctrang", "bac lieu", "baclieu", "kien giang", "kiengiang",
  "tra vinh", "travinh", "hau giang", "haugiang", "dong thap", "dongthap",
  "binh phuoc", "binhphuoc", "binh thuan", "binhthuan", "ninh thuan", "ninhthuan",
  "lam dong", "lamdong", "khanh hoa", "khanhhoa", "phu yen", "phuyen",
  "binh dinh", "binhdinh", "quang ngai", "quangngai", "quang nam", "quangnam",
  "quang tri", "quangtri", "quang binh", "quangbinh", "ha tinh", "hatinh",
  "nghe an", "nghean", "ninh binh", "ninhbinh", "nam dinh", "namdinh",
  "thai binh", "thaibinh", "hung yen", "hungyen", "bac ninh", "bacninh",
  "vinh phuc", "vinhphuc", "phu tho", "phutho", "thai nguyen", "thainguyen",
  "lang son", "langson", "cao bang", "caobang", "bac kan", "backan",
  "tuyen quang", "tuyenquang", "ha giang", "hagiang", "lao cai", "laocai",
  "yen bai", "yenbai", "son la", "sonla", "dien bien", "dienbien", "lai chau",
  "laichau", "hoa binh", "hoabinh", "bac giang", "bacgiang", "quang ninh",
  "quangninh", "hai duong", "haiduong", "gia lai", "gialai", "dak lak",
  "daklak", "dak nong", "daknong",
];

function findProvinceMentions(normText) {
  const found = [];
  for (const prov of VN_PROVINCES) {
    if (normText.includes(prov)) {
      found.push({ prov, idx: normText.indexOf(prov) });
    }
  }
  found.sort((a, b) => a.idx - b.idx);
  return found.map((f) => f.prov);
}

function toDisplayProvince(raw) {
  // Map normalized form back to display form (rough — let backend normalize too).
  const map = {
    "ha noi": "Hà Nội",
    "ho chi minh": "Hồ Chí Minh",
    "hcm": "Hồ Chí Minh",
    "sai gon": "Sài Gòn",
    "saigon": "Sài Gòn",
    "da nang": "Đà Nẵng",
    "danang": "Đà Nẵng",
    "hue": "Huế",
    "hai phong": "Hải Phòng",
    "haiphong": "Hải Phòng",
    "can tho": "Cần Thơ",
    "cantho": "Cần Thơ",
    "nha trang": "Nha Trang",
    "nhatrang": "Nha Trang",
    "vung tau": "Vũng Tàu",
    "vungtau": "Vũng Tàu",
    "da lat": "Đà Lạt",
    "dalat": "Đà Lạt",
  };
  return map[raw] ?? raw.replace(/\b\w/g, (c) => c.toUpperCase());
}

export function localIsoDate(
  now = new Date(),
  timeZone = DEFAULT_FAST_PLANNER_TIME_ZONE,
) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(now);

  const get = (type) => parts.find((part) => part.type === type)?.value;
  const year = get("year");
  const month = get("month");
  const day = get("day");

  if (!year || !month || !day) {
    return now.toISOString().slice(0, 10);
  }

  return `${year}-${month}-${day}`;
}

function addDaysToIsoDate(isoDate, days) {
  const match = String(isoDate ?? "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) return null;

  const date = new Date(
    Date.UTC(
      Number.parseInt(match[1], 10),
      Number.parseInt(match[2], 10) - 1,
      Number.parseInt(match[3], 10) + days,
    ),
  );

  return date.toISOString().slice(0, 10);
}

function extractDate(normText, todayIso) {
  // hôm nay, ngày mai, ngày kia
  if (/\bhom nay\b/.test(normText)) return todayIso;
  if (/\bmai\b|\bngay mai\b/.test(normText)) {
    return addDaysToIsoDate(todayIso, 1);
  }
  if (/\bngay kia\b|\bmot mai\b/.test(normText)) {
    return addDaysToIsoDate(todayIso, 2);
  }
  // "18 tháng 5 năm 2026", "ngày 18 tháng 5 năm 2026"
  const wordDateMatch = normText.match(/\b(\d{1,2})\s*thang\s*(\d{1,2})(?:\s*nam\s*(\d{2,4}))?\b/);
  if (wordDateMatch) {
    const dd = String(wordDateMatch[1]).padStart(2, "0");
    const mm = String(wordDateMatch[2]).padStart(2, "0");
    let yyyy = wordDateMatch[3];
    if (!yyyy) yyyy = todayIso.slice(0, 4);
    if (yyyy.length === 2) yyyy = `20${yyyy}`;
    return `${yyyy}-${mm}-${dd}`;
  }
  // dd/mm or dd-mm
  const dmMatch = normText.match(/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/);
  if (dmMatch) {
    const dd = String(dmMatch[1]).padStart(2, "0");
    const mm = String(dmMatch[2]).padStart(2, "0");
    let yyyy = dmMatch[3];
    if (!yyyy) yyyy = todayIso.slice(0, 4);
    if (yyyy.length === 2) yyyy = `20${yyyy}`;
    return `${yyyy}-${mm}-${dd}`;
  }
  return null;
}

function extractTime(normText) {
  // "7 gio 30 phut", "7 giờ 30 phút"
  const gioPhut = normText.match(
    /\b(\d{1,2})\s*gio\s*(\d{1,2})\s*phut\b/,
  );
  if (gioPhut) {
    const hh = String(gioPhut[1]).padStart(2, "0");
    const mm = String(gioPhut[2]).padStart(2, "0");
    return `${hh}:${mm}`;
  }
  // "8h", "08:30", "21h30", "8 gio ruoi", "8h ruoi"
  const colonMatch = normText.match(/\b(\d{1,2})[:h](\d{2})\b/);
  if (colonMatch) {
    const hh = String(colonMatch[1]).padStart(2, "0");
    return `${hh}:${colonMatch[2]}`;
  }
  const ruoiMatch = normText.match(/\b(\d{1,2})\s*(?:h|gio)?\s*ruoi\b/);
  if (ruoiMatch) {
    const hh = String(ruoiMatch[1]).padStart(2, "0");
    return `${hh}:30`;
  }
  const hMatch = normText.match(/\b(\d{1,2})\s*(?:h|gio)\b/);
  if (hMatch) {
    const hh = String(hMatch[1]).padStart(2, "0");
    return `${hh}:00`;
  }
  return null;
}

function extractMaNhaXe(normText) {
  const match = normText.match(/\b(nx[a-z0-9_-]{2,})\b/i);
  return match ? match[1].toUpperCase() : null;
}

function extractVnPhone(normText) {
  const m = String(normText ?? "").match(/\b(0\d{9,10})\b/);
  return m ? m[1] : null;
}

function extractEmailLoose(normText) {
  const m = String(normText ?? "").match(
    /\b[^\s@]{1,64}@[^\s@]{1,255}\.[^\s@]{2,32}\b/,
  );
  return m ? m[0] : null;
}

function extractPassword(rawText) {
  const s = String(rawText ?? "");
  // Slash format: "email@x.com / Password123" — raw text preserves case
  const slashM = s.match(/@[^\s/]+\s*\/\s*(\S+)/);
  if (slashM) return slashM[1].replace(/[.,;]+$/, "");
  // Keyword format: locate keyword anchor in raw text, then skip connectors and capture token
  // Use a broad Unicode-friendly pattern — \S+ after keyword/colon/space/connector
  const kwRe = /(?:m[ậâa]t\s*kh[ẩâa]u|password|pass(?:word)?)[\s:]+/i;
  const anchorM = s.match(kwRe);
  if (!anchorM) return null;
  const afterKw = s.slice(anchorM.index + anchorM[0].length);
  // Skip optional connector words (là, la, is, are) then grab next non-space token
  const tokenM = afterKw.match(/^(?:l[aà]\s+|is\s+|are\s+)?(\S+)/i);
  if (tokenM) return tokenM[1].replace(/[.,;]+$/, "");
  return null;
}

/**
 * Chỉ hỏi **có tuyến / có xe chạy** giữa hai điểm — không hỏi lịch, giờ, giá → `search_routes`.
 * Dùng cho fast path và `postProcessGr45Plan` (ghi đè khi LLM gọi nhầm `search_trips`).
 *
 * @param {string} normalizedText — kết quả `normalize(userMessage)`
 * @returns {{ diem_di: string, diem_den: string } | null}
 */
export function routeExistenceSearchRoutesArgsFromNorm(normalizedText) {
  const n = String(normalizedText ?? "").trim();
  if (!n) return null;

  const asksRouteExistenceVi =
    /\b(co|con)\b.*\btuyen\b/.test(n) ||
    /\b(tim|kiem)\b.*\btuyen\b/.test(n) ||
    /\b(tuyen (xe|duong|khach))\b.*\b(tu|di tu)\b/.test(n) ||
    /\b(co|con)\b.*\b(chay xe|xe (chay|khach))\b.*\b(tu|di tu)\b/.test(n);

  const asksRouteExistenceEn =
    /\b(route|bus|coach|line|service)\b/.test(n) &&
    /\bfrom\b/.test(n) &&
    /\bto\b/.test(n);

  if (!asksRouteExistenceVi && !asksRouteExistenceEn) return null;

  const wantsTripSchedule =
    /\b(chuyen nao|lich chuyen|tim chuyen|chuyen cu the|gio khoi hanh|ve xe|dat ve)\b/.test(
      n,
    ) ||
    /\b(ngay|hom nay|ngay mai|mai\b|\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2})\b/.test(
      n,
    ) ||
    /\b\d{1,2}\s*thang\s*\d{1,2}\b/.test(n) ||
    /\b(chieu nay|chieu mai|toi nay|sang nay)\b/.test(n) ||
    /\b(departure|schedule|what time|which trip|book (a )?ticket)\b/.test(n) ||
    /\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/.test(n) ||
    /\b\d{1,2}\s*(january|february|march|april|may|june|july|august|september|october|november|december)\b/.test(
      n,
    ) ||
    /\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,4}\b/.test(
      n,
    );

  if (wantsTripSchedule) return null;

  const provs = findProvinceMentions(n);
  const args = {};
  if (provs[0]) args.diem_di = toDisplayProvince(provs[0]);
  if (provs[1]) args.diem_den = toDisplayProvince(provs[1]);
  if (!args.diem_di || !args.diem_den) return null;
  return args;
}

export function routeExistenceSearchRoutesArgsFromUserMessage(userMessage) {
  const text = String(userMessage ?? "").trim();
  if (!text) return null;
  return routeExistenceSearchRoutesArgsFromNorm(normalize(text));
}

const PATTERNS = [
  // Đăng nhập — phải trước pattern tìm chuyến để tránh nuốt nhầm (SĐT / “bằng số …”).
  {
    test: (n) => /\b(dang nhap|dang nhap vao|log in|log me in|login|sign in|signin)\b/.test(n),
    build: (n, _today, rawText) => {
      const args = {};
      const phone = extractVnPhone(n);
      const email = extractEmailLoose(n);
      if (phone) args.so_dien_thoai = phone;
      if (email) args.email = email;
      // Password must be extracted from raw (pre-normalize) text to preserve case
      const pwd = extractPassword(rawText ?? n);
      if (pwd) args.password = pwd;
      return {
        toolName: "auth_login",
        rationale: "Khách muốn đăng nhập.",
        arguments: args,
      };
    },
  },
  // Chỉ hỏi **tuyến** (có chạy giữa hai điểm không) — không hỏi lịch/chuyến cụ thể → search_routes (tránh search_trips thiếu ngày).
  {
    test: (n) => routeExistenceSearchRoutesArgsFromNorm(n) !== null,
    build: (n) => {
      const args = routeExistenceSearchRoutesArgsFromNorm(n);
      return {
        toolName: "search_routes",
        rationale:
          "Khách hỏi có tuyến / tuyến đường giữa các điểm — tra danh mục tuyến công khai (không cần ngày).",
        arguments: args ?? {},
      };
    },
  },
  // Trip search: "tìm chuyến", "tìm xe", "có chuyến", "chuyến từ X đến Y", "đi từ X đến Y"
  {
    test: (n) =>
      /\b(tim|kiem|co|xem)\b.*\b(chuyen|xe|tuyen|lich)\b/.test(n) ||
      /\b(chuyen|xe|tuyen)\b.*\b(tu|di tu)\b.*\b(den|toi|ve)\b/.test(n) ||
      /\b(di tu|tu)\s+\w+.*\b(den|toi|ve)\b/.test(n),
    build: (n, todayIso) => {
      const provs = findProvinceMentions(n);
      const args = {};
      if (provs[0]) args.diem_di = toDisplayProvince(provs[0]);
      if (provs[1]) args.diem_den = toDisplayProvince(provs[1]);
      const date = extractDate(n, todayIso);
      if (date) args.ngay_khoi_hanh = date;
      const time = extractTime(n);
      if (time) args.gio_khoi_hanh = time;
      return {
        toolName: "search_trips",
        rationale: "Khách tìm chuyến xe.",
        arguments: args,
      };
    },
  },
  // Ticket list: "vé của tôi", "danh sách vé", "vé đã đặt"
  {
    test: (n) =>
      /\b(ve|nhung ve|cac ve|danh sach ve)\s+(cua toi|cua minh|da dat|toi da)\b/.test(n) ||
      /\b(xem|liet ke)\s+(ve|cac ve|danh sach ve)\b/.test(n) ||
      /\b(lich su (dat ve|giao dich))\b/.test(n),
    build: () => ({
      toolName: "ticket_list_tickets",
      rationale: "Khách muốn xem danh sách vé.",
      arguments: {},
    }),
  },
  // Support / admin
  {
    test: (n) =>
      /\b(ho tro|gap admin|gap nha xe|gap nhan vien|tong dai|tu van vien|live support|chat voi nhan vien|chat voi nha xe|noi chuyen voi nguoi|lien he)\b/.test(n),
    build: (n) => {
      const maNhaXe = extractMaNhaXe(n);
      const wantsOperator =
        /\b(gap nha xe|chat voi nha xe|lien he nha xe|nha xe)\b/.test(n);

      return {
        toolName: "support_create_support_session",
        rationale: wantsOperator
          ? "Khách muốn mở phiên hỗ trợ với nhà xe."
          : "Khách muốn gặp hỗ trợ viên/admin.",
        arguments:
          wantsOperator && maNhaXe
            ? { target: "nha_xe", ma_nha_xe: maNhaXe }
            : { target: "admin" },
      };
    },
  },
  // Account / profile
  {
    test: (n) =>
      /\b(tai khoan cua toi|ho so cua toi|thong tin ca nhan|xem profile|xem ho so)\b/.test(n),
    build: () => ({
      toolName: "account_get_profile",
      rationale: "Khách xem thông tin tài khoản.",
      arguments: {},
    }),
  },
  // Tracking
  {
    test: (n) =>
      /\b(xe (dang )?o dau|vi tri (xe|chuyen)|tracking|theo doi (xe|chuyen)|xe da toi)\b/.test(n),
    build: () => ({
      toolName: "tracking_get_live_vehicle_location",
      rationale: "Khách hỏi vị trí xe.",
      arguments: {},
    }),
  },
  // Voucher
  {
    test: (n) =>
      /\b(voucher|ma giam gia|khuyen mai)\b/.test(n) &&
      !/\b(ap dung|dung)\b/.test(n),
    build: () => ({
      toolName: "voucher_list_available_vouchers",
      rationale: "Khách xem voucher khả dụng.",
      arguments: {},
    }),
  },
  // Loyalty points
  {
    test: (n) => /\b(diem (thuong|tich luy|hang)|loyalty)\b/.test(n),
    build: () => ({
      toolName: "loyalty_get_current_points",
      rationale: "Khách xem điểm thưởng.",
      arguments: {},
    }),
  },
  // Logout
  {
    test: (n) => /\b(dang xuat|thoat tai khoan|logout|sign out)\b/.test(n),
    build: () => ({
      toolName: "auth_logout",
      rationale: "Khách yêu cầu đăng xuất.",
      arguments: {},
    }),
  },
];

/**
 * Try regex-based fast planning. Returns a complete plan if any pattern matches,
 * or null to fall back to LLM planner.
 *
 * @param {{ userMessage: string, now?: Date, timeZone?: string }} input
 * @returns {object | null}
 */
export function gr45FastPlanner({
  userMessage,
  now = new Date(),
  timeZone = DEFAULT_FAST_PLANNER_TIME_ZONE,
} = {}) {
  const text = String(userMessage ?? "").trim();
  if (!text) return null;
  const n = normalize(text);
  const todayIso = localIsoDate(now, timeZone);

  for (const pattern of PATTERNS) {
    if (pattern.test(n)) {
      const toolCall = pattern.build(n, todayIso, text);
      return {
        goal: text.slice(0, 200),
        steps: [],
        stopCondition: "tool_result_observed",
        confidence: 0.85,
        toolCalls: [toolCall],
        needs_grounding: false,
        needs_rag_fallback: false,
      };
    }
  }

  return null;
}
