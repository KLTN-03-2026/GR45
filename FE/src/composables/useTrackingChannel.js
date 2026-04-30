import { ref, onUnmounted } from 'vue';
import { createEcho } from '@/utils/echo.js';

/**
 * Vue composable để subscribe/unsubscribe WebSocket tracking channels.
 *
 * Sử dụng:
 *   const { subscribe, unsubscribe, unsubscribeAll } = useTrackingChannel()
 *   subscribe(tripId, (data) => { ... })     // Lắng nghe realtime
 *   unsubscribe(tripId)                       // Dừng lắng nghe 1 trip
 *   unsubscribeAll()                          // Dừng tất cả
 */
export function useTrackingChannel() {
  let echoInstance = null;
  const subscribedChannels = ref(new Map()); // tripId → channel name

  const getEcho = () => {
    if (!echoInstance) {
      echoInstance = createEcho();
    }
    return echoInstance;
  };

  /**
   * Subscribe vào channel tracking của 1 chuyến xe.
   * @param {number} tripId - ID chuyến xe
   * @param {Function} onUpdate - Callback nhận dữ liệu tracking mới
   */
  const subscribe = (tripId, onUpdate) => {
    if (subscribedChannels.value.has(tripId)) return; // Đã subscribe rồi

    const echo = getEcho();
    const channelName = `tracking.trip.${tripId}`;

    echo.channel(channelName)
      .listen('.tracking.updated', (data) => {
        if (typeof onUpdate === 'function') {
          onUpdate(data);
        }
      });

    subscribedChannels.value.set(tripId, channelName);
  };

  /**
   * Unsubscribe khỏi channel tracking của 1 chuyến xe.
   */
  const unsubscribe = (tripId) => {
    const channelName = subscribedChannels.value.get(tripId);
    if (channelName && echoInstance) {
      echoInstance.leave(channelName);
      subscribedChannels.value.delete(tripId);
    }
  };

  /**
   * Unsubscribe tất cả channels.
   */
  const unsubscribeAll = () => {
    if (!echoInstance) return;
    for (const [tripId, channelName] of subscribedChannels.value) {
      echoInstance.leave(channelName);
    }
    subscribedChannels.value.clear();
  };

  /**
   * Kiểm tra trip đã được subscribe chưa.
   */
  const isSubscribed = (tripId) => subscribedChannels.value.has(tripId);

  // Cleanup khi component unmount
  onUnmounted(() => {
    unsubscribeAll();
    if (echoInstance) {
      echoInstance.disconnect();
      echoInstance = null;
    }
  });

  return {
    subscribe,
    unsubscribe,
    unsubscribeAll,
    isSubscribed,
    subscribedChannels,
  };
}
