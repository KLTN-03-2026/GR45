export const DEFAULT_FAST_PLANNER_TIME_ZONE = "Asia/Ho_Chi_Minh";

export function normalize(text) {
  return String(text ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
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

export function extractDate(normText, todayIso) {
  if (/\bhom nay\b/.test(normText)) return todayIso;
  if (/\bmai\b|\bngay mai\b/.test(normText)) {
    return addDaysToIsoDate(todayIso, 1);
  }
  if (/\bngay kia\b|\bmot mai\b/.test(normText)) {
    return addDaysToIsoDate(todayIso, 2);
  }
  const isoMatch = normText.match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
  if (isoMatch) {
    return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
  }
  const englishDateMatch = normText.match(
    /\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{1,2})(?:,\s*|\s+)(\d{4})\b/,
  );
  if (englishDateMatch) {
    const monthMap = {
      january: "01",
      february: "02",
      march: "03",
      april: "04",
      may: "05",
      june: "06",
      july: "07",
      august: "08",
      september: "09",
      october: "10",
      november: "11",
      december: "12",
    };
    return `${englishDateMatch[3]}-${monthMap[englishDateMatch[1]]}-${String(englishDateMatch[2]).padStart(2, "0")}`;
  }
  const wordDateMatch = normText.match(
    /\b(\d{1,2})\s*thang\s*(\d{1,2})(?:\s*nam\s*(\d{2,4}))?\b/,
  );
  if (wordDateMatch) {
    const dd = String(wordDateMatch[1]).padStart(2, "0");
    const mm = String(wordDateMatch[2]).padStart(2, "0");
    let yyyy = wordDateMatch[3];
    if (!yyyy) yyyy = todayIso.slice(0, 4);
    if (yyyy.length === 2) yyyy = `20${yyyy}`;
    return `${yyyy}-${mm}-${dd}`;
  }
  const dmMatch = normText.match(
    /\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/,
  );
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

export function extractTime(normText) {
  const gioPhut = normText.match(/\b(\d{1,2})\s*gio\s*(\d{1,2})\s*phut\b/);
  if (gioPhut) {
    return `${String(gioPhut[1]).padStart(2, "0")}:${String(gioPhut[2]).padStart(2, "0")}`;
  }
  const colonMatch = normText.match(/\b(\d{1,2})[:h](\d{2})\b/);
  if (colonMatch) {
    return `${String(colonMatch[1]).padStart(2, "0")}:${colonMatch[2]}`;
  }
  const ruoiMatch = normText.match(/\b(\d{1,2})\s*(?:h|gio)?\s*ruoi\b/);
  if (ruoiMatch) {
    return `${String(ruoiMatch[1]).padStart(2, "0")}:30`;
  }
  const hMatch = normText.match(/\b(\d{1,2})\s*(?:h|gio)\b/);
  if (hMatch) {
    return `${String(hMatch[1]).padStart(2, "0")}:00`;
  }
  return null;
}

function to24Hour(rawHour, period = "") {
  let hour = Number.parseInt(rawHour, 10);
  if (!Number.isFinite(hour)) return null;

  const p = String(period ?? "").trim();
  if (/^(pm|p\.?m\.?|toi|chieu)$/.test(p) && hour < 12) {
    hour += 12;
  }
  if (/^(am|a\.?m\.?|sang)$/.test(p) && hour === 12) {
    hour = 0;
  }
  return String(hour).padStart(2, "0");
}

function clockFromParts(hour, minute = "00", period = "") {
  const hh = to24Hour(hour, period);
  return hh ? `${hh}:${String(minute).padStart(2, "0")}` : "";
}

function shiftClockByMinutes(clock, deltaMinutes) {
  const [rawHour, rawMinute] = String(clock ?? "").split(":");
  const hour = Number.parseInt(rawHour, 10);
  const minute = Number.parseInt(rawMinute, 10);
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) return "";

  const totalMinutes = Math.max(
    0,
    Math.min(23 * 60 + 59, hour * 60 + minute + deltaMinutes),
  );
  const shiftedHour = String(Math.floor(totalMinutes / 60)).padStart(2, "0");
  const shiftedMinute = String(totalMinutes % 60).padStart(2, "0");
  return `${shiftedHour}:${shiftedMinute}`;
}

export function extractTimeFilter(normText) {
  const n = String(normText ?? "");

  const tripExactMatch = n.match(
    /\b(?:co\s+)?chuyen\s+(?:luc\s+|at\s+)?(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(sang|chieu|toi|a\.?m\.?|p\.?m\.?)?\b/,
  );
  if (tripExactMatch) {
    return {
      gio_khoi_hanh: clockFromParts(
        tripExactMatch[1],
        tripExactMatch[2] ?? "00",
        tripExactMatch[3] ?? "",
      ),
    };
  }

  const englishBetweenMatch = n.match(
    /\bbetween\s*(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)\s*and\s*(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)\b/,
  );
  if (englishBetweenMatch) {
    return {
      gio_khoi_hanh_tu: clockFromParts(
        englishBetweenMatch[1],
        englishBetweenMatch[2] ?? "00",
        englishBetweenMatch[3],
      ),
      gio_khoi_hanh_den: clockFromParts(
        englishBetweenMatch[4],
        englishBetweenMatch[5] ?? "00",
        englishBetweenMatch[6],
      ),
    };
  }

  const betweenMatch = n.match(
    /\b(?:khoang|tu|between)\s*(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(?:-|–|—|den|toi|and)\s*(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(sang|chieu|toi|a\.?m\.?|p\.?m\.?)?\b/,
  );
  if (betweenMatch) {
    const [, fromHour, fromMinute = "00", toHour, toMinute = "00", period = ""] =
      betweenMatch;
    return {
      gio_khoi_hanh_tu: clockFromParts(fromHour, fromMinute, period),
      gio_khoi_hanh_den: clockFromParts(toHour, toMinute, period),
    };
  }

  const beforeMatch = n.match(
    /\b(?:truoc|before)\s*(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(sang|chieu|toi|a\.?m\.?|p\.?m\.?)?\b/,
  );
  if (beforeMatch) {
    const upperBound = clockFromParts(
      beforeMatch[1],
      beforeMatch[2] ?? "00",
      beforeMatch[3] ?? "",
    );
    return {
      gio_khoi_hanh_den: shiftClockByMinutes(upperBound, -1),
    };
  }

  const afterMatch = n.match(
    /\b(?:sau|after)\s*(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(sang|chieu|toi|a\.?m\.?|p\.?m\.?)?\b/,
  );
  if (afterMatch) {
    const lowerBound = clockFromParts(
      afterMatch[1],
      afterMatch[2] ?? "00",
      afterMatch[3] ?? "",
    );
    return {
      gio_khoi_hanh_tu: shiftClockByMinutes(lowerBound, 1),
    };
  }

  const exactMatch = n.match(
    /\b(?:dung|luc|at)?\s*(\d{1,2})(?::|h)?(\d{2})?\s*(?:gio)?\s*(sang|chieu|toi|a\.?m\.?|p\.?m\.?)\b/,
  );
  if (exactMatch) {
    return {
      gio_khoi_hanh: clockFromParts(
        exactMatch[1],
        exactMatch[2] ?? "00",
        exactMatch[3],
      ),
    };
  }
  const exact = extractTime(n);
  if (exact) return { gio_khoi_hanh: exact };

  if (/\b(sang som|early morning)\b/.test(n)) {
    return {
      gio_khoi_hanh_tu: "00:00",
      gio_khoi_hanh_den: "06:00",
    };
  }
  if (/\b(buoi sang|morning)\b/.test(n)) {
    return {
      gio_khoi_hanh_tu: "06:00",
      gio_khoi_hanh_den: "12:00",
    };
  }
  if (/\b(buoi chieu|afternoon)\b/.test(n)) {
    return {
      gio_khoi_hanh_tu: "12:00",
      gio_khoi_hanh_den: "18:00",
    };
  }
  if (/\b(buoi toi|evening|chieu toi)\b/.test(n)) {
    return {
      gio_khoi_hanh_tu: "18:00",
      gio_khoi_hanh_den: "23:59",
    };
  }

  return {};
}

export function extractDateFromText(
  rawText,
  now = new Date(),
  tz = DEFAULT_FAST_PLANNER_TIME_ZONE,
) {
  return extractDate(normalize(rawText), localIsoDate(now, tz));
}

export function extractTimeFromText(rawText) {
  return extractTime(normalize(rawText));
}

export function extractTimeFilterFromText(rawText) {
  return extractTimeFilter(normalize(rawText));
}

export const VN_PROVINCES = [
  "ha noi", "hanoi", "hn",
  "thanh pho ho chi minh", "tp ho chi minh", "ho chi minh", "hochiminh", "tphcm", "tp hcm", "hcm", "sai gon", "saigon", "sg",
  "da nang", "danang", "dn",
  "hue", "hai phong", "haiphong", "hp", "can tho", "cantho", "ct", "nha trang", "nhatrang",
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

const PROVINCE_DISPLAY = {
  "ha noi": "Hà Nội", "hanoi": "Hà Nội", "hn": "Hà Nội",
  "ho chi minh": "TP Hồ Chí Minh", "hochiminh": "TP Hồ Chí Minh",
  "hcm": "TP Hồ Chí Minh", "tphcm": "TP Hồ Chí Minh", "tp hcm": "TP Hồ Chí Minh",
  "tp ho chi minh": "TP Hồ Chí Minh", "thanh pho ho chi minh": "TP Hồ Chí Minh",
  "sai gon": "TP Hồ Chí Minh", "saigon": "TP Hồ Chí Minh", "sg": "TP Hồ Chí Minh",
  "da nang": "Đà Nẵng", "danang": "Đà Nẵng", "dn": "Đà Nẵng",
  "hue": "Huế",
  "hai phong": "Hải Phòng", "haiphong": "Hải Phòng", "hp": "Hải Phòng",
  "can tho": "Cần Thơ", "cantho": "Cần Thơ", "ct": "Cần Thơ",
  "nha trang": "Nha Trang", "nhatrang": "Nha Trang",
  "vung tau": "Vũng Tàu", "vungtau": "Vũng Tàu",
  "da lat": "Đà Lạt", "dalat": "Đà Lạt",
  "quy nhon": "Quy Nhơn", "quynhon": "Quy Nhơn",
  "phan thiet": "Phan Thiết", "phanthiet": "Phan Thiết",
  "ha long": "Hạ Long", "halong": "Hạ Long",
  "bien hoa": "Biên Hòa", "bienhoa": "Biên Hòa",
  "buon ma thuot": "Buôn Ma Thuột", "buonmathuot": "Buôn Ma Thuột",
  "ban me thuot": "Buôn Ma Thuột",
  "thanh hoa": "Thanh Hóa", "thanhhoa": "Thanh Hóa",
  "vinh": "Vinh",
  "pleiku": "Pleiku",
  "kontum": "Kon Tum", "kon tum": "Kon Tum",
  "rach gia": "Rạch Giá", "rachgia": "Rạch Giá",
  "ca mau": "Cà Mau", "camau": "Cà Mau",
  "long xuyen": "Long Xuyên", "longxuyen": "Long Xuyên",
  "my tho": "Mỹ Tho", "mytho": "Mỹ Tho",
  "tay ninh": "Tây Ninh", "tayninh": "Tây Ninh",
  "binh duong": "Bình Dương", "binhduong": "Bình Dương",
  "dong nai": "Đồng Nai", "dongnai": "Đồng Nai",
  "ben tre": "Bến Tre", "bentre": "Bến Tre",
  "tien giang": "Tiền Giang", "tiengiang": "Tiền Giang",
  "vinh long": "Vĩnh Long", "vinhlong": "Vĩnh Long",
  "an giang": "An Giang", "angiang": "An Giang",
  "soc trang": "Sóc Trăng", "soctrang": "Sóc Trăng",
  "bac lieu": "Bạc Liêu", "baclieu": "Bạc Liêu",
  "kien giang": "Kiên Giang", "kiengiang": "Kiên Giang",
  "tra vinh": "Trà Vinh", "travinh": "Trà Vinh",
  "hau giang": "Hậu Giang", "haugiang": "Hậu Giang",
  "dong thap": "Đồng Tháp", "dongthap": "Đồng Tháp",
  "binh phuoc": "Bình Phước", "binhphuoc": "Bình Phước",
  "binh thuan": "Bình Thuận", "binhthuan": "Bình Thuận",
  "ninh thuan": "Ninh Thuận", "ninhthuan": "Ninh Thuận",
  "lam dong": "Lâm Đồng", "lamdong": "Lâm Đồng",
  "khanh hoa": "Khánh Hòa", "khanhhoa": "Khánh Hòa",
  "phu yen": "Phú Yên", "phuyen": "Phú Yên",
  "binh dinh": "Bình Định", "binhdinh": "Bình Định",
  "quang ngai": "Quảng Ngãi", "quangngai": "Quảng Ngãi",
  "quang nam": "Quảng Nam", "quangnam": "Quảng Nam",
  "quang tri": "Quảng Trị", "quangtri": "Quảng Trị",
  "quang binh": "Quảng Bình", "quangbinh": "Quảng Bình",
  "ha tinh": "Hà Tĩnh", "hatinh": "Hà Tĩnh",
  "nghe an": "Nghệ An", "nghean": "Nghệ An",
  "ninh binh": "Ninh Bình", "ninhbinh": "Ninh Bình",
  "nam dinh": "Nam Định", "namdinh": "Nam Định",
  "thai binh": "Thái Bình", "thaibinh": "Thái Bình",
  "hung yen": "Hưng Yên", "hungyen": "Hưng Yên",
  "bac ninh": "Bắc Ninh", "bacninh": "Bắc Ninh",
  "vinh phuc": "Vĩnh Phúc", "vinhphuc": "Vĩnh Phúc",
  "phu tho": "Phú Thọ", "phutho": "Phú Thọ",
  "thai nguyen": "Thái Nguyên", "thainguyen": "Thái Nguyên",
  "lang son": "Lạng Sơn", "langson": "Lạng Sơn",
  "cao bang": "Cao Bằng", "caobang": "Cao Bằng",
  "bac kan": "Bắc Kạn", "backan": "Bắc Kạn",
  "tuyen quang": "Tuyên Quang", "tuyenquang": "Tuyên Quang",
  "ha giang": "Hà Giang", "hagiang": "Hà Giang",
  "lao cai": "Lào Cai", "laocai": "Lào Cai",
  "yen bai": "Yên Bái", "yenbai": "Yên Bái",
  "son la": "Sơn La", "sonla": "Sơn La",
  "dien bien": "Điện Biên", "dienbien": "Điện Biên",
  "lai chau": "Lai Châu", "laichau": "Lai Châu",
  "hoa binh": "Hòa Bình", "hoabinh": "Hòa Bình",
  "bac giang": "Bắc Giang", "bacgiang": "Bắc Giang",
  "quang ninh": "Quảng Ninh", "quangninh": "Quảng Ninh",
  "hai duong": "Hải Dương", "haiduong": "Hải Dương",
  "gia lai": "Gia Lai", "gialai": "Gia Lai",
  "dak lak": "Đắk Lắk", "daklak": "Đắk Lắk",
  "dak nong": "Đắk Nông", "daknong": "Đắk Nông",
};

export function toDisplayProvince(raw) {
  return (
    PROVINCE_DISPLAY[raw] ??
    String(raw).replace(/\b\w/g, (c) => c.toUpperCase())
  );
}

function provinceRegex(prov) {
  return new RegExp(`\\b${prov.replace(/ /g, "\\s+")}\\b`);
}

export function findProvinceMentions(normText) {
  const found = [];
  for (const prov of VN_PROVINCES) {
    const m = provinceRegex(prov).exec(normText);
    if (m) {
      found.push({ prov, idx: m.index });
    }
  }
  found.sort((a, b) => a.idx - b.idx);
  return found.map((f) => f.prov);
}

/**
 * Resolve diem_di / diem_den honoring direction markers (từ X đến Y, đến Y từ X).
 */
export function findDirectionedProvinces(rawText) {
  const n = normalize(rawText);
  const found = [];
  for (const prov of VN_PROVINCES) {
    const m = provinceRegex(prov).exec(n);
    if (m) {
      found.push({ prov, idx: m.index });
    }
  }
  if (found.length < 2) {
    if (found.length === 1) {
      const only = found[0];
      const left = n.slice(0, only.idx);
      const leftDest = /\b(den|toi|ve|sang|di|to)\b/.test(left);
      const leftOrigin = /\b(tu|di tu|xuat phat|from)\b/.test(left);
      const display = toDisplayProvince(only.prov);
      return leftDest && !leftOrigin ? [null, display] : [display];
    }
    return [];
  }
  found.sort((a, b) => a.idx - b.idx);
  const first = found[0];
  const second = found[1];

  const left = n.slice(0, first.idx);
  const between = n.slice(first.idx + first.prov.length, second.idx);

  const leftDest = /\b(den|toi|ve|sang)\b/.test(left);
  const betweenOrigin = /\b(tu)\b/.test(between);
  const betweenDest = /\b(den|toi|ve|sang|di)\b/.test(between);
  const leftOrigin = /\b(tu|di tu|xuat phat)\b/.test(left);

  const reverse =
    (leftDest && betweenOrigin) || (leftDest && !leftOrigin && !betweenDest);

  if (reverse) {
    return [toDisplayProvince(second.prov), toDisplayProvince(first.prov)];
  }
  return [toDisplayProvince(first.prov), toDisplayProvince(second.prov)];
}

export function extractVnPhone(normText) {
  const m = String(normText ?? "").match(/\b(0\d{9,10})\b/);
  return m ? m[1] : null;
}

export function extractEmailLoose(normText) {
  const m = String(normText ?? "").match(
    /\b[^\s@]{1,64}@[^\s@]{1,255}\.[^\s@]{2,32}\b/,
  );
  return m ? m[0] : null;
}

export function extractPassword(rawText) {
  const s = String(rawText ?? "");
  const slashM = s.match(/@[^\s/]+\s*\/\s*(\S+)/);
  if (slashM) return slashM[1].replace(/[.,;]+$/, "");
  const kwRe = /(?:m[ậâa]t\s*kh[ẩâa]u|password|pass(?:word)?)[\s:]+/i;
  const anchorM = s.match(kwRe);
  if (!anchorM) return null;
  const afterKw = s.slice(anchorM.index + anchorM[0].length);
  const tokenM = afterKw.match(/^(?:l[aà]\s+|is\s+|are\s+)?(\S+)/i);
  if (tokenM) return tokenM[1].replace(/[.,;]+$/, "");
  return null;
}

export function extractMaNhaXe(normText) {
  const match = normText.match(/\b(nx[a-z0-9_-]{2,})\b/i);
  return match ? match[1].toUpperCase() : null;
}

/**
 * Trích tên nhà xe ở dạng tự do — sau từ khoá "nhà xe" (giữ nguyên dấu).
 *   - Bỏ qua câu hỏi: "nhà xe nào", "nhà xe gì", "nhà xe đó",…
 *   - Cắt đến dấu chấm/hỏi/phẩy, ≤60 ký tự.
 *   - Trả null nếu sau "nhà xe" không có tên (1 từ ngắn không tính).
 *
 * @returns {string|null}
 */
export function extractOperatorNameLoose(rawText) {
  const s = String(rawText ?? "").replace(/\s+/g, " ").trim();
  if (!s) return null;
  const m = s.match(/nh[aà]\s+xe\s+([^\n?.!,;]{2,80})/iu);
  if (!m) return null;
  let name = m[1].trim();
  const STOP_PREFIX = /^(co|có|đang|hiện|của|cho|thuộc|từ|tới|đến|đi|ngày|trên|tuyến|nào|gì|đó|này|kia|khác)(?=\s|$|[^\p{L}\p{N}])/iu;
  if (STOP_PREFIX.test(name)) return null;
  name = name.split(/\s+(?:co|có|đang|hiện|của|cho|thuộc|từ|tới|đến|đi|ngày|trên|tuyến)(?=\s|$|[^\p{L}\p{N}])/iu)[0]?.trim() ?? name;
  name = name.replace(/[.,;:!?]+$/, "").trim();
  if (name.length < 2 || name.length > 80) return null;
  const skipPhrase = /^(nao|nào|gi|gì|do|đó|nay|nay\s+do|kia|khac|khác|tren|trên)$/i;
  if (skipPhrase.test(name)) return null;
  if (/^[a-z0-9_-]{2,}$/i.test(name) && name.toLowerCase().startsWith("nx")) {
    return null;
  }
  return name;
}

export function extractTripId(normText) {
  const m = normText.match(/\b(?:chuyen|trip)\s*(?:so|id|#)?\s*(\d{1,12})\b/);
  return m ? m[1] : null;
}

export function extractBookingId(normText) {
  const m = normText.match(
    /\b(?:booking|dat ve|don dat)\s*(?:so|id|#)?\s*(\d{1,12})\b/,
  );
  return m ? m[1] : null;
}

export function extractMaVe(normText) {
  const m = normText.match(
    /\b(?:ve|ticket|ma ve)\s*(?:so|id|#)?\s*([a-z0-9_-]{2,20})\b/,
  );
  return m ? m[1].toUpperCase() : null;
}

export function extractSeatIds(normText) {
  return Array.from(normText.matchAll(/\b([a-z]\d{1,2})\b/gi)).map((m) =>
    m[1].toUpperCase(),
  );
}

export function extractVoucherCode(rawText) {
  const s = String(rawText ?? "");
  const anchored = s.match(/(?:voucher|ma|code)\s+([A-Z0-9_-]{3,30})\b/i);
  if (anchored) return anchored[1].toUpperCase();
  const upper = s.match(/\b([A-Z][A-Z0-9_-]{3,30})\b/);
  return upper ? upper[1].toUpperCase() : null;
}

export function extractPaymentCode(normText) {
  const m = normText.match(
    /\b(?:thanh toan|payment|ma thanh toan)\s*(?:so|id|#)?\s*([a-z0-9_-]{2,30})\b/,
  );
  return m ? m[1].toUpperCase() : null;
}

export function extractOtpCode(normText) {
  const m = normText.match(/\b(\d{4,8})\b/);
  return m ? m[1] : null;
}

/** True → message looks like finding a trip/date/time/booking (not chỉ có/không tuyến). */
export function normalizedTextWantsTripScheduleOrDate(normalizedText) {
  const n = String(normalizedText ?? "").trim();
  if (!n) return false;

  return (
    /\b(chuyen nao|lich chuyen|tim chuyen|chuyen cu the|gio khoi hanh|ve xe|dat ve)\b/.test(
      n,
    ) ||
    /\b(ngay|hom nay|ngay mai|mai\b|\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2})\b/.test(
      n,
    ) ||
    /\b\d{1,2}\s*thang\s*\d{1,2}\b/.test(n) ||
    /\b(chieu nay|chieu mai|toi nay|sang nay)\b/.test(n) ||
    /\b(morning|afternoon|evening|before|after|between)\b/.test(n) ||
    /\b(departure|schedule|what time|which trip|book (a )?ticket)\b/.test(n) ||
    /\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/.test(n) ||
    /\b\d{1,2}\s*(january|february|march|april|may|june|july|august|september|october|november|december)\b/.test(
      n,
    ) ||
    /\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,4}\b/.test(
      n,
    )
  );
}

/**
 * "Has the user asked about route existence (without date/time)?" → returns
 * {diem_di, diem_den} or null. Used by search_routes pattern.
 */
export function routeExistenceSearchRoutesArgsFromNorm(normalizedText) {
  const n = String(normalizedText ?? "").trim();
  if (!n) return null;

  const asksRouteExistenceVi =
    /\b(co|con)\b.*\btuyen\b/.test(n) ||
    /\b(tim|kiem)\b.*\btuyen\b/.test(n) ||
    /\b(tuyen (xe|duong|khach))\b.*\b(tu|di tu)\b/.test(n) ||
    /\b(co|con)\b.*\b(chay xe|xe (chay|khach))\b.*\b(tu|di tu)\b/.test(n) ||
    /\b(co|con)\b.*\bxe\b.*\b(tu|di tu)\b/.test(n);

  const asksRouteExistenceEn =
    /\b(routes?|buses?|coaches?|line|service)\b/.test(n) &&
    /\bfrom\b/.test(n) &&
    /\bto\b/.test(n);

  if (!asksRouteExistenceVi && !asksRouteExistenceEn) return null;

  if (normalizedTextWantsTripScheduleOrDate(n)) return null;

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
