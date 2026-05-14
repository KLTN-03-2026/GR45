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
 * Echo ↔ Laravel **Reverb**:
 * Laravel Echo ≥2 có `broadcaster: 'reverb'` → gộp đúng tùy chọn WS (cluster rỗng, host/port của bạn).
 * Dùng `broadcaster: 'pusher'` + cluster `mt1` trên **Reverb self-host** dễ khiến pusher-js cư xử như Cloud (thử wss không cần, trỏ sai).
 *
 * Nếu đủ **VITE_PUSHER_APP_KEY**: kết nối Pusher Cloud (`sockjs*.pusher.com`).
 *
 * BE Reverb: `BROADCAST_CONNECTION=reverb` + chạy `php artisan reverb:start` (cùng REVERB_SERVER_PORT / VITE_REVERB_PORT).
 */
export function buildLaravelEchoTransportOptions() {
  if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
  }

  const reverbKey = String(import.meta.env.VITE_REVERB_APP_KEY ?? '').trim();
  const pusherKey = String(import.meta.env.VITE_PUSHER_APP_KEY ?? '').trim();
  const pusherCluster =
    String(import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1').trim() || 'mt1';

  const scheme = (import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase();
  const portRaw = import.meta.env.VITE_REVERB_PORT;
  const port =
    portRaw !== undefined && portRaw !== ''
      ? Number(portRaw)
      : scheme === 'https'
        ? 443
        : 80;

  const host = resolveReverbWsHost(import.meta.env.VITE_REVERB_HOST);

  if (reverbKey.length > 0) {
    if (
      import.meta.env.DEV &&
      port === 8000 &&
      /:8000\b/.test(String(import.meta.env.VITE_API_URL || ''))
    ) {
      console.warn(
        '[Echo/Reverb] VITE_REVERB_PORT=8000 gần như luôn sai: :8000 là HTTP của `php artisan serve`. ' +
          'Đặt VITE_REVERB_PORT = cổng Reverb (khớp BE `REVERB_SERVER_PORT`, thường 8080), restart `npm run dev`.',
      );
    }
    return {
      broadcaster: 'reverb',
      key: reverbKey,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      /** Dev http: chỉ ws — tránh spam lỗi wss://127.0.0.1:8080 */
      enabledTransports: scheme === 'https' ? ['wss', 'ws'] : ['ws'],
      disableStats: true,
    };
  }

  if (pusherKey.length > 0) {
    return {
      broadcaster: 'pusher',
      key: pusherKey,
      cluster: pusherCluster,
      forceTLS: true,
    };
  }

  return null;
}

export function isEchoRealtimeConfigured() {
  return buildLaravelEchoTransportOptions() != null;
}

function normalizedApiUrl() {
  let apiUrl = import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/';
  if (!apiUrl.endsWith('/')) apiUrl += '/';
  return apiUrl;
}

/**
 * Echo cho api prefix `v1/nha-xe` (chat hỗ trợ, kênh private nhà xe, …).
 * @param {string | null} token Bearer nhà xe
 */
export function createEcho(token = null) {
  const transport = buildLaravelEchoTransportOptions();
  if (!transport) {
    console.error(
      '[Echo] Thiếu cấu hình realtime. Trong FE .env: ' +
        '(1) Reverb: VITE_REVERB_APP_KEY + host/port + BE `php artisan reverb:start`, hoặc ' +
        '(2) Pusher Cloud: VITE_PUSHER_APP_KEY + VITE_PUSHER_APP_CLUSTER. ' +
        'Restart `npm run dev`.',
    );
    return null;
  }

  const apiUrl = normalizedApiUrl();
  /** @type Record<string, unknown> */
  const options = { ...transport };

  if (token) {
    options.authEndpoint = `${apiUrl}v1/nha-xe/broadcasting/auth`;
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
