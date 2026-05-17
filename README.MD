"""# 🚌 BusSafe: Hệ thống Quản lý Xe khách Thông minh

> **Tích hợp AI Thị giác máy tính, Định vị thời gian thực và Quản trị vận tải hiện đại.**

---

## 🌟 1. Tổng quan & Mục đích dự án

### 1.1. Giới thiệu

**BusSafe** không chỉ là một ứng dụng đặt vé xe khách thông thường. Đây là một hệ sinh thái vận tải thông minh được thiết kế để giải quyết hai vấn đề cốt lõi của ngành xe khách Việt Nam: **An toàn lái xe** và **Minh bạch trong quản lý**. Hệ thống ứng dụng trí tuệ nhân tạo (AI) để giám sát hành vi tài xế và công nghệ WebSockets để theo dõi hành trình theo thời gian thực.

### 1.2. Mục đích thiết lập

- **Nâng cao an toàn:** Giảm thiểu tai nạn do ngủ gật và mất tập trung thông qua cảnh báo AI tức thời.
- **Chuyển đổi số toàn diện:** Tự động hóa từ khâu đặt vé, thanh toán đến quản lý doanh thu và ví điện tử nhà xe.
- **Tối ưu trải nghiệm:** Cung cấp cho hành khách công cụ theo dõi vị trí xe trực tiếp và hỗ trợ 24/7 từ Chatbot AI.
- **Hỗ trợ điều hành:** Giúp Admin và Nhà xe nắm bắt mọi sự cố trên đường trong vòng mili giây ("Zero-latency").

---

## 🛠 2. Công nghệ & Thư viện sử dụng

### 2.1. Backend (Laravel 11.x)

| Thư viện                             | Mục đích                                              |
| :----------------------------------- | :---------------------------------------------------- |
| `laravel/sanctum`                    | Xác thực API cho nhiều vai trò (Admin, User, Driver). |
| `spatie/laravel-permission`          | Quản lý phân quyền dựa trên vai trò (RBAC).           |
| `cloudinary-labs/cloudinary-laravel` | Lưu trữ ảnh snapshot vi phạm AI lên đám mây.          |
| `intervention/image`                 | Xử lý nén và resize ảnh trước khi upload.             |
| `pusher/pusher-php-server`           | Hỗ trợ Real-time Broadcasting qua WebSockets.         |
| `maatwebsite/excel`                  | Xuất báo cáo doanh thu và danh sách khách hàng.       |

### 2.2. Frontend (Vue.js 3 - Vite)

| Thư viện                   | Mục đích                                                  |
| :------------------------- | :-------------------------------------------------------- |
| `@mediapipe/tasks-vision`  | Nhận diện 478 điểm mốc khuôn mặt và tính toán chỉ số EAR. |
| `onnxruntime-web`          | Chạy mô hình YOLOv8 để phát hiện điện thoại, thuốc lá.    |
| `@openmapvn/openmapvn-gl`  | Hiển thị bản đồ hành trình chuyên dụng OpenMap.vn.        |
| `pinia`                    | Quản lý trạng thái ứng dụng (Giỏ hàng, User session).     |
| `chart.js` & `vue-chartjs` | Vẽ biểu đồ thống kê doanh thu và chỉ số an toàn.          |

---

## 🎭 3. Phân hệ chức năng chi tiết

### 3.1. Đối với Khách hàng

- **Tìm kiếm & Đặt vé:** Tra cứu chuyến theo điểm đi/đến, chọn ghế trên sơ đồ xe thực tế.
- **Thanh toán đa năng:** Hỗ trợ Momo, Chuyển khoản hoặc Tiền mặt (Chờ xác nhận).
- **Live Tracking:** Theo dõi vị trí xe thực tế trên OpenMap.vn và xem thời gian dự kiến đến (ETA).
- **Hệ thống ưu đãi:** Tích điểm thành viên, nâng hạng (Bạc/Vàng/Kim cương) và áp dụng Voucher.
- **Chatbot AI:** Trợ lý ảo giải đáp chính sách và tra cứu hành trình 24/7.

### 3.2. Đối với Tài xế & AI Safety

- **Trợ lý an toàn AI:** Kích hoạt giám sát EAR (ngủ gật) và YOLO (điện thoại/hút thuốc).
- **Cảnh báo tức thời:** Phát âm thanh cảnh báo tại cabin khi phát hiện vi phạm.
- **Snapshot vi phạm:** Tự động chụp ảnh bằng chứng gửi về Dashboard quản lý.
- **Quản lý lịch trình:** Xem danh sách ca trực và lộ trình được phân công.

### 3.3. Đối với Nhà xe (Operator)

- **Dashboard quản trị:** Theo dõi doanh thu, số vé bán ra và các cảnh báo an toàn theo thời gian thực.
- **Quản lý ví tiền:** Ví doanh thu tự động trừ chiết khấu và duy trì quỹ ký quỹ bảo đảm.
- **Quản lý tài nguyên:** Thêm/sửa chuyến xe, tuyến đường, xe và hồ sơ tài xế.
- **Thiết kế sơ đồ ghế:** Công cụ tự động sinh sơ đồ ghế theo loại xe (Giường nằm/Ghế ngồi).

### 3.4. Đối với Admin hệ thống

- **Phê duyệt & Kiểm duyệt:** Duyệt yêu cầu tham gia của nhà xe và các tuyến đường mới đề xuất.
- **Quản trị tri thức:** Cập nhật file PDF/Docx vào kho dữ liệu để huấn luyện Chatbot AI.
- **Phân quyền (RBAC):** Gán chức năng cụ thể cho Kế toán, CSKH, Kiểm duyệt viên.

---

## 🚀 4. Hướng dẫn cài đặt nhanh

### Bước 1: Cài đặt Backend (Laravel)

```bash
# Cài đặt các thư viện lõi
composer require laravel/sanctum spatie/laravel-permission cloudinary-labs/cloudinary-laravel intervention/image pusher/pusher-php-server maatwebsite/excel

# Cài đặt máy chủ WebSockets tốc độ cao Laravel Reverb (Mới)
composer require laravel/reverb
php artisan reverb:install

# Publish cấu hình Cloudinary và chạy CSDL
php artisan vendor:publish --provider="CloudinaryLabs\\CloudinaryLaravel\\CloudinaryServiceProvider"
php artisan migrate --seed
```

### Bước 2: Cài đặt Frontend (Vue.js)

```bash
# Cài đặt thư viện AI, Bản đồ và Laravel Echo (Realtime)
npm install @mediapipe/tasks-vision onnxruntime-web @openmapvn/openmapvn-gl leaflet pinia chart.js vue-chartjs pusher-js laravel-echo
```

**Lưu ý:** Tìm đầy đủ các key trong file `.env.example` và thay thế bằng key của bạn.

---

## 📡 5. Cấu hình Real-time (Laravel Reverb), Cloudflare Tunnel & VietQR

### 5.1. Quy trình chạy Real-time & Giám sát hệ thống ở Local

Để hệ thống hoạt động đầy đủ tính năng realtime (Cảnh báo vi phạm AI, Cập nhật trạng thái vé, Rút tiền ví doanh thu), bạn cần chạy song song 4 tiến trình sau:

1. **Khởi chạy Web Server Backend:**
   ```bash
   php artisan serve
   ```
2. **Khởi chạy Máy chủ WebSocket (Laravel Reverb):**
   ```bash
   php artisan reverb:start
   ```
3. **Khởi chạy Hàng đợi Queue (Xử lý tác vụ ngầm cho realtime & tracking):**
   ```bash
   php artisan queue:work --queue=tracking,default
   ```
4. **Khởi chạy Máy chủ Frontend (Vue.js):**
   ```bash
   npm run dev -- --force
   ```

### 5.2. Cấu hình Môi trường (.env) cho Hệ thống Real-time

#### 💻 Phía Backend (`BE/.env`)

Cấu hình để Laravel và máy chủ Reverb liên kết thống nhất thông qua đường truyền Cloudflare Tunnel:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=2041227
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=socket.bussafe.io.vn
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8090
```

#### 🌐 Phía Frontend (`FE/.env`)

Cấu hình kết nối an toàn WSS đến domain Socket biên của Cloudflare:

```env
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=socket.bussafe.io.vn
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

#### 🛡 Thiết lập trên Cloudflare Tunnel (Zero Trust Dashboard)

- Định tuyến Public Hostname: `socket.bussafe.io.vn` ➔ `http://127.0.0.1:8090`.
- **Rất quan trọng:** Trong **Cloudflare Dashboard** -> **Network** -> Bật chế độ **WebSockets** sang **ON (Enabled)** để thông suốt các yêu cầu bắt tay `101 Switching Protocols`.

### 5.3. Cấu hình Cổng thanh toán VietQR tự động (`FE/.env`)

Hệ thống tích hợp thư viện tạo mã VietQR động để khách hàng quét thanh toán đặt vé và xem hóa đơn tức thời. Cấu hình tài khoản thụ hưởng tại file cấu hình:

```env
VITE_BANK_ID=MB                  # Mã ngân hàng (Ví dụ: MB, VCB, BIDV,...)
VITE_BANK_ACCOUNT=0377417720     # Số tài khoản ngân hàng nhận tiền
VITE_ACCOUNT_NAME=NGUYEN HUU THAI # Tên chủ tài khoản viết hoa không dấu
```

_Giao diện Vue sẽ tự động sử dụng định dạng `compact2.png` của VietQR để sinh mã QR thanh toán kèm đầy đủ thông tin số tiền và mã vé làm nội dung chuyển khoản._
