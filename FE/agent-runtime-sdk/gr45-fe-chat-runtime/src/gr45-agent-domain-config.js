/**
 * Không dùng trực tiếp `MONEY_RISK_RE` mặc định: `\bdoi\b` khớp cả chữ "đổi" trong
 * "chuyển đổi phương tiện" (nghiên cứu / khảo sát GTCC) → nhầm risk_sensitive → planner.
 */
const GR45_MONEY_RISK_RE =
  /(\b(refund|hoan tien|cancel|huy|change|payment|thanh toan|invoice|hoa don|chuyen khoan)\b)|((?<!\bchuyen\s)\bdoi\b)/i;

export const GR45_INTENT_CLASSIFIER_OPTIONS = {
  moneyRiskPattern: GR45_MONEY_RISK_RE,
  operationalPatterns: [
    /\b(tuyen|tim tuyen|tim chuyen(?!\s+doi\b)|tim xe|co xe|xe khach|chuyen(?!\s+doi\b)|lich xe|gio xe|ve xe|dat ve|diem don|don tai|tram dung|kiem tra ve|chi tiet ve|trang thai ve|xem ve|thong tin ve|tra cuu ve|tra cuu|ma ve|so ve|ve cua toi|danh sach ve|lich su dat ve|ghe|chon ghe|nha xe|thanh toan|huy ve|doi ve|hoan tien|tracking|vi tri xe|ho so|tai khoan|dang nhap|voucher|ho tro|lien he|lien lac|gap admin|gap nha xe|tong dai|tu van)\b/i,
    /\b(toi muon di|i want to travel|find me .*?\b(bus|coach|trip)\b|i need .*?\b(bus|coach|trip)\b|buses?\b|coach(?:es)?\b|bus service\b|routes?\b|operator\b|operated by\b)\b/i,
  ],
  followUpOperationalPatterns: [
    /\b(ngay|buoi sang|buoi chieu|buoi toi|truoc|sau|di tu|tu|den|toi|ve|morning|afternoon|evening|before|after|between|may|january|february|march|april|june|july|august|september|october|november|december)\b/i,
  ],
  shortOperationalPatterns: [
    /\b(vx|bs|bv|tk)[a-z0-9]{2,}\b/i,
    /\btra cuu\b/i,
    /\b(ma ve|so ve)\b/i,
  ],
};

export const GR45_SUGGESTION_INTENT_GROUPS = [
  {
    re: /tim|kiem|chuyen|tuyen|lich|gio|nha xe|limousine|ve xe|xe khach/,
    label: /chuyen|tuyen|ve|ghe|nha xe|lich|gio|tim|loc|seat/,
  },
  {
    re: /ve cua toi|lich su|booking|dat ve|huy ve|doi ve/,
    label: /ve|huy|doi|hoan|booking|dat|lich su/,
  },
  {
    re: /thanh toan|payment|hoa don|chuyen khoan/,
    label: /thanh toan|payment|hoa don|voucher/,
  },
  { re: /hoan tien|refund|huy/, label: /hoan|refund|huy/ },
  {
    re: /voucher|ma giam|khuyen mai|giam gia/,
    label: /voucher|giam|khuyen mai/,
  },
  {
    re: /diem (thuong|tich luy|hang)|loyalty/,
    label: /diem|thuong|loyalty|hang|doi diem/,
  },
  {
    re: /ho tro|nhan vien|admin|lien he|tu van|tong dai|live support/,
    label: /ho tro|admin|nhan vien|lien he|tu van/,
  },
  {
    re: /tai khoan|ho so|profile|dang nhap|dang xuat|cap nhat|email|sdt|so dien thoai|anh dai dien/,
    label: /tai khoan|ho so|email|sdt|profile|dang nhap|dang xuat|anh dai dien|avatar|cap nhat/,
  },
  {
    re: /vi tri|tracking|xe (dang )?o dau|theo doi|xe da toi/,
    label: /tracking|vi tri|theo doi|toa do/,
  },
  {
    re: /diem don|diem tra|tram dung|gan day|nearest|nearby/,
    label: /diem don|diem tra|tram|gan|nearby/,
  },
];

export const GR45_PINNED_SUGGESTION_LABELS = [
  "Tìm tuyến khác",
  "Tìm chuyến khác",
  "Đặt vé",
  "Liên hệ hỗ trợ",
];

export const GR45_SUPPORT_SUGGESTION_LABEL = "Liên hệ hỗ trợ";
