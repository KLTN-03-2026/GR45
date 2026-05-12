const EchoLib = require('laravel-echo');
const Echo = EchoLib.default || EchoLib;
const PusherLib = require('pusher-js/react-native');
const Pusher = PusherLib.default || PusherLib;

// Register Pusher globally for React Native support
// Register Pusher globally removed due to explicitly passing it in constructor options below

let echoInstance: any = null;

export const createEcho = () => {
  if (echoInstance) return echoInstance;

  const key = process.env.EXPO_PUBLIC_PUSHER_APP_KEY;
  const cluster = process.env.EXPO_PUBLIC_PUSHER_APP_CLUSTER || 'ap1';

  if (!key) {
    console.warn("⚠️ EXPO_PUBLIC_PUSHER_APP_KEY is missing. Real-time capabilities will not function.");
    return null;
  }

  // Safely resolve class in all environments
  let EchoClass: any = null;
  if (typeof Echo === 'function') {
    EchoClass = Echo;
  } else if (typeof (Echo as any).default === 'function') {
    EchoClass = (Echo as any).default;
  } else {
    // Final extreme fallback if somehow mangled
    EchoClass = require('laravel-echo').default || require('laravel-echo');
  }
  
  if (typeof EchoClass !== 'function') {
    console.error("❌ CỰC KỲ NGHIÊM TRỌNG: Không thể phân giải Laravel Echo Constructor.", typeof EchoClass);
    return null;
  }

  let PusherClass: any = null;
  if (typeof Pusher === 'function') {
    PusherClass = Pusher;
  } else if (typeof (Pusher as any).Pusher === 'function') {
    PusherClass = (Pusher as any).Pusher;
  } else if (typeof (Pusher as any).default === 'function') {
    PusherClass = (Pusher as any).default;
  } else {
    const tempPusher = require('pusher-js/react-native');
    PusherClass = tempPusher.Pusher || tempPusher.default || tempPusher;
  }

  if (typeof PusherClass !== 'function') {
     console.error("❌ LỖI: Không phân giải được Pusher constructor.", typeof PusherClass);
     return null;
  }

// Safely inject into window immediately to assist nested libraries
try {
  (window as any).Pusher = PusherClass;
} catch (e) {}

// Enable logging for development/diagnostics
PusherClass.logToConsole = true;

  try {
    echoInstance = new EchoClass({
      broadcaster: 'pusher',
      key: key,
      cluster: cluster,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      // Pass the validated library constructor
      Pusher: PusherClass
    });
    return echoInstance;
  } catch (err) {
    console.error("❌ LỖI KHI KHỞI TẠO ECHO INSTANCE:", err);
    return null;
  }
};
