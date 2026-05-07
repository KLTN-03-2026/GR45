import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    // Cho phép mọi host (ngrok / tunnel) — giữ từ cấu hình cũ
    allowedHosts: true,
    proxy: {
      '/api': {
        // target: 'http://127.0.0.1:8000',
        target: 'https://api.bussafe.io.vn', 
        changeOrigin: true,
      },
    },
  },
  optimizeDeps: {
    exclude: ['onnxruntime-web']
  }
})

