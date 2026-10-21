/* Face punch flow for the shared kiosk and the employee's own device. */
(() => {
  const root = document.getElementById('punch-app');
  if (!root) return;
  const FE = window.FaceEngine;
  const mode = root.dataset.mode; // kiosk | personal
  const video = root.querySelector('video');
  const overlay = root.querySelector('canvas');
  const statusEl = root.querySelector('[data-status]');
  const subEl = root.querySelector('[data-sub]');
  const resultEl = root.querySelector('[data-result]');
  const chips = [...root.querySelectorAll('[data-type]')];
  const wantsGeo = root.dataset.geo === '1' || mode === 'personal';

  let state = 'loading';
  let selectedType = '';
  let blink = new FE.BlinkDetector();
  let lastSeen = 0, stateSince = 0, captures = [], snap = null;
  let position = null;

  const setStatus = (main, sub = '', tone = '') => {
    statusEl.textContent = main;
    subEl.textContent = sub;
    root.dataset.tone = tone;
  };
  const go = (s) => { state = s; stateSince = performance.now(); };

  chips.forEach((chip) => chip.addEventListener('click', () => {
    selectedType = chip.dataset.type;
    chips.forEach((c) => c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'));
  }));
  const resetType = () => { const auto = chips.find((c) => c.dataset.type === ''); if (auto) auto.click(); };

  function startGeo() {
    if (!wantsGeo || !navigator.geolocation) return;
    navigator.geolocation.watchPosition(
      (p) => { position = p.coords; },
      () => { if (root.dataset.geo === '1') subEl.textContent = 'Location is required. Please allow location access.'; },
      { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 },
    );
  }

  async function refreshToken() {
    if (!root.dataset.tokenUrl) return false;
    try {
      const r = await fetch(root.dataset.tokenUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json();
      document.querySelector('meta[name="csrf-token"]').content = j.token;
      return true;
    } catch (e) { return false; }
  }

  function showResult(ok, data) {
    resultEl.hidden = false;
    resultEl.dataset.ok = ok ? '1' : '0';
    resultEl.querySelector('[data-r-title]').textContent = ok ? data.employee.name : (data.employee?.name || 'Not recorded');
    resultEl.querySelector('[data-r-main]').textContent = ok ? `${data.punch.label} · ${data.punch.time}` : data.message;
    resultEl.querySelector('[data-r-sub]').textContent = ok ? data.punch.date : '';
    FE.beep(ok);
  }
  function hideResult() { resultEl.hidden = true; }

  async function send(descriptor) {
    go('sending');
    setStatus('Checking…', '', 'busy');
    const body = { descriptor, snapshot: snap, liveness: true, type: selectedType || null };
    if (position) Object.assign(body, { latitude: position.latitude, longitude: position.longitude, accuracy: position.accuracy });

    let { status, data } = await FE.postJson(root.dataset.url, body);
    if (status === 419 && await refreshToken()) ({ status, data } = await FE.postJson(root.dataset.url, body));
    if (status === 419) { location.reload(); return; }

    showResult(!!data.ok, data);
    if (mode === 'personal' && data.ok) {
      go('done');
      FE.stopCamera(video);
      FE.drawBox(overlay, video, null);
      setStatus('Done', 'You can close this page.', 'ok');
      root.querySelector('[data-after]')?.removeAttribute('hidden');
      return;
    }
    go('cooldown');
    setStatus(data.ok ? 'Recorded' : 'Try again', '', data.ok ? 'ok' : 'error');
    setTimeout(() => { hideResult(); resetType(); blink = new FE.BlinkDetector(); go('scan'); }, data.ok ? 4000 : 3500);
  }

  async function tick() {
    if (state === 'done') return;
    if (state === 'sending' || state === 'cooldown' || document.hidden) { setTimeout(tick, 200); return; }

    let res;
    // While waiting for a blink, skip face recognition: landmarks alone are several times faster,
    // so a quick blink isn't missed between frames.
    try { res = state === 'blink' ? await FE.detectLandmarks(video) : await FE.detect(video); } catch (e) { setTimeout(tick, 300); return; }
    const { face, count } = res;
    const now = performance.now();
    FE.drawBox(overlay, video, face, state === 'capture' ? '#16a34a' : '#2563eb');

    if (face) lastSeen = now;
    const rel = face ? FE.relativeBox(face, video) : null;
    const tooSmall = rel && rel.w < (mode === 'kiosk' ? 0.22 : 0.28);

    if (state === 'scan') {
      if (!face) setStatus(mode === 'kiosk' ? 'Step up and look at the camera' : 'Look at the camera');
      else if (mode === 'personal' && count > 1) setStatus('Only one face, please', 'Make sure no one else is in the frame.');
      else if (tooSmall) setStatus('Come a little closer');
      else { blink.reset(); blink.update(face); go('blink'); setStatus('Blink your eyes', 'This confirms you are a live person.', 'busy'); }
    } else if (state === 'blink') {
      if (!face && now - lastSeen > 1500) { go('scan'); }
      else if (face) {
        if (blink.update(face)) { captures = []; snap = null; go('capture'); setStatus('Hold still…', '', 'busy'); }
        else if (now - stateSince > 4000) setStatus('Blink, or slowly turn your head left and right', 'Face the light. Remove sunglasses or a cap if needed.', 'busy');
      }
    } else if (state === 'capture') {
      if (!face) { if (now - lastSeen > 1000) go('scan'); }
      else if (blink.isOpen(face) && !tooSmall) {
        if (!snap) snap = FE.snapshot(video);
        captures.push(Array.from(face.descriptor));
        if (captures.length >= 3) { send(FE.averageDescriptors(captures)); }
      }
      if (now - stateSince > 6000 && state === 'capture') go('scan');
    }
    setTimeout(tick, state === 'blink' ? 0 : 60);
  }

  function startClock() {
    const clock = root.querySelector('[data-clock]');
    const date = root.querySelector('[data-date]');
    if (!clock) return;
    const update = () => {
      const d = new Date();
      clock.textContent = d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
      if (date) date.textContent = d.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    };
    update(); setInterval(update, 1000);
  }

  async function start() {
    startClock();
    setStatus('Starting camera…', 'Allow camera access when asked.', 'busy');
    try {
      await FE.startCamera(video);
      setStatus('Loading face recognition…', 'First load can take a few seconds.', 'busy');
      await FE.loadModels();
    } catch (e) {
      setStatus('Camera unavailable', e.message || String(e), 'error');
      root.querySelector('[data-retry]')?.removeAttribute('hidden');
      return;
    }
    startGeo();
    go('scan');
    tick();
  }

  root.querySelector('[data-retry]')?.addEventListener('click', () => location.reload());
  if (mode === 'kiosk') {
    // Keep the kiosk fresh: reload in the small hours, and recover from sleep.
    setInterval(() => { const h = new Date().getHours(); if (h === 3 && state === 'scan') location.reload(); }, 30 * 60 * 1000);
  }
  start();
})();
