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
const nhaXes = ref([])
const newSessionData = ref({ id_nha_xe: '', tieu_de: '', noi_dung: '' })
const isCreatingSession = ref(false)

const { subscribe, unsubscribeAll, isSubscribed } = useChatSupportChannel()

// Fetch Sessions
const fetchSessions = async () => {
  try {
    isLoadingSessions.value = true
    const res = await axiosClient.get('/v1/admin/ho-tro/nha-xe/sessions', {
      params: { search: searchQuery.value }
    })
    sessions.value = res.data.data.data || res.data.data
  } catch (error) {
    console.error('Failed to fetch sessions', error)
  } finally {
    isLoadingSessions.value = false
  }
}

// Fetch Nha Xes for dropdown
const fetchNhaXes = async () => {
  try {
    const res = await axiosClient.get('/v1/admin/nha-xe/list-minimal')
    nhaXes.value = res.data.data
  } catch (error) {
    console.error('Failed to fetch nha xes', error)
  }
}

// Select Session
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
    const res = await axiosClient.get(`/v1/admin/ho-tro/nha-xe/sessions/${session.id}`)
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
  if (!hasMoreMessages.value || isLoadingMore.value) return;

  const container = messagesContainer.value;
  const previousScrollHeight = container ? container.scrollHeight : 0;

  isLoadingMore.value = true;
  try {
    const res = await axiosClient.get(
      `/v1/admin/ho-tro/nha-xe/sessions/${currentSessionId.value}?page=${currentMessagePage.value + 1}`
    );
    const newMessages = res.data.messages.data.reverse();
    messages.value = [...newMessages, ...messages.value];
    currentMessagePage.value = res.data.messages.current_page;
    hasMoreMessages.value = res.data.messages.current_page < res.data.messages.last_page;

    await nextTick();
    if (container) {
      container.scrollTop = container.scrollHeight - previousScrollHeight;
    }
  } catch (error) {
    console.error("Failed to load more messages", error);
  } finally {
    isLoadingMore.value = false;
  }
};

const onScroll = (e) => {
  if (e.target.scrollTop === 0) {
    loadMoreMessages();
  }
};

const sendMessage = async () => {
  if (!newMessage.value.trim() || !currentSessionId.value || isSending.value) return
  
  const content = newMessage.value.trim()
  newMessage.value = ''
  isSending.value = true
  
  try {
    await axiosClient.post(`/v1/admin/ho-tro/nha-xe/sessions/${currentSessionId.value}/reply`, {
      content
    })
    // Realtime will handle appending
  } catch (error) {
    console.error('Failed to send message', error)
    newMessage.value = content
  } finally {
    isSending.value = false
  }
}

const createSession = async () => {
  if (!newSessionData.value.id_nha_xe) return
  
  isCreatingSession.value = true
  try {
    const res = await axiosClient.post('/v1/admin/ho-tro/nha-xe/sessions', {
      id_nha_xe: newSessionData.value.id_nha_xe,
      tieu_de: newSessionData.value.tieu_de,
      noi_dung: newSessionData.value.noi_dung
    })
    
    const newSession = res.data.data
    // Mock a messages array so preview doesn't break
    if (newSessionData.value.noi_dung) {
      newSession.messages = [{ content: newSessionData.value.noi_dung, created_at: new Date().toISOString() }]
    } else {
      newSession.messages = []
    }
    
    sessions.value = [newSession, ...sessions.value]
    
    // Reset & close modal
    newSessionData.value = { id_nha_xe: '', tieu_de: '', noi_dung: '' }
    showNewSessionModal.value = false
    
    // Select newly created
    selectSession(newSession)
  } catch (error) {
    console.error('Failed to create session', error)
    alert(error.response?.data?.message || 'Có lỗi xảy ra khi tạo cuộc hội thoại')
  } finally {
    isCreatingSession.value = false
  }
}

const scrollToBottom = async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

let searchTimeout = null
const onSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchSessions()
  }, 500)
}

onMounted(() => {
  fetchSessions()
  fetchNhaXes()
})

onUnmounted(() => {
  unsubscribeAll()
})

const getInitials = (name) => {
  if (!name) return 'NX'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const formatTime = (isoString) => {
  if (!isoString) return ''
  const date = new Date(isoString)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="chat-support-container">
    <div class="row g-0 h-100 shadow-sm rounded-4 overflow-hidden chat-wrapper">
      
      <!-- Sidebar Sessions -->
      <div class="col-md-4 col-lg-3 border-end bg-white d-flex flex-column">
        <!-- Sidebar Header -->
        <div class="p-3 border-bottom bg-light">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="m-0 fw-bold text-primary">
              <i class="bi bi-buildings me-2"></i>Hỗ Trợ Nhà Xe
            </h5>
            <span class="badge bg-primary rounded-pill">{{ sessions.length }}</span>
          </div>
          <button class="btn btn-sm btn-outline-primary w-100" @click="showNewSessionModal = true">
            <i class="bi bi-plus-circle me-1"></i> Tạo cuộc hội thoại mới
          </button>
        </div>
        
        <!-- Search -->
        <div class="p-2 border-bottom bg-white">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent border-end-0 text-muted">
              <i class="bi bi-search"></i>
            </span>
            <input 
              type="text" 
              class="form-control border-start-0 ps-0 shadow-none" 
              placeholder="Tìm tên nhà xe..."
              v-model="searchQuery"
              @input="onSearch"
            >
          </div>
        </div>

        <!-- Session List -->
        <div class="flex-grow-1 overflow-auto session-list">
          <div v-if="isLoadingSessions" class="text-center p-4">
            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
            <div class="small mt-2 text-muted">Đang tải...</div>
          </div>
          
          <div v-else-if="sessions.length === 0" class="text-center p-4 text-muted small">
            Không có dữ liệu hội thoại.
          </div>
          
          <div v-else>
            <div 
              v-for="session in sessions" 
              :key="session.id"
              class="session-item p-3 border-bottom cursor-pointer"
              :class="{ 'active': currentSessionId === session.id }"
              @click="selectSession(session)"
            >
              <div class="d-flex align-items-center">
                <!-- Avatar -->
                <div class="avatar-circle me-3 flex-shrink-0" :class="`bg-gradient-${(session.id % 4) + 1}`">
                  {{ getInitials(session.nha_xe?.ten_nha_xe) }}
                </div>
                
                <!-- Info -->
                <div class="flex-grow-1 min-w-0">
                  <div class="d-flex justify-content-between align-items-baseline mb-1">
                    <h6 class="m-0 text-truncate fw-semibold">
                      {{ session.nha_xe?.ten_nha_xe || 'Không rõ' }}
                    </h6>
                    <small class="text-muted ms-2 date-text">
                       {{ session.messages && session.messages.length > 0 ? formatTime(session.messages[0].created_at) : formatTime(session.created_at) }}
                    </small>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="m-0 text-muted small text-truncate pe-2">
                       <span v-if="session.tieu_de" class="fw-bold text-dark me-1">[{{ session.tieu_de }}]</span>
                       {{ session.messages && session.messages.length > 0 ? session.messages[0].content : 'Chưa có tin nhắn' }}
                    </p>
                    <span v-if="isSubscribed(session.id)" class="live-indicator" title="Đang kết nối Realtime"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chat Area -->
      <div class="col-md-8 col-lg-9 d-flex flex-column bg-chat h-100 overflow-hidden">
        
        <template v-if="currentSessionId">
          <!-- Chat Header -->
          <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between shadow-sm z-1">
            <div class="d-flex align-items-center">
              <div class="avatar-circle me-3" :class="`bg-gradient-${(currentSessionId % 4) + 1}`">
                {{ getInitials(currentSessionDetails?.nha_xe?.ten_nha_xe) }}
              </div>
              <div>
                <h5 class="m-0 fw-bold">
                  {{ currentSessionDetails?.nha_xe?.ten_nha_xe || 'Không rõ' }}
                  <span v-if="currentSessionDetails?.tieu_de" class="badge bg-secondary ms-2 fw-normal fs-6">{{ currentSessionDetails.tieu_de }}</span>
                </h5>
                <small class="text-success d-flex align-items-center">
                  <span class="live-indicator me-1"></span> Real-time active
                </small>
              </div>
            </div>
            
            <div class="text-end">
              <span class="badge bg-light text-dark border">ID: {{ currentSessionDetails?.session_key?.substring(0, 8) }}...</span>
              <div class="small text-muted mt-1">{{ currentSessionDetails?.nha_xe?.so_dien_thoai || currentSessionDetails?.nha_xe?.email }}</div>
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
                  'align-items-end': msg.role === 'admin' || msg.role === 'assistant',
                  'align-items-start': msg.role === 'user'
                }"
              >
                <!-- Role badge -->
                <div 
                  class="small mb-1 text-muted d-flex align-items-center"
                  v-if="index === 0 || messages[index-1].role !== msg.role"
                >
                  <template v-if="msg.role === 'user'">
                    <i class="bi bi-buildings-fill me-1"></i> Nhà xe
                  </template>
                  <template v-else-if="msg.role === 'admin'">
                    <span class="badge bg-purple text-white rounded-pill me-1"><i class="bi bi-headset"></i> Support</span>
                    {{ msg.admin_name || 'Admin' }}
                  </template>
                </div>

                <!-- Message Bubble -->
                <div 
                  class="message-bubble shadow-sm"
                  :class="{
                    'bg-primary text-white admin-bubble': msg.role === 'admin',
                    'bg-white text-dark border user-bubble': msg.role !== 'admin'
                  }"
                  :title="msg.role === 'admin' ? (msg.admin_name || 'Admin') : 'Nhà xe'"
                >
                  <div class="content preserve-lines">{{ msg.content }}</div>
                  <div 
                    class="timestamp text-end mt-1 small opacity-75"
                    :class="{'text-white': msg.role === 'admin'}"
                  >
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
                placeholder="Nhập tin nhắn hỗ trợ..."
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
            <i class="bi bi-chat-square-text fs-1 text-primary opacity-75"></i>
          </div>
          <h4 class="fw-semibold text-dark">Chưa chọn hội thoại</h4>
          <p>Vui lòng chọn hoặc tạo một cuộc hội thoại mới với nhà xe để bắt đầu hỗ trợ.</p>
        </div>

      </div>
    </div>
  </div>

  <!-- Modal Tạo Cuộc Hội Thoại Mới -->
  <div v-if="showNewSessionModal" class="modal-backdrop fade show"></div>
  <div class="modal fade show d-block" tabindex="-1" v-if="showNewSessionModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-light border-bottom-0">
          <h5 class="modal-title fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Tạo hội thoại mới với nhà xe</h5>
          <button type="button" class="btn-close shadow-none" @click="showNewSessionModal = false"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-medium text-dark">Chọn nhà xe <span class="text-danger">*</span></label>
            <select class="form-select shadow-none" v-model="newSessionData.id_nha_xe">
              <option value="">-- Chọn nhà xe --</option>
              <option v-for="nx in nhaXes" :key="nx.id" :value="nx.id">
                {{ nx.ten_nha_xe }} ({{ nx.email }})
              </option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium text-dark">Chủ đề hỗ trợ (Không bắt buộc)</label>
            <input type="text" class="form-control shadow-none" v-model="newSessionData.tieu_de" placeholder="Ví dụ: Vấn đề rút tiền, Khiếu nại...">
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium text-dark">Tin nhắn mở đầu (Không bắt buộc)</label>
            <textarea class="form-control shadow-none" v-model="newSessionData.noi_dung" rows="3" placeholder="Xin chào, chúng tôi có thể giúp gì..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light border-top-0">
          <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" @click="showNewSessionModal = false">Hủy</button>
          <button type="button" class="btn btn-primary px-4 rounded-pill" :disabled="!newSessionData.id_nha_xe || isCreatingSession" @click="createSession">
            <span v-if="isCreatingSession" class="spinner-border spinner-border-sm me-2" role="status"></span>
            Tạo hội thoại
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<style scoped>
.chat-support-container {
  height: calc(100vh - 100px);
  padding: 1rem;
}

.chat-wrapper {
  background-color: #f8f9fa;
  border: 1px solid rgba(0,0,0,0.05);
}

.cursor-pointer {
  cursor: pointer;
}

.session-item {
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.session-item:hover {
  background-color: #f8f9fa;
}

.session-item.active {
  background-color: #eef2ff;
  border-left-color: #4f46e5;
}

.bg-chat {
  background-color: #f3f4f6;
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: white;
  font-size: 1.1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.min-w-0 {
  min-width: 0;
}

.date-text {
  font-size: 0.75rem;
  white-space: nowrap;
}

.live-indicator {
  width: 8px;
  height: 8px;
  background-color: #10b981;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
  animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.message-bubble {
  max-width: 75%;
  padding: 0.75rem 1rem;
  border-radius: 1rem;
  position: relative;
  line-height: 1.5;
}

/* Rounded corners depending on role */
.admin-bubble,
.assistant-bubble {
  border-bottom-right-radius: 0.25rem;
}

.admin-bubble {
  background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
  border: none !important;
}

.user-bubble {
  border-bottom-left-radius: 0.25rem;
}

.preserve-lines {
  white-space: pre-wrap;
  word-wrap: break-word;
}

.bg-purple {
  background-color: #8b5cf6;
}

.focus-ring-group:focus-within {
  border-color: #86b7fe !important;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.empty-state-icon {
  width: 100px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Gradients for avatars */
.bg-gradient-1 { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
.bg-gradient-2 { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
.bg-gradient-3 { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
.bg-gradient-4 { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%); }

/* Custom Scrollbar */
.chat-messages-container::-webkit-scrollbar,
.session-list::-webkit-scrollbar {
  width: 6px;
}
.chat-messages-container::-webkit-scrollbar-track,
.session-list::-webkit-scrollbar-track {
  background: transparent;
}
.chat-messages-container::-webkit-scrollbar-thumb,
.session-list::-webkit-scrollbar-thumb {
  background-color: rgba(0,0,0,0.1);
  border-radius: 10px;
}
.chat-messages-container::-webkit-scrollbar-thumb:hover,
.session-list::-webkit-scrollbar-thumb:hover {
  background-color: rgba(0,0,0,0.2);
}
</style>
