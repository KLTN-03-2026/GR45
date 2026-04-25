<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kích hoạt tài khoản</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:24px auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="padding:20px 24px;background:#2563eb;color:#ffffff;">
            <h2 style="margin:0;font-size:20px;">Kích hoạt tài khoản GoBus</h2>
        </div>
        <div style="padding:24px;">
            <p style="margin-top:0;">Xin chào,</p>
            <p>Bạn vừa đăng ký tài khoản khách hàng. Vui lòng nhấn nút bên dưới để kích hoạt tài khoản.</p>
            <p style="text-align:center;margin:28px 0;">
                <a href="{{ $activationLink }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">
                    Kích hoạt tài khoản
                </a>
            </p>
            <p>Liên kết có hiệu lực trong <strong>{{ $expiresMinutes }} phút</strong>.</p>
            <p>Nếu bạn không thực hiện đăng ký, vui lòng bỏ qua email này.</p>
        </div>
    </div>
</body>
</html>

