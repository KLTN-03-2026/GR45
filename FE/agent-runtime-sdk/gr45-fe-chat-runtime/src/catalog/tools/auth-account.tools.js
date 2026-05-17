import {
  extractEmailLoose,
  extractOtpCode,
  extractPassword,
  extractVnPhone,
  normalize,
} from "../../domain/planner/text-utils.js";
import {
  ACCOUNT_TOOL_SLOTS,
  AUTH_TOOL_SLOTS,
  CUSTOMER_TOOL_SLOTS,
  LOYALTY_TOOL_SLOTS,
  PASSWORD_TOOL_SLOTS,
  REGISTRATION_TOOL_SLOTS,
} from "../slots/auth-account.slots.js";

export function registerAuthAccountTools(ctx) {
  const { clearKhachToken, jsonResult, register, stub, withQuery } = ctx;

  register(
    "auth_login",
    AUTH_TOOL_SLOTS.login,
    "sensitive",
    ["Đăng nhập"],
    async (args) => {
      const email = String(args.email == null ? "" : args.email).trim();
      const password = String(
        args.password == null ? "" : args.password,
      ).trim();

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return {
          ok: false,
          data: {
            success: false,
            invalid_email_format: true,
            error: email
              ? `Email "${email}" không đúng định dạng. Vui lòng nhập email dạng name@example.com.`
              : "Bạn chưa cung cấp email. Vui lòng cho biết email đã đăng ký.",
          },
          error: "Invalid email format.",
        };
      }

      if (!password) {
        return {
          ok: false,
          data: {
            success: false,
            missing_password: true,
            error: "Bạn chưa cung cấp mật khẩu để đăng nhập.",
          },
          error: "Thiếu password.",
        };
      }

      const result = await jsonResult("dang-nhap", {
        method: "POST",
        auth: "none",
        persistToken: true,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email,
          password,
        }),
      });

      if (result.ok) {
        const body = result.data ?? {};
        const khach = body?.data?.khach_hang ?? body?.khach_hang ?? null;
        return {
          ok: true,
          data: {
            success: true,
            login_success: true,
            khach_hang: khach
              ? {
                  id: khach.id ?? null,
                  ho_va_ten: khach.ho_va_ten ?? null,
                  email: khach.email ?? null,
                }
              : null,
            message: "Đăng nhập thành công.",
          },
        };
      }

      return {
        ok: false,
        data: {
          success: false,
          auth_failed: true,
          email,
          error:
            typeof result.error === "string"
              ? `Đăng nhập thất bại: ${result.error}. Vui lòng kiểm tra lại email và mật khẩu.`
              : "Đăng nhập thất bại. Vui lòng kiểm tra lại email và mật khẩu.",
        },
        error: result.error,
      };
    },
    {
      test: (n, rawText, recentUserMessages) => {
        if (
          /\b(dang nhap|dang nhap vao|log in|log me in|login|sign in|signin)\b/.test(
            n,
          )
        ) {
          return true;
        }

        if (
          /\b(dang xuat|logout|sign out|quen mat khau|dat lai mat khau|doi mat khau|dang ky|kich hoat|ho so|profile|tai khoan|lich su|diem thuong|hang thanh vien|cap nhat)\b/.test(
            n,
          )
        ) {
          return false;
        }

        if (!Array.isArray(recentUserMessages) || recentUserMessages.length < 2) {
          return false;
        }

        const prior = recentUserMessages.slice(0, -1);
        const hadLoginIntent = prior.some((m) =>
          /\b(dang nhap|log in|login|sign in|signin|email da dang ky|mat khau de dang nhap|mat khau)\b/.test(
            normalize(m?.content ?? m),
          ),
        );
        if (!hadLoginIntent) return false;

        const raw = String(rawText ?? "").trim();
        const isBareEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw);
        if (isBareEmail) return true;
        const priorHasEmail = prior.some((m) =>
          (m?.role == null || m.role === "user") &&
          extractEmailLoose(normalize(m?.content ?? m)),
        );
        const isBarePassword = /^[^\s]{3,128}$/.test(raw) && !raw.includes("@");
        const priorNeedsEmail = recentUserMessages.some((m) =>
          normalize(m?.content ?? m).includes("chua cung cap email"),
        );
        if (priorNeedsEmail && isBarePassword) return true;
        return priorHasEmail && isBarePassword;
      },
      build: (n, _today, rawText, recentUserMessages) => {
        const args = {};
        const phone = extractVnPhone(n);
        let email = extractEmailLoose(n);
        const raw = String(rawText ?? "").trim();
        const explicitEmail = raw.match(/\bemail\s+([^\s,;]+)/i)?.[1];
        if (!email && explicitEmail) email = explicitEmail.replace(/[.,;]+$/, "");
        if (!email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw)) email = raw;
        if (phone) args.so_dien_thoai = phone;
        if (email) args.email = email;
        let pwd = extractPassword(raw || n);
        if (pwd) args.password = pwd;
        const explicitLoginTurn =
          /\b(dang nhap|dang nhap vao|log in|login|sign in|signin)\b/.test(n);

        // Multi-turn login: check if this is a continuation from prior "đăng nhập" intent
        const AUTH_HISTORY_RE =
          /\b(dang nhap|dang ky|log in|login|sign in|signin|mat khau|password)\b/;
        if (
          !explicitLoginTurn &&
          Array.isArray(recentUserMessages) &&
          recentUserMessages.length >= 2
        ) {
          const prior = recentUserMessages.slice(0, -1);
          const hadLoginIntent = prior.some((m) =>
            AUTH_HISTORY_RE.test(normalize(m?.content ?? m)),
          );

          if (hadLoginIntent) {
            // If no email extracted yet, scan prior messages
            if (!email) {
              for (const msg of prior) {
                if (msg?.role != null && msg.role !== "user") continue;
                const extracted = extractEmailLoose(normalize(msg?.content ?? msg));
                if (extracted) {
                  email = extracted;
                  break;
                }
              }
            }
            // If no password extracted yet, scan prior messages
            if (!pwd) {
              for (const msg of prior) {
                if (msg?.role != null && msg.role !== "user") continue;
                const extracted = extractPassword(msg?.content ?? msg);
                if (extracted) {
                  pwd = extracted;
                  break;
                }
              }
            }

            if (!pwd && email && !raw.includes("@")) {
              const rawEmail = extractEmailLoose(normalize(raw));
              if (
                !rawEmail &&
                /^[^\s]{3,128}$/.test(raw) &&
                !/\b(dang xuat|logout|quen mat khau|dat lai mat khau|doi mat khau|dang ky|kich hoat|ho so|profile|tai khoan|lich su|diem thuong|hang thanh vien|cap nhat)\b/.test(
                  n,
                )
              ) {
                pwd = raw;
              }
            }

            if (!email && !pwd && /^[^\s]{2,128}$/.test(raw)) {
              const priorNeedsEmail = recentUserMessages.some((m) =>
                normalize(m?.content ?? m).includes("chua cung cap email"),
              );
              if (priorNeedsEmail) email = raw;
            }
          }
        }

        if (email) args.email = email;
        if (pwd) args.password = pwd;
        return {
          toolName: "auth_login",
          rationale: "Khách muốn đăng nhập.",
          arguments: args,
        };
      },
      suggestions: (result) => {
        const data = result?.data ?? {};
        if (result?.ok === true && data.login_success === true) {
          return [
            { text: "Tìm chuyến xe", action: "", params: {} },
            {
              text: "Xem vé của tôi",
              action: "open_tickets",
              params: { path: "/lich-su-dat-ve" },
            },
            { text: "Kiểm tra tuyến xe", action: "", params: {} },
          ];
        }
        if (data.invalid_email_format === true || data.missing_password === true) {
          return [
            { text: "Đăng nhập", action: "login", params: {} },
            { text: "Quên mật khẩu", action: "", params: {} },
          ];
        }
        if (data.auth_failed === true) {
          return [
            { text: "Thử lại", action: "", params: {} },
            { text: "Quên mật khẩu", action: "", params: {} },
            { text: "Liên hệ hỗ trợ", action: "", params: {} },
          ];
        }
        return [];
      },
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
    {
      test: (n) => /\b(dang xuat|thoat tai khoan|logout|sign out)\b/.test(n),
      build: () => ({
        toolName: "auth_logout",
        rationale: "Khách yêu cầu đăng xuất.",
        arguments: {},
      }),
    },
  );

  register(
    "account_get_profile",
    ACCOUNT_TOOL_SLOTS.get_profile,
    "safe",
    ["Hồ sơ"],
    () =>
      jsonResult("profile", {
        method: "GET",
        auth: "bearer",
      }),
    {
      test: (n) =>
        /\b(tai khoan cua toi|ho so cua toi|thong tin (ca nhan|tai khoan|ho so)|xem (profile|ho so|tai khoan))\b/.test(
          n,
        ),
      build: () => ({
        toolName: "account_get_profile",
        rationale: "Khách xem thông tin tài khoản.",
        arguments: {},
      }),
    },
  );

  register(
    "account_update_profile",
    ACCOUNT_TOOL_SLOTS.update_profile,
    "sensitive",
    ["Cập nhật hồ sơ"],
    (args) => {
      const payload = {};
      for (const key of [
        "ho_va_ten",
        "email",
        "so_dien_thoai",
        "dia_chi",
        "ngay_sinh",
      ]) {
        const value = args?.[key];
        if (
          value !== undefined &&
          value !== null &&
          String(value).trim() !== ""
        ) {
          payload[key] = value;
        }
      }

      if (Object.keys(payload).length === 0) {
        return Promise.resolve({
          ok: false,
          error: "Không có trường hồ sơ nào để cập nhật.",
        });
      }

      return jsonResult("profile", {
        method: "PUT",
        auth: "bearer",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        /\b(doi (ten|ho ten)|cap nhat (ten|ho ten)|thay (ten|ho ten))\b/.test(
          n,
        ),
      build: (_n, _t, rawText) => {
        const args = {};
        const m = String(rawText ?? "").match(
          /(?:thành|sang|to)\s+([\p{L}\s]{2,60})$/u,
        );
        if (m) args.ho_va_ten = m[1].trim();
        return {
          toolName: "account_update_profile",
          rationale: "Khách đổi họ tên.",
          arguments: args,
        };
      },
    },
  );

  register(
    "account_update_avatar",
    ACCOUNT_TOOL_SLOTS.update_avatar,
    "safe",
    ["Ảnh đại diện"],
    (args) => stub("account_update_avatar", args),
    {
      test: (n) =>
        /\b(doi (anh dai dien|avatar)|cap nhat (anh dai dien|avatar)|thay avatar)\b/.test(
          n,
        ),
      build: () => ({
        toolName: "account_update_avatar",
        rationale: "Khách đổi ảnh đại diện.",
        arguments: {},
      }),
    },
  );

  register(
    "account_update_email",
    ACCOUNT_TOOL_SLOTS.update_email,
    "sensitive",
    ["Đổi email"],
    (args) => {
      const email = String(args.email == null ? "" : args.email).trim();

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      return jsonResult("profile", {
        method: "PUT",
        auth: "bearer",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });
    },
    {
      test: (n) => /\b(doi email|cap nhat email|thay email)\b/.test(n),
      build: (n) => {
        const args = {};
        const email = extractEmailLoose(n);
        if (email) args.email = email;
        return {
          toolName: "account_update_email",
          rationale: "Khách đổi email.",
          arguments: args,
        };
      },
    },
  );

  register(
    "account_update_phone",
    ACCOUNT_TOOL_SLOTS.update_phone,
    "sensitive",
    ["Đổi SĐT"],
    (args) => {
      const soDienThoai = String(
        args.so_dien_thoai == null ? "" : args.so_dien_thoai,
      ).trim();

      if (!soDienThoai) {
        return Promise.resolve({
          ok: false,
          error: "Thiếu số điện thoại.",
        });
      }

      return jsonResult("profile", {
        method: "PUT",
        auth: "bearer",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          so_dien_thoai: soDienThoai,
        }),
      });
    },
    {
      test: (n) =>
        /\b(doi (so dien thoai|sdt|so dt)|cap nhat (so dien thoai|sdt))\b/.test(
          n,
        ),
      build: (n) => {
        const args = {};
        const phone = extractVnPhone(n);
        if (phone) args.so_dien_thoai = phone;
        return {
          toolName: "account_update_phone",
          rationale: "Khách đổi số điện thoại.",
          arguments: args,
        };
      },
    },
  );

  register(
    "password_change_password",
    PASSWORD_TOOL_SLOTS.change_password,
    "sensitive",
    ["Đổi mật khẩu"],
    (args) => {
      const payload = {
        mat_khau_cu: String(
          args.old_password == null ? args.mat_khau_cu : args.old_password,
        ).trim(),
        mat_khau_moi: String(
          args.password == null ? args.mat_khau_moi : args.password,
        ).trim(),
        mat_khau_moi_confirmation: String(
          args.password_confirmation == null
            ? args.mat_khau_moi_confirmation
            : args.password_confirmation,
        ).trim(),
      };
      const missing = [
        "mat_khau_cu",
        "mat_khau_moi",
        "mat_khau_moi_confirmation",
      ].filter(
        (field) => !String(payload[field] == null ? "" : payload[field]).trim(),
      );
      if (missing.length) {
        return Promise.resolve({
          ok: false,
          error: `Thiếu ${missing.join(", ")}.`,
        });
      }
      if (!payload.mat_khau_moi || !payload.mat_khau_moi_confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Thiếu mật khẩu hoặc xác nhận mật khẩu.",
        });
      }
      if (payload.mat_khau_moi !== payload.mat_khau_moi_confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Mật khẩu xác nhận không khớp.",
        });
      }

      return jsonResult("doi-mat-khau", {
        method: "POST",
        auth: "bearer",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        /\b(doi mat khau|thay mat khau|change password|cap nhat mat khau)\b/.test(
          n,
        ),
      build: (_n, _today, rawText) => {
        const text = String(rawText == null ? "" : rawText);
        const oldPassword = text.match(/(?:cũ|cu|old)\s+([^\s,;]+)/i)?.[1];
        const newPassword = text.match(
          /(?:mới|moi|new)\s+([^\s,;]+)/i,
        )?.[1];
        const confirmation = text.match(
          /(?:xác nhận|xac nhan|confirm)\s+([^\s,;]+)/i,
        )?.[1];
        const args = {};
        if (oldPassword) args.old_password = oldPassword;
        if (newPassword) args.password = newPassword;
        if (confirmation) args.password_confirmation = confirmation;
        return {
          toolName: "password_change_password",
          rationale: "Khách đổi mật khẩu.",
          arguments: args,
        };
      },
    },
  );

  register(
    "password_forgot_password",
    PASSWORD_TOOL_SLOTS.forgot_password,
    "safe",
    ["Quên mật khẩu", "Đặt lại mật khẩu"],
    (args) => {
      const email = String(args.email == null ? "" : args.email).trim();

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return Promise.resolve({
          ok: false,
          error:
            "BE hiện yêu cầu email để quên mật khẩu. Vui lòng nhập email hợp lệ.",
        });
      }

      return jsonResult("quen-mat-khau", {
        method: "POST",
        auth: "none",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          role: "khach_hang",
          email,
        }),
      });
    },
    {
      test: (n) =>
        /\b(quen mat khau|forgot password|khoi phuc mat khau)\b/.test(n) &&
        !/\b(dat lai mat khau|reset password)\b/.test(n),
      build: (n) => {
        const args = {};
        const phone = extractVnPhone(n);
        const email = extractEmailLoose(n);
        if (phone) args.so_dien_thoai = phone;
        if (email) args.email = email;
        return {
          toolName: "password_forgot_password",
          rationale: "Khách quên mật khẩu.",
          arguments: args,
        };
      },
    },
  );

  register(
    "password_reset_password",
    PASSWORD_TOOL_SLOTS.reset_password,
    "sensitive",
    ["Đặt lại mật khẩu", "Đổi mật khẩu"],
    (args) => {
      const payload = {
        role: "khach_hang",
        email: String(args.email == null ? "" : args.email).trim(),
        token: String(args.token == null ? "" : args.token).trim(),
        mat_khau_moi: String(
          args.password == null ? args.mat_khau_moi : args.password,
        ).trim(),
        mat_khau_moi_confirmation: String(
          args.password_confirmation == null
            ? args.mat_khau_moi_confirmation
            : args.password_confirmation,
        ).trim(),
      };
      const missing = [
        "email",
        "token",
        "mat_khau_moi",
        "mat_khau_moi_confirmation",
      ].filter(
        (field) => !String(payload[field] == null ? "" : payload[field]).trim(),
      );
      if (missing.length) {
        return Promise.resolve({
          ok: false,
          error: `Thiếu ${missing.join(", ")}.`,
        });
      }

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      if (!payload.mat_khau_moi || !payload.mat_khau_moi_confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Thiếu mật khẩu hoặc xác nhận mật khẩu.",
        });
      }
      if (payload.mat_khau_moi !== payload.mat_khau_moi_confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Mật khẩu xác nhận không khớp.",
        });
      }

      return jsonResult("dat-lai-mat-khau", {
        method: "POST",
        auth: "none",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        /\b(dat lai mat khau|reset password|tao mat khau moi)\b/.test(n),
      build: (n, _today, rawText) => {
        const text = String(rawText == null ? "" : rawText);
        const args = {};
        const email = extractEmailLoose(n);
        const token = text.match(/(?:token|mã|ma)\s+([^\s,;]+)/i)?.[1];
        const password = text.match(
          /(?:mật khẩu mới|mat khau moi|password new|new password)\s+([^\s,;]+)/i,
        )?.[1];
        const confirmation = text.match(
          /(?:xác nhận|xac nhan|confirm)\s+([^\s,;]+)/i,
        )?.[1];
        if (email) args.email = email;
        if (token) args.token = token;
        if (password) args.password = password;
        if (confirmation) args.password_confirmation = confirmation;
        return {
          toolName: "password_reset_password",
          rationale: "Khách đặt lại mật khẩu.",
          arguments: args,
        };
      },
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
      const payload = {};
      for (const key of [
        "ho_va_ten",
        "so_dien_thoai",
        "email",
        "password",
        "password_confirmation",
        "dia_chi",
        "ngay_sinh",
      ]) {
        const value = args?.[key];
        if (
          value !== undefined &&
          value !== null &&
          String(value).trim() !== ""
        ) {
          payload[key] = value;
        }
      }
      const missing = [
        "ho_va_ten",
        "so_dien_thoai",
        "password",
        "password_confirmation",
      ].filter(
        (field) => !String(payload[field] == null ? "" : payload[field]).trim(),
      );
      if (missing.length) {
        return Promise.resolve({
          ok: false,
          error: `Thiếu ${missing.join(", ")}.`,
        });
      }

      const password = String(
        payload.password == null ? "" : payload.password,
      ).trim();
      const confirmation = String(
        payload.password_confirmation == null
          ? ""
          : payload.password_confirmation,
      ).trim();
      if (!password || !confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Thiếu mật khẩu hoặc xác nhận mật khẩu.",
        });
      }
      if (password !== confirmation) {
        return Promise.resolve({
          ok: false,
          error: "Mật khẩu xác nhận không khớp.",
        });
      }

      if (
        payload.email &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(payload.email).trim())
      ) {
        return Promise.resolve({
          ok: false,
          error: "Email không hợp lệ.",
        });
      }

      return jsonResult("dang-ky", {
        method: "POST",
        auth: "none",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        /\b(dang ky tai khoan|dang ky moi|tao tai khoan|sign up|signup|register)\b/.test(
          n,
        ),
      build: (n, _today, rawText) => {
        const args = {};
        const text = String(rawText == null ? "" : rawText);
        const phone = extractVnPhone(n);
        const email = extractEmailLoose(n);
        const name = text.match(
          /(?:tên|ten|họ tên|ho ten)\s+(.+?)\s+(?:số điện thoại|so dien thoai|sdt|email|mật khẩu|mat khau)/iu,
        )?.[1];
        if (phone) args.so_dien_thoai = phone;
        if (email) args.email = email;
        const pwd = extractPassword(rawText ?? n);
        if (pwd) args.password = pwd;
        const confirmation = text.match(
          /(?:xác nhận|xac nhan|confirm)\s+([^\s,;]+)/i,
        )?.[1];
        if (name) args.ho_va_ten = name.trim();
        if (confirmation) args.password_confirmation = confirmation;
        return {
          toolName: "registration_register_account",
          rationale: "Khách muốn đăng ký tài khoản.",
          arguments: args,
        };
      },
    },
  );

  const activateAccountRequest = (args) => {
    const payload = {
      email: String(args.email == null ? "" : args.email).trim(),
      token: String(args.token == null ? args.otp : args.token).trim(),
    };
    const missing = ["email", "token"].filter(
      (field) => !String(payload[field] == null ? "" : payload[field]).trim(),
    );
    if (missing.length) {
      return Promise.resolve({
        ok: false,
        error: `Thiếu ${missing.join(", ")}.`,
      });
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
      return Promise.resolve({
        ok: false,
        error: "Email không hợp lệ.",
      });
    }

    return jsonResult("kich-hoat-tai-khoan", {
      method: "POST",
      auth: "none",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
  };

  register(
    "registration_verify_otp",
    REGISTRATION_TOOL_SLOTS.verify_otp,
    "safe",
    ["Xác thực OTP", "Đăng ký"],
    activateAccountRequest,
    {
      test: (n) =>
        /\b(xac (minh|nhan) otp|nhap otp|ma otp|verify otp|otp la|otp:)\b/.test(
          n,
        ),
      build: (n) => {
        const otp = extractOtpCode(n);
        return {
          toolName: "registration_verify_otp",
          rationale: "Khách xác minh OTP.",
          arguments: otp ? { otp } : {},
        };
      },
    },
  );

  register(
    "registration_activate_account",
    REGISTRATION_TOOL_SLOTS.activate_account,
    "safe",
    ["Kích hoạt tài khoản", "Đăng nhập"],
    activateAccountRequest,
    {
      test: (n) =>
        /\b(kich hoat tai khoan|activate account|xac nhan tai khoan)\b/.test(
          n,
        ),
      build: (n) => {
        const args = {};
        const email = extractEmailLoose(n);
        const token = extractOtpCode(n);
        if (email) args.email = email;
        if (token) args.token = token;
        return {
          toolName: "registration_activate_account",
          rationale: "Khách kích hoạt tài khoản.",
          arguments: args,
        };
      },
    },
  );

  register(
    "customer_get_customer_info",
    CUSTOMER_TOOL_SLOTS.get_customer_info,
    "safe",
    ["Thông tin khách hàng", "Hồ sơ"],
    () =>
      jsonResult("profile", {
        method: "GET",
        auth: "bearer",
      }),
  );

  register(
    "customer_get_account_status",
    CUSTOMER_TOOL_SLOTS.get_account_status,
    "safe",
    ["Trạng thái tài khoản", "Hồ sơ"],
    async () => {
      const result = await jsonResult("profile", {
        method: "GET",
        auth: "bearer",
      });

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
    {
      test: (n) =>
        /\b(trang thai tai khoan|tinh trang tai khoan|account status)\b/.test(
          n,
        ),
      build: () => ({
        toolName: "customer_get_account_status",
        rationale: "Khách xem trạng thái tài khoản.",
        arguments: {},
      }),
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
    {
      test: (n) =>
        /\b(lich su dat ve|cac don da dat|booking history)\b/.test(n),
      build: () => ({
        toolName: "customer_get_booking_history",
        rationale: "Khách xem lịch sử đặt vé.",
        arguments: {},
      }),
    },
  );

  register(
    "customer_get_transaction_history",
    CUSTOMER_TOOL_SLOTS.get_transaction_history,
    "safe",
    ["Lịch sử giao dịch", "Thanh toán"],
    (args) => stub("customer_get_transaction_history", args),
    {
      test: (n) =>
        /\b(lich su giao dich|cac giao dich|transaction history)\b/.test(n),
      build: () => ({
        toolName: "customer_get_transaction_history",
        rationale: "Khách xem lịch sử giao dịch.",
        arguments: {},
      }),
    },
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
    {
      test: (n) =>
        /\b(diem (thuong|tich luy|hang)|loyalty|diem cua toi)\b/.test(n),
      build: () => ({
        toolName: "loyalty_get_current_points",
        rationale: "Khách xem điểm thưởng.",
        arguments: {},
      }),
    },
  );

  register(
    "loyalty_redeem_points",
    LOYALTY_TOOL_SLOTS.redeem_points,
    "sensitive",
    ["Đổi điểm thưởng", "Voucher khả dụng"],
    (args) => stub("loyalty_redeem_points", args),
    {
      test: (n) =>
        /\b(doi diem|redeem (point|diem)|quy doi diem|su dung diem)\b/.test(n),
      build: () => ({
        toolName: "loyalty_redeem_points",
        rationale: "Khách quy đổi điểm thưởng.",
        arguments: {},
      }),
    },
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
    {
      test: (n) =>
        /\b(lich su diem|points history|lich su tich diem)\b/.test(n),
      build: () => ({
        toolName: "loyalty_get_points_history",
        rationale: "Khách xem lịch sử điểm.",
        arguments: {},
      }),
    },
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
    {
      test: (n) =>
        /\b(hang thanh vien|membership tier|hang hien tai|hang bac|hang vang|hang kim cuong)\b/.test(
          n,
        ),
      build: () => ({
        toolName: "loyalty_get_membership_tier",
        rationale: "Khách xem hạng thành viên.",
        arguments: {},
      }),
    },
  );
}
