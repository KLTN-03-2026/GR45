# 📱 SmartBus Mobile App

> **Ứng dụng di động dành cho Khách hàng và Tài xế trong hệ sinh thái SmartBus — Xây dựng bằng React Native & Expo (v54).**

---

## 🛠️ 1. Hướng dẫn cài đặt nhanh (Setup)

### Yêu cầu tiên quyết
* **Node.js:** Phiên bản `>= 20.19.0` hoặc `>= 22.12.0`.
* **Thiết bị chạy thử:**
  * **iOS Simulator** (đã cài Xcode trên macOS) hoặc **Android Emulator** (đã cài Android Studio).
  * **Thiết bị thật:** Cài đặt ứng dụng **Expo Go** từ App Store hoặc Google Play Store.

### Các bước khởi chạy lần đầu
1. **Cài đặt các thư viện (Dependencies):**
   ```bash
   cd Mobile
   npm install
   ```
2. **Cấu hình biến môi trường (`.env`):**
   * Dự án đã có sẵn file `.env` mẫu.
   * **Mặc định:** Ứng dụng sẽ tự kết nối tới API Production đã deploy: `https://api.bussafe.io.vn/api`.
   * **Khi muốn chạy local:** Hãy mở file `.env` và bỏ dấu comment `#` ở dòng tương ứng với môi trường phát triển của bạn (`localhost` hoặc IP cục bộ của máy Mac).

---

## 🚀 2. Tổng hợp các lệnh khởi chạy (Scripts)

Hãy sử dụng các lệnh sau trong Terminal (tại thư mục `Mobile`) để vận hành ứng dụng:

| Lệnh chạy | Ý nghĩa |
| :--- | :--- |
| `npm run start` | Khởi động máy chủ Metro Bundler (giao diện điều khiển chính). |
| `npm run ios:local` | **(Khuyên dùng)** Mở trực tiếp trên iOS Simulator qua dải `localhost` (không lo lỗi kết nối mạng Wi-Fi). |
| `npm run ios` | Mở trực tiếp trên iOS Simulator (sử dụng dải IP nội bộ LAN). |
| `npm run android` | Mở trực tiếp trên Android Emulator / Thiết bị Android đang kết nối. |
| `npm run web` | Chạy phiên bản web thử nghiệm trên trình duyệt. |
| `npx expo start -c` | Khởi động Metro Bundler và **xóa sạch cache** (dùng khi cập nhật `.env` hoặc gặp lỗi import lạ). |

---

## ⌨️ 3. Phím tắt tương tác trong Terminal (Metro Bundler Shortcuts)

Khi bạn đã chạy server Metro Bundler (`npm run start` hoặc các lệnh khởi chạy trên), bạn có thể nhấn trực tiếp các phím sau trên bàn phím Terminal để điều khiển:

| Phím bấm | Thao tác thực hiện |
| :---: | :--- |
| **`i`** | Mở ứng dụng trên **iOS Simulator** (nếu simulator đang chạy). |
| **`a`** | Mở ứng dụng trên **Android Emulator** hoặc thiết bị Android thực đang cắm cáp USB. |
| **`w`** | Mở ứng dụng trên trình duyệt **Web**. |
| **`r`** | **Reload ứng dụng** (tải lại toàn bộ gói JS tức thì mà không cần cài lại app). |
| **`d`** | Mở **Developer Menu** (Menu gỡ lỗi) trong ứng dụng để debug. |
| **`c`** | Xóa bộ nhớ đệm (Clear cache) và khởi động lại Metro Bundler. |
| **`o`** | Mở nhanh thư mục dự án trên trình soạn thảo mã nguồn (VS Code/Cursor). |
| **`shift + d`** | Bật/tắt tự động mở công cụ gỡ lỗi (React Native DevTools). |
| **`Ctrl + C`** | **Dừng hoàn toàn** máy chủ phát triển Metro Bundler. |

---

## 📲 4. Phím tắt điều khiển gỡ lỗi trong ứng dụng (In-App Debug Shortcuts)

Khi ứng dụng đang chạy trên Emulator / Simulator hoặc Thiết bị thật, bạn có thể mở **Developer Menu** (Menu gỡ lỗi của React Native để kích hoạt Fast Refresh, Debug JS, Inspect Elements) bằng các phím tắt sau:

### Trực tiếp trên thiết bị gỡ lỗi
* **iOS Simulator:** Nhấn cụm phím `Cmd ⌘ + D`.
* **Android Emulator:** 
  * Trên macOS: Nhấn cụm phím `Cmd ⌘ + M`.
  * Trên Windows / Linux: Nhấn cụm phím `Ctrl + M`.
* **Thiết bị thật (Expo Go):** Cầm điện thoại lên và **lắc mạnh (Shake)** thiết bị.

### Các tùy chọn hữu ích trong Developer Menu
* **Reload:** Tải lại giao diện nhanh.
* **Toggle Element Inspector:** Bật thanh công cụ kiểm tra giao diện (giống F12 trên Web) để xem kích thước, padding, margin của các thẻ Text, View.
* **Show Keep Awake:** Giữ màn hình điện thoại luôn sáng khi đang code.

---

## 📂 5. Quy tắc tổ chức cấu trúc code
* Thư mục `app/` chỉ quản lý các đường dẫn điều hướng (**Route Layer**) thông qua Expo Router.
* Thư mục `src/` quản lý toàn bộ logic nghiệp vụ thực tế:
  * `src/components/`: Chứa các component UI dùng chung và độc lập.
  * `src/services/`: Quản lý các lệnh gọi API thông qua `client-api.ts` (đã tích hợp tự động đọc cấu hình `.env`).
  * `src/constants/`: Quản lý các hằng số, bảng màu và theme của app.
