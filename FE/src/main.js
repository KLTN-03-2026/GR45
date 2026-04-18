import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import pinia from './stores'

try {
  const rawPrefs = localStorage.getItem('admin.personal.settings')
  const prefs = rawPrefs ? JSON.parse(rawPrefs) : null
  const theme = prefs?.theme === 'dark' ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', theme)
} catch {
  document.documentElement.setAttribute('data-theme', 'light')
}

const app = createApp(App)

app.use(pinia)
app.use(router)

app.mount('#app')

