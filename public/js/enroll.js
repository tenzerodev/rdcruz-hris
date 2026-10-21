/* Face enrollment: auto-captures several angles, sends only the numeric descriptors. */
(() => {
  const root = document.getElementById('enroll-app');
  if (!root) return;
  const FE = window.FaceEngine;
  const video = root.querySelector('video');
  const overlay = root.querySelector('canvas');
  const statusEl = root.querySelector('[data-status]');
  const thumbs = root.querySelector('[data-thumbs]');
  const saveBtn = root.querySelector('[data-save]');
  const restartBtn = root.querySelector('[data-restart]');
  const consent = root.querySelector('[name="consent"]');
  const replace = root.querySelector('[name="replace"]');
  const msg = root.querySelector('[data-msg]');

  const prompts = [
    'Look straight at the camera',
    'Turn your head slightly to the LEFT',
    'Turn your head slightly to the RIGHT',
    'Tilt your chin slightly UP',
    'Look straight again and smile',
  ];
  let samples = [], lastCapture = 0, running = false;

  const setStatus = (t) => { statusEl.textContent = t; };
  const updateSave = () => { saveBtn.disabled = samples.length < prompts.length || !consent.checked; };
  consent.addEventListener('change', updateSave);

  function reset() {
    samples = []; thumbs.innerHTML = ''; msg.textContent = ''; msg.dataset.tone = '';
    lastCapture = performance.now();
    updateSave();
    if (!running) { running = true; loop(); }
  }

  async function loop() {
    if (!running) return;
    if (samples.length >= prompts.length) {
      running = false; FE.drawBox(overlay, video, null);
      setStatus('All captures done. Check consent, then Save.');
      return;
    }
    setStatus(`${samples.length + 1}/${prompts.length}: ${prompts[samples.length]}`);
    let res;
    try { res = await FE.detect(video); } catch (e) { setTimeout(loop, 300); return; }
    const { face, count } = res;
    FE.drawBox(overlay, video, face, '#2563eb');
    const now = performance.now();
    if (face && count === 1 && face.detection.score >= 0.7 && FE.relativeBox(face, video).w >= 0.25 && now - lastCapture > 1400) {
      samples.push({ descriptor: Array.from(face.descriptor).map((v) => +v.toFixed(6)), score: +face.detection.score.toFixed(3) });
      lastCapture = now;
      const img = new Image(); img.src = FE.snapshot(video, 120); img.alt = 'Capture ' + samples.length;
      thumbs.appendChild(img); // shown on this screen only; never uploaded
      FE.beep(true);
      updateSave();
    } else if (face && count > 1) {
      setStatus('Only the employee should be in front of the camera.');
    }
    setTimeout(loop, 120);
  }

  saveBtn.addEventListener('click', async () => {
    saveBtn.disabled = true;
    msg.textContent = 'Saving…'; msg.dataset.tone = '';
    const { data } = await FE.postJson(root.dataset.url, { samples, consent: consent.checked, replace: replace.checked });
    msg.textContent = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed.');
    msg.dataset.tone = data.ok ? 'ok' : 'error';
    if (data.ok) {
      root.querySelector('[data-count]').textContent = data.total;
      FE.beep(true);
    } else { updateSave(); }
  });
  restartBtn.addEventListener('click', reset);

  (async () => {
    setStatus('Starting camera…');
    try {
      await FE.startCamera(video);
      setStatus('Loading face recognition…');
      await FE.loadModels();
    } catch (e) { setStatus('Camera unavailable: ' + (e.message || e)); return; }
    reset();
  })();
})();
