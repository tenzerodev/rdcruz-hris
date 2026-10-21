/* Shared camera + face detection helpers (face-api.js, runs fully in the browser). */
window.FaceEngine = (() => {
  const MODEL_URL = document.querySelector('meta[name="face-models"]').content;
  let loading = null;

  function loadModels() {
    if (!loading) {
      loading = (async () => {
        try { await faceapi.tf.setBackend('webgl'); } catch (e) { /* falls back to cpu/wasm */ }
        await faceapi.tf.ready();
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
          faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
      })();
    }
    return loading;
  }

  async function startCamera(video) {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      throw new Error('Camera needs a secure connection (https:// or localhost).');
    }
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
    });
    video.srcObject = stream;
    video.setAttribute('playsinline', '');
    video.muted = true;
    await video.play();
    return stream;
  }

  function stopCamera(video) {
    const stream = video.srcObject;
    if (stream) stream.getTracks().forEach((t) => t.stop());
    video.srcObject = null;
  }

  const detectorOptions = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });

  /** Largest face in the frame with landmarks + 128-d descriptor, or null. Also returns face count. */
  async function detect(video) {
    const results = await faceapi.detectAllFaces(video, detectorOptions()).withFaceLandmarks().withFaceDescriptors();
    if (!results.length) return { face: null, count: 0 };
    results.sort((a, b) => b.detection.box.area - a.detection.box.area);
    return { face: results[0], count: results.length };
  }

  /** Faster: largest face with landmarks only (no recognition). Used while checking for a blink. */
  async function detectLandmarks(video) {
    const results = await faceapi.detectAllFaces(video, detectorOptions()).withFaceLandmarks();
    if (!results.length) return { face: null, count: 0 };
    results.sort((a, b) => b.detection.box.area - a.detection.box.area);
    return { face: results[0], count: results.length };
  }

  /** Face box relative to the video size (0..1) for size/centering checks. */
  function relativeBox(face, video) {
    const b = face.detection.box;
    const w = video.videoWidth || 640, h = video.videoHeight || 480;
    return { x: b.x / w, y: b.y / h, w: b.width / w, h: b.height / h, cx: (b.x + b.width / 2) / w, cy: (b.y + b.height / 2) / h };
  }

  const dist = (p, q) => Math.hypot(p.x - q.x, p.y - q.y);
  function eyeAspectRatio(eye) {
    return (dist(eye[1], eye[5]) + dist(eye[2], eye[4])) / (2 * dist(eye[0], eye[3]));
  }
  function ear(face) {
    const lm = face.landmarks;
    return (eyeAspectRatio(lm.getLeftEye()) + eyeAspectRatio(lm.getRightEye())) / 2;
  }

  /**
   * Liveness: a blink (eyes close, then open again) or, as a fallback, a slow head turn left and right.
   * A printed or on-screen photo can do neither. The open-eye level is learned per person from recent
   * frames, so naturally narrow eyes and glasses work too.
   */
  class BlinkDetector {
    constructor() { this.reset(); }
    reset() { this.history = []; this.closed = false; this.blinked = false; this.yaws = []; this.turned = false; }
    openLevel() {
      if (this.history.length < 3) return null;
      const sorted = [...this.history].sort((a, b) => a - b);
      return sorted[Math.floor(sorted.length * 0.8)]; // typical open-eye value, ignoring the blink itself
    }
    update(face) {
      const e = ear(face);
      const open = this.openLevel();
      if (open !== null) {
        if (e < open * 0.8) this.closed = true;
        else if (this.closed && e > open * 0.9) this.blinked = true;
      }
      this.history.push(e);
      if (this.history.length > 30) this.history.shift();
      this.trackTurn(face);
      return this.blinked || this.turned;
    }
    /** Head yaw from the nose's position between the eyes; a turn both ways is needed. */
    trackTurn(face) {
      const lm = face.landmarks;
      const center = (pts) => pts.reduce((a, p) => ({ x: a.x + p.x / pts.length, y: a.y + p.y / pts.length }), { x: 0, y: 0 });
      const l = center(lm.getLeftEye()), r = center(lm.getRightEye()), nose = lm.getNose()[3];
      const span = dist(l, r);
      if (!span) return;
      const yaw = (nose.x - (l.x + r.x) / 2) / span;
      this.yaws.push(yaw);
      if (this.yaws.length > 60) this.yaws.shift();
      const min = Math.min(...this.yaws), max = Math.max(...this.yaws);
      if (max - min > 0.35 && min < -0.12 && max > 0.12) this.turned = true;
    }
    isOpen(face) { const open = this.openLevel(); return open === null || ear(face) >= open * 0.85; }
  }

  function averageDescriptors(list) {
    const out = new Array(128).fill(0);
    list.forEach((d) => { for (let i = 0; i < 128; i++) out[i] += d[i]; });
    return out.map((v) => +(v / list.length).toFixed(6));
  }

  /** Small JPEG of the current frame (mirrored like the preview). */
  function snapshot(video, width = 320) {
    const h = Math.round(width * (video.videoHeight / video.videoWidth || 0.75));
    const c = document.createElement('canvas');
    c.width = width; c.height = h;
    const ctx = c.getContext('2d');
    ctx.translate(width, 0); ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, width, h);
    return c.toDataURL('image/jpeg', 0.7);
  }

  /** Draw the face box on an overlay canvas sitting on top of the (mirrored) video. */
  function drawBox(canvas, video, face, color) {
    const rect = video.getBoundingClientRect();
    canvas.width = rect.width; canvas.height = rect.height;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!face) return;
    // object-fit: cover scaling
    const vw = video.videoWidth, vh = video.videoHeight;
    const scale = Math.max(rect.width / vw, rect.height / vh);
    const ox = (rect.width - vw * scale) / 2, oy = (rect.height - vh * scale) / 2;
    const b = face.detection.box;
    const x = rect.width - (b.x * scale + ox) - b.width * scale; // mirrored
    const y = b.y * scale + oy;
    ctx.strokeStyle = color; ctx.lineWidth = 3;
    const r = 14, w = b.width * scale, hh = b.height * scale;
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(x, y, w, hh, r) : ctx.rect(x, y, w, hh);
    ctx.stroke();
  }

  function beep(ok = true) {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = ok ? 880 : 220; o.type = 'sine';
      g.gain.setValueAtTime(0.15, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (ok ? 0.25 : 0.5));
      o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + 0.5);
    } catch (e) { /* no audio */ }
  }

  function csrf() { return document.querySelector('meta[name="csrf-token"]').content; }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* not json */ }
    if (!data.message && !res.ok) data.message = res.status === 429 ? 'Too many attempts. Please wait a moment.' : 'Something went wrong (' + res.status + ').';
    return { status: res.status, data };
  }

  return { loadModels, startCamera, stopCamera, detect, detectLandmarks, relativeBox, BlinkDetector, averageDescriptors, snapshot, drawBox, beep, postJson, csrf };
})();
