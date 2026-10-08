// Image Requirements Checker — interface. Everything runs in the browser:
// the file is read locally, never uploaded or stored.
//
//   mount(rootElement, { onEvent })   onEvent(name, params) receives
//   tool_started / tool_completed / tool_error (no file names or pixels).
import { FORMAT_NAMES, SUPPORTED_FORMATS, analyzeBytes, sniffFormat } from './analyze.js';
import { STATUS_ORDER, customProfile, evaluate, evaluateAll, fileNotes, formatBytes, readiness } from './check.js';
import { orientation, ratioLabel } from './geometry.js';
import { PROFILES, REVIEWED } from '../data/profiles.js';

export const MAX_INPUT_BYTES = 100 * 1024 * 1024;

const STATUS_TEXT = { pass: 'Pass', warning: 'Warning', fail: 'Fail', info: 'Info' };
const STATUS_ICON = { pass: '✓', warning: '!', fail: '✕', info: 'i' };
const LEVEL_TEXT = { hard: 'Platform limit', recommended: 'Recommendation', advisory: 'Guidance' };

function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') el.className = v;
        else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
        else if (k === 'text') el.textContent = v;
        else el.setAttribute(k, v === true ? '' : v);
    }
    for (const c of children.flat()) if (c !== null && c !== undefined && c !== false) el.append(c instanceof Node ? c : document.createTextNode(String(c)));
    return el;
}

const badge = (status) => h('span', { class: `irc-badge irc-${status}` }, h('span', { 'aria-hidden': 'true' }, STATUS_ICON[status]), ` ${STATUS_TEXT[status]}`);
const fmtName = (f) => (f === 'jpeg' ? 'JPEG' : FORMAT_NAMES[f] || f);

export function mount(root, { onEvent = () => {} } = {}) {
    const state = { file: null, url: null, image: null, meta: null, facts: null, all: null, selected: null, focus: { x: 0.5, y: 0.5 }, mode: 'platforms', custom: null, showSafe: true };
    let uid = 0;
    const id = (p) => `irc-${p}-${++uid}`;

    root.classList.add('irc');
    root.textContent = '';

    // --- Upload -----------------------------------------------------------
    // The visible "Choose an image" button is the control; the input stays out of the tab order.
    const input = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp', class: 'irc-sr', id: id('file'), tabindex: '-1', 'aria-label': 'Choose an image file' });
    const status = h('p', { class: 'irc-status', role: 'status', 'aria-live': 'polite' });
    const choose = h('button', { type: 'button', class: 'irc-btn irc-btn-primary', onclick: () => input.click() }, 'Choose an image');
    const drop = h('div', { class: 'irc-drop' },
        h('strong', {}, 'Check my image'),
        h('span', {}, 'Drag an image here, paste it, or choose a file.'),
        choose,
        h('span', { class: 'irc-muted' }, 'JPG, PNG or WebP, up to 100 MB'),
    );
    const privacy = h('p', { class: 'irc-privacy' }, h('strong', {}, 'Your image stays on your device. '), 'We analyse it in your browser and never upload or store it.');
    const errorBox = h('div', { class: 'irc-panel irc-error', role: 'alert', hidden: true });
    const upload = h('section', { class: 'irc-upload', 'aria-label': 'Upload an image' }, privacy, drop, input);
    const results = h('div', { class: 'irc-results', hidden: true });
    root.append(upload, status, errorBox, results);

    input.addEventListener('change', () => {
        if (input.files?.length) load(input.files[0]);
        else showError('empty', 'No image was selected. Choose a JPG, PNG or WebP file.');
        input.value = '';
    });
    for (const ev of ['dragenter', 'dragover']) drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); });
    for (const ev of ['dragleave', 'drop']) drop.addEventListener(ev, () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        const file = e.dataTransfer?.files?.[0];
        if (file) load(file);
        else showError('empty', 'Nothing was dropped. Drag an image file onto the box.');
    });
    document.addEventListener('paste', (e) => {
        if (!root.isConnected || e.target.closest?.('input, textarea')) return;
        const item = [...(e.clipboardData?.items || [])].find((i) => i.kind === 'file' && i.type.startsWith('image/'));
        if (item) load(item.getAsFile());
    });

    function showError(code, message) {
        onEvent('tool_error', { error_code: code });
        errorBox.hidden = false;
        errorBox.replaceChildren(h('p', { class: 'irc-error-title' }, message), h('button', { type: 'button', class: 'irc-btn', onclick: () => input.click() }, 'Choose another image'));
        status.textContent = '';
    }

    function reset() {
        if (state.url) URL.revokeObjectURL(state.url);
        Object.assign(state, { file: null, url: null, image: null, meta: null, facts: null, all: null, selected: null, focus: { x: 0.5, y: 0.5 }, custom: null });
        results.hidden = true;
        results.replaceChildren();
        upload.hidden = false;
        errorBox.hidden = true;
        status.textContent = '';
        choose.focus();
    }

    async function load(file) {
        errorBox.hidden = true;
        if (!file) return showError('empty', 'No image was selected. Choose a JPG, PNG or WebP file.');
        if (file.size === 0) return showError('empty', 'This file is empty. Choose an image file.');
        if (file.size > MAX_INPUT_BYTES) return showError('too-large', `This file is ${formatBytes(file.size)}, more than the 100 MB a browser can check reliably. Every platform here would reject it too; export a smaller version.`);
        onEvent('tool_started', {});
        status.textContent = 'Checking your image…';
        let bytes;
        try {
            bytes = new Uint8Array(await file.arrayBuffer());
        } catch {
            return showError('read', 'The file could not be read. Try choosing it again.');
        }
        const format = sniffFormat(bytes);
        if (!format) return showError('unsupported', 'This is not an image we can check. Use a JPG, PNG or WebP file.');
        if (!SUPPORTED_FORMATS.includes(format)) {
            const hint = format === 'heic' ? ' iPhone photos: share or export the photo as JPG first.' : format === 'svg' ? ' Export the graphic as PNG.' : ' Save it as JPG or PNG and check again.';
            return showError('unsupported', `${FORMAT_NAMES[format]} images are not supported by this checker or by most platforms.${hint}`);
        }
        const meta = analyzeBytes(bytes);
        const url = URL.createObjectURL(file);
        const image = new Image();
        try {
            // load/error rather than image.decode(): decode() waits while the tab is hidden.
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = reject;
                image.src = url;
            });
        } catch {
            URL.revokeObjectURL(url);
            return showError('corrupt', 'This image could not be opened. The file may be damaged or incomplete. Try exporting it again.');
        }
        if (!image.naturalWidth || !image.naturalHeight) {
            URL.revokeObjectURL(url);
            return showError('corrupt', 'This image has no visible size. The file may be damaged.');
        }
        if (state.url) URL.revokeObjectURL(state.url);

        const transparent = meta.alphaChannel === 'yes' ? usesTransparency(image) : meta.alphaChannel;
        const facts = { width: image.naturalWidth, height: image.naturalHeight, bytes: file.size, format, transparent, animated: meta.animated };
        // A new image in Custom requirements mode is checked against the same requirements straight away.
        const custom = state.mode === 'custom' && state.customValues ? customProfile(state.customValues).profile : null;
        Object.assign(state, { file, url, image, meta, facts, focus: { x: 0.5, y: 0.5 }, custom });
        state.all = evaluateAll(facts, PROFILES);
        state.selected = pickDefault(state.all);
        upload.hidden = true;
        render();
        const n = (count) => `${count} placement${count === 1 ? '' : 's'}`;
        status.textContent = `Checked: ready for ${n(state.all.best.length)}, usable with changes for ${n(state.all.adjust.length)}, not suitable for ${n(state.all.unsuitable.length)}.`;
        onEvent('tool_completed', { ready: state.all.best.length, adjust: state.all.adjust.length, unsuitable: state.all.unsuitable.length, format });
        results.querySelector('h2')?.focus();
    }

    const pickDefault = (all) => (all.adjust.find((r) => r.needsCrop) || all.best[0] || all.results[0]).profile.id;

    function usesTransparency(image) {
        const scale = Math.min(1, 512 / Math.max(image.naturalWidth, image.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(image.naturalWidth * scale));
        c.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const ctx = c.getContext('2d', { willReadFrequently: true });
        try {
            ctx.drawImage(image, 0, 0, c.width, c.height);
            const d = ctx.getImageData(0, 0, c.width, c.height).data;
            for (let i = 3; i < d.length; i += 4) if (d[i] < 250) return 'yes';
            return 'no';
        } catch {
            return 'unknown';
        }
    }

    // --- Results ----------------------------------------------------------
    function current() {
        if (state.mode === 'custom') return state.custom ? evaluate(state.facts, state.custom, state.focus) : null;
        const profile = PROFILES.find((p) => p.id === state.selected);
        return profile ? evaluate(state.facts, profile, state.focus) : null;
    }

    function render() {
        const f = state.facts;
        const ready = readiness(f, state.all);
        results.hidden = false;
        results.replaceChildren(
            yourImage(f),
            readinessPanel(ready),
            modeTabs(),
            state.mode === 'platforms' ? platformsView() : customView(),
            technical(),
            h('div', { class: 'irc-again' }, h('button', { type: 'button', class: 'irc-btn irc-btn-primary', onclick: reset }, 'Check another image')),
        );
    }

    function yourImage(f) {
        return h('section', { class: 'irc-panel irc-image', 'aria-labelledby': 'irc-h-image' },
            h('img', { src: state.url, alt: 'The image you are checking', class: 'irc-thumb' }),
            h('div', {},
                h('h2', { id: 'irc-h-image', tabindex: '-1' }, 'Your image'),
                h('p', { class: 'irc-filename' }, state.file.name || 'Pasted image'),
                h('ul', { class: 'irc-facts' },
                    h('li', {}, h('strong', {}, `${f.width} × ${f.height} px`)),
                    h('li', {}, `${ratioLabel(f.width, f.height)} · ${orientation(f.width, f.height)}`),
                    h('li', {}, `${fmtName(f.format)} · ${formatBytes(f.bytes)}`),
                    f.transparent === 'yes' ? h('li', {}, 'Has transparency') : null,
                ),
                h('button', { type: 'button', class: 'irc-btn irc-small', onclick: reset }, 'Check another image'),
            ),
        );
    }

    function readinessPanel(ready) {
        const tone = ready.score >= 85 ? 'pass' : ready.score >= 60 ? 'warning' : 'fail';
        return h('section', { class: 'irc-panel', 'aria-labelledby': 'irc-h-ready' },
            h('h2', { id: 'irc-h-ready' }, 'Image readiness'),
            h('div', { class: 'irc-ready' },
                h('p', { class: `irc-score irc-${tone}` }, h('span', { class: 'irc-score-num' }, String(ready.score)), h('span', { class: 'irc-score-of' }, '/ 100')),
                h('div', {},
                    h('p', { class: 'irc-score-label' }, ready.label),
                    h('ul', { class: 'irc-checklist' }, ready.items.map((i) => h('li', {}, badge(i.status), h('span', {}, h('strong', {}, `${i.label}: `), i.text)))),
                ),
            ),
            h('details', { class: 'irc-how' }, h('summary', {}, 'How the score works'),
                h('p', {}, 'The score is a quick summary: format (20 points), resolution (25), file size (20) and how many placements the image fits (35, warnings count half). The detailed results below always take priority.')),
        );
    }

    function modeTabs() {
        // A two-button switch (aria-pressed), not ARIA tabs: it behaves like buttons, so it is announced as buttons.
        const tab = (mode, label) => h('button', {
            type: 'button', class: 'irc-tab', 'aria-pressed': String(state.mode === mode), id: `irc-tab-${mode}`,
            onclick: () => { state.mode = mode; state.focus = { x: 0.5, y: 0.5 }; render(); document.getElementById(`irc-tab-${mode}`)?.focus(); },
        }, label);
        return h('div', { class: 'irc-tabs', role: 'group', 'aria-label': 'Check against' }, tab('platforms', 'Platforms'), tab('custom', 'Custom requirements'));
    }

    function platformsView() {
        const all = state.all;
        const chip = (r) => h('li', {}, h('button', { type: 'button', class: 'irc-chip', onclick: () => select(r.profile.id) }, `${r.profile.platform} ${r.profile.placement}`));
        const group = (title, list, tone, empty) => h('div', { class: `irc-best-col irc-${tone}-col` },
            h('h3', {}, badge(tone), ` ${title}`),
            list.length ? h('ul', {}, list.map(chip)) : h('p', { class: 'irc-muted' }, empty));

        const platforms = [...new Set(PROFILES.map((p) => p.platform))];
        const where = h('section', { class: 'irc-panel', 'aria-labelledby': 'irc-h-where' },
            h('h2', { id: 'irc-h-where' }, 'Where can I use this image?'),
            h('div', { class: 'irc-platforms' }, platforms.map((name) => h('div', { class: 'irc-platform' },
                h('h3', {}, name),
                h('ul', {}, all.results.filter((r) => r.profile.platform === name).map((r) => {
                    const reason = r.checks.filter((c) => c.status === r.status && r.status !== 'pass').sort((a, b) => STATUS_ORDER[b.status] - STATUS_ORDER[a.status])[0];
                    return h('li', {},
                        h('button', { type: 'button', class: 'irc-row', 'aria-pressed': String(state.selected === r.profile.id), onclick: () => select(r.profile.id) },
                            h('span', { class: 'irc-row-name' }, r.profile.placement),
                            badge(r.status),
                            h('span', { class: 'irc-row-why' }, reason ? reason.title : r.headline),
                        ));
                })),
            ))),
        );

        return h('div', { class: 'irc-stack' },
            h('section', { class: 'irc-panel', 'aria-labelledby': 'irc-h-best' },
                h('h2', { id: 'irc-h-best' }, 'Best matches'),
                h('div', { class: 'irc-best' },
                    group('Ready to use', all.best, 'pass', 'No placement fits without changes.'),
                    group('Works with changes', all.adjust, 'warning', 'None.'),
                    group('Not suitable', all.unsuitable, 'fail', 'None. The image meets every hard limit.'),
                ),
            ),
            where,
            problems(),
            detailView(current()),
        );
    }

    function select(profileId) {
        state.selected = profileId;
        state.focus = { x: 0.5, y: 0.5 };
        render();
        document.getElementById('irc-h-detail')?.focus();
    }

    function problems() {
        const notes = fileNotes(state.meta);
        const fails = state.all.unsuitable.length;
        const crops = state.all.results.filter((r) => r.needsCrop).length;
        return h('section', { class: 'irc-panel', 'aria-labelledby': 'irc-h-problems' },
            h('h2', { id: 'irc-h-problems' }, 'Problems & warnings'),
            h('ul', { class: 'irc-notes' },
                notes.map((n) => h('li', { class: `irc-note irc-${n.status}-note` }, badge(n.status), h('div', {}, h('strong', {}, n.title), h('p', {}, n.detail), n.action ? h('p', { class: 'irc-action' }, n.action) : null))),
                fails ? h('li', { class: 'irc-note' }, badge('fail'), h('div', {}, h('strong', {}, `${fails} placement${fails === 1 ? '' : 's'} not suitable`), h('p', {}, 'Select one in the list above to see exactly what is wrong and what to do.'))) : null,
                crops ? h('li', { class: 'irc-note' }, badge('warning'), h('div', {}, h('strong', {}, `${crops} placement${crops === 1 ? ' crops' : 's crop'} the image`), h('p', {}, 'Each needs a different shape. The crop preview shows what stays visible.'))) : null,
                !notes.length && !fails && !crops ? h('li', { class: 'irc-note' }, badge('pass'), h('div', {}, h('strong', {}, 'No problems found for the placements checked.'))) : null,
            ),
        );
    }

    function detailView(result) {
        if (!result) return null;
        const p = result.profile;
        return h('section', { class: 'irc-panel irc-detail', 'aria-labelledby': 'irc-h-detail' },
            h('h2', { id: 'irc-h-detail', tabindex: '-1' }, p.id === 'custom' ? 'Your requirements' : `${p.platform}: ${p.placement}`),
            h('p', { class: 'irc-headline' }, badge(result.status), ` ${result.headline}`),
            h('ul', { class: 'irc-checks' }, result.checks.map((c) => h('li', { class: `irc-check irc-${c.status}-check` },
                badge(c.status),
                h('div', {},
                    h('strong', {}, c.title),
                    c.detail ? h('p', {}, c.detail) : null,
                    c.expected || c.actual ? h('p', { class: 'irc-expect' }, c.expected ? h('span', {}, `Expected: ${c.expected}`) : null, c.actual ? h('span', {}, /^(Your|After)/.test(c.actual) ? c.actual : `Your image: ${c.actual}`) : null) : null,
                    c.action ? h('p', { class: 'irc-action' }, h('strong', {}, 'What to do: '), c.action) : null,
                )))),
            cropPreview(result),
            p.notes?.length ? h('ul', { class: 'irc-pnotes' }, p.notes.map((n) => h('li', {}, n))) : null,
            p.sources?.length ? h('p', { class: 'irc-sources' }, `Sources (reviewed ${REVIEWED}): `, p.sources.map((s, i) => [i ? '; ' : '', s.url ? h('a', { href: s.url, target: '_blank', rel: 'noopener noreferrer' }, s.label) : s.label])) : null,
        );
    }

    // --- Crop and safe-zone preview ----------------------------------------
    function cropPreview(result) {
        const p = result.profile;
        const crop = result.crop || { x: 0, y: 0, w: state.facts.width, h: state.facts.height, axis: null, removed: 0, ratio: state.facts.width / state.facts.height };
        const canCrop = !!crop.axis;
        const original = h('canvas', {
            class: 'irc-canvas', role: canCrop ? 'slider' : 'img', tabindex: canCrop ? '0' : null,
            'aria-label': canCrop ? 'Crop position. Use the arrow keys, or drag, to choose which part stays visible.' : 'Your image, with nothing cropped',
            'aria-valuemin': canCrop ? '0' : null, 'aria-valuemax': canCrop ? '100' : null,
            'aria-valuenow': canCrop ? String(Math.round((crop.axis === 'width' ? state.focus.x : state.focus.y) * 100)) : null,
        });
        const out = h('canvas', { class: 'irc-canvas', role: 'img', 'aria-label': `Expected result for ${p.placement}` });
        const safe = p.safeZone;
        const explain = canCrop
            ? `Your image is ${crop.axis === 'width' ? 'wider' : 'taller'} than ${ratioLabel(crop.ratio * 1000, 1000)}. About ${Math.max(1, Math.round(crop.removed * 100))}% of the ${crop.axis} will be removed${crop.axis === 'width' ? ' from the left and right' : ' from the top and bottom'}. Drag the image or use the arrow keys to see what you would keep.`
            : 'Nothing needs to be cropped for this placement.';

        const draw = () => {
            const r = evaluate(state.facts, p, state.focus).crop || crop;
            drawOriginal(original, state.image, r);
            drawResult(out, state.image, r, p, state.showSafe && safe);
            if (canCrop) original.setAttribute('aria-valuenow', String(Math.round((r.axis === 'width' ? state.focus.x : state.focus.y) * 100)));
            views.forEach(([canvas, view]) => drawView(canvas, state.image, r, view));
        };

        if (canCrop) {
            const move = (delta) => {
                const key = crop.axis === 'width' ? 'x' : 'y';
                state.focus = { ...state.focus, [key]: Math.min(1, Math.max(0, state.focus[key] + delta)) };
                draw();
            };
            original.addEventListener('keydown', (e) => {
                const step = e.shiftKey ? 0.2 : 0.05;
                if (['ArrowLeft', 'ArrowUp'].includes(e.key)) { e.preventDefault(); move(-step); }
                if (['ArrowRight', 'ArrowDown'].includes(e.key)) { e.preventDefault(); move(step); }
                if (e.key === 'Home') { e.preventDefault(); move(-1); }
                if (e.key === 'End') { e.preventDefault(); move(1); }
            });
            let last = null;
            original.addEventListener('pointerdown', (e) => { last = e; original.setPointerCapture(e.pointerId); });
            original.addEventListener('pointermove', (e) => {
                if (!last) return;
                const rect = original.getBoundingClientRect();
                const free = crop.axis === 'width' ? (1 - crop.w / state.facts.width) * rect.width : (1 - crop.h / state.facts.height) * rect.height;
                const d = crop.axis === 'width' ? e.clientX - last.clientX : e.clientY - last.clientY;
                if (free > 0) move(d / free);
                last = e;
            });
            for (const ev of ['pointerup', 'pointercancel']) original.addEventListener(ev, () => { last = null; });
        }

        const views = (p.views || []).map((v) => [h('canvas', { class: 'irc-canvas irc-view', role: 'img', 'aria-label': `${v.label}: approximately what shows` }), v]);
        // After the section is in the page (a timeout, not requestAnimationFrame, which waits while the tab is
        // hidden); redrawn when the available width changes, e.g. when a phone is rotated.
        const pair = h('div', { class: 'irc-canvases' },
            h('figure', {}, original, h('figcaption', {}, canCrop ? 'Original — the bright part stays visible' : 'Original')),
            h('figure', {}, out, h('figcaption', {}, p.frame ? `Expected result (${ratioLabel(crop.ratio * 1000, 1000)}, ${p.frame.width} × ${p.frame.height} px recommended)` : `Expected result (${ratioLabel(crop.ratio * 1000, 1000)})`)),
        );
        setTimeout(draw, 0);
        if (typeof ResizeObserver === 'function') {
            let width = 0;
            new ResizeObserver(([entry]) => {
                const next = Math.round(entry.contentRect.width);
                if (next && next !== width) { width = next; draw(); }
            }).observe(pair);
        }

        return h('div', { class: 'irc-preview' },
            h('h3', {}, 'Crop preview'),
            h('p', { class: 'irc-explain' }, explain),
            pair,
            canCrop ? h('div', { class: 'irc-actions' }, h('button', { type: 'button', class: 'irc-btn irc-small', onclick: () => { state.focus = { x: 0.5, y: 0.5 }; draw(); } }, 'Centre the crop')) : null,
            safe ? h('div', { class: 'irc-safe' },
                h('h3', {}, 'Safe zone preview'),
                h('label', { class: 'irc-toggle' }, h('input', { type: 'checkbox', checked: state.showSafe ? true : null, onchange: (e) => { state.showSafe = e.target.checked; draw(); } }), ' Show the safe zone on the expected result'),
                h('p', {}, h('strong', {}, `${safe.label} (${LEVEL_TEXT[safe.level]}). `), safe.note),
                h('ul', { class: 'irc-legend' },
                    h('li', {}, h('span', { class: 'irc-key irc-key-safe', 'aria-hidden': 'true' }), 'Safe area: keep text, faces and logos inside'),
                    h('li', {}, h('span', { class: 'irc-key irc-key-covered', 'aria-hidden': 'true' }), 'May be hidden or cropped'),
                ),
            ) : null,
            views.length ? h('div', { class: 'irc-views' }, h('h3', {}, 'How it may also be shown'),
                h('div', { class: 'irc-canvases' }, views.map(([canvas, v]) => h('figure', {}, canvas, h('figcaption', {}, `${v.label} (${ratioLabel(v.ratio[0] * 1000, v.ratio[1] * 1000)}). ${v.note}`))))) : null,
        );
    }

    function sized(canvas, w, h2) {
        // Size from the figure's real width; CSS max-width + height:auto keep the shape if it shrinks later.
        const box = Math.min(canvas.closest('figure')?.clientWidth || canvas.parentElement?.clientWidth || 280, 480);
        const scale = Math.min(box / w, 420 / h2);
        const dpr = window.devicePixelRatio || 1;
        canvas.style.width = `${Math.round(w * scale)}px`;
        canvas.width = Math.max(1, Math.round(w * scale * dpr));
        canvas.height = Math.max(1, Math.round(h2 * scale * dpr));
        const ctx = canvas.getContext('2d');
        return [ctx, canvas.width / w];
    }

    function drawOriginal(canvas, image, r) {
        const W = state.facts.width;
        const H = state.facts.height;
        const [ctx, s] = sized(canvas, W, H);
        ctx.drawImage(image, 0, 0, W * s, H * s);
        if (!r.axis) return;
        ctx.fillStyle = 'rgba(15, 18, 24, 0.62)';
        ctx.beginPath();
        ctx.rect(0, 0, W * s, H * s);
        ctx.rect(r.x * s, r.y * s, r.w * s, r.h * s);
        ctx.fill('evenodd');
        ctx.strokeStyle = '#F0E442';
        ctx.lineWidth = Math.max(2, 3 * (window.devicePixelRatio || 1));
        ctx.strokeRect(r.x * s, r.y * s, r.w * s, r.h * s);
    }

    function drawResult(canvas, image, r, profile, safe) {
        const [ctx, s] = sized(canvas, r.w, r.h);
        const W = r.w * s;
        const H = r.h * s;
        ctx.save();
        if (profile.display === 'circle') {
            ctx.fillStyle = '#e8ebf0';
            ctx.fillRect(0, 0, W, H);
            ctx.beginPath();
            ctx.arc(W / 2, H / 2, Math.min(W, H) / 2, 0, Math.PI * 2);
            ctx.clip();
        }
        ctx.drawImage(image, r.x, r.y, r.w, r.h, 0, 0, W, H);
        ctx.restore();
        if (!safe) return;
        const [l, t, rr, b] = safe.safe;
        const covered = safe.covered || [[0, 0, 1, t], [0, b, 1, 1], [0, t, l, b], [rr, t, 1, b]];
        ctx.fillStyle = 'rgba(213, 94, 0, 0.45)';
        for (const [cl, ct, cr, cb] of covered) ctx.fillRect(cl * W, ct * H, (cr - cl) * W, (cb - ct) * H);
        ctx.strokeStyle = '#009E73';
        ctx.lineWidth = Math.max(2, 3 * (window.devicePixelRatio || 1));
        ctx.setLineDash([10, 6]);
        ctx.strokeRect(l * W, t * H, (rr - l) * W, (b - t) * H);
    }

    function drawView(canvas, image, r, view) {
        const want = view.ratio[0] / view.ratio[1];
        let { x, y, w, h: hh } = r;
        if (w / hh > want) { const nw = hh * want; x += (w - nw) / 2; w = nw; } else { const nh = w / want; y += (hh - nh) / 2; hh = nh; }
        const [ctx, s] = sized(canvas, w, hh);
        ctx.drawImage(image, x, y, w, hh, 0, 0, w * s, hh * s);
    }

    // --- Custom requirements ----------------------------------------------
    function customView() {
        const v = state.customValues || {};
        const field = (key, label, hint, type = 'number') => {
            const fid = `irc-c-${key}`;
            return h('div', { class: 'irc-field' },
                h('label', { for: fid }, label),
                h('input', { id: fid, name: key, type, inputmode: type === 'number' ? 'decimal' : null, min: type === 'number' ? '1' : null, step: 'any', value: v[key] ?? '', placeholder: hint, 'aria-describedby': `${fid}-err` }),
                h('p', { class: 'irc-field-error', id: `${fid}-err` }, state.customErrors?.[key] || ''),
            );
        };
        const form = h('form', {
            class: 'irc-form', novalidate: true,
            onsubmit: (e) => {
                e.preventDefault();
                const data = new FormData(e.target);
                const values = { ...Object.fromEntries(['minWidth', 'minHeight', 'maxWidth', 'maxHeight', 'ratio', 'maxMB'].map((k) => [k, data.get(k)])), formats: data.getAll('formats') };
                const { profile, errors } = customProfile(values);
                state.customValues = values;
                state.customErrors = errors;
                state.custom = profile;
                state.focus = { x: 0.5, y: 0.5 };
                render();
                document.getElementById(profile ? 'irc-h-detail' : 'irc-h-custom')?.focus();
            },
        },
            h('div', { class: 'irc-fields' },
                field('minWidth', 'Minimum width (px)', 'e.g. 1200'),
                field('minHeight', 'Minimum height (px)', 'e.g. 630'),
                field('maxWidth', 'Maximum width (px)', 'optional'),
                field('maxHeight', 'Maximum height (px)', 'optional'),
                field('ratio', 'Required shape (aspect ratio)', 'e.g. 1.91:1 or 16:9', 'text'),
                field('maxMB', 'Maximum file size (MB)', 'e.g. 2'),
            ),
            h('fieldset', { class: 'irc-formats' }, h('legend', {}, 'Allowed formats (leave all unticked for any)'),
                ['jpeg', 'png', 'webp'].map((f) => h('label', {}, h('input', { type: 'checkbox', name: 'formats', value: f, checked: (v.formats || []).includes(f) ? true : null }), ` ${f === 'jpeg' ? 'JPG' : fmtName(f)}`))),
            state.customErrors?.form ? h('p', { class: 'irc-field-error', role: 'alert' }, state.customErrors.form) : null,
            h('button', { type: 'submit', class: 'irc-btn irc-btn-primary' }, 'Check against my requirements'),
        );
        return h('div', { class: 'irc-stack' },
            h('section', { class: 'irc-panel', 'aria-labelledby': 'irc-h-custom' },
                h('h2', { id: 'irc-h-custom', tabindex: '-1' }, 'Custom requirements'),
                h('p', { class: 'irc-muted' }, 'Enter the requirements you were given, for example by a client, a print shop or a website. Fill in only what you need.'),
                form),
            state.custom ? detailView(current()) : null,
        );
    }

    // --- Technical details -------------------------------------------------
    function technical() {
        const f = state.facts;
        const m = state.meta;
        const yesNo = (v) => (v === 'yes' ? 'Yes' : v === 'no' ? 'No' : 'Could not be determined');
        const transparency = m.alphaChannel === 'yes' ? (f.transparent === 'yes' ? 'Yes — the image has transparent areas' : f.transparent === 'no' ? 'Alpha channel present, but every pixel is opaque' : 'Alpha channel present') : yesNo(m.alphaChannel);
        const colour = [m.cmyk ? 'CMYK (print)' : 'RGB', m.colorProfile.srgb ? 'sRGB' : null, m.colorProfile.icc ? 'embedded ICC profile' : null].filter(Boolean).join(', ');
        const stored = m.width && m.height && (m.width !== f.width || m.height !== f.height) ? `${m.width} × ${m.height} px stored, shown upright as ${f.width} × ${f.height} px` : `${f.width} × ${f.height} px`;
        const rows = [
            ['Dimensions', stored],
            ['Aspect ratio', `${ratioLabel(f.width, f.height)} (${(f.width / f.height).toFixed(3)})`],
            ['Orientation', orientation(f.width, f.height)],
            ['File size', `${formatBytes(f.bytes)} (${f.bytes.toLocaleString('en-US')} bytes)`],
            ['Format', fmtName(f.format)],
            ['MIME type', `${m.mime} (detected from the file's content${state.file.type && state.file.type !== m.mime ? `; the file says ${state.file.type}` : ''})`],
            ['File name', state.file.name || 'Pasted image'],
            ['Transparency', transparency],
            ['Animated', yesNo(m.animated)],
            ['Colour', colour],
            m.bitDepth ? ['Bit depth', `${m.bitDepth}-bit`] : null,
            m.progressive !== null && f.format === 'jpeg' ? ['JPEG encoding', m.progressive ? 'Progressive' : 'Baseline'] : null,
            ['DPI', m.dpi ? `${m.dpi} DPI (only matters for print; screens use pixels)` : 'Not set (only matters for print)'],
            ['Camera / date', m.exif && (m.exif.make || m.exif.model || m.exif.dateTaken) ? [m.exif.make, m.exif.model, m.exif.dateTaken].filter(Boolean).join(' · ') : 'No camera information'],
            ['Location (GPS)', m.exif ? (m.exif.hasGps ? 'Present in the metadata' : 'Not present') : 'No EXIF metadata'],
        ].filter(Boolean);
        return h('details', { class: 'irc-panel irc-tech' },
            h('summary', {}, h('h2', {}, 'Technical details')),
            h('table', {}, h('tbody', {}, rows.map(([k, val]) => h('tr', {}, h('th', { scope: 'row' }, k), h('td', {}, val))))),
        );
    }
}
