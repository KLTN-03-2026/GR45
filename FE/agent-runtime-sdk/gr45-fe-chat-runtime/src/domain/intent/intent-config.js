/**
 * Không dùng trực tiếp `MONEY_RISK_RE` mặc định: `\bdoi\b` khớp cả chữ "đổi" trong
 * "chuyển đổi phương tiện" (nghiên cứu / khảo sát GTCC) → nhầm risk_sensitive → planner.
 */
const GR45_MONEY_RISK_RE =
  /(\b(refund|hoan tien|cancel|huy|change|payment|thanh toan|invoice|hoa don|chuyen khoan)\b)|((?<!\bchuyen\s)\bdoi\b)/i;

export const GR45_INTENT_CLASSIFIER_OPTIONS = {
  moneyRiskPattern: GR45_MONEY_RISK_RE,
  operationalPatterns: [
    /\b(tuyen|tim tuyen|tim chuyen(?!\s+doi\b)|tim xe|co xe|xe khach|chuyen(?!\s+doi\b)|lich xe|gio xe|ve xe|dat ve|diem don|don tai|tram dung|kiem tra ve|chi tiet ve|trang thai ve|xem ve|thong tin ve|tra cuu ve|tra cuu|ma ve|so ve|ve cua toi|danh sach ve|lich su dat ve|ghe|chon ghe|nha xe|thanh toan|huy ve|doi ve|hoan tien|tracking|vi tri xe|ho so|tai khoan|dang nhap|dang xuat|voucher|ho tro|lien he|lien lac|gap admin|gap nha xe|tong dai|tu van|diem thuong|diem tich luy|diem cua toi|hang thanh vien|hang bac|hang vang|hang kim cuong|loyalty|doi mat khau|quen mat khau|doi chuyen|lich su don hang)\b/i,
    /\b(toi muon di|i want to travel|find me .*?\b(bus|coach|trip)\b|i need .*?\b(bus|coach|trip)\b|buses?\b|coach(?:es)?\b|bus service\b|routes?\b|operator\b|operated by\b)\b/i,
  ],
  followUpOperationalPatterns: [
    /\b(ngay|buoi sang|buoi chieu|buoi toi|truoc|sau|di tu|tu|den|toi|ve|morning|afternoon|evening|before|after|between|may|january|february|march|april|june|july|august|september|october|november|december)\b/i,
  ],
  shortOperationalPatterns: [
    /\b(vx|bs|bv|tk)[a-z0-9]{2,}\b/i,
    /\btra cuu\b/i,
    /\b(ma ve|so ve)\b/i,
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/i,
    /^\d{6,128}$/i,
  ],
};
