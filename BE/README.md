# 🚌 BusSafe Backend (BE)

> **Hệ thống điều hành và xử lý nghiệp vụ trung tâm cho BusSafe — Tích hợp Real-time WebSockets, AI Driver Safety và AI Chatbot Agent (RAG).**

---

## 🛠️ 1. Công nghệ & Thư viện sử dụng

Backend của hệ thống BusSafe được xây dựng trên nền tảng **Laravel 11.x** kết hợp với các thư viện chuyên dụng sau:

| Thư viện                             | Mục đích sử dụng                                                                       |
| :----------------------------------- | :------------------------------------------------------------------------------------- |
| `laravel/sanctum`                    | Xác thực API phân quyền đa vai trò (Admin, Nhà xe - Operator, Khách hàng, Tài xế).     |
| `spatie/laravel-permission`          | Quản lý phân quyền dựa trên vai trò (RBAC - Role-Based Access Control).                |
| `cloudinary-labs/cloudinary-laravel` | Upload và lưu trữ snapshot bằng chứng vi phạm của tài xế lên Cloudinary.               |
| `intervention/image`                 | Xử lý nén, định dạng và tối ưu hóa ảnh trước khi lưu trữ.                              |
| `laravel/reverb`                     | WebSocket realtime cho Live Tracking, Live Support, cảnh báo AI và thông báo vé.       |
| `smalot/pdfparser`                   | Trích xuất nội dung từ các file tri thức định dạng PDF phục vụ huấn luyện RAG.         |
| `maatwebsite/excel`                  | Xuất file báo cáo doanh thu, hành trình và danh sách đặt vé dưới dạng Excel.           |

---

## 🚀 2. Các tính năng cốt lõi xử lý ở Backend

1. **Hệ thống Quản trị & Phân quyền:** Quản lý Tài xế, Nhà xe, Tuyến đường, Xe, Chuyến đi và phân quyền chính xác qua Middleware.
2. **Xử lý Đặt vé & Thanh toán tự động:**
    - Tự động sinh sơ đồ ghế theo loại xe.
    - Đồng bộ giao dịch ngân hàng qua cổng SePay webhook, tự động đổi trạng thái vé sang "Đã thanh toán".
    - Tích lũy điểm thưởng thành viên và áp dụng Voucher khuyến mãi.
3. **Giám sát & Quản trị AI Safety:**
    - Tiếp nhận snapshot, dữ liệu vi phạm của tài xế (ngủ gật, hút thuốc, dùng điện thoại) từ AI client-side gửi lên.
    - Phát sự kiện cảnh báo thời gian thực về Dashboard điều hành của Nhà xe thông qua Laravel Reverb/Echo.
4. **Chatbot AI & Công nghệ RAG (Retrieval-Augmented Generation):**
    - Cho phép Admin cập nhật tài liệu PDF/Docx để nạp cơ sở kiến thức.
    - Đồng bộ và nhúng (embedding) danh mục tỉnh thành phục vụ truy vấn chuyến đi thông minh.
    - Hỗ trợ đa dạng Engine: Ollama (Local), Groq & Hugging Face (Cloud).

---

## ⚙️ 3. Hướng dẫn cài đặt nhanh

### Yêu cầu hệ thống

- PHP >= 8.2
- Composer
- MySQL / MariaDB
- Ollama (nếu chạy mô hình AI nội bộ)

### Các bước thiết lập ban đầu

1. **Cài đặt các gói phụ thuộc (Dependencies):**

    ```bash
    composer install
    ```

2. **Cấu hình môi trường (`.env`):**
   Sao chép file cấu hình mẫu và cập nhật các thông số Database, Cloudinary, Reverb, SePay và AI:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. **Khởi chạy cơ sở dữ liệu (Migration & Seeding):**
   Tạo các bảng và nạp dữ liệu mẫu ban đầu:

    ```bash
    php artisan migrate --seed
    ```

4. **Khởi chạy máy chủ phát triển:**
    ```bash
    php artisan serve
    ```

---

## 🧠 4. Hướng dẫn thiết lập hệ thống AI (Local Ollama)

Để vận hành Chatbot và đồng bộ dữ liệu tri thức RAG trên máy chủ của bạn:

1. **Khởi động Ollama** trên máy và tải các mô hình cấu hình trong file `config/ai.php`:

    ```bash
    ollama pull qwen2.5:7b
    ollama pull nomic-embed-text
    ollama pull qllama/bge-reranker-v2-m3:latest
    ```

2. **Chạy lệnh đồng bộ danh mục tỉnh thành thành vector nhúng:**
    ```bash
    php artisan ai:embed-provinces
    ```
    _(Thêm `--force` nếu muốn xóa cơ sở dữ liệu cũ để đồng bộ lại từ đầu)._

---

## 🛠️ 5. Các lệnh vận hành quan trọng (CLI Commands)

### Vận hành hàng đợi (Queue Worker)

Hệ thống sử dụng hàng đợi bất đồng bộ để xử lý các tác vụ nặng, tránh nghẽn luồng và lỗi HTTP Timeout (30s) của PHP:
- **`tracking` queue**: Lưu trữ tọa độ hành trình thời gian thực (`StoreTrackingPointJob`).
- **`default` queue**: Upload ảnh vi phạm tài xế lên Cloudinary (`UploadViolationImageJob`), nén ảnh hồ sơ xe (`UploadXeImageJob`), kiểm tra hạn thanh toán vé (`CheckPaymentStatusJob`), và **gửi email thông báo phân công lịch trình tài xế** (`SendDriverScheduleEmailJob`).

```bash
# Chạy xử lý Queue (Xử lý đồng thời cả hàng đợi tracking và default)
php artisan queue:work --queue=tracking,default

# Khởi động lại hàng đợi sau khi cập nhật code (Bắt buộc chạy khi cập nhật code Job/Mailable)
php artisan queue:restart
```

### Quản trị hệ thống

```bash
# Làm mới toàn bộ cache, config, route và tối ưu hóa hệ thống
php artisan optimize:clear

# Reset và nạp lại toàn bộ cơ sở dữ liệu mẫu
php artisan migrate:fresh --seed
```

### Hỗ trợ kết nối & Tunneling (Dành cho nhà phát triển)

Sử dụng khi cần public API ra ngoài môi trường Internet (để nhận SePay Webhook hoặc liên kết với FE khi test thiết bị di động):

- **Ngrok (Public cổng Backend 8000):**

    ```bash
    ngrok http 8000
    ```

    _(Thời gian sử dụng: không giới hạn đối với tài khoản cá nhân, giới hạn băng thông 1GB)._

- **Pinggy (Public cổng Frontend 5173 - hữu ích khi test camera tài xế trên mobile):**
    ```bash
    pinggy tunnel 5173
    ```
    _(Thời gian sử dụng: 60 phút mỗi phiên kết nối, tối đa 50 kết nối đồng thời)._
