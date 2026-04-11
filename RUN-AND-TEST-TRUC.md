# GR45 — Chạy dự án & test phần Trúc

Tài liệu này gom **chức năng Trúc đã làm**, **tài khoản + dữ liệu seed** (`TrucFeatureSeeder`), và **route FE** để vào từng phần.  
`BASE` = URL frontend (Docker private: `http://localhost:15173`; GR45 chỉ Vite local thì thay cho đúng).

---

## 1. Chức năng Trúc (phạm vi đồ án)

| Mã / nhóm | Nội dung |
|-----------|----------|
| **US06** | Quên mật khẩu — gửi email chứa link đặt lại (API `quen-mat-khau`, bảng `dat_lai_mat_khau_tokens`, view mail `emails.reset-password`). |
| **US07** | Đặt lại mật khẩu (link email → `reset-password`) + **đổi mật khẩu** sau khi đăng nhập (khách: `/profile`; admin/nhà xe: màn cài đặt trên app đầy đủ). |
| **US13** | Quản lý **xe** + **sơ đồ ghế** (admin / nhà xe — cần FE+BE đủ route). |
| **Seed Trúc** | Đồng bộ **tỉnh thành** (mã 48 Đà Nẵng, 46 Huế, …), nhà xe **TRUC01**, xe **43A-999.99** + 8 ghế, **10 tuyến** Đà Nẵng → Huế (tên `Da Nang - Hue (Truc Demo …)`), chuyến mẫu. |

---

## 2. Tài khoản sau seed (`TrucFeatureSeeder`)

Mật khẩu chung: **`Truc@123456`**

| Vai trò | Email |
|---------|--------|
| Admin | `thanhtruc5699+1@gmail.com` |
| Nhà xe (TRUC01) | `thanhtruc5699+2@gmail.com` |
| Khách | `thanhtruc5699+3@gmail.com` |
| Tài xế | `truc.driver@gobus.vn` |

---

## 3. Dữ liệu tuyến / xe (seed — test US13 / API / UI đầy đủ)

| Loại | Giá trị |
|------|---------|
| Nhà xe | Mã **`TRUC01`**, tên *Nha xe Truc Demo* |
| Xe | Biển **`43A-999.99`**, *Truc Demo Sleeper*, 8 ghế: **`A01`–`A04`**, **`B01`–`B04`** |
| Điểm tuyến | `diem_bat_dau` ≈ **Đà Nẵng**, `diem_ket_thuc` ≈ **Huế** |
| Tuyến | **10** tuyến, tên dạng **`Da Nang - Hue (Truc Demo {1–10} - {suffix})`** — suffix: *Express Hai Van*, *Sang Som*, *Toc Hanh 1*, *Toc Hanh 2*, *Trua Tien Loi*, *Chieu 1*, *Chieu 2*, *Toi Som*, *Toi Muon*, *Dem* |
| Giờ xuất bếp (theo tuyến) | 06:00, 07:00, 08:00, 09:30, 11:00, 13:00, 14:30, 16:00, 18:00, 21:00 |
| Chuyến mẫu | Mỗi tuyến **một** chuyến: `ngay_khoi_hanh` = **hôm nay + (1 + chỉ số tuyến 0→9)** ngày, `gio_khoi_hanh` = giờ tuyến tương ứng |

---

## 4. Route FE — **GR45** (`FE/src/router/index.ts`)

Chỉ các màn build được trong skeleton; **không** có admin dashboard, phương tiện.

| Path | `name` | Chức năng Trúc |
|------|--------|----------------|
| `/auth/login` | `client-login` | Đăng nhập khách |
| `/auth/admin-login` | `admin-login` | Đăng nhập admin |
| `/auth/operator-login` | `operator-login` | Đăng nhập nhà xe |
| `/auth/driver-login` | `driver-login` | Đăng nhập tài xế |
| `/auth/forgot-password` | `forgot-password` | **US06** — query `role`: `khach_hang` \| `nha_xe` \| `admin` |
| `/auth/reset-password` | `reset-password` | **US07** — đặt lại MK (kèm query từ email) |
| `/profile` | `client-profile` | **US07** — hồ sơ + đổi MK khách |
| `/` | — | → `/auth/login` |

Ví dụ: `BASE/auth/forgot-password?role=khach_hang`, `BASE/profile`.

---

## 5. Route FE — **DoAnPrivate** (auth + đổi MK + phương tiện)

Cùng `BASE` như trên; cần router đồ án.

| Path | Chức năng Trúc |
|------|----------------|
| `BASE/auth/forgot-password` (+ `role`) | US06 |
| `BASE/auth/reset-password` | US07 |
| `BASE/profile` | US07 khách |
| `BASE/admin/cai-dat` / `BASE/nha-xe/cai-dat` | US07 đổi MK admin / nhà xe |
| `BASE/admin/phuong-tien` / `BASE/nha-xe/phuong-tien` | US13 |

---

## 6. Chạy GR45

### Docker (thư mục `GR45/`)

```bash
docker compose build && docker compose up -d
```

- FE: http://localhost:15173 — `VITE_API_BASE_URL=http://localhost:18080`
- API: http://localhost:18080/api/v1/...
- MySQL: `127.0.0.1:34007` — `gobus_db` / `gobus_user` / `gobus_pass`
- Mail (reset): đã cấu hình trong `docker-compose.yml` (service `web`)
- Container: **`gr45-db`**, **`gr45-web`**, **`gr45-frontend`** (không dùng tên `datn-*` để tránh conflict khi private vẫn chạy).

```bash
docker compose exec web php artisan migrate:fresh --seed
```

### Local (không Docker)

```bash
cd GR45/BE && composer install && cp .env.example .env
# DB_*, FRONTEND_URL, MAIL_*
php artisan key:generate && php artisan migrate:fresh --seed && php artisan serve
```

```bash
cd GR45/FE && npm install
# VITE_API_BASE_URL=... trùng cổng BE
npm run dev
```

---

## 7. Ghi chú

- **GR45/BE** có thể thiếu một số API so với BE đồ án đầy đủ (ví dụ `/v1/ve`, `/v1/admin/xe`) → tab vé trên `ProfileView` hoặc màn phương tiện có thể lỗi nếu chỉ chạy GR45.
- Bản rút gọn **router + URL**: `TRUC_TEST_URLS_AND_ACCOUNTS.md` (thư mục `GitHub/`).




- Frontend: `http://localhost:15173`
- Quên mật khẩu: `http://localhost:15173/auth/forgot-password`
- Đặt lại mật khẩu: `http://localhost:15173/auth/reset-password`
- Nhà xe quản lý ghế: `http://localhost:15173/nha-xe/phuong-tien`
- Admin quản lý ghế: `http://localhost:15173/admin/phuong-tien`

