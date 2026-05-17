/**
 * drowsinessAgent.js — AI Agent phát hiện ngủ gật sử dụng MediaPipe FaceLandmarker + EAR
 *
 * Module độc lập, export class DrowsinessAgent.
 * Workflow: Camera → FaceLandmarker → EAR calc → Violation → Snapshot → API
 */

import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';
import driverApi from '@/api/driverApi';

// ===== EAR Landmarks Index (MediaPipe 478 landmarks) =====
// Mắt trái (right eye trong mirror mode)
const LEFT_EYE = {
  top: [159, 158, 160],    // mí trên
  bottom: [145, 153, 144], // mí dưới
  left: 33,                // khoé trái
  right: 133,              // khoé phải
};
// Mắt phải
const RIGHT_EYE = {
  top: [386, 387, 385],
  bottom: [374, 373, 380],
  left: 362,
  right: 263,
};

// ===== Defaults =====
const DEFAULT_CONFIG = {
  earThreshold: 0.22,
  violationDurationMs: 3000, // ~3s thay vì đếm frame
  snapshotWidth: 640,
  snapshotHeight: 480,
  cooldownMs: 10000,       // 10s giữa các lần chụp
};

/**
 * Tính khoảng cách Euclidean 2D
 */
function dist(p1, p2) {
  return Math.sqrt((p1.x - p2.x) ** 2 + (p1.y - p2.y) ** 2);
}

/**
 * Tính EAR cho 1 mắt
 * EAR = (|p2-p6| + |p3-p5|) / (2 * |p1-p4|)
 */
function calcEAR(landmarks, eyeDef) {
  const p1 = landmarks[eyeDef.left];
  const p4 = landmarks[eyeDef.right];

  // Trung bình các điểm trên/dưới
  let vertSum = 0;
  for (let i = 0; i < eyeDef.top.length; i++) {
    vertSum += dist(landmarks[eyeDef.top[i]], landmarks[eyeDef.bottom[i]]);
  }
  const vertAvg = vertSum / eyeDef.top.length;
  const horiz = dist(p1, p4);

  if (horiz === 0) return 0.3; // tránh chia 0
  return vertAvg / (2 * horiz) * 3; // scale factor do dùng 3 cặp điểm
}

/**
 * Tạo beep cảnh báo bằng Web Audio API
 */
function createAlertSound() {
  let audioCtx = null;
  return {
    play() {
      try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        // Beep 3 lần
        for (let i = 0; i < 3; i++) {
          const osc = audioCtx.createOscillator();
          const gain = audioCtx.createGain();
          osc.connect(gain);
          gain.connect(audioCtx.destination);
          osc.frequency.value = 880; // A5 note
          osc.type = 'square';
          gain.gain.value = 0.3;
          const startTime = audioCtx.currentTime + i * 0.3;
          osc.start(startTime);
          osc.stop(startTime + 0.15);
        }
      } catch (e) {
        console.warn('Audio alert failed:', e);
      }
    },
  };
}

// ===== Main Class =====
export class DrowsinessAgent {
  constructor() {
    this.faceLandmarker = null;
    this.video = null;
    this.canvas = null;
    this.ctx = null;
    this.running = false;
    this.animFrameId = null;
    this.isExternalStream = false;

    // Counters
    this.closedFrameCount = 0;
    this.closedStartTime = null;
    this.violationActive = false;
    this.lastViolationTime = 0;

    // Config (có thể load từ API)
    this.config = { ...DEFAULT_CONFIG };

    // Callbacks
    this.onStatusChange = null;   // (status: 'normal'|'warning'|'danger', ear: number) => void
    this.onViolation = null;      // (data: {ear, imageUrl, ...}) => void
    this.onLandmarks = null;      // (landmarks: array) => void
    this.onFpsUpdate = null;      // (fps: number) => void

    // Context
    this.tripId = null;
    this.position = { lat: 0, lng: 0 };

    // FPS tracking
    this._frameCount = 0;
    this._lastFpsTime = performance.now();
    this._currentFps = 0;

    // Sound
    this._alertSound = createAlertSound();
  }

  /**
   * Khởi tạo model MediaPipe
   */
  async init() {
    const vision = await FilesetResolver.forVisionTasks(
      'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm'
    );

    this.faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
      baseOptions: {
        modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
        delegate: 'GPU',
      },
      runningMode: 'VIDEO',
      numFaces: 1,
      outputFaceBlendshapes: false,
      outputFacialTransformationMatrixes: false,
    });

    // Load cấu hình AI từ backend
    await this.loadConfig();
  }

  /**
   * Load ngưỡng EAR từ backend (CauHinhAiTaiXe)
   */
  async loadConfig() {
    try {
      const res = await driverApi.getCauHinhAi();
      const data = res?.data || res;
      if (data?.ear_threshold) {
        this.config.earThreshold = Number(data.ear_threshold);
      }
      if (data?.nguong_ms) {
        // Sử dụng trực tiếp ms thay vì quy đổi ra frame
        this.config.violationDurationMs = Number(data.nguong_ms);
      }
    } catch (e) {
      console.warn('Không lấy được cấu hình AI, dùng mặc định:', e.message);
    }
  }

  /**
   * Attach video element + overlay canvas
   */
  attachElements(videoEl, canvasEl) {
    this.video = videoEl;
    this.canvas = canvasEl;
    this.ctx = canvasEl.getContext('2d');
  }

  /**
   * Bắt đầu camera + vòng lặp giám sát
   */
  async start(externalStream = null) {
    if (!this.faceLandmarker || !this.video) {
      throw new Error('Agent chưa init hoặc chưa attach video element');
    }

    this.isExternalStream = !!externalStream;
    let stream = externalStream;

    if (!stream) {
      // Xin quyền camera
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
        audio: false,
      });
    }

    this.video.srcObject = stream;
    await this.video.play();

    // Resize canvas theo video
    this.canvas.width = this.video.videoWidth;
    this.canvas.height = this.video.videoHeight;

    this.running = true;
    this.closedFrameCount = 0;
    this.closedStartTime = null;
    this.violationActive = false;

    this._processFrame();
  }

  /**
   * Dừng giám sát
   */
  stop() {
    this.running = false;
    if (this.animFrameId) {
      cancelAnimationFrame(this.animFrameId);
      this.animFrameId = null;
    }
    if (this.video?.srcObject) {
      if (!this.isExternalStream) {
        this.video.srcObject.getTracks().forEach((t) => t.stop());
      }
      this.video.srcObject = null;
    }
  }

  /**
   * Vòng lặp xử lý mỗi frame
   */
  _processFrame() {
    if (!this.running) return;

    const now = performance.now();

    // FPS counter
    this._frameCount++;
    if (now - this._lastFpsTime >= 1000) {
      this._currentFps = this._frameCount;
      this._frameCount = 0;
      this._lastFpsTime = now;
      this.onFpsUpdate?.(this._currentFps);
    }

    if (this.video.readyState >= 2) {
      const results = this.faceLandmarker.detectForVideo(this.video, now);
      this._handleResults(results, now);
    }

    this.animFrameId = requestAnimationFrame(() => this._processFrame());
  }

  /**
   * Xử lý kết quả FaceLandmarker
   */
  _handleResults(results, timestamp) {
    // Clear canvas
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    if (!results.faceLandmarks || results.faceLandmarks.length === 0) {
      this.onStatusChange?.('no_face', 0);
      return;
    }

    const landmarks = results.faceLandmarks[0];
    this.onLandmarks?.(landmarks);

    // Tính EAR
    const earLeft = calcEAR(landmarks, LEFT_EYE);
    const earRight = calcEAR(landmarks, RIGHT_EYE);
    const earAvg = (earLeft + earRight) / 2;

    // Vẽ landmarks mắt overlay
    this._drawEyeLandmarks(landmarks);

    // Logic phát hiện dựa trên thời gian thực tế (chống FPS drop khi chạy cùng YOLO)
    if (earAvg < this.config.earThreshold) {
      this.closedFrameCount++;
      if (this.closedStartTime === null) {
        this.closedStartTime = timestamp;
      }
      
      const closedDuration = timestamp - this.closedStartTime;

      if (closedDuration >= this.config.violationDurationMs) {
        // VI PHẠM
        this.onStatusChange?.('danger', earAvg);
        this._handleViolation(earAvg, timestamp);
      } else if (closedDuration >= this.config.violationDurationMs * 0.5) {
        this.onStatusChange?.('warning', earAvg);
      }
    } else {
      // Mắt mở → reset
      if (this.closedStartTime !== null) {
        this.closedStartTime = null;
        this.closedFrameCount = 0;
        this.violationActive = false;
      }
      this.onStatusChange?.('normal', earAvg);
    }
  }

  /**
   * Xử lý khi phát hiện vi phạm
   */
  async _handleViolation(ear, timestamp) {
    // Cooldown: chỉ chụp 1 ảnh / đợt ngủ gật
    if (this.violationActive) return;
    if (timestamp - this.lastViolationTime < this.config.cooldownMs) return;

    this.violationActive = true;
    this.lastViolationTime = timestamp;

    // 1. Phát âm thanh cảnh báo
    this._alertSound.play();

    // 2. Chụp snapshot
    const base64Image = this._captureSnapshot();

    // 3. Gửi API (background, không block)
    this._sendViolationToBackend(ear, base64Image);

    // Callback
    this.onViolation?.({ ear, timestamp });
  }

  /**
   * Capture frame từ video → base64 JPEG
   */
  _captureSnapshot() {
    const w = this.config.snapshotWidth;
    const h = this.config.snapshotHeight;
    const tmpCanvas = document.createElement('canvas');
    tmpCanvas.width = w;
    tmpCanvas.height = h;
    const tmpCtx = tmpCanvas.getContext('2d');
    tmpCtx.drawImage(this.video, 0, 0, w, h);
    return tmpCanvas.toDataURL('image/jpeg', 0.8);
  }

  /**
   * Gửi vi phạm lên backend (async, không block UI)
   */
  async _sendViolationToBackend(ear, base64Image) {
    if (!this.tripId) {
      console.warn('Không có tripId, bỏ qua upload vi phạm');
      return;
    }

    try {
      await driverApi.postBaoDong({
        id_chuyen_xe: this.tripId,
        loai_bao_dong: 'ngu_gat',
        muc_do: 'nguy_hiem',
        anh_vi_pham: base64Image,
        vi_do_luc_bao: this.position.lat || null,
        kinh_do_luc_bao: this.position.lng || null,
        du_lieu_phat_hien: {
          ear_value: ear,
          ear_threshold: this.config.earThreshold,
          closed_frames: this.closedFrameCount,
          fps: this._currentFps,
          timestamp: new Date().toISOString(),
        },
      });
      console.log('✅ Đã gửi báo động ngủ gật lên server');
    } catch (e) {
      console.error('❌ Lỗi gửi báo động:', e);
    }
  }

  /**
   * Vẽ landmarks mắt lên canvas overlay
   */
  _drawEyeLandmarks(landmarks) {
    const w = this.canvas.width;
    const h = this.canvas.height;

    const drawEye = (eyeDef, color) => {
      const allIndices = [...eyeDef.top, ...eyeDef.bottom, eyeDef.left, eyeDef.right];

      this.ctx.beginPath();
      this.ctx.strokeStyle = color;
      this.ctx.lineWidth = 2;

      // Vẽ contour mắt
      const contour = [
        eyeDef.left,
        ...eyeDef.top,
        eyeDef.right,
        ...[...eyeDef.bottom].reverse(),
      ];

      contour.forEach((idx, i) => {
        const p = landmarks[idx];
        const x = p.x * w;
        const y = p.y * h;
        if (i === 0) this.ctx.moveTo(x, y);
        else this.ctx.lineTo(x, y);
      });
      this.ctx.closePath();
      this.ctx.stroke();

      // Vẽ các điểm
      allIndices.forEach((idx) => {
        const p = landmarks[idx];
        this.ctx.beginPath();
        this.ctx.arc(p.x * w, p.y * h, 2, 0, Math.PI * 2);
        this.ctx.fillStyle = color;
        this.ctx.fill();
      });
    };

    const earLeft = calcEAR(landmarks, LEFT_EYE);
    const earRight = calcEAR(landmarks, RIGHT_EYE);
    const earAvg = (earLeft + earRight) / 2;

    const color = earAvg < this.config.earThreshold ? '#ef4444' : '#4ade80';
    drawEye(LEFT_EYE, color);
    drawEye(RIGHT_EYE, color);
  }

  /** Cập nhật vị trí hiện tại */
  setPosition(lat, lng) {
    this.position = { lat, lng };
  }

  /** Cập nhật trip ID */
  setTripId(id) {
    this.tripId = id;
  }

  /** Lấy FPS hiện tại */
  getFps() {
    return this._currentFps;
  }
}

export default DrowsinessAgent;
