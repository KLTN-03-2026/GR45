import {
  ACCOUNT_TOOL_SLOTS,
  AUTH_TOOL_SLOTS,
  CUSTOMER_TOOL_SLOTS,
  LOYALTY_TOOL_SLOTS,
  PASSWORD_TOOL_SLOTS,
  REGISTRATION_TOOL_SLOTS,
} from "../slots.js";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function asString(value) {
  return String(value ?? "").trim();
}

function isEmail(value) {
  return EMAIL_RE.test(asString(value));
}

function jsonBody(body) {
  return {
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  };
}

function pickDefined(source, keys) {
  const out = {};

  for (const key of keys) {
    const value = source?.[key];

    if (value !== undefined && value !== null && String(value).trim() !== "") {
      out[key] = value;
    }
  }

  return out;
}

function requireFields(args, fields) {
  const missing = fields.filter((field) => !asString(args?.[field]));

  if (missing.length) {
    return {
      ok: false,
      error: `Thiếu ${missing.join(", ")}.`,
    };
  }

  return null;
}

function getProfile(jsonResult) {
  return jsonResult("profile", {
    method: "GET",
    auth: "bearer",
  });
}

function updateProfile(jsonResult, payload) {
  return jsonResult("profile", {
    method: "PUT",
    auth: "bearer",
    ...jsonBody(payload),
  });
}

function normalizeProfileUpdatePayload(args) {
  return pickDefined(args, [
    "ho_va_ten",
    "email",
    "so_dien_thoai",
    "dia_chi",
    "ngay_sinh",
  ]);
}

function normalizeRegisterPayload(args) {
  return pickDefined(args, [
    "ho_va_ten",
    "so_dien_thoai",
    "email",
    "password",
    "password_confirmation",
    "dia_chi",
    "ngay_sinh",
  ]);
}

function normalizePasswordChangePayload(args) {
  const oldPassword = asString(args.old_password ?? args.mat_khau_cu);
  const password = asString(args.password ?? args.mat_khau_moi);
  const confirmation = asString(
    args.password_confirmation ?? args.mat_khau_moi_confirmation,
  );

  return {
    mat_khau_cu: oldPassword,
    mat_khau_moi: password,
    mat_khau_moi_confirmation: confirmation,
  };
}

function normalizeResetPasswordPayload(args) {
  return {
    role: "khach_hang",
    email: asString(args.email),
    token: asString(args.token),
    mat_khau_moi: asString(args.password ?? args.mat_khau_moi),
    mat_khau_moi_confirmation: asString(
      args.password_confirmation ?? args.mat_khau_moi_confirmation,
    ),
  };
}

function normalizeActivationPayload(args) {
  return {
    email: asString(args.email),
    token: asString(args.token ?? args.otp),
  };
}

function validatePasswordConfirmation(password, confirmation) {
  if (!password || !confirmation) {
    return {
      ok: false,
      error: "Thiếu mật khẩu hoặc xác nhận mật khẩu.",
    };
  }

  if (password !== confirmation) {
    return {
      ok: false,
      error: "Mật khẩu xác nhận không khớp.",
    };
  }

  return null;
}

export function registerAuthAccountTools(ctx) {
  const { clearKhachToken, jsonResult, register, stub, withQuery } = ctx;

  register(
    "auth_login",
    AUTH_TOOL_SLOTS.login,
    "sensitive",
    ["Đăng nhập"],
    async (args) => {
      const email = asString(args.email);
      const password = asString(args.password);

      if (!isEmail(email)) {
        return {
          ok: false,
          error:
            "API /dang-nhap hiện yêu cầu email hợp lệ. SĐT chưa thay thế email trên BE hiện tại.",
        };
      }

      if (!password) {
        return {
          ok: false,
          error: "Thiếu password.",
        };
      }

      return jsonResult("dang-nhap", {
        method: "POST",
        auth: "none",
        persistToken: true,
        ...jsonBody({
          email,
          password,
        }),
      });
    },
  );

  register(
    "auth_logout",
    AUTH_TOOL_SLOTS.logout,
    "safe",
    ["Đăng xuất"],
    async () => {
      const result = await jsonResult("dang-xuat", {
        method: "POST",
        auth: "bearer",
      });

      if (result.ok) {
        clearKhachToken();
        return result;
      }

      return result;
    },
  );

  register(
    "account_get_profile",
    ACCOUNT_TOOL_SLOTS.get_profile,
    "safe",
    ["Hồ sơ"],
    () => getProfile(jsonResult),
  );

  register(
    "account_update_profile",
    ACCOUNT_TOOL_SLOTS.update_profile,
    "sensitive",
    ["Cập nhật hồ sơ"],
    (args) => {
      const payload = normalizeProfileUpdatePayload(args);

      if (Object.keys(payload).length === 0) {
        return Promise.resolve({
          ok: false,
          error: "Không có trường hồ sơ nào để cập nhật.",
        });
      }

      return updateProfile(jsonResult, payload);
    },
  );

  register(
    "account_update_avatar",
    ACCOUNT_TOOL_SLOTS.update_avatar,
    "safe",
    ["Ảnh đại diện"],
    (args) => stub("account_update_avatar", args),
  );

  register(
    "account_update_email",
    ACCOUNT_TOOL_SLOTS.update_email,
    "sensitive",
    ["Đổi email"],
    (args) => {
      const email = asString(args.email);

      if (!isEmail(email)) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      return updateProfile(jsonResult, { email });
    },
  );

  register(
    "account_update_phone",
    ACCOUNT_TOOL_SLOTS.update_phone,
    "sensitive",
    ["Đổi SĐT"],
    (args) => {
      const soDienThoai = asString(args.so_dien_thoai);

      if (!soDienThoai) {
        return Promise.resolve({
          ok: false,
          error: "Thiếu số điện thoại.",
        });
      }

      return updateProfile(jsonResult, {
        so_dien_thoai: soDienThoai,
      });
    },
  );

  register(
    "password_change_password",
    PASSWORD_TOOL_SLOTS.change_password,
    "sensitive",
    ["Đổi mật khẩu"],
    (args) => {
      const payload = normalizePasswordChangePayload(args);

      const missing = requireFields(payload, [
        "mat_khau_cu",
        "mat_khau_moi",
        "mat_khau_moi_confirmation",
      ]);

      if (missing) return Promise.resolve(missing);

      const confirmationError = validatePasswordConfirmation(
        payload.mat_khau_moi,
        payload.mat_khau_moi_confirmation,
      );

      if (confirmationError) {
        return Promise.resolve(confirmationError);
      }

      return jsonResult("doi-mat-khau", {
        method: "POST",
        auth: "bearer",
        ...jsonBody(payload),
      });
    },
  );

  register(
    "password_forgot_password",
    PASSWORD_TOOL_SLOTS.forgot_password,
    "safe",
    ["Quên mật khẩu", "Đặt lại mật khẩu"],
    (args) => {
      const email = asString(args.email);

      if (!isEmail(email)) {
        return Promise.resolve({
          ok: false,
          error:
            "BE hiện yêu cầu email để quên mật khẩu. Vui lòng nhập email hợp lệ.",
        });
      }

      return jsonResult("quen-mat-khau", {
        method: "POST",
        auth: "none",
        ...jsonBody({
          role: "khach_hang",
          email,
        }),
      });
    },
  );

  register(
    "password_reset_password",
    PASSWORD_TOOL_SLOTS.reset_password,
    "sensitive",
    ["Đặt lại mật khẩu", "Đổi mật khẩu"],
    (args) => {
      const payload = normalizeResetPasswordPayload(args);

      const missing = requireFields(payload, [
        "email",
        "token",
        "mat_khau_moi",
        "mat_khau_moi_confirmation",
      ]);

      if (missing) return Promise.resolve(missing);

      if (!isEmail(payload.email)) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      const confirmationError = validatePasswordConfirmation(
        payload.mat_khau_moi,
        payload.mat_khau_moi_confirmation,
      );

      if (confirmationError) {
        return Promise.resolve(confirmationError);
      }

      return jsonResult("dat-lai-mat-khau", {
        method: "POST",
        auth: "none",
        ...jsonBody(payload),
      });
    },
  );

  register(
    "password_verify_reset_token",
    PASSWORD_TOOL_SLOTS.verify_reset_token,
    "safe",
    ["Xác thực OTP", "Đặt lại mật khẩu"],
    (args) => stub("password_verify_reset_token", args),
  );

  register(
    "registration_register_account",
    REGISTRATION_TOOL_SLOTS.register_account,
    "sensitive",
    ["Đăng ký", "Xác thực OTP"],
    (args) => {
      const payload = normalizeRegisterPayload(args);

      const missing = requireFields(payload, [
        "ho_va_ten",
        "so_dien_thoai",
        "password",
        "password_confirmation",
      ]);

      if (missing) return Promise.resolve(missing);

      const confirmationError = validatePasswordConfirmation(
        asString(payload.password),
        asString(payload.password_confirmation),
      );

      if (confirmationError) {
        return Promise.resolve(confirmationError);
      }

      if (payload.email && !isEmail(payload.email)) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      return jsonResult("dang-ky", {
        method: "POST",
        auth: "none",
        ...jsonBody(payload),
      });
    },
  );

  const activateAccountRequest = (args) => {
    const payload = normalizeActivationPayload(args);

    const missing = requireFields(payload, ["email", "token"]);
    if (missing) return Promise.resolve(missing);

    if (!isEmail(payload.email)) {
      return Promise.resolve({
        ok: false,
        error: "Email không hợp lệ.",
      });
    }

    return jsonResult("kich-hoat-tai-khoan", {
      method: "POST",
      auth: "none",
      ...jsonBody(payload),
    });
  };

  register(
    "registration_verify_otp",
    REGISTRATION_TOOL_SLOTS.verify_otp,
    "safe",
    ["Xác thực OTP", "Đăng ký"],
    activateAccountRequest,
  );

  register(
    "registration_activate_account",
    REGISTRATION_TOOL_SLOTS.activate_account,
    "safe",
    ["Kích hoạt tài khoản", "Đăng nhập"],
    activateAccountRequest,
  );

  register(
    "customer_get_customer_info",
    CUSTOMER_TOOL_SLOTS.get_customer_info,
    "safe",
    ["Thông tin khách hàng", "Hồ sơ"],
    () => getProfile(jsonResult),
  );

  register(
    "customer_get_account_status",
    CUSTOMER_TOOL_SLOTS.get_account_status,
    "safe",
    ["Trạng thái tài khoản", "Hồ sơ"],
    async () => {
      const result = await getProfile(jsonResult);

      if (!result.ok) return result;

      const profile = result.data?.data ?? result.data;

      return {
        ok: true,
        data: {
          success: true,
          account_status: {
            id: profile?.id,
            email: profile?.email,
            so_dien_thoai: profile?.so_dien_thoai,
            tinh_trang: profile?.tinh_trang,
            trang_thai: profile?.trang_thai,
            da_kich_hoat: profile?.da_kich_hoat,
          },
          raw: profile,
        },
      };
    },
  );

  register(
    "customer_get_booking_history",
    CUSTOMER_TOOL_SLOTS.get_booking_history,
    "safe",
    ["Lịch sử đặt vé", "Vé của tôi"],
    (args) =>
      jsonResult(
        withQuery("ve", {
          from_date: args.from_date,
          to_date: args.to_date,
          ticket_status: args.ticket_status,
        }),
        {
          method: "GET",
          auth: "bearer",
        },
      ),
  );

  register(
    "customer_get_transaction_history",
    CUSTOMER_TOOL_SLOTS.get_transaction_history,
    "safe",
    ["Lịch sử giao dịch", "Thanh toán"],
    (args) => stub("customer_get_transaction_history", args),
  );

  register(
    "loyalty_get_current_points",
    LOYALTY_TOOL_SLOTS.get_current_points,
    "safe",
    ["Điểm thưởng", "Đổi điểm thưởng", "Hạng thành viên"],
    () =>
      jsonResult("diem-thanh-vien", {
        method: "GET",
        auth: "bearer",
      }),
  );

  register(
    "loyalty_redeem_points",
    LOYALTY_TOOL_SLOTS.redeem_points,
    "sensitive",
    ["Đổi điểm thưởng", "Voucher khả dụng"],
    (args) => stub("loyalty_redeem_points", args),
  );

  register(
    "loyalty_get_points_history",
    LOYALTY_TOOL_SLOTS.get_points_history,
    "safe",
    ["Lịch sử điểm", "Điểm thưởng"],
    (args) =>
      jsonResult(
        withQuery("lich-su-diem", {
          from_date: args.from_date,
          to_date: args.to_date,
          loai_giao_dich: args.loai_giao_dich,
        }),
        {
          method: "GET",
          auth: "bearer",
        },
      ),
  );

  register(
    "loyalty_get_membership_tier",
    LOYALTY_TOOL_SLOTS.get_membership_tier,
    "safe",
    ["Hạng thành viên", "Điểm thưởng"],
    async () => {
      const result = await jsonResult("diem-thanh-vien", {
        method: "GET",
        auth: "bearer",
      });

      if (!result.ok) return result;

      const payload = result.data?.data ?? result.data;

      return {
        ok: true,
        data: {
          success: true,
          membership_tier:
            payload?.hang_thanh_vien ??
            payload?.tier ??
            payload?.cap_bac ??
            null,
          raw: payload,
        },
      };
    },
  );
}