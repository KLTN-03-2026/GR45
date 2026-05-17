import { ref, onUnmounted } from "vue";
import { createEcho } from "@/utils/echo.js";

/**
 * Live support (live_support_sessions) — kênh Echo `live-support.session.{publicId}`
 * (Laravel broadcast `LiveSupportMessageSentEvent` / `.live_support.message_created`).
 *
 * `viewer`: vai trò UI — ảnh hưởng map `sender_type` → `role` bubble (admin | user | assistant).
 */
export function useLiveSupportChannel(viewer = "admin_panel") {
  
  let echoInstance;

  /** Kênh `live-support.inbox.customer` — refetch sidebar admin khi có phiên/tin (không cần subscribe từng public_id). */
  let customerInboxSubscribed = false;

  
  const subscribedChannels = ref(new Map());

  const getEcho = () => {
    if (echoInstance === undefined) {
      echoInstance = createEcho(null);
    }
    return echoInstance;
  };

  const mapSenderToRole = (senderType) => {
    if (viewer === "nhaxe_busafe") {
      if (senderType === "admin") return "admin";
      if (senderType === "nha_xe") return "user";
      if (senderType === "chatbot") return "assistant";
      return "user";
    }
    if (senderType === "customer" || senderType === "system") return "user";
    if (senderType === "chatbot") return "assistant";
    if (viewer === "admin_panel" || viewer === "customer_widget") {
      if (senderType === "admin" || senderType === "nha_xe") return "admin";
    }
    if (viewer === "nha_xe_panel") {
      if (senderType === "admin") return "assistant";
      if (senderType === "nha_xe") return "admin";
    }
    return "user";
  };

  const normalizePayload = (data) => ({
    id: data.id,
    thread_type: data.thread_type ?? null,
    role: mapSenderToRole(data.sender_type),
    content: data.body ?? "",
    id_admin: data.sender_admin_id ?? null,
    admin_name: data.admin_name ?? null,
    meta: null,
    created_at: data.created_at,
  });

  
  const subscribe = (publicId, onMessage, options = {}) => {
    if (!publicId || subscribedChannels.value.has(publicId)) return;

    const echo = getEcho();
    if (!echo) return;

    const channelName = `live-support.session.${publicId}`;

    const channel = echo.channel(channelName);

    channel.listen(".live_support.message_created", (data) => {
      if (typeof onMessage === "function") {
        onMessage(normalizePayload(data));
      }
    });

    const onSessionEnded = options.onSessionEnded;
    if (typeof onSessionEnded === "function") {
      channel.listen(".live_support.session_resolved", () => {
        onSessionEnded({ kind: "resolved", public_id: publicId });
      });
      channel.listen(".live_support.customer_disconnected", () => {
        onSessionEnded({ kind: "customer_disconnected", public_id: publicId });
      });
    }

    subscribedChannels.value.set(publicId, channelName);
  };

  
  const subscribeCustomerInbox = (onPing) => {
    const echo = getEcho();
    if (!echo || customerInboxSubscribed) return;

    customerInboxSubscribed = true;
    const channel = echo.channel("live-support.inbox.customer");
    channel.listen(".live_support.inbox_ping", (data) => {
      if (typeof onPing === "function") {
        onPing(data && typeof data === "object" ? data : {});
      }
    });
  };

  const unsubscribeCustomerInbox = () => {
    if (!customerInboxSubscribed || !echoInstance) return;
    echoInstance.leave("live-support.inbox.customer");
    customerInboxSubscribed = false;
  };

  const unsubscribe = (publicId) => {
    const channelName = subscribedChannels.value.get(publicId);
    if (channelName && echoInstance) {
      echoInstance.leave(channelName);
      subscribedChannels.value.delete(publicId);
    }
  };

  const unsubscribeAll = () => {
    if (!echoInstance) return;
    for (const [, channelName] of subscribedChannels.value) {
      echoInstance.leave(channelName);
    }
    subscribedChannels.value.clear();
  };

  const isSubscribed = (publicId) => subscribedChannels.value.has(publicId);

  onUnmounted(() => {
    unsubscribeAll();
    unsubscribeCustomerInbox();
    if (echoInstance) {
      echoInstance.disconnect();
    }
    echoInstance = undefined;
  });

  return {
    subscribe,
    subscribeCustomerInbox,
    unsubscribeCustomerInbox,
    unsubscribe,
    unsubscribeAll,
    isSubscribed,
    subscribedChannels,
  };
}
