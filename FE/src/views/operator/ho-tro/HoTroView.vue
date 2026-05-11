<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axiosClient from '@/api/axiosClient'
import { useChatSupportChannel } from '@/composables/useChatSupportChannel'

// State
const sessions = ref([])
const currentSessionId = ref(null)
const currentSessionDetails = ref(null)
const messages = ref([])
const newMessage = ref('')
const isLoadingSessions = ref(true)
const isLoadingMessages = ref(false)
const isLoadingMore = ref(false)
const isSending = ref(false)
const searchQuery = ref('')
const messagesContainer = ref(null)
const currentMessagePage = ref(1)
const hasMoreMessages = ref(true)

// For Creating New Session
const showNewSessionModal = ref(false)
const isCreatingSession = ref(false)
const newSessionData = ref({
  tieu_de: 'Tuyến đường',
  noi_dung: ''
})
const supportTopics = ['Tuyến đường', 'Chuyến xe', 'Hợp đồng', 'Thanh toán', 'Khác']

const { subscribe, unsubscribeAll, isSubscribed } = useChatSupportChannel()

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  return new Date(timeStr).toLocaleTimeString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  })
}

const fetchSessions = async () => {
  try {
    isLoadingSessions.value = true
    const res = await axiosClient.get('/v1/nha-xe/ho-tro/sessions', {
      params: { search: searchQuery.value }
    })
    sessions.value = res.data.data.data || res.data.data
  } catch (error) {
    console.error('Failed to fetch sessions', error)
  } finally {
    isLoadingSessions.value = false
  }
}

const selectSession = async (session) => {
  if (currentSessionId.value === session.id) return

  currentSessionId.value = session.id
  currentSessionDetails.value = session
  messages.value = []
  currentMessagePage.value = 1
  hasMoreMessages.value = true

  unsubscribeAll()

  try {
    isLoadingMessages.value = true
    const res = await axiosClient.get(`/v1/nha-xe/ho-tro/sessions/${session.id}`)
    messages.value = res.data.messages.data.reverse()
    currentMessagePage.value = res.data.messages.current_page
    hasMoreMessages.value = res.data.messages.current_page < res.data.messages.last_page
    currentSessionDetails.value = res.data.session

    scrollToBottom()

    // Subscribe realtime
    subscribe(session.id, (message) => {
      messages.value.push(message)
      scrollToBottom()

      // Update preview in sidebar
      const s = sessions.value.find(x => x.id === session.id)
      if (s) {
        if (!s.messages) s.messages = []
        s.messages[0] = message
        // bring to top
        sessions.value = [s, ...sessions.value.filter(x => x.id !== session.id)]
      }
    })
  } catch (error) {
    console.error('Failed to fetch session messages', error)
  } finally {
    isLoadingMessages.value = false
  }
}

const loadMoreMessages = async () => {
  if (!hasMoreMessages.value || isLoadingMore.value) return

  const container = messagesContainer.value
  const previousScrollHeight = container ? container.scrollHeight : 0

  isLoadingMore.value = true
  try {
    const res = await axiosClient.get(`/v1/nha-xe/ho-tro/sessions/${currentSessionId.value}?page=${currentMessagePage.value + 1}`)
    const newMessages = res.data.messages.data.reverse()
    messages.value = [...newMessages, ...messages.value]
    currentMessagePage.value = res.data.messages.current_page
    hasMoreMessages.value = res.data.messages.current_page < res.data.messages.last_page

    await nextTick()
    if (container) {
      container.scrollTop = container.scrollHeight - previousScrollHeight
    }
  } catch (error) {
    console.error('Failed to load more messages', error)
  } finally {
    isLoadingMore.value = false
  }
}

const onScroll = (e) => {
  if (e.target.scrollTop === 0) {
    loadMoreMessages()
  }
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || !currentSessionId.value || isSending.value) return

  const content = newMessage.value.trim()
  newMessage.value = ''
  isSending.value = true

  try {
    await axiosClient.post(`/v1/nha-xe/ho-tro/sessions/${currentSessionId.value}/reply`, {
      content
    })
    // Realtime will handle appending
  } catch (error) {
    console.error('Failed to send message', error)
  } finally {
    isSending.value = false
  }
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const createNewSession = async () => {
  if (!newSessionData.value.tieu_de || !newSessionData.value.noi_dung) return

  isCreatingSession.value = true
  try {
    const res = await axiosClient.post('/v1/nha-xe/ho-tro/sessions', {
      tieu_de: newSessionData.value.tieu_de,
      noi_dung: newSessionData.value.noi_dung
    })
    
    await fetchSessions()
    
    // Auto select new session
    const newSession = res.data.data
    await selectSession(newSession)
    
    // Reset and close modal
    showNewSessionModal.value = false
    newSessionData.value = { tieu_de: 'Tuyến đường', noi_dung: '' }
    
    const backdrop = document.querySelector('.modal-backdrop')
    if (backdrop) backdrop.remove()
    document.body.classList.remove('modal-open')
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
    
  } catch (error) {
    console.error('Failed to create new session', error)
  } finally {
    isCreatingSession.value = false
  }
}

onMounted(() => {
  fetchSessions()
})

onUnmounted(() => {
  unsubscribeAll()
})
</script>

<template>
  <div class="chat-support-container">
    <div class="row g-0 h-100 shadow-sm rounded-4 overflow-hidden chat-wrapper">
      
      <!-- Sidebar List -->
      <div class="col-md-4 col-lg-3 bg-white border-end d-flex flex-column z-1 h-100">
        <!-- Sidebar Header -->
        <div class="p-3 border-bottom bg-light">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="m-0 fw-bold text-primary d-flex align-items-center gap-2">
              <i class="bi bi-headset"></i> Trung tâm hỗ trợ
            </h5>
            <button 
              class="btn btn-primary btn-sm rounded-circle shadow-sm"
              title="Tạo yêu cầu mới"
              @click="showNewSessionModal = true"
              data-bs-toggle="modal" 
              data-bs-target="#newSupportModal"
            >
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          
          <div class="input-group input-group-sm mb-0">
            <span class="input-group-text bg-white border-end-0 text-muted">
              <i class="bi bi-search"></i>
            </span>
            <input 
              type="text" 
              class="form-control border-start-0 ps-0" 
              placeholder="Tìm kiếm chủ đề..."
              v-model="searchQuery"
              @keyup.enter="fetchSessions"
            >
          </div>
        </div>

        <!-- Sessions List -->
        <div class="flex-grow-1 overflow-auto session-list">
          <div v-if="isLoadingSessions" class="text-center p-4 text-muted">
            <div class="spinner-border spinner-border-sm mb-2 text-primary" role="status"></div>
            <div>Đang tải...</div>
          </div>
          
          <template v-else>
            <div v-if="sessions.length === 0" class="p-4 text-center text-muted">
              <i class="bi bi-inbox fs-1 d-block mb-2 text-black-50"></i>
              Chưa có yêu cầu hỗ trợ nào.
            </div>
            
            <div 
              v-for="session in sessions" 
              :key="session.id"
              @click="selectSession(session)"
              class="session-item p-3 border-bottom"
              :class="{'active-session': currentSessionId === session.id}"
            >
              <div class="d-flex justify-content-between align-items-start mb-1">
                <div class="fw-bold text-truncate d-flex align-items-center gap-2">
                  <div class="avatar-circle bg-primary text-white flex-shrink-0">
                    <i class="bi bi-ticket-detailed"></i>
                  </div>
                  {{ session.tieu_de || 'Không có tiêu đề' }}
                </div>
                <small class="text-muted text-nowrap ms-2" v-if="session.messages && session.messages.length > 0">
                  {{ formatTime(session.messages[0].created_at) }}
                </small>
              </div>
              <div class="text-muted small text-truncate ms-5" v-if="session.messages && session.messages.length > 0">
                <span v-if="session.messages[0].role === 'admin'" class="text-primary fw-medium">Admin: </span>
                {{ session.messages[0].content }}
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Chat Area -->
      <div class="col-md-8 col-lg-9 d-flex flex-column bg-chat h-100 overflow-hidden">
        
        <template v-if="currentSessionId">
          <!-- Chat Header -->
          <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between shadow-sm z-1">
            <div class="d-flex align-items-center">
              <div class="avatar-circle bg-primary text-white me-3 fs-5">
                <i class="bi bi-headset"></i>
              </div>
              <div>
                <h6 class="m-0 fw-bold">{{ currentSessionDetails?.tieu_de }}</h6>
                <div class="small text-success d-flex align-items-center">
                  <span class="status-dot bg-success me-1"></span> Đang xử lý
                </div>
              </div>
            </div>
            <div class="text-muted small bg-light px-2 py-1 rounded">
              ID: {{ currentSessionDetails?.session_key.substring(0, 8) }}...
            </div>
          </div>

          <!-- Messages Area -->
          <div class="flex-grow-1 overflow-auto p-4 chat-messages-container" ref="messagesContainer" @scroll="onScroll">
            
            <div v-if="isLoadingMessages" class="text-center mt-5">
              <div class="spinner-border text-primary" role="status"></div>
            </div>
            
            <template v-else>
              <div v-if="isLoadingMore" class="text-center mb-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              </div>
              <div 
                v-for="(msg, index) in messages" 
                :key="msg.id" 
                class="mb-3 d-flex flex-column"
                :class="{
                  'align-items-end': msg.role === 'user',
                  'align-items-start': msg.role === 'admin' || msg.role === 'assistant'
                }"
              >
                <!-- Role badge -->
                <div class="small mb-1 text-muted d-flex align-items-center" v-if="index === 0 || messages[index-1].role !== msg.role">
                  <template v-if="msg.role === 'admin' || msg.role === 'assistant'">
                    <span class="badge bg-primary text-white rounded-pill me-1"><i class="bi bi-headset"></i> Support</span>
                    {{ msg.admin_name || 'Admin BusSafe' }}
                  </template>
                  <template v-else>
                    <i class="bi bi-person-fill me-1"></i> Nhà xe
                  </template>
                </div>
                
                <!-- Message Bubble -->
                <div 
                  class="message-bubble shadow-sm"
                  :class="{
                    'bg-primary text-white user-bubble': msg.role === 'user',
                    'bg-white text-dark border admin-bubble': msg.role === 'admin' || msg.role === 'assistant'
                  }"
                  :title="msg.role === 'admin' ? (msg.admin_name || 'Admin') : 'Nhà xe'"
                >
                  <div class="content preserve-lines">{{ msg.content }}</div>
                  <div class="timestamp text-end mt-1 small opacity-75" :class="{'text-white': msg.role === 'user'}">
                    {{ formatTime(msg.created_at) }}
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Input Area -->
          <div class="p-3 bg-white border-top">
            <div class="input-group shadow-sm rounded-pill overflow-hidden border focus-ring-group">
              <input 
                type="text" 
                class="form-control border-0 px-4 py-3 shadow-none" 
                placeholder="Nhập tin nhắn..." 
                v-model="newMessage"
                @keyup.enter="sendMessage"
                :disabled="isSending"
              >
              <button 
                class="btn btn-primary px-4 border-0 d-flex align-items-center gap-2" 
                type="button" 
                @click="sendMessage"
                :disabled="isSending || !newMessage.trim()"
              >
                <span v-if="isSending" class="spinner-border spinner-border-sm" role="status"></span>
                <i v-else class="bi bi-send-fill"></i>
                <span class="d-none d-md-inline fw-semibold">Gửi</span>
              </button>
            </div>
          </div>
        </template>
        
        <!-- Empty State -->
        <div v-else class="flex-grow-1 d-flex flex-column justify-content-center align-items-center text-muted bg-light">
          <div class="empty-state-icon bg-white shadow-sm rounded-circle p-4 mb-3">
            <i class="bi bi-chat-dots fs-1 text-primary"></i>
          </div>
          <h5 class="fw-bold text-dark">Hỗ trợ Nhà Xe</h5>
          <p class="mb-0 text-center px-4">Chọn một yêu cầu bên trái hoặc tạo yêu cầu mới<br>để bắt đầu trò chuyện với đội ngũ hỗ trợ BusSafe.</p>
        </div>
        
      </div>
    </div>
  </div>

  <!-- Modal Tạo Yêu Cầu Mới -->
  <div class="modal fade" id="newSupportModal" tabindex="-1" aria-labelledby="newSupportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white border-0">
          <h5 class="modal-title fw-bold" id="newSupportModalLabel"><i class="bi bi-plus-circle me-2"></i>Tạo Yêu Cầu Hỗ Trợ Mới</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <form @submit.prevent="createNewSession">
            <div class="mb-4">
              <label class="form-label fw-bold text-secondary small text-uppercase">Chủ đề hỗ trợ <span class="text-danger">*</span></label>
              <select class="form-select form-select-lg bg-light" v-model="newSessionData.tieu_de" required>
                <option v-for="topic in supportTopics" :key="topic" :value="topic">{{ topic }}</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold text-secondary small text-uppercase">Nội dung chi tiết <span class="text-danger">*</span></label>
              <textarea 
                class="form-control bg-light" 
                rows="4" 
                placeholder="Mô tả chi tiết vấn đề bạn đang gặp phải để admin có thể hỗ trợ nhanh nhất..." 
                v-model="newSessionData.noi_dung"
                required
              ></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Hủy</button>
              <button type="submit" class="btn btn-primary px-4 fw-medium" :disabled="isCreatingSession || !newSessionData.tieu_de || !newSessionData.noi_dung">
                <span v-if="isCreatingSession" class="spinner-border spinner-border-sm me-2" role="status"></span>
                <i v-else class="bi bi-send-fill me-2"></i> Gửi Yêu Cầu
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-support-container {
  height: calc(100vh - 100px);
  max-height: 900px;
}

.bg-chat {
  background-color: #f8f9fa;
  background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
  background-size: 20px 20px;
}

.session-list {
  scrollbar-width: thin;
}

.session-item {
  cursor: pointer;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.session-item:hover {
  background-color: #f8f9fa;
}

.active-session {
  background-color: #f0f4ff;
  border-left-color: #0d6efd;
}

.avatar-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.chat-messages-container {
  scrollbar-width: thin;
}

.message-bubble {
  max-width: 75%;
  padding: 12px 16px;
  border-radius: 1rem;
  line-height: 1.5;
  position: relative;
}

/* User (NhaXe) bubble is right, Admin bubble is left */
.user-bubble {
  border-bottom-right-radius: 0.25rem;
  background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
}

.admin-bubble {
  border-bottom-left-radius: 0.25rem;
}

.preserve-lines {
  white-space: pre-wrap;
  word-break: break-word;
}

.focus-ring-group:focus-within {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
  border-color: #86b7fe !important;
}

.empty-state-icon {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
