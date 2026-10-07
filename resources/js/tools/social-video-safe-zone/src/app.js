// Social Video Safe Zone Checker: UI controller (spec 7). Mounts into any element:
//   import { mount } from './src/app.js'; mount(document.querySelector('[data-svsz-root]'));
// Everything runs in the browser; the file is never uploaded. Analytics are only
// reported through the optional onEvent callback (wired in P1b, not here).
import defaultData from '../data/safezones.v1.js';
import { classifyAspect, isLowResolution, referenceFrame } from './aspect.js';
import { ELEMENT_TYPES, SAMPLE_INTERVAL_S, CRITICAL_COVERAGE, IMPORTANT_TYPES } from './constants.js';
import { resolveZones } from './dataset.js';
import { annotatedFrame, download, exportNames, overlayTemplate } from './export.js';
import { AnalysisError, analyse, loadImage, loadVideo } from './frames.js';
import { coverage, normalize } from './geometry.js';
import { sniff } from './intake.js';
import { COLORS, HANDLE, drawElements, drawReference, drawSource, drawZones } from './render.js';
import { buildReport, summaryText } from './report.js';
import { sampleFile } from './sample.js';
import { TYPE_LABEL, VERDICT_LABEL, evaluatePlatform } from './scoring.js';

const ACCEPT = 'video/mp4,video/quicktime,video/webm,image/png,image/jpeg,image/webp';
const VERDICT_ICON = { pass: '✓', 'needs-review': '!', fail: '✕', 'not-scored': '–' };
const SEVERITY_LABEL = { critical: 'Critical', warning: 'Warning', notice: 'Notice' };
const CAPTIONS = [['C0', 'None'], ['C1', 'Short'], ['C2', 'Medium'], ['C3', 'Long'], ['C3E', 'Long, expanded']];
const PREVIEW_SCALE = 0.5;
const MIN_BOX = 20;

const TEMPLATE = `
<p class="svsz-privacy"><span aria-hidden="true">🔒</span> Your video never leaves your device. Everything runs in this browser tab.</p>
<div class="svsz-grid">
  <section class="svsz-stage" aria-label="Preview">
    <div class="svsz-panel svsz-empty" data-view="empty">
      <div class="svsz-drop" data-drop>
        <strong>Drop a vertical video or image here</strong>
        <span id="svsz-drop-help">or choose a file: MP4, MOV, WebM, PNG, JPG or WebP</span>
        <span class="svsz-actions">
          <button type="button" class="svsz-btn svsz-btn-primary" data-act="choose">Choose file</button>
          <button type="button" class="svsz-btn" data-act="sample">Try a sample</button>
        </span>
      </div>
      <input type="file" accept="${ACCEPT}" data-file hidden>
    </div>
    <div class="svsz-panel" data-view="checking" hidden><p class="svsz-status" data-checking>Checking file…</p></div>
    <div class="svsz-panel" data-view="analysing" hidden>
      <p class="svsz-status">Analysing frames <span data-progress-text>0%</span></p>
      <progress max="100" value="0" data-progress aria-label="Analysis progress"></progress>
      <button type="button" class="svsz-btn" data-act="cancel">Cancel</button>
    </div>
    <div class="svsz-panel svsz-error" data-view="error" hidden role="alert">
      <p data-error-text></p>
      <span class="svsz-actions">
        <button type="button" class="svsz-btn svsz-btn-primary" data-act="choose">Choose another file</button>
        <button type="button" class="svsz-btn" data-act="choose-image" data-error-image hidden>Load a screenshot instead</button>
      </span>
    </div>
    <div class="svsz-preview" data-view="results" hidden>
      <div class="svsz-tabs" role="tablist" aria-label="Zones shown in the preview" data-tabs></div>
      <div class="svsz-canvas-wrap">
        <canvas data-canvas tabindex="0" aria-label="Preview with safe zones. Use the Key elements list or the keyboard to edit boxes." aria-describedby="svsz-canvas-help"></canvas>
      </div>
      <p id="svsz-canvas-help" class="svsz-help">Select a box, then use arrow keys to move it (Shift for 1 px), Alt + arrows to resize, Delete to remove.</p>
      <div class="svsz-timeline" data-timeline hidden>
        <button type="button" class="svsz-btn svsz-icon" data-act="play" aria-label="Play">▶</button>
        <input type="range" min="0" max="0" step="0.01" value="0" data-seek aria-label="Video position">
        <span class="svsz-time" data-time>0.0 s</span>
        <canvas class="svsz-strip" data-strip height="14" role="img" aria-label="Risk over time"></canvas>
      </div>
      <div class="svsz-legend" aria-hidden="true">
        <span><i class="svsz-sw svsz-sw-hard"></i>Covered by app UI</span>
        <span><i class="svsz-sw svsz-sw-soft"></i>Sometimes covered</span>
        <span><i class="svsz-sw svsz-sw-crop"></i>Cut off or masked</span>
        <span><i class="svsz-sw svsz-sw-box"></i>Your box</span>
        <span><i class="svsz-sw svsz-sw-auto"></i>Detected</span>
      </div>
    </div>
  </section>
  <aside class="svsz-side">
    <fieldset class="svsz-settings" data-settings disabled>
      <legend>Check for</legend>
      <div class="svsz-field" role="group" aria-label="Platforms" data-platforms></div>
      <div class="svsz-field">
        <span class="svsz-label" id="svsz-cap-label">Caption length</span>
        <div class="svsz-seg" role="radiogroup" aria-labelledby="svsz-cap-label" data-captions></div>
      </div>
      <label class="svsz-check"><input type="checkbox" data-set="cc"> Auto-captions on</label>
      <label class="svsz-check"><input type="checkbox" data-set="soundLine"> Music or sound line (YouTube)</label>
      <label class="svsz-field"><span class="svsz-label">Phone</span>
        <select data-set="device">
          <option value="all">All measured phones</option>
          <option value="ios">iPhone 14 Plus</option>
          <option value="android">Android</option>
        </select>
      </label>
      <label class="svsz-field"><span class="svsz-label">Overlay opacity</span><input type="range" min="0.1" max="0.9" step="0.05" data-set="opacity"></label>
      <label class="svsz-check"><input type="checkbox" data-set="showRefs"> Show ads guidance for comparison</label>
    </fieldset>
    <section class="svsz-results" aria-labelledby="svsz-results-h">
      <h2 id="svsz-results-h" class="svsz-h">Results</h2>
      <div data-results><p class="svsz-help">Load a file to see results.</p></div>
    </section>
    <section class="svsz-elements" aria-labelledby="svsz-el-h" data-elements-section hidden>
      <h2 id="svsz-el-h" class="svsz-h">Key elements</h2>
      <p class="svsz-help">Dashed boxes were detected automatically. Add boxes for anything viewers must see.</p>
      <div class="svsz-actions">
        <button type="button" class="svsz-btn" data-act="add" aria-pressed="false">Add box</button>
      </div>
      <ol class="svsz-ellist" data-ellist></ol>
    </section>
    <section class="svsz-exports" aria-labelledby="svsz-ex-h" data-exports hidden>
      <h2 id="svsz-ex-h" class="svsz-h">Export</h2>
      <div class="svsz-actions svsz-wrap">
        <button type="button" class="svsz-btn" data-act="export-annotated">Annotated frame (PNG)</button>
        <button type="button" class="svsz-btn" data-act="export-overlay">Overlay template (PNG)</button>
        <button type="button" class="svsz-btn" data-act="export-json">Report (JSON)</button>
        <button type="button" class="svsz-btn" data-act="export-print">Print or save as PDF</button>
        <button type="button" class="svsz-btn" data-act="export-copy">Copy summary</button>
      </div>
      <p class="svsz-help" data-dataset></p>
      <button type="button" class="svsz-btn svsz-link" data-act="reset">Check another file</button>
    </section>
  </aside>
</div>
<div class="svsz-live" aria-live="polite" data-live></div>
<div class="svsz-print" data-print></div>`;

export function mount(root, { data = defaultData, onEvent = () => {} } = {}) {
    root.classList.add('svsz');
    root.innerHTML = TEMPLATE;
    const $ = (sel) => root.querySelector(sel);
    const $$ = (sel) => [...root.querySelectorAll(sel)];
    const els = {
        file: $('[data-file]'), drop: $('[data-drop]'), canvas: $('[data-canvas]'), strip: $('[data-strip]'),
        seek: $('[data-seek]'), time: $('[data-time]'), timeline: $('[data-timeline]'), tabs: $('[data-tabs]'),
        settings: $('[data-settings]'), platforms: $('[data-platforms]'), captions: $('[data-captions]'),
        results: $('[data-results]'), ellist: $('[data-ellist]'), live: $('[data-live]'), print: $('[data-print]'),
        progress: $('[data-progress]'), progressText: $('[data-progress-text]'), errorText: $('[data-error-text]'),
        errorImage: $('[data-error-image]'), checking: $('[data-checking]'), dataset: $('[data-dataset]'),
        addBtn: $('[data-act="add"]'),
    };

    const state = {
        status: 'empty', src: null, input: null, elements: [], selectedId: null, results: [], resolved: {},
        settings: { platforms: ['instagram-reels', 'youtube-shorts'], caption: 'C1', cc: false, soundLine: false, device: 'all', opacity: 0.45, showRefs: false },
        active: 'instagram-reels', abort: null, drawMode: false, drag: null, started: false, completedSent: false, nextUserId: 1, raf: 0,
    };
    const emit = (name, params = {}) => { try { onEvent(name, params); } catch { /* analytics must never break the tool */ } };

    // ---------- settings controls ----------
    for (const p of data.platforms) {
        const id = `svsz-p-${p.id}`;
        const label = document.createElement('label');
        label.className = 'svsz-check';
        label.innerHTML = `<input type="checkbox" id="${id}" value="${p.id}"> <span></span>${p.status !== 'measured' ? ' <span class="svsz-badge">Provisional</span>' : ''}`;
        label.querySelector('span').textContent = p.name;
        label.querySelector('input').checked = state.settings.platforms.includes(p.id);
        els.platforms.appendChild(label);
    }
    const captionGroup = `svsz-caption-${uid()}`;
    for (const [v, text] of CAPTIONS) {
        const l = document.createElement('label');
        l.className = 'svsz-seg-item';
        l.innerHTML = `<input type="radio" name="${captionGroup}" value="${v}"><span></span>`;
        l.querySelector('span').textContent = text;
        l.querySelector('input').checked = v === state.settings.caption;
        l.title = data.captionVariants[v];
        els.captions.appendChild(l);
    }
    $('[data-set="opacity"]').value = state.settings.opacity;
    $('[data-set="device"]').value = state.settings.device;
    const iosModel = data.platforms.find((p) => p.devices.length)?.devices.find((d) => d.os === 'ios')?.model;
    if (iosModel) $('[data-set="device"] option[value="ios"]').textContent = iosModel;
    els.dataset.textContent = `Zones: dataset ${data.datasetVersion}, measured on real phones (P0, ${data.generatedFrom.p0Report.replace(/^P0 completion report, /, '')}). TikTok is provisional.`;

    els.platforms.addEventListener('change', () => {
        state.settings.platforms = $$('[data-platforms] input:checked').map((i) => i.value);
        if (!state.settings.platforms.includes(state.active)) state.active = state.settings.platforms[0] || data.platforms[0].id;
        refresh();
    });
    els.captions.addEventListener('change', (e) => { state.settings.caption = e.target.value; refresh(); });
    for (const input of $$('[data-set]')) {
        input.addEventListener(input.type === 'range' ? 'input' : 'change', () => {
            const k = input.dataset.set;
            state.settings[k] = input.type === 'checkbox' ? input.checked : input.type === 'range' ? Number(input.value) : input.value;
            k === 'opacity' || k === 'showRefs' ? draw() : refresh();
        });
    }

    // ---------- views ----------
    function view(status) {
        state.status = status;
        for (const v of $$('[data-view]')) v.hidden = v.dataset.view !== status;
        const ready = status === 'results';
        els.settings.disabled = !ready;
        $('[data-elements-section]').hidden = !ready;
        $('[data-exports]').hidden = !ready;
        if (!ready) els.results.innerHTML = '<p class="svsz-help">Load a file to see results.</p>';
    }

    function showError(code, message, offerImage = false) {
        stopLoop();
        els.errorText.textContent = message;
        els.errorImage.hidden = !offerImage;
        view('error');
        emit('safezone_error', { error_code: code });
        announce(message);
    }

    const announce = (text) => { els.live.textContent = ''; setTimeout(() => { els.live.textContent = text; }, 30); };

    // ---------- file intake ----------
    let imageOnly = false;
    els.file.addEventListener('change', () => {
        const f = els.file.files && els.file.files[0];
        els.file.value = '';
        if (f) handleFile(f);
    });
    els.drop.addEventListener('dragover', (e) => { e.preventDefault(); els.drop.classList.add('is-over'); });
    els.drop.addEventListener('dragleave', () => els.drop.classList.remove('is-over'));
    els.drop.addEventListener('drop', (e) => {
        e.preventDefault();
        els.drop.classList.remove('is-over');
        const f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) handleFile(f);
    });

    function choose(images) {
        imageOnly = images;
        els.file.accept = images ? 'image/png,image/jpeg,image/webp' : ACCEPT;
        els.file.click();
    }

    async function handleFile(file) {
        if (!state.started) { state.started = true; emit('tool_started'); }
        cleanup();
        view('checking');
        els.checking.textContent = `Checking ${file.name} (${(file.size / 1048576).toFixed(1)} MB)…`;
        const head = new Uint8Array(await file.slice(0, 64).arrayBuffer());
        const type = sniff(head, file.size);
        if (!type.ok) return showError(type.code, type.message);
        if (imageOnly && type.kind !== 'image') return showError('unsupported', 'Please choose an image (PNG, JPG or WebP) of the frame.');
        try {
            let src;
            if (type.kind === 'image') src = { kind: 'image', bitmap: await loadImage(file) };
            else src = { kind: 'video', video: await loadVideo(file) };
            state.src = src;
            const w = src.kind === 'image' ? src.bitmap.width : src.video.videoWidth;
            const h = src.kind === 'image' ? src.bitmap.height : src.video.videoHeight;
            view('analysing');
            els.progress.value = 0;
            els.progressText.textContent = '0%';
            state.abort = new AbortController();
            const result = await analyse(src, {
                signal: state.abort.signal,
                onProgress: (done, total) => {
                    const pct = Math.round((done / total) * 100);
                    els.progress.value = pct;
                    els.progressText.textContent = `${pct}% (${done} of ${total} frames)`;
                },
            });
            state.abort = null;
            const aspect = classifyAspect(w, h);
            state.input = {
                kind: src.kind, width: w, height: h, aspect, frame: referenceFrame(w, h),
                durationS: result.durationS, analysedS: result.analysedS, sampleIntervalS: result.sampleIntervalS || SAMPLE_INTERVAL_S,
                samples: result.samples, lowResolution: isLowResolution(w, h), truncated: result.truncated,
            };
            state.elements = result.elements;
            state.selectedId = null;
            state.completedSent = false;
            emit('safezone_file_loaded', { kind: src.kind, aspect, duration_bucket: durationBucket(result.durationS) });
            setupPreview();
            view('results');
            refresh();
            const v = state.results.map((r) => `${r.name}: ${VERDICT_LABEL[r.verdict]}${r.score != null ? `, score ${r.score}` : ''}`).join('. ');
            announce(`Analysis complete. ${v}.${result.truncated ? ' Only the first 3 minutes were analysed.' : ''}`);
        } catch (err) {
            state.abort = null;
            if (err instanceof AnalysisError && err.code === 'cancelled') { cleanup(); view('empty'); announce('Analysis cancelled.'); return; }
            const code = err instanceof AnalysisError ? err.code : 'analysis';
            showError(code, err instanceof AnalysisError ? err.message : `Analysis failed: ${err.message || err}`, code === 'decode' && type.kind === 'video');
        }
    }

    function cleanup() {
        stopLoop();
        if (state.abort) state.abort.abort();
        if (state.src && state.src.kind === 'video') { state.src.video.pause(); URL.revokeObjectURL(state.src.video.src); state.src.video.removeAttribute('src'); }
        if (state.src && state.src.kind === 'image' && state.src.bitmap.close) state.src.bitmap.close();
        state.src = null;
        state.input = null;
        state.elements = [];
        state.results = [];
    }

    // ---------- preview ----------
    function setupPreview() {
        const { frame } = state.input;
        els.canvas.width = Math.round(frame.width * PREVIEW_SCALE);
        els.canvas.height = Math.round(frame.height * PREVIEW_SCALE);
        els.canvas.style.aspectRatio = `${frame.width} / ${frame.height}`;
        const video = state.src.kind === 'video' ? state.src.video : null;
        els.timeline.hidden = !video;
        if (video) {
            els.seek.max = String(state.input.analysedS || state.input.durationS || 0);
            els.seek.value = '0';
            video.currentTime = 0;
            video.onseeked = () => draw();
            video.onpause = () => { stopLoop(); setPlayLabel(false); draw(); };
            video.onplay = () => { setPlayLabel(true); loop(); };
            video.onended = () => { stopLoop(); setPlayLabel(false); };
        }
    }

    const currentTime = () => (state.src && state.src.kind === 'video' ? state.src.video.currentTime : 0);
    function setPlayLabel(playing) {
        const b = $('[data-act="play"]');
        b.textContent = playing ? '❚❚' : '▶';
        b.setAttribute('aria-label', playing ? 'Pause' : 'Play');
    }
    function loop() {
        stopLoop();
        const tick = () => { draw(); els.seek.value = String(currentTime()); state.raf = requestAnimationFrame(tick); };
        state.raf = requestAnimationFrame(tick);
    }
    function stopLoop() { if (state.raf) cancelAnimationFrame(state.raf); state.raf = 0; }
    els.seek.addEventListener('input', () => { if (state.src && state.src.kind === 'video') state.src.video.currentTime = Number(els.seek.value); });

    // Element as shown at time t (tracked boxes move; user boxes stay)
    function rectAt(el, t) {
        if (el.fromS != null && (t < el.fromS - 1e-6 || t > el.toS + 1e-6)) return null;
        if (!el.samples || !el.samples.length) return el.rect;
        let best = el.samples[0];
        for (const s of el.samples) if (Math.abs(s.t - t) < Math.abs(best.t - t)) best = s;
        return Math.abs(best.t - t) <= (state.input.sampleIntervalS || SAMPLE_INTERVAL_S) * 1.5 ? best.rect : null;
    }

    function numbered() {
        return state.elements.map((e, i) => ({ ...e, label: `#${i + 1} ${TYPE_LABEL[e.type]}` }));
    }
    const numbersMap = () => Object.fromEntries(state.elements.map((e, i) => [e.id, i + 1]));

    function draw() {
        if (state.status !== 'results' || !state.input) return;
        const ctx = els.canvas.getContext('2d');
        const { frame } = state.input;
        const resolved = state.resolved[state.active];
        ctx.setTransform(PREVIEW_SCALE, 0, 0, PREVIEW_SCALE, 0, 0);
        const source = state.src.kind === 'video' ? state.src.video : state.src.bitmap;
        drawSource(ctx, source, state.input.width, state.input.height, frame);
        if (resolved) {
            drawZones(ctx, resolved.zones, { opacity: state.settings.opacity, provisional: resolved.platform.status !== 'measured', fontScale: 1.4 });
            if (state.settings.showRefs && resolved.mode === 'scored') for (const r of data.references.filter((x) => x.platform === state.active)) drawReference(ctx, r, frame);
        }
        const t = currentTime();
        const shown = state.elements.map((e) => ({ ...e, rect: rectAt(e, t) })).filter((e) => e.rect);
        drawElements(ctx, shown, { selectedId: state.selectedId, numbers: numbersMap(), issueByElement: issueByElement(), fontScale: 1.4 });
        if (state.src.kind === 'video') els.time.textContent = `${t.toFixed(1)} s`;
    }

    function issueByElement() {
        const r = state.results.find((x) => x.platform === state.active);
        const out = {};
        if (!r) return out;
        for (const i of r.issues) if (i.element && (!out[i.element] || i.severity === 'critical')) out[i.element] = i.severity;
        return out;
    }

    // ---------- scoring ----------
    function refresh() {
        if (!state.input) return;
        const input = { kind: state.input.kind, durationS: state.input.analysedS ?? state.input.durationS, sampleIntervalS: state.input.sampleIntervalS, lowResolution: state.input.lowResolution };
        const elements = numbered();
        state.resolved = {};
        state.results = [];
        for (const platformId of state.settings.platforms) {
            const resolved = resolveZones(data, { platformId, aspect: state.input.aspect, caption: state.settings.caption, cc: state.settings.cc, soundLine: state.settings.soundLine, device: state.settings.device });
            state.resolved[platformId] = resolved;
            state.results.push(evaluatePlatform(resolved, elements, input));
        }
        if (!state.settings.platforms.includes(state.active)) state.active = state.settings.platforms[0];
        renderTabs();
        renderResults();
        renderElementList();
        drawStrip();
        draw();
        if (!state.completedSent && state.results.length) {
            state.completedSent = true;
            emit('tool_completed', { platforms: state.settings.platforms.join(','), verdicts: state.results.map((r) => `${r.platform}:${r.verdict}`).join(','), score_buckets: state.results.map((r) => `${r.platform}:${bucket(r.score)}`).join(',') });
        }
    }

    function renderTabs() {
        els.tabs.innerHTML = '';
        for (const id of state.settings.platforms) {
            const p = data.platforms.find((x) => x.id === id);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'svsz-tab';
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-selected', String(id === state.active));
            b.tabIndex = id === state.active ? 0 : -1;
            b.textContent = p.name + (p.status !== 'measured' ? ' (provisional)' : '');
            b.addEventListener('click', () => { state.active = id; renderTabs(); drawStrip(); draw(); });
            b.addEventListener('keydown', (e) => {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                const ids = state.settings.platforms;
                const i = (ids.indexOf(state.active) + (e.key === 'ArrowRight' ? 1 : ids.length - 1)) % ids.length;
                state.active = ids[i];
                renderTabs();
                drawStrip();
                draw();
                els.tabs.querySelector('[aria-selected="true"]').focus();
            });
            els.tabs.appendChild(b);
        }
        if (!state.settings.platforms.length) els.tabs.innerHTML = '<p class="svsz-help">Choose at least one platform.</p>';
    }

    function renderResults() {
        els.results.innerHTML = '';
        if (!state.results.length) { els.results.innerHTML = '<p class="svsz-help">Choose at least one platform.</p>'; return; }
        for (const r of state.results) {
            const card = document.createElement('article');
            card.className = `svsz-card svsz-v-${r.verdict}`;
            const h = document.createElement('h3');
            h.innerHTML = `<span class="svsz-verdict"><span aria-hidden="true">${VERDICT_ICON[r.verdict]}</span> <span data-v></span></span> <span data-name></span>`;
            h.querySelector('[data-v]').textContent = VERDICT_LABEL[r.verdict];
            h.querySelector('[data-name]').textContent = r.name;
            card.appendChild(h);
            const meta = document.createElement('p');
            meta.className = 'svsz-meta';
            meta.textContent = r.score != null ? `Score ${r.score} of 100${r.status !== 'measured' ? ' · provisional zones, not measured' : ''}` : 'No score';
            card.appendChild(meta);
            const list = document.createElement('ol');
            list.className = 'svsz-issues';
            for (const i of r.issues) {
                const li = document.createElement('li');
                li.className = `svsz-sev-${i.severity}`;
                const btn = document.createElement(i.element ? 'button' : 'div');
                if (i.element) { btn.type = 'button'; btn.className = 'svsz-issue-btn'; }
                const sev = document.createElement('strong');
                sev.textContent = `${SEVERITY_LABEL[i.severity]}: `;
                btn.appendChild(sev);
                btn.appendChild(document.createTextNode(i.message));
                if (i.fixText) {
                    const fx = document.createElement('span');
                    fx.className = 'svsz-fix';
                    fx.textContent = ` Fix: ${i.fixText}.`;
                    btn.appendChild(fx);
                }
                if (i.element) btn.addEventListener('click', () => focusIssue(r.platform, i));
                li.appendChild(btn);
                list.appendChild(li);
            }
            if (!r.issues.length) list.innerHTML = '<li class="svsz-sev-ok">No problems found.</li>';
            card.appendChild(list);
            els.results.appendChild(card);
        }
    }

    function focusIssue(platformId, issue) {
        state.active = platformId;
        state.selectedId = issue.element;
        renderTabs();
        renderElementList();
        drawStrip();
        const el = state.elements.find((e) => e.id === issue.element);
        const zone = state.resolved[platformId].zones.find((z) => z.id === issue.zone);
        if (el && state.src.kind === 'video') {
            let t = el.fromS ?? 0;
            if (el.samples && zone) {
                const hit = el.samples.find((s) => coverage(s.rect, zone.rect) > 0);
                if (hit) t = hit.t;
            }
            state.src.video.pause();
            state.src.video.currentTime = t;
            els.seek.value = String(t);
        }
        draw();
        els.canvas.focus();
    }

    function drawStrip() {
        if (!state.input || state.input.kind !== 'video') return;
        const resolved = state.resolved[state.active];
        const strip = els.strip;
        const times = state.input.samples;
        strip.width = Math.max(1, times.length);
        const ctx = strip.getContext('2d');
        ctx.clearRect(0, 0, strip.width, strip.height);
        const levels = times.map((t) => {
            if (!resolved || resolved.mode !== 'scored' && resolved.mode !== 'scored-tall') return 0;
            let lvl = 0;
            for (const el of state.elements) {
                const r = rectAt(el, t);
                if (!r) continue;
                for (const z of resolved.zones) {
                    if (z.severity === 'info') continue;
                    const c = coverage(r, z.rect);
                    if (c <= 0) continue;
                    const strong = z.severity === 'hard' && c >= CRITICAL_COVERAGE && el.source === 'user' && IMPORTANT_TYPES.includes(el.type);
                    lvl = Math.max(lvl, strong ? 2 : 1);
                }
            }
            return lvl;
        });
        levels.forEach((l, i) => { ctx.fillStyle = l === 2 ? COLORS.critical : l === 1 ? COLORS.warning : COLORS.ok; ctx.fillRect(i, 0, 1, strip.height); });
        const spans = [];
        levels.forEach((l, i) => { if (l === 2) { const t = times[i]; const last = spans[spans.length - 1]; if (last && i > 0 && levels[i - 1] === 2) last[1] = t; else spans.push([t, t]); } });
        strip.setAttribute('aria-label', spans.length ? `High risk at ${spans.map(([a, b]) => (a === b ? `${a.toFixed(1)} s` : `${a.toFixed(1)}–${b.toFixed(1)} s`)).join(', ')}` : 'No high-risk moments');
    }

    // ---------- element list (keyboard and screen-reader path) ----------
    function renderElementList() {
        els.ellist.innerHTML = '';
        state.elements.forEach((el, i) => {
            const li = document.createElement('li');
            li.className = `svsz-el${el.id === state.selectedId ? ' is-selected' : ''}`;
            const sel = document.createElement('button');
            sel.type = 'button';
            sel.className = 'svsz-el-name';
            sel.setAttribute('aria-pressed', String(el.id === state.selectedId));
            sel.textContent = `#${i + 1} ${el.source === 'auto' ? 'detected' : 'your box'} · ${el.rect.map(Math.round).join(', ')}`;
            sel.addEventListener('click', () => { state.selectedId = el.id; renderElementList(); draw(); els.canvas.focus(); });
            const type = document.createElement('select');
            type.setAttribute('aria-label', `Type of box ${i + 1}`);
            for (const t of ELEMENT_TYPES) { const o = document.createElement('option'); o.value = t; o.textContent = TYPE_LABEL[t]; type.appendChild(o); }
            type.value = el.type;
            type.addEventListener('change', () => { userEdit(el, { type: type.value }); refresh(); });
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'svsz-btn svsz-small';
            del.textContent = 'Delete';
            del.setAttribute('aria-label', `Delete box ${i + 1}`);
            del.addEventListener('click', () => removeElement(el.id));
            li.append(sel, type, del);
            els.ellist.appendChild(li);
        });
        if (!state.elements.length) els.ellist.innerHTML = '<li class="svsz-help">No boxes yet. Use Add box to mark text, logos or faces.</li>';
    }

    // An edited detection becomes the user's own box: full confidence, fixed position.
    function userEdit(el, patch) {
        Object.assign(el, patch);
        if (el.source === 'auto') {
            el.source = 'user';
            el.confidence = 1;
            if (el.samples && !patch.rect) el.rect = rectAt(el, currentTime()) || el.rect;
            delete el.samples;
        }
    }

    function removeElement(id) {
        state.elements = state.elements.filter((e) => e.id !== id);
        if (state.selectedId === id) state.selectedId = null;
        refresh();
        announce('Box deleted.');
    }

    // ---------- canvas editing ----------
    const toRef = (e) => {
        const r = els.canvas.getBoundingClientRect();
        const { frame } = state.input;
        return [((e.clientX - r.left) * frame.width) / r.width, ((e.clientY - r.top) * frame.height) / r.height];
    };
    const handleSize = () => {
        const r = els.canvas.getBoundingClientRect();
        return Math.max(HANDLE, (24 * state.input.frame.width) / r.width); // at least 24 screen px
    };

    function hit(x, y) {
        const t = currentTime();
        const sel = state.elements.find((e) => e.id === state.selectedId);
        const hs = handleSize();
        if (sel) {
            const r = rectAt(sel, t);
            if (r) {
                const corners = [['tl', r[0], r[1]], ['tr', r[2], r[1]], ['bl', r[0], r[3]], ['br', r[2], r[3]]];
                for (const [k, cx, cy] of corners) if (Math.abs(x - cx) <= hs / 2 && Math.abs(y - cy) <= hs / 2) return { el: sel, mode: k };
            }
        }
        const under = state.elements.map((e) => ({ e, r: rectAt(e, t) })).filter(({ r }) => r && x >= r[0] && x <= r[2] && y >= r[1] && y <= r[3]);
        under.sort((a, b) => (a.r[2] - a.r[0]) * (a.r[3] - a.r[1]) - (b.r[2] - b.r[0]) * (b.r[3] - b.r[1]));
        return under[0] ? { el: under[0].e, mode: 'move' } : null;
    }

    els.canvas.addEventListener('pointerdown', (e) => {
        if (state.status !== 'results') return;
        els.canvas.setPointerCapture(e.pointerId);
        const [x, y] = toRef(e);
        if (state.drawMode) {
            const el = { id: `u${state.nextUserId++}`, type: 'text', source: 'user', confidence: 1, rect: [x, y, x, y], ...(state.input.kind === 'video' ? { fromS: 0, toS: state.input.analysedS ?? state.input.durationS } : {}) };
            state.elements.push(el);
            state.selectedId = el.id;
            state.drag = { el, mode: 'draw', x0: x, y0: y };
            return;
        }
        const h = hit(x, y);
        state.selectedId = h ? h.el.id : null;
        if (h) {
            const r = rectAt(h.el, currentTime());
            state.drag = { el: h.el, mode: h.mode, x0: x, y0: y, start: r.slice(), moved: false };
        }
        renderElementList();
        draw();
    });
    els.canvas.addEventListener('pointermove', (e) => {
        const d = state.drag;
        if (!d) {
            if (state.status === 'results') {
                const [x, y] = toRef(e);
                const h = state.drawMode ? null : hit(x, y);
                els.canvas.style.cursor = state.drawMode ? 'crosshair' : !h ? 'default' : h.mode === 'move' ? 'move' : (h.mode === 'tl' || h.mode === 'br') ? 'nwse-resize' : 'nesw-resize';
            }
            return;
        }
        const [x, y] = toRef(e);
        if (d.mode !== 'draw' && !d.moved) {
            if (Math.abs(x - d.x0) < 3 && Math.abs(y - d.y0) < 3) return; // a click selects, it does not edit
            d.moved = true;
            userEdit(d.el, { rect: d.start.slice() });
        }
        const { frame } = state.input;
        const cx = Math.max(0, Math.min(frame.width, x)), cy = Math.max(0, Math.min(frame.height, y));
        if (d.mode === 'draw') d.el.rect = normalize([d.x0, d.y0, cx, cy]);
        else if (d.mode === 'move') {
            const w = d.start[2] - d.start[0], hgt = d.start[3] - d.start[1];
            const nx = Math.max(0, Math.min(frame.width - w, d.start[0] + x - d.x0));
            const ny = Math.max(0, Math.min(frame.height - hgt, d.start[1] + y - d.y0));
            d.el.rect = [nx, ny, nx + w, ny + hgt];
        } else {
            const r = d.start.slice();
            if (d.mode.includes('l')) r[0] = cx; else r[2] = cx;
            if (d.mode.includes('t')) r[1] = cy; else r[3] = cy;
            d.el.rect = normalize(r);
        }
        draw();
    });
    const endDrag = () => {
        const d = state.drag;
        if (!d) return;
        state.drag = null;
        if (d.mode !== 'draw' && !d.moved) return;
        d.el.rect = d.el.rect.map(Math.round);
        if (d.mode === 'draw') {
            setDrawMode(false);
            if (d.el.rect[2] - d.el.rect[0] < MIN_BOX || d.el.rect[3] - d.el.rect[1] < MIN_BOX) {
                state.elements = state.elements.filter((x) => x !== d.el);
                state.selectedId = null;
                announce('Box too small; drag to draw a larger box.');
            } else announce('Box added. Choose its type in the Key elements list.');
        }
        refresh();
    };
    els.canvas.addEventListener('pointerup', endDrag);
    els.canvas.addEventListener('pointercancel', endDrag);

    els.canvas.addEventListener('keydown', (e) => {
        const el = state.elements.find((x) => x.id === state.selectedId);
        if (e.key === 'Escape') { state.selectedId = null; setDrawMode(false); draw(); return; }
        if (!el) return;
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); removeElement(el.id); return; }
        const step = e.shiftKey ? 1 : 10;
        const dirs = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (!dirs[e.key]) return;
        e.preventDefault();
        const [dx, dy] = dirs[e.key];
        const { frame } = state.input;
        const r = (rectAt(el, currentTime()) || el.rect).slice();
        let n;
        if (e.altKey) n = [r[0], r[1], Math.max(r[0] + MIN_BOX, Math.min(frame.width, r[2] + dx)), Math.max(r[1] + MIN_BOX, Math.min(frame.height, r[3] + dy))];
        else {
            const mx = Math.max(-r[0], Math.min(frame.width - r[2], dx)), my = Math.max(-r[1], Math.min(frame.height - r[3], dy));
            n = [r[0] + mx, r[1] + my, r[2] + mx, r[3] + my];
        }
        userEdit(el, { rect: n });
        refresh();
    });

    function setDrawMode(on) {
        state.drawMode = on;
        els.addBtn.setAttribute('aria-pressed', String(on));
        els.addBtn.textContent = on ? 'Drag on the preview to draw…' : 'Add box';
        els.canvas.style.cursor = on ? 'crosshair' : 'default';
    }

    // ---------- buttons ----------
    root.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn || !root.contains(btn)) return;
        const act = btn.dataset.act;
        if (act === 'choose') choose(false);
        else if (act === 'choose-image') choose(true);
        else if (act === 'sample') handleFile(await sampleFile());
        else if (act === 'cancel') { if (state.abort) state.abort.abort(); }
        else if (act === 'reset') { cleanup(); view('empty'); root.querySelector('[data-view="empty"] [data-act="choose"]').focus(); }
        else if (act === 'play') { const v = state.src && state.src.video; if (v) v.paused ? v.play() : v.pause(); }
        else if (act === 'add') { setDrawMode(!state.drawMode); if (state.drawMode) els.canvas.focus(); }
        else if (act.startsWith('export-')) doExport(act.slice(7));
    });

    async function doExport(kind) {
        const resolved = state.resolved[state.active];
        if (!resolved) return;
        emit('safezone_export', { export_type: kind });
        const source = state.src.kind === 'video' ? state.src.video : state.src.bitmap;
        const elements = state.elements.map((e) => ({ ...e, rect: rectAt(e, currentTime()) })).filter((e) => e.rect);
        if (kind === 'annotated') {
            download(await annotatedFrame({ source, sw: state.input.width, sh: state.input.height, resolved, elements, numbers: numbersMap(), issueByElement: issueByElement(), settings: state.settings, datasetVersion: data.datasetVersion }), exportNames.annotated(state.active, resolved.captionUsed));
        } else if (kind === 'overlay') {
            const ref = state.settings.showRefs ? data.references.find((r) => r.platform === state.active) : null;
            download(await overlayTemplate({ resolved, datasetVersion: data.datasetVersion, reference: ref }), exportNames.overlay(state.active, resolved.captionUsed));
        } else if (kind === 'json') {
            const report = buildReport({ data, input: state.input, settings: state.settings, elements: numbered(), results: state.results });
            download(new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' }), exportNames.report(state.settings.caption));
        } else if (kind === 'copy') {
            const text = summaryText(state.results, data);
            try { await navigator.clipboard.writeText(text); announce('Summary copied.'); } catch {
                const ta = document.createElement('textarea');
                ta.value = text;
                root.appendChild(ta);
                ta.select();
                announce('Copy is blocked in this browser; the summary is selected, press Ctrl+C.');
                setTimeout(() => ta.remove(), 15000);
            }
        } else if (kind === 'print') {
            await buildPrint(source, elements);
            window.print();
        }
    }

    async function buildPrint(source, elements) {
        const resolved = state.resolved[state.active];
        const blob = await annotatedFrame({ source, sw: state.input.width, sh: state.input.height, resolved, elements, numbers: numbersMap(), issueByElement: issueByElement(), settings: state.settings, datasetVersion: data.datasetVersion });
        const url = await new Promise((resolve) => { const fr = new FileReader(); fr.onload = () => resolve(fr.result); fr.readAsDataURL(blob); });
        const p = els.print;
        p.innerHTML = '';
        const h = document.createElement('h1');
        h.textContent = 'Social Video Safe Zone Checker report';
        const meta = document.createElement('p');
        meta.textContent = `${new Date().toLocaleString()} · ${state.input.kind} ${state.input.width} x ${state.input.height} (${state.input.aspect}) · caption ${state.settings.caption} · auto-captions ${state.settings.cc ? 'on' : 'off'} · phone view ${state.settings.device} · dataset ${data.datasetVersion}`;
        p.append(h, meta);
        for (const r of state.results) {
            const h2 = document.createElement('h2');
            h2.textContent = `${r.name}: ${VERDICT_LABEL[r.verdict]}${r.score != null ? ` (score ${r.score})` : ''}${r.status !== 'measured' ? ' - provisional zones' : ''}`;
            const ol = document.createElement('ol');
            for (const i of r.issues) { const li = document.createElement('li'); li.textContent = `${SEVERITY_LABEL[i.severity]}: ${i.message}${i.fixText ? `. Fix: ${i.fixText}.` : ''}`; ol.appendChild(li); }
            if (!r.issues.length) { const li = document.createElement('li'); li.textContent = 'No problems found.'; ol.appendChild(li); }
            p.append(h2, ol);
        }
        const img = document.createElement('img');
        img.src = url;
        img.alt = `Annotated frame for ${resolved.platform.name}`;
        p.appendChild(img);
    }

    view('empty');
    return { destroy() { cleanup(); root.innerHTML = ''; root.classList.remove('svsz'); }, state };
}

let uidN = 0;
const uid = () => ++uidN;
const bucket = (s) => (s == null ? 'none' : s < 60 ? '0-59' : s < 85 ? '60-84' : '85-100');
const durationBucket = (d) => (d == null ? 'image' : d < 15 ? '<15s' : d < 60 ? '15-60s' : d < 180 ? '60-180s' : '180s+');
