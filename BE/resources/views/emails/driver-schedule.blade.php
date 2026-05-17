<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật lịch trình - BusSafe</title>
</head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f8fafc;padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.06);border:1px solid #f1f5f9;">
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);padding:36px 32px;text-align:left;">
                            <span style="display:inline-block;padding:6px 12px;background:rgba(255,255,255,0.18);border-radius:30px;font-size:12px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;">
                                {{ $action === 'new' ? 'Xếp lịch mới' : 'Cập nhật lịch trình' }}
                            </span>
                            <h1 style="margin:0;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.03em;">Thông báo phân công lịch trình</h1>
                            <p style="margin:6px 0 0;font-size:14px;color:rgba(255,255,255,0.85);font-weight:500;">Hệ thống an toàn xe khách BusSafe</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1e293b;font-weight:600;">
                                Xin chào tài xế {{ $driver->ho_va_ten }},
                            </p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                                Bạn vừa được hệ thống tự động phân công {{ $actionText }} cho chuyến xe có thông tin chi tiết dưới đây. Vui lòng kiểm tra và chuẩn bị tốt cho lộ trình di chuyển.
                            </p>

                            <!-- Trip Info Card -->
                            <div style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:28px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <!-- Trip Code -->
                                    <tr>
                                        <td style="padding-bottom:12px;width:35%;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Mã Chuyến</td>
                                        <td style="padding-bottom:12px;font-size:15px;color:#0f172a;font-weight:700;">#{{ $trip->id }}</td>
                                    </tr>
                                    <!-- Route -->
                                    <tr>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Tuyến Đường</td>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:15px;color:#0f172a;font-weight:700;">
                                            {{ $trip->tuyenDuong->ten_tuyen_duong ?? 'N/A' }}
                                        </td>
                                    </tr>
                                    <!-- Time -->
                                    <tr>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Khởi Hành</td>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:15px;color:#2563eb;font-weight:700;">
                                            {{ \Carbon\Carbon::parse($trip->gio_khoi_hanh)->format('H:i') }} - {{ \Carbon\Carbon::parse($trip->ngay_khoi_hanh)->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                    <!-- Vehicle -->
                                    <tr>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Phương Tiện</td>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:15px;color:#0f172a;font-weight:700;">
                                            {{ $trip->xe->bien_so ?? 'Chưa gán xe' }}
                                            @if(!empty($trip->xe->loaiXe->ten_loai))
                                                <span style="font-size:13px;color:#64748b;font-weight:500;">({{ $trip->xe->loaiXe->ten_loai }})</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <!-- Duration -->
                                    <tr>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Dự Kiến</td>
                                        <td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:15px;color:#475569;font-weight:600;">
                                            @if(!empty($trip->tuyenDuong->gio_du_kien))
                                                {{ $trip->tuyenDuong->gio_du_kien }} giờ
                                            @endif
                                            @if(!empty($trip->tuyenDuong->quang_duong))
                                                ({{ $trip->tuyenDuong->quang_duong }} km)
                                            @endif
                                            @if(empty($trip->tuyenDuong->gio_du_kien) && empty($trip->tuyenDuong->quang_duong))
                                                N/A
                                            @endif
                                        </td>
                                    </tr>
                                    <!-- Nha xe -->
                                    <tr>
                                        <td style="padding-top:12px;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Nhà Xe</td>
                                        <td style="padding-top:12px;border-top:1px solid #e2e8f0;font-size:15px;color:#0f172a;font-weight:700;">
                                            {{ $trip->tuyenDuong->nhaXe->ten_nha_xe ?? 'N/A' }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Action Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 10px;">
                                <tr>
                                    <td style="border-radius:10px;background:linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);">
                                        <a href="{{ config('app.frontend_url', 'https://bussafe.io.vn') }}/tai-xe/lich-trinh" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;box-shadow:0 4px 12px rgba(79,70,229,0.2);">Xem Chi Tiết Lịch Trình</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;font-style:italic;">
                                *Lưu ý: Mọi sự thay đổi hoặc gặp vấn đề về sức khỏe/phương tiện, vui lòng liên hệ trực tiếp với điều hành nhà xe để kịp thời điều phối hành trình.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 32px 32px;border-top:1px solid #f1f5f9;background:#f8fafc;text-align:center;">
                            <p style="margin:0 0 6px;font-size:12px;line-height:1.5;color:#94a3b8;font-weight:500;">
                                Email này được gửi tự động từ hệ thống BusSafe. Vui lòng không trả lời trực tiếp email này.
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#cbd5e1;">
                                © {{ date('Y') }} BusSafe Team. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
