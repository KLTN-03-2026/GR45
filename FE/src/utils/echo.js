import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Tạo instance Echo + Pusher cho WebSocket connections.
 * Sử dụng: const echo = createEcho(token)
 *          echo.channel('tracking.trip.1').listen('.tracking.updated', cb)
 */
export function createEcho(token = null) {
  window.Pusher = Pusher;

  const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
  const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
  let apiUrl = import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/';
  if (!apiUrl.endsWith('/')) apiUrl += '/';

  const options = {
    broadcaster: 'pusher',
    key: pusherKey,
    cluster: pusherCluster,
    forceTLS: true,
  };

  // Nếu có token, thêm auth cho private channels
  if (token) {
    options.authEndpoint = `${apiUrl}v1/nha-xe/broadcasting/auth`;
    options.auth = {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'ngrok-skip-browser-warning': 'true'
      },
    };
  }

  return new Echo(options);
}
