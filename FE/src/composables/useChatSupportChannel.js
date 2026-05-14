import { ref, onUnmounted } from 'vue';
import { createEcho } from '@/utils/echo.js';

/**
 * Vue composable để subscribe/unsubscribe WebSocket chat support channels (Pusher).
 * Dùng public channel không cần auth.
 *
 * Sử dụng:
 *   const { subscribe, unsubscribe, unsubscribeAll } = useChatSupportChannel()
 *   subscribe(sessionId, (data) => { ... })  // Lắng nghe realtime
 *   unsubscribe(sessionId)                   // Dừng lắng nghe 1 session
 *   unsubscribeAll()                         // Dừng tất cả
 */
export function useChatSupportChannel() {
  /** @type {ReturnType<typeof createEcho> | undefined} */
  let echoInstance;
  const subscribedChannels = ref(new Map()); // sessionId → channel name

  const getEcho = () => {
    if (echoInstance === undefined) {
      echoInstance = createEcho(null);
    }
    return echoInstance;
  };

  /**
   * Subscribe vào channel tracking của 1 session chat.
   * @param {number} sessionId - ID của chat session
   * @param {Function} onMessage - Callback nhận dữ liệu tin nhắn mới
   */
  const subscribe = (sessionId, onMessage) => {
    if (subscribedChannels.value.has(sessionId)) return; // Đã subscribe rồi

    const echo = getEcho();
    if (!echo) return;

    const channelName = `chat-support.session.${sessionId}`;

    echo.channel(channelName)
      .listen('.chat.message_sent', (data) => {
        if (typeof onMessage === 'function') {
          onMessage(data);
        }
      });

    subscribedChannels.value.set(sessionId, channelName);
  };

  /**
   * Unsubscribe khỏi channel tracking của 1 session.
   */
  const unsubscribe = (sessionId) => {
    const channelName = subscribedChannels.value.get(sessionId);
    if (channelName && echoInstance) {
      echoInstance.leave(channelName);
      subscribedChannels.value.delete(sessionId);
    }
  };

  /**
   * Unsubscribe tất cả channels.
   */
  const unsubscribeAll = () => {
    if (!echoInstance) return;
    for (const [sessionId, channelName] of subscribedChannels.value) {
      echoInstance.leave(channelName);
    }
    subscribedChannels.value.clear();
  };

  /**
   * Kiểm tra session đã được subscribe chưa.
   */
  const isSubscribed = (sessionId) => subscribedChannels.value.has(sessionId);

  // Cleanup khi component unmount
  onUnmounted(() => {
    unsubscribeAll();
    if (echoInstance) {
      echoInstance.disconnect();
    }
    echoInstance = undefined;
  });

  return {
    subscribe,
    unsubscribe,
    unsubscribeAll,
    isSubscribed,
    subscribedChannels,
  };
}
