<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê nhà xe</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .muted { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Báo cáo thống kê nhà xe</h1>
    <div class="muted">Nhà xe: {{ $nhaXe->ten_nha_xe }} | Từ {{ $data['filters']['tu_ngay'] }} đến {{ $data['filters']['den_ngay'] }}</div>
    <table>
        <tr><th>Tổng doanh thu</th><td>{{ number_format($data['tong_doanh_thu']) }}</td></tr>
        <tr><th>Tổng vé bán</th><td>{{ $data['tong_ve_ban'] }}</td></tr>
        <tr><th>Tổng chuyến xe</th><td>{{ $data['tong_chuyen_xe'] }}</td></tr>
        <tr><th>Tổng khách hàng</th><td>{{ $data['tong_khach_hang'] }}</td></tr>
    </table>
    <h3>Doanh thu theo tuyến</h3>
    <table>
        <tr><th>Tuyến đường</th><th>Số vé</th><th>Doanh thu</th></tr>
        @foreach($data['doanh_thu_theo_tuyen'] as $row)
            <tr>
                <td>{{ $row['ten_tuyen_duong'] }}</td>
                <td>{{ $row['so_ve'] }}</td>
                <td>{{ number_format($row['doanh_thu']) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
