/**
 * violationDetectionAgent.js — AI Agent phát hiện vật thể vi phạm sử dụng YOLO + ONNX Runtime Web
 *
 * Module độc lập, export class ViolationDetectionAgent.
 * Workflow: Camera → Preprocess → ONNX Inference → NMS → Bounding Boxes → Violation → Snapshot → API
 *
 * Hỗ trợ phát hiện: dao (knife), hút thuốc (smoking), vi phạm khác
 */

import * as ort from 'onnxruntime-web';
import driverApi from '@/api/driverApi';

// ===== Cấu hình mặc định =====
const DEFAULT_CONFIG = {
  modelPath: '/models/yolo-violations.onnx',  // Đường dẫn model ONNX trong public/
  inputSize: 640,                              // Kích thước input model (640x640)
  confidenceThreshold: 0.65,                   // Ngưỡng confidence tối thiểu (nâng cao để giảm false positive)
  iouThreshold: 0.5,                           // Ngưỡng IOU cho NMS
  snapshotWidth: 640,
  snapshotHeight: 480,
  cooldownMs: 30000,                           // 30s giữa các lần gửi vi phạm cùng loại
  detectIntervalMs: 200,                       // Chạy detect mỗi 200ms (~5fps) để giảm tải CPU/GPU
};

// ===== Tên class theo thứ tự output model (COCO 80 classes) =====
const CLASS_NAMES = [
  'person', 'bicycle', 'car', 'motorcycle', 'airplane', 'bus', 'train', 'truck', 'boat', 'traffic light',
  'fire hydrant', 'stop sign', 'parking meter', 'bench', 'bird', 'cat', 'dog', 'horse', 'sheep', 'cow',
  'elephant', 'bear', 'zebra', 'giraffe', 'backpack', 'umbrella', 'handbag', 'tie', 'suitcase', 'frisbee',
  'skis', 'snowboard', 'sports ball', 'kite', 'baseball bat', 'baseball glove', 'skateboard', 'surfboard',
  'tennis racket', 'bottle', 'wine glass', 'cup', 'fork', 'knife', 'spoon', 'bowl', 'banana', 'apple',
  'sandwich', 'orange', 'broccoli', 'carrot', 'hot dog', 'pizza', 'donut', 'cake', 'chair', 'couch',
  'potted plant', 'bed', 'dining table', 'toilet', 'tv', 'laptop', 'mouse', 'remote', 'keyboard', 'cell phone',
  'microwave', 'oven', 'toaster', 'sink', 'refrigerator', 'book', 'clock', 'vase', 'scissors', 'teddy bear',
  'hair drier', 'toothbrush', 'smoking'
];

// ===== Mapping class → loại báo động API =====
// Chỉ map những class thật sự là vi phạm. Bỏ "person" vì tài xế luôn có trong khung hình.
const CLASS_TO_BAO_DONG = {
  knife: 'phat_hien_dao',
  scissors: 'phat_hien_dao',
  'cell phone': 'su_dung_dien_thoai',
  'smoking': 'hut_thuoc',
};

// ===== Mapping class → label hiển thị tiếng Việt =====
const CLASS_LABELS = {
  knife: '🔪 Dao / Vật sắc nhọn',
  scissors: '✂️ Kéo / Vật sắc nhọn',
  'cell phone': '📱 Sử dụng điện thoại',
  'smoking': '🚬 Hút thuốc',
};

// ===== Mapping class → màu bounding box =====
const CLASS_COLORS = {
  knife: '#ef4444',       // Đỏ
  scissors: '#ef4444',    // Đỏ
  'cell phone': '#f59e0b', // Cam
};

/**
 * Tạo beep cảnh báo bằng Web Audio API
 */
function createAlertSound() {
  let audioCtx = null;
  return {
    play() {
      try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        // Beep 2 lần - tone khác với drowsinessAgent
        for (let i = 0; i < 2; i++) {
          const osc = audioCtx.createOscillator();
          const gain = audioCtx.createGain();
          osc.connect(gain);
          gain.connect(audioCtx.destination);
          osc.frequency.value = 660; // Tone khác (E5)
          osc.type = 'triangle';
          gain.gain.value = 0.25;
          const startTime = audioCtx.currentTime + i * 0.25;
          osc.start(startTime);
          osc.stop(startTime + 0.12);
        }
      } catch (e) {
        console.warn('Audio alert (YOLO) failed:', e);
      }
    },
  };
}

// ===== Main Class =====
export class ViolationDetectionAgent {
  constructor() {
    this.session = null;       // ONNX InferenceSession
    this.video = null;
    this.canvas = null;
    this.ctx = null;
    this.running = false;
    this.detectTimerId = null;

    // Cấu hình
    this.config = { ...DEFAULT_CONFIG };

    // Cooldown cho mỗi loại vi phạm
    this.lastViolationTimes = {};

    // Callbacks
    this.onStatusChange = null;   // (status: 'init'|'loading'|'detecting'|'violation'|'error', info?) => void
    this.onDetection = null;      // (detections: [{class, confidence, bbox}]) => void
    this.onViolation = null;      // (data: {className, confidence, ...}) => void
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
   * Khởi tạo ONNX session — load model
   */
  async init() {
    try {
      // Cấu hình ONNX Runtime ưu tiên WASM (tương thích tốt nhất trên browser)
      const options = {
        executionProviders: ['wasm'],
        graphOptimizationLevel: 'all',
      };

      this.session = await ort.InferenceSession.create(
        this.config.modelPath,
        options
      );

      console.log('✅ YOLO ONNX model loaded thành công');
      console.log('   Input names:', this.session.inputNames);
      console.log('   Output names:', this.session.outputNames);
    } catch (e) {
      console.error('❌ Lỗi load YOLO ONNX model:', e);
      throw new Error(`Không thể load model YOLO: ${e.message}`);
    }
  }

  /**
   * Gắn video element + overlay canvas
   */
  attachElements(videoEl, canvasEl) {
    this.video = videoEl;
    this.canvas = canvasEl;
    this.ctx = canvasEl.getContext('2d');
  }

  /**
   * Bắt đầu camera + vòng lặp detect
   */
  async start() {
    if (!this.session || !this.video) {
      throw new Error('Agent chưa init hoặc chưa attach video element');
    }

    // Mở camera — ưu tiên camera sau (environment) để quay cabin
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 640 },
        height: { ideal: 480 },
      },
      audio: false,
    });

    this.video.srcObject = stream;
    await this.video.play();

    // Resize canvas theo video
    this.canvas.width = this.video.videoWidth;
    this.canvas.height = this.video.videoHeight;

    this.running = true;
    this.lastViolationTimes = {};

    // Dùng setInterval thay vì requestAnimationFrame để kiểm soát tần suất detect
    // YOLO inference nặng hơn MediaPipe, nên chạy ~5fps là đủ
    this._startDetectionLoop();
  }

  /**
   * Dừng giám sát
   */
  stop() {
    this.running = false;
    if (this.detectTimerId) {
      clearInterval(this.detectTimerId);
      this.detectTimerId = null;
    }
    if (this.video?.srcObject) {
      this.video.srcObject.getTracks().forEach((t) => t.stop());
      this.video.srcObject = null;
    }
    // Clear canvas
    if (this.ctx && this.canvas) {
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    }
  }

  /**
   * Vòng lặp detect theo interval
   */
  _startDetectionLoop() {
    this.detectTimerId = setInterval(async () => {
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
        try {
          await this._processFrame();
        } catch (e) {
          console.error('Lỗi YOLO inference:', e);
        }
      }
    }, this.config.detectIntervalMs);
  }

  /**
   * Xử lý 1 frame: preprocess → inference → postprocess → draw
   */
  async _processFrame() {
    const { inputSize } = this.config;

    // 1. Preprocess: lấy pixel data từ video, resize về inputSize x inputSize
    const inputTensor = this._preprocess(inputSize);

    // 2. Inference
    const results = await this.session.run({ [this.session.inputNames[0]]: inputTensor });

    // 3. Postprocess: parse output → detections
    const output = results[this.session.outputNames[0]];
    const detections = this._postprocess(output);

    // 4. Clear + vẽ bounding boxes
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this._drawDetections(detections);

    // 5. Xử lý vi phạm nếu có detection thuộc danh sách vi phạm
    const violationDetections = detections.filter(
      (d) => CLASS_TO_BAO_DONG[d.className] != null
    );

    if (violationDetections.length > 0) {
      this.onStatusChange?.('violation', violationDetections);
      this.onDetection?.(detections); // Trả về toàn bộ detections cho UI
      this._handleDetections(violationDetections); // Chỉ gửi API cho vi phạm thực sự
    } else {
      this.onStatusChange?.('detecting', null);
      this.onDetection?.(detections); // Vẫn trả detections cho UI vẽ bbox
    }
  }

  /**
   * Preprocess: Video frame → Float32 tensor [1, 3, H, W] (normalized 0-1)
   */
  _preprocess(size) {
    // Tạo canvas tạm để resize
    const tmpCanvas = document.createElement('canvas');
    tmpCanvas.width = size;
    tmpCanvas.height = size;
    const tmpCtx = tmpCanvas.getContext('2d');

    // Tính toán letterbox (giữ tỷ lệ, padding)
    const vw = this.video.videoWidth;
    const vh = this.video.videoHeight;
    const scale = Math.min(size / vw, size / vh);
    const nw = Math.round(vw * scale);
    const nh = Math.round(vh * scale);
    const dx = (size - nw) / 2;
    const dy = (size - nh) / 2;

    // Nền xám (114/255 theo chuẩn YOLO) rồi vẽ frame lên
    tmpCtx.fillStyle = `rgb(114, 114, 114)`;
    tmpCtx.fillRect(0, 0, size, size);
    tmpCtx.drawImage(this.video, dx, dy, nw, nh);

    // Lưu thông tin letterbox để chuyển đổi tọa độ về gốc
    this._letterbox = { scale, dx, dy };

    // Extract pixel data → Float32Array [1, 3, H, W] CHW format, normalized 0-1
    const imageData = tmpCtx.getImageData(0, 0, size, size);
    const pixels = imageData.data;
    const totalPixels = size * size;

    const float32Data = new Float32Array(1 * 3 * totalPixels);
    for (let i = 0; i < totalPixels; i++) {
      const idx = i * 4;
      float32Data[i] = pixels[idx] / 255.0;                         // R
      float32Data[totalPixels + i] = pixels[idx + 1] / 255.0;       // G
      float32Data[2 * totalPixels + i] = pixels[idx + 2] / 255.0;   // B
    }

    return new ort.Tensor('float32', float32Data, [1, 3, size, size]);
  }

  /**
   * Postprocess: Parse YOLO output → detections list
   * YOLOv8 output: [1, numClasses + 4, numBoxes] (transposed format)
   */
  _postprocess(outputTensor) {
    const data = outputTensor.data;
    const dims = outputTensor.dims;

    // YOLOv8 output thường là [1, 4+numClasses, numBoxes]
    const numClasses = CLASS_NAMES.length;
    let numBoxes, stride;

    // Xử lý cả 2 format phổ biến:
    // Format 1: [1, 4+C, N] (YOLOv8 default) — transposed
    // Format 2: [1, N, 4+C] (một số export)
    if (dims.length === 3) {
      if (dims[1] === 4 + numClasses) {
        // Format 1: [1, 4+C, N] — cần transpose
        numBoxes = dims[2];
        return this._parseTransposedFormat(data, numBoxes, numClasses);
      } else if (dims[2] === 4 + numClasses) {
        // Format 2: [1, N, 4+C]
        numBoxes = dims[1];
        return this._parseStandardFormat(data, numBoxes, numClasses);
      } else {
        // Thử đoán format dựa trên kích thước
        numBoxes = dims[2];
        return this._parseTransposedFormat(data, numBoxes, numClasses);
      }
    }

    console.warn('Output tensor dims không nhận diện được:', dims);
    return [];
  }

  /**
   * Parse format transposed: [1, 4+C, N]
   * Row 0-3: cx, cy, w, h
   * Row 4+: class scores
   */
  _parseTransposedFormat(data, numBoxes, numClasses) {
    const candidates = [];
    const totalRows = 4 + numClasses;

    for (let i = 0; i < numBoxes; i++) {
      // Tìm class có score cao nhất
      let maxScore = 0;
      let maxClassIdx = 0;
      for (let c = 0; c < numClasses; c++) {
        const score = data[(4 + c) * numBoxes + i];
        if (score > maxScore) {
          maxScore = score;
          maxClassIdx = c;
        }
      }

      if (maxScore < this.config.confidenceThreshold) continue;

      // Lấy bounding box (cx, cy, w, h)
      const cx = data[0 * numBoxes + i];
      const cy = data[1 * numBoxes + i];
      const w = data[2 * numBoxes + i];
      const h = data[3 * numBoxes + i];

      candidates.push({
        classIndex: maxClassIdx,
        className: CLASS_NAMES[maxClassIdx] || `class_${maxClassIdx}`,
        confidence: maxScore,
        bbox: [cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2], // x1, y1, x2, y2
      });
    }

    return this._nms(candidates);
  }

  /**
   * Parse format standard: [1, N, 4+C]
   */
  _parseStandardFormat(data, numBoxes, numClasses) {
    const candidates = [];
    const rowSize = 4 + numClasses;

    for (let i = 0; i < numBoxes; i++) {
      const offset = i * rowSize;

      let maxScore = 0;
      let maxClassIdx = 0;
      for (let c = 0; c < numClasses; c++) {
        const score = data[offset + 4 + c];
        if (score > maxScore) {
          maxScore = score;
          maxClassIdx = c;
        }
      }

      if (maxScore < this.config.confidenceThreshold) continue;

      const cx = data[offset + 0];
      const cy = data[offset + 1];
      const w = data[offset + 2];
      const h = data[offset + 3];

      candidates.push({
        classIndex: maxClassIdx,
        className: CLASS_NAMES[maxClassIdx] || `class_${maxClassIdx}`,
        confidence: maxScore,
        bbox: [cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2],
      });
    }

    return this._nms(candidates);
  }

  /**
   * Non-Maximum Suppression (NMS)
   */
  _nms(candidates) {
    // Sắp xếp theo confidence giảm dần
    candidates.sort((a, b) => b.confidence - a.confidence);

    const selected = [];
    const suppressed = new Set();

    for (let i = 0; i < candidates.length; i++) {
      if (suppressed.has(i)) continue;
      selected.push(candidates[i]);

      for (let j = i + 1; j < candidates.length; j++) {
        if (suppressed.has(j)) continue;
        const iou = this._calcIOU(candidates[i].bbox, candidates[j].bbox);
        if (iou > this.config.iouThreshold) {
          suppressed.add(j);
        }
      }
    }

    return selected;
  }

  /**
   * Tính Intersection over Union (IoU) cho 2 bbox [x1,y1,x2,y2]
   */
  _calcIOU(boxA, boxB) {
    const x1 = Math.max(boxA[0], boxB[0]);
    const y1 = Math.max(boxA[1], boxB[1]);
    const x2 = Math.min(boxA[2], boxB[2]);
    const y2 = Math.min(boxA[3], boxB[3]);

    const intersection = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
    const areaA = (boxA[2] - boxA[0]) * (boxA[3] - boxA[1]);
    const areaB = (boxB[2] - boxB[0]) * (boxB[3] - boxB[1]);
    const union = areaA + areaB - intersection;

    return union === 0 ? 0 : intersection / union;
  }

  /**
   * Vẽ bounding boxes + labels lên canvas overlay
   */
  _drawDetections(detections) {
    if (!this._letterbox || detections.length === 0) return;

    const { scale, dx, dy } = this._letterbox;
    const vw = this.canvas.width;
    const vh = this.canvas.height;

    // Chuyển đổi tọa độ từ model space (640x640) về video space
    const inputSize = this.config.inputSize;
    const scaleX = vw / inputSize;
    const scaleY = vh / inputSize;

    detections.forEach((det) => {
      const [x1, y1, x2, y2] = det.bbox;

      // Chuyển tọa độ letterbox → video gốc
      const rx1 = ((x1 - dx) / scale) * (vw / (this.video.videoWidth || vw));
      const ry1 = ((y1 - dy) / scale) * (vh / (this.video.videoHeight || vh));
      const rx2 = ((x2 - dx) / scale) * (vw / (this.video.videoWidth || vw));
      const ry2 = ((y2 - dy) / scale) * (vh / (this.video.videoHeight || vh));

      const bw = rx2 - rx1;
      const bh = ry2 - ry1;

      const color = CLASS_COLORS[det.className] || '#ef4444';
      const label = CLASS_LABELS[det.className] || det.className;
      const confText = `${(det.confidence * 100).toFixed(0)}%`;

      // Vẽ bounding box
      this.ctx.strokeStyle = color;
      this.ctx.lineWidth = 3;
      this.ctx.strokeRect(rx1, ry1, bw, bh);

      // Vẽ fill mờ
      this.ctx.fillStyle = color + '20'; // 12% opacity
      this.ctx.fillRect(rx1, ry1, bw, bh);

      // Vẽ label background
      const labelText = `${label} ${confText}`;
      this.ctx.font = 'bold 14px Inter, sans-serif';
      const textWidth = this.ctx.measureText(labelText).width;
      const labelH = 24;
      const labelY = ry1 > labelH ? ry1 - labelH : ry1;

      this.ctx.fillStyle = color;
      this.ctx.fillRect(rx1, labelY, textWidth + 12, labelH);

      // Vẽ label text
      this.ctx.fillStyle = '#ffffff';
      this.ctx.fillText(labelText, rx1 + 6, labelY + 17);

      // Vẽ 4 góc nhấn mạnh (corner accents)
      const cornerLen = Math.min(20, bw / 4, bh / 4);
      this.ctx.strokeStyle = color;
      this.ctx.lineWidth = 4;
      this.ctx.lineCap = 'round';

      // Góc trên-trái
      this.ctx.beginPath();
      this.ctx.moveTo(rx1, ry1 + cornerLen);
      this.ctx.lineTo(rx1, ry1);
      this.ctx.lineTo(rx1 + cornerLen, ry1);
      this.ctx.stroke();

      // Góc trên-phải
      this.ctx.beginPath();
      this.ctx.moveTo(rx2 - cornerLen, ry1);
      this.ctx.lineTo(rx2, ry1);
      this.ctx.lineTo(rx2, ry1 + cornerLen);
      this.ctx.stroke();

      // Góc dưới-trái
      this.ctx.beginPath();
      this.ctx.moveTo(rx1, ry2 - cornerLen);
      this.ctx.lineTo(rx1, ry2);
      this.ctx.lineTo(rx1 + cornerLen, ry2);
      this.ctx.stroke();

      // Góc dưới-phải
      this.ctx.beginPath();
      this.ctx.moveTo(rx2 - cornerLen, ry2);
      this.ctx.lineTo(rx2, ry2);
      this.ctx.lineTo(rx2, ry2 - cornerLen);
      this.ctx.stroke();
    });
  }

  /**
   * Xử lý khi phát hiện vi phạm
   */
  async _handleDetections(detections) {
    const now = performance.now();

    for (const det of detections) {
      const key = det.className;

      // Cooldown: mỗi loại vi phạm chỉ gửi 1 lần trong khoảng cooldownMs
      if (this.lastViolationTimes[key] && (now - this.lastViolationTimes[key]) < this.config.cooldownMs) {
        continue;
      }

      this.lastViolationTimes[key] = now;

      // 1. Phát âm thanh cảnh báo
      this._alertSound.play();

      // 2. Chụp snapshot
      const base64Image = this._captureSnapshot();

      // 3. Gửi API (background, không block)
      this._sendViolationToBackend(det, base64Image);

      // Callback
      this.onViolation?.({
        className: det.className,
        confidence: det.confidence,
        label: CLASS_LABELS[det.className] || det.className,
        timestamp: Date.now(),
      });
    }
  }

  /**
   * Chụp snapshot từ video → base64 JPEG
   */
  _captureSnapshot() {
    const w = this.config.snapshotWidth;
    const h = this.config.snapshotHeight;
    const tmpCanvas = document.createElement('canvas');
    tmpCanvas.width = w;
    tmpCanvas.height = h;
    const tmpCtx = tmpCanvas.getContext('2d');
    tmpCtx.drawImage(this.video, 0, 0, w, h);

    // Vẽ cả bounding box lên snapshot
    if (this.ctx && this.canvas) {
      tmpCtx.drawImage(this.canvas, 0, 0, w, h);
    }

    return tmpCanvas.toDataURL('image/jpeg', 0.8);
  }

  /**
   * Gửi vi phạm lên backend (async, không block UI)
   */
  async _sendViolationToBackend(detection, base64Image) {
    if (!this.tripId) {
      console.warn('Không có tripId, bỏ qua upload vi phạm YOLO');
      return;
    }

    const loaiBaoDong = CLASS_TO_BAO_DONG[detection.className] || 'vi_pham_khac';

    try {
      await driverApi.postBaoDong({
        id_chuyen_xe: this.tripId,
        loai_bao_dong: loaiBaoDong,
        muc_do: detection.className === 'dao' ? 'khan_cap' : 'canh_bao',
        anh_vi_pham: base64Image,
        vi_do_luc_bao: this.position.lat || null,
        kinh_do_luc_bao: this.position.lng || null,
        du_lieu_phat_hien: {
          model: 'yolo_violations',
          class_name: detection.className,
          confidence: detection.confidence,
          bbox: detection.bbox,
          fps: this._currentFps,
          timestamp: new Date().toISOString(),
        },
      });
      console.log(`✅ Đã gửi báo động YOLO [${detection.className}] lên server`);
    } catch (e) {
      console.error('❌ Lỗi gửi báo động YOLO:', e);
    }
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

export default ViolationDetectionAgent;
