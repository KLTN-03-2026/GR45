/**
 * Nhãn người gửi hiển thị trong bubble live support (khách ↔ admin/nhà xe hoặc nhà xe ↔ BusSafe).
 */


export function liveSupportBubbleSenderLabelCustomerThread(session, msg) {
  if (!msg) return "";
  const role = msg.role;
  if (role === "assistant") {
    const n = msg.admin_name && String(msg.admin_name).trim();
    return n || "Trợ lý AI";
  }
  if (role === "admin") {
    const n = msg.admin_name && String(msg.admin_name).trim();
    return n || "Hỗ trợ";
  }
  const d = session || {};
  return (
    (d.customer_display_name && String(d.customer_display_name).trim()) ||
    (d.khach_hang?.ho_va_ten && String(d.khach_hang.ho_va_ten).trim()) ||
    (d.guest_name && String(d.guest_name).trim()) ||
    (d.guest_email && String(d.guest_email).trim()) ||
    (d.guest_phone && String(d.guest_phone).trim()) ||
    "Khách hàng"
  );
}


export function liveSupportBubbleSenderLabelBusafeThread(session, msg) {
  if (!msg) return "";
  if (msg.role === "assistant") {
    const n = msg.admin_name && String(msg.admin_name).trim();
    return n || "Trợ lý AI";
  }
  if (msg.role === "admin") {
    const n = msg.admin_name && String(msg.admin_name).trim();
    return n || "BusSafe";
  }
  const d = session || {};
  return (
    (d.operator_display_name && String(d.operator_display_name).trim()) ||
    (d.nha_xe?.ten_nha_xe && String(d.nha_xe.ten_nha_xe).trim()) ||
    "Nhà xe"
  );
}

/**
 * Modifier classes (.bubble-sender-line--*) — màu trong assets/main.css
 */
export function liveSupportBubbleSenderLineClassCustomer(msg) {
  if (!msg) return "";
  if (msg.role === "admin") return "bubble-sender-line--on-solid-messenger";
  if (msg.role === "assistant") return "bubble-sender-line--on-ai";
  return "bubble-sender-line--on-light";
}

/** Bubble nhà xe (#0084ff) và bubble BusSafe trắng — mapping modifier giống customer nhánh vai trò */
export function liveSupportBubbleSenderLineClassBusafe(msg) {
  if (!msg) return "";
  if (msg.role === "user") return "bubble-sender-line--on-solid-messenger";
  if (msg.role === "assistant") return "bubble-sender-line--on-ai";
  return "bubble-sender-line--on-light";
}
