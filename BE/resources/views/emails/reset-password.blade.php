<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#4f46e5 0%,#2563eb 100%);padding:28px 32px;">
                            <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">Đặt lại mật khẩu</h1>
                            <p style="margin:8px 0 0;font-size:14px;color:rgba(255,255,255,0.9);">GoBus — Hệ thống quản lý xe khách</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#334155;">Xin chào,</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
                                Bạn vừa yêu cầu đặt lại mật khẩu cho tài khoản của mình. Nhấn nút bên dưới để tạo mật khẩu mới.
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0;">
                                <tr>
                                    <td style="border-radius:10px;background:linear-gradient(135deg,#4f46e5 0%,#2563eb 100%);">
                                        <a href="{{ $resetLink }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Đặt lại mật khẩu</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#64748b;">
                                Hoặc sao chép liên kết sau vào trình duyệt:
                            </p>
                            <p style="margin:0 0 24px;padding:12px 14px;background:#f8fafc;border-radius:8px;font-size:12px;word-break:break-all;color:#475569;border:1px solid #e2e8f0;">{{ $resetLink }}</p>
                            <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#64748b;">
                                <strong style="color:#334155;">Lưu ý:</strong> Liên kết có hiệu lực trong <strong>{{ $expiresMinutes }}</strong> phút. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này — mật khẩu hiện tại vẫn an toàn.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                                Email được gửi tự động, vui lòng không trả lời trực tiếp.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
