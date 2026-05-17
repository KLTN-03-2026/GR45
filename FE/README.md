# DoAnPrivateFE

This template should help get you started developing with Vue 3 in Vite.

## Recommended IDE Setup

[VS Code](https://code.visualstudio.com/) + [Vue (Official)](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Recommended Browser Setup

- Chromium-based browsers (Chrome, Edge, Brave, etc.):
  - [Vue.js devtools](https://chromewebstore.google.com/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd)
  - [Turn on Custom Object Formatter in Chrome DevTools](http://bit.ly/object-formatters)
- Firefox:
  - [Vue.js devtools](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)
  - [Turn on Custom Object Formatter in Firefox DevTools](https://fxdx.dev/firefox-devtools-custom-object-formatters/)

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Compile and Minify for Production

```sh
npm run build
```

## Các thư viện được sử dụng (Dependencies)

Dưới đây là danh sách các lệnh cài đặt chi tiết từng nhóm thư viện để dự án có thể chạy ổn định:

- **Framework & State Management**:
  - `vue` (v3.x), `vite`, `vue-router`, `pinia` (Quản lý state)

  ```sh
  npm install vue vue-router pinia
  ```

- **API & Realtime**:
  - `axios` (Gọi HTTP requests)
  - `laravel-echo` & `pusher-js` (Echo client cho Laravel Reverb realtime)
  ```sh
  npm install axios laravel-echo pusher-js
  ```
  npm i onnxruntime-web
- **UI & Biểu đồ (Giao diện & Tiện ích)**:
  - `bootstrap` (UI framework chính)
  - `lucide-vue-next` (Thư viện icon hiện đại)
  - `chart.js` & `vue-chartjs` (Vẽ biểu đồ thống kê)
  - `leaflet` (Bản đồ hỗ trợ tracking thời gian thực)
  ```sh
  npm install bootstrap lucide-vue-next chart.js vue-chartjs leaflet
  ```

**(Hoặc nếu đã tải đủ mã nguồn từ repo, bạn chỉ cần chạy lệnh `npm install` để Node tự động lấy toàn bộ danh sách dựa trên `package.json`)**

## Cấu hình Môi trường (.env)

Bạn cần tạo file `.env` ở thư mục gốc của dự án Frontend (ngang hàng `package.json`) và thiết lập các thông số sau:

```env
# Địa chỉ API của Backend (Ví dụ local: http://127.0.0.1:8000/api/)
VITE_API_URL=http://127.0.0.1:8000/api/

# Cấu hình Laravel Reverb - Đồng bộ với Backend
VITE_REVERB_APP_KEY=YOUR_REVERB_KEY
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http

# Cấu hình Thanh toán VietQR
VITE_BANK_ID=
VITE_BANK_ACCOUNT=
VITE_ACCOUNT_NAME=
```

Invoke-WebRequest -Uri "https://github.com/ultralytics/assets/releases/download/v8.3.0/yolov8n.onnx" -OutFile "c:\xampp\htdocs\DATN1\DoAnPrivate\public\models\yolo-violations.onnx" -UseBasicParsing

curl -L -o "c:\xampp\htdocs\DATN1\DoAnPrivate\public\models\yolo-violations.onnx" "https://github.com/ultralytics/assets/releases/download/v8.3.0/yolov8n.onnx"

curl.exe -L -o "c:\xampp\htdocs\DATN1\DoAnPrivate\public\models\yolo-violations.onnx" "https://github.com/ultralytics/assets/releases/download/v8.3.0/yolov8n.onnx"

Get-Content -Path "c:\xampp\htdocs\DATN1\DoAnPrivate\public\models\yolo-violations.onnx"

curl.exe -L -o "c:\xampp\htdocs\DATN1\DoAnPrivate\public\models\yolo-violations.onnx" "https://github.com/Hyuto/yolov8-onnxruntime-web/raw/master/public/model/yolov8n.onnx"

new update

npm run dev -- --force
