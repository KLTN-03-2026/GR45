import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Mở FE bằng IP LAN (192.168.x) nhưng VITE_REVERB_HOST=127.0.0.1 → WS phải tới hostname trang,
 * không phải loopback của máy client.
 */
function resolveReverbWsHost(configHostRaw) {
  const configured = String(configHostRaw ?? '').trim();
  const pageHost =
    typeof window !== 'undefined' && window.location?.hostname
      ? window.location.hostname
      : '';

  if (!configured) {
    return pageHost || '127.0.0.1';
  }

  const configuredLoopback =
    configured === '127.0.0.1' || configured === 'localhost';
  const pageIsNotLoopback =
    pageHost &&
    pageHost !== '127.0.0.1' &&
    pageHost !== 'localhost';

  if (configuredLoopback && pageIsNotLoopback) {
    return pageHost;
  }

  return configured;
}

/**
 * Echo ↔ Laravel Reverb.
 *
 * BE: `BROADCAST_CONNECTION=reverb` + chạy `php artisan reverb:start`
 * cùng `REVERB_SERVER_PORT` / `VITE_REVERB_PORT`.
 */
export function buildLaravelEchoTransportOptions() {
  if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
  }

  const reverbKey = String(import.meta.env.VITE_REVERB_APP_KEY ?? '').trim();
  if (reverbKey.length === 0) return null;

  const isHttps = typeof window !== 'undefined' && window.location?.protocol === 'https:';
  const pagePort = typeof window !== 'undefined' && window.location?.port ? Number(window.location.port) : null;
  const pageHost = typeof window !== 'undefined' && window.location?.hostname ? window.location.hostname : '';

  // 1. Lấy cấu hình từ .env làm mặc định
  let host = String(import.meta.env.VITE_REVERB_HOST || '127.0.0.1').trim();
  let port = import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 80;
  let scheme = String(import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase();

  // 2. Bảo vệ Mixed Content: Nếu trang đang chạy HTTPS, kết nối WebSocket BẮT BUỘC dùng HTTPS/WSS
  if (isHttps) {
    scheme = 'https';
    // Nếu host cấu hình mặc định là localhost/loopback nhưng page chạy ở domain thật, tự động định tuyến qua proxy Vite
    if ((host === '127.0.0.1' || host === 'localhost') && pageHost && pageHost !== 'localhost' && pageHost !== '127.0.0.1') {
      host = pageHost;
      port = pagePort || 443;
    }
  } else {
    // Nếu chạy HTTP thường (local dev)
    if ((host === '127.0.0.1' || host === 'localhost') && pageHost) {
      host = pageHost;
      port = pagePort || 80;
    }
  }

  console.log(`[Echo] Đang kết nối Reverb: ${scheme === 'https' ? 'wss' : 'ws'}://${host}:${port}`);

  return {
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: scheme === 'https' ? ['wss', 'ws'] : ['ws'],
    disableStats: true,
  };
}

export function isEchoRealtimeConfigured() {
  return buildLaravelEchoTransportOptions() != null;
}

function normalizedApiUrl() {
  let apiUrl = import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/';
  if (!apiUrl.endsWith('/')) apiUrl += '/';
  return apiUrl;
}


export function createEcho(token = null) {
  const transport = buildLaravelEchoTransportOptions();
  if (!transport) {
    console.error(
      '[Echo] Thiếu cấu hình realtime. Trong FE .env: ' +
        'cần VITE_REVERB_APP_KEY + host/port + BE `php artisan reverb:start`. ' +
        'Restart `npm run dev`.',
    );
    return null;
  }

  const apiUrl = normalizedApiUrl();
  
  const options = { ...transport };

  if (token) {
    const endpointPath = apiUrl.endsWith('v1/') ? 'nha-xe/broadcasting/auth' : 'v1/nha-xe/broadcasting/auth';
    options.authEndpoint = `${apiUrl}${endpointPath}`;
    options.auth = {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'ngrok-skip-browser-warning': 'true',
      },
    };
  }

  try {
    return new Echo(options);
  } catch (e) {
    console.error('[Echo] Lỗi khởi tạo:', e?.message || e);
    return null;
  }
}
