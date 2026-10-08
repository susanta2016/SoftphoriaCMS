// Exact Image KB Optimizer — interface. Everything happens in the browser:
// images are read, compressed and zipped locally and never uploaded.
//
//   mount(rootElement, { onEvent })   onEvent(name, params) receives
//   tool_started / tool_completed / tool_error / tool_download — never file
//   names, sizes of specific files or image data.
import { Cancelled, keepFormat, optimize } from './optimize.js';
import { FORMAT_NAMES, ImageError, encoderFor, load, outputName } from './image.js';
import { PRESETS, formatSize, limitBytes, parseTargetParam, targetError, targetLabel } from './target.js';
import { zip } from './zip.js';

export const MAX_FILES = 20;

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

const badge = (ok, text) => h('span', { class: `iko-badge ${ok ? 'iko-pass' : 'iko-fail'}` }, h('span', { 'aria-hidden': 'true' }, ok ? '✓' : '✕'), ` ${text}`);
const dims = (w, h2) => `${w} × ${h2} px`;

export function mount(root, { onEvent = () => {} } = {}) {
    const preset = parseTargetParam(new URLSearchParams(window.location.search).get('target'));
    const state = {
        files: [], // { id, file, name, bytes, format, width, height, image, url, transparent, result, resultUrl, error }
        target: preset || { value: 50, unit: 'KB' },
        customText: '', customUnit: 'KB', customError: null,
        format: 'keep', maxWidth: '', maxHeight: '',
        busy: false, cancelled: false, progress: null, previewId: null,
    };
    let nextId = 1;
    let started = false;

    root.classList.add('iko');
    root.textContent = '';
    const status = h('p', { class: 'iko-status', role: 'status', 'aria-live': 'polite' });
    const view = h('div', { class: 'iko-view' });
    const input = h('input', { type: 'file', multiple: true, accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp', class: 'iko-sr', tabindex: '-1', 'aria-label': 'Choose images' });
    root.append(input, view, status);

    input.addEventListener('change', () => { addFiles([...(input.files || [])]); input.value = ''; });
    document.addEventListener('paste', (e) => {
        if (!root.isConnected || state.busy || e.target.closest?.('input, textarea')) return;
        const files = [...(e.clipboardData?.items || [])].filter((i) => i.kind === 'file' && i.type.startsWith('image/')).map((i) => i.getAsFile());
        if (files.length) addFiles(files);
    });

    const limit = () => limitBytes(state.target.value, state.target.unit);
    const targetText = () => targetLabel(state.target.value, state.target.unit);
    const say = (text) => { status.textContent = text; };
    const fail = (code, message) => { onEvent('tool_error', { error_code: code }); say(message); };

    async function addFiles(list) {
        if (state.busy) return;
        if (!list.length) return fail('empty', 'No image was selected. Choose a JPG, PNG or WebP image.');
        const room = MAX_FILES - state.files.length;
        if (room <= 0) return fail('too-many', `You can compress up to ${MAX_FILES} images at a time.`);
        const errors = [];
        for (const file of list.slice(0, room)) {
            try {
                const img = await load(file);
                state.files.push({ id: nextId++, file, name: file.name || 'pasted-image', bytes: file.size, ...img, result: null, resultUrl: null, error: null });
            } catch (e) {
                const message = e instanceof ImageError ? e.message : 'This image could not be opened.';
                errors.push(`${file.name || 'Image'}: ${message}`);
                onEvent('tool_error', { error_code: e instanceof ImageError ? e.code : 'load' });
            }
        }
        if (list.length > room) errors.push(`Only the first ${room} images were added (the limit is ${MAX_FILES}).`);
        if (state.files.length && !started) {
            started = true;
            onEvent('tool_started', { files: state.files.length });
        }
        clearResults();
        render();
        say(errors.length ? errors.join(' ') : `${state.files.length} ${state.files.length === 1 ? 'image' : 'images'} ready. Choose a target size and compress.`);
        if (errors.length) view.querySelector('.iko-errors')?.focus();
    }

    function removeFile(id) {
        const f = state.files.find((x) => x.id === id);
        if (!f) return;
        URL.revokeObjectURL(f.url);
        if (f.resultUrl) URL.revokeObjectURL(f.resultUrl);
        state.files = state.files.filter((x) => x.id !== id);
        render();
        say(`${f.name} removed.`);
    }

    function clearAll() {
        state.files.forEach((f) => { URL.revokeObjectURL(f.url); if (f.resultUrl) URL.revokeObjectURL(f.resultUrl); });
        state.files = [];
        render();
        say('All images cleared.');
        view.querySelector('.iko-choose')?.focus();
    }

    function clearResults() {
        state.files.forEach((f) => { if (f.resultUrl) URL.revokeObjectURL(f.resultUrl); f.result = null; f.resultUrl = null; f.error = null; });
        state.previewId = null;
    }

    // Browsers that cannot write WebP (older Safari) get JPEG instead, with a note.
    const outputFormat = (f) => {
        const wanted = state.format === 'keep' ? keepFormat(f.format) : state.format;
        return wanted === 'webp' && state.webp === false ? 'jpeg' : wanted;
    };

    async function compress(formatOverride = null, onlyId = null) {
        if (!state.files.length || state.busy) return;
        if (!limit()) return fail('target', 'Choose a valid target size first.');
        if (formatOverride) state.format = formatOverride;
        const maxWidth = Number(state.maxWidth) > 0 ? Math.round(Number(state.maxWidth)) : null;
        const maxHeight = Number(state.maxHeight) > 0 ? Math.round(Number(state.maxHeight)) : null;
        const queue = onlyId ? state.files.filter((f) => f.id === onlyId) : state.files;
        if (!onlyId) clearResults();
        state.busy = true;
        state.cancelled = false;
        let index = 0;
        for (const f of queue) {
            index += 1;
            state.progress = { index, total: queue.length };
            render();
            say(`Compressing ${queue.length > 1 ? `image ${index} of ${queue.length}` : f.name}…`);
            if (f.resultUrl) URL.revokeObjectURL(f.resultUrl);
            f.result = null; f.resultUrl = null; f.error = null;
            const encode = encoderFor(f.image);
            try {
                const result = await optimize(
                    { width: f.width, height: f.height, bytes: f.bytes, format: f.format, blob: f.file },
                    { limit: limit(), format: outputFormat(f), maxWidth, maxHeight },
                    encode,
                    { cancelled: () => state.cancelled },
                );
                const wanted = state.format === 'keep' ? keepFormat(f.format) : state.format;
                f.result = { ...result, limit: limit(), target: targetText(), maxWidth, maxHeight, webpFallback: wanted === 'webp' && result.format === 'jpeg' };
                if (result.blob) f.resultUrl = URL.createObjectURL(result.blob);
            } catch (e) {
                if (e instanceof Cancelled) {
                    f.error = 'Cancelled.';
                    break;
                }
                f.error = e instanceof ImageError ? e.message : 'This image could not be compressed in your browser. Try a different target or format.';
                onEvent('tool_error', { error_code: e instanceof ImageError ? e.code : 'compress' });
            } finally {
                encode.release();
            }
        }
        state.busy = false;
        state.progress = null;
        const done = state.files.filter((f) => f.result);
        const passed = done.filter((f) => f.result.ok).length;
        if (!state.previewId && done.length) state.previewId = done[0].id;
        render();
        if (state.cancelled) say('Compression cancelled.');
        else say(done.length === 1 && state.files.length === 1
            ? (passed ? `Done: ${formatSize(done[0].result.blob.size)}, under ${targetText()}.` : `The target of ${targetText()} could not be reached.`)
            : `${passed} of ${state.files.length} images are under ${targetText()}.`);
        onEvent('tool_completed', { files: state.files.length, passed, failed: done.length - passed, target_kb: Math.round(limit() / 1000), output_format: state.format, batch: state.files.length > 1 });
        view.querySelector('.iko-results h2')?.focus();
    }

    function download(f) {
        const a = h('a', { href: f.resultUrl, download: outputName(f.name, f.result.target, f.result.format) });
        document.body.append(a);
        a.click();
        a.remove();
        onEvent('tool_download', { download_type: 'single', passed: f.result.ok });
    }

    async function downloadZip() {
        const ok = state.files.filter((f) => f.result?.ok);
        if (!ok.length) return;
        say('Creating the ZIP file…');
        try {
            const files = await Promise.all(ok.map(async (f) => ({ name: outputName(f.name, f.result.target, f.result.format), data: new Uint8Array(await f.result.blob.arrayBuffer()) })));
            const url = URL.createObjectURL(new Blob([zip(files)], { type: 'application/zip' }));
            const a = h('a', { href: url, download: `compressed-${targetText().replace(/\s+/g, '').toLowerCase()}.zip` });
            document.body.append(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 4000);
            say(`ZIP file with ${ok.length} ${ok.length === 1 ? 'image' : 'images'} downloaded.`);
            onEvent('tool_download', { download_type: 'zip', files: ok.length });
        } catch {
            fail('zip', 'The ZIP file could not be created. Download the images one by one instead.');
        }
    }

    // --- Rendering ----------------------------------------------------------
    function render() {
        view.replaceChildren(
            h('p', { class: 'iko-privacy' }, h('span', { 'aria-hidden': 'true' }, '🔒 '), h('strong', {}, 'Your images never leave your device.'), ' Compression happens entirely in your browser.'),
            state.files.length ? fileList() : dropZone(),
            targetPicker(),
            options(),
            actions(),
            results(),
        );
    }

    function dropZone() {
        const zone = h('div', { class: 'iko-drop' },
            h('strong', {}, 'Select an image'),
            h('span', {}, 'Drag images here, paste one, or choose files. JPG, PNG or WebP, up to 50 MB each.'),
            h('button', { type: 'button', class: 'iko-btn iko-btn-primary iko-choose', onclick: () => input.click() }, 'Choose images'),
        );
        for (const ev of ['dragenter', 'dragover']) zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('is-over'); });
        for (const ev of ['dragleave', 'drop']) zone.addEventListener(ev, () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', (e) => { e.preventDefault(); addFiles([...(e.dataTransfer?.files || [])]); });
        return zone;
    }

    function fileList() {
        const list = h('section', { class: 'iko-panel iko-files', 'aria-labelledby': 'iko-h-files' },
            h('div', { class: 'iko-row' },
                h('h2', { id: 'iko-h-files' }, state.files.length === 1 ? 'Your image' : `Your images (${state.files.length})`),
                h('div', { class: 'iko-row-actions' },
                    state.files.length < MAX_FILES ? h('button', { type: 'button', class: 'iko-btn iko-small iko-choose', onclick: () => input.click(), disabled: state.busy }, 'Add images') : null,
                    h('button', { type: 'button', class: 'iko-btn iko-small', onclick: clearAll, disabled: state.busy }, 'Clear all'),
                ),
            ),
            h('ul', {}, state.files.map((f) => h('li', { class: 'iko-file' },
                h('img', { src: f.url, alt: '', class: 'iko-file-thumb' }),
                h('div', { class: 'iko-file-info' },
                    h('span', { class: 'iko-file-name' }, f.name),
                    h('span', { class: 'iko-muted' }, `${formatSize(f.bytes)} · ${dims(f.width, f.height)} · ${FORMAT_NAMES[f.format]}${f.transparent ? ' · transparent' : ''}`),
                ),
                h('button', { type: 'button', class: 'iko-btn iko-small', onclick: () => removeFile(f.id), disabled: state.busy, 'aria-label': `Remove ${f.name}` }, 'Remove'),
            ))),
        );
        list.addEventListener('dragover', (e) => e.preventDefault());
        list.addEventListener('drop', (e) => { e.preventDefault(); addFiles([...(e.dataTransfer?.files || [])]); });
        return list;
    }

    function setTarget(target) {
        state.target = target;
        state.customError = null;
        clearResults();
        render();
        view.querySelector('.iko-chip[aria-pressed="true"]')?.focus();
    }

    function targetPicker() {
        const isPreset = (p) => !state.customActive && p.value === state.target.value && p.unit === state.target.unit;
        const applyCustom = () => {
            const error = targetError(state.customText, state.customUnit);
            state.customError = error;
            if (!error) {
                state.customActive = true;
                state.target = { value: Number(String(state.customText).replace(',', '.')), unit: state.customUnit };
                clearResults();
            }
            render();
            view.querySelector('#iko-custom')?.focus();
        };
        return h('section', { class: 'iko-panel', 'aria-labelledby': 'iko-h-target' },
            h('h2', { id: 'iko-h-target' }, 'Maximum file size'),
            h('div', { class: 'iko-chips', role: 'group', 'aria-label': 'Target size' },
                PRESETS.map((p) => h('button', {
                    type: 'button', class: 'iko-chip', 'aria-pressed': String(isPreset(p)), disabled: state.busy,
                    onclick: () => { state.customActive = false; setTarget({ value: p.value, unit: p.unit }); },
                }, p.label))),
            h('div', { class: 'iko-custom' },
                h('label', { for: 'iko-custom' }, 'Custom size'),
                h('div', { class: 'iko-custom-row' },
                    h('input', {
                        id: 'iko-custom', type: 'text', inputmode: 'decimal', placeholder: 'e.g. 75', value: state.customText, disabled: state.busy,
                        'aria-describedby': 'iko-custom-error', 'aria-invalid': state.customError ? 'true' : null,
                        oninput: (e) => { state.customText = e.target.value; },
                        onkeydown: (e) => { if (e.key === 'Enter') { e.preventDefault(); applyCustom(); } },
                    }),
                    h('select', { 'aria-label': 'Unit', disabled: state.busy, onchange: (e) => { state.customUnit = e.target.value; } },
                        ['KB', 'MB'].map((u) => h('option', { value: u, selected: state.customUnit === u ? true : null }, u))),
                    h('button', { type: 'button', class: 'iko-btn', onclick: applyCustom, disabled: state.busy, 'aria-pressed': String(!!state.customActive) }, 'Use'),
                ),
                h('p', { id: 'iko-custom-error', class: 'iko-field-error' }, state.customError || ''),
            ),
            h('p', { class: 'iko-muted iko-target-note' }, `Target: ≤ ${targetText()} (${limit()?.toLocaleString('en-US')} bytes). We aim under the stricter count, so it passes whether a site counts 1 KB as 1,000 or 1,024 bytes.`),
        );
    }

    function options() {
        const transparentToJpeg = state.files.some((f) => f.transparent) && state.format === 'jpeg';
        const field = (id, label, key) => h('div', { class: 'iko-field' },
            h('label', { for: id }, label),
            h('input', { id, type: 'number', inputmode: 'numeric', min: '1', step: '1', placeholder: 'No limit', value: state[key], disabled: state.busy, oninput: (e) => { state[key] = e.target.value; clearResults(); } }),
        );
        return h('details', { class: 'iko-panel iko-options', open: state.optionsOpen ? true : null, ontoggle: (e) => { state.optionsOpen = e.target.open; } },
            h('summary', {}, 'More options: format and dimensions'),
            h('div', { class: 'iko-fields' },
                h('div', { class: 'iko-field' },
                    h('label', { for: 'iko-format' }, 'Output format'),
                    h('select', { id: 'iko-format', disabled: state.busy, onchange: (e) => { state.format = e.target.value; clearResults(); render(); view.querySelector('#iko-format')?.focus(); } },
                        [['keep', 'Keep the original format'], ['jpeg', 'JPEG'], ['webp', 'WebP'], ['png', 'PNG']]
                            .filter(([v]) => v !== 'webp' || state.webp !== false)
                            .map(([v, l]) => h('option', { value: v, selected: state.format === v ? true : null }, l))),
                ),
                field('iko-maxw', 'Maximum width (px)', 'maxWidth'),
                field('iko-maxh', 'Maximum height (px)', 'maxHeight'),
            ),
            h('p', { class: 'iko-muted' }, 'The aspect ratio is always kept and images are never enlarged. PNG is lossless, so it can only get smaller by reducing dimensions.'),
            transparentToJpeg ? h('p', { class: 'iko-warning', role: 'note' }, h('strong', {}, 'Transparency will be removed. '), 'JPEG has no transparency, so transparent areas will be filled with white. Choose WebP or PNG to keep them.') : null,
        );
    }

    function actions() {
        const n = state.files.length;
        if (state.busy) {
            return h('div', { class: 'iko-actions' },
                h('progress', { max: String(state.progress?.total || 1), value: String((state.progress?.index || 1) - 1), 'aria-label': 'Compression progress' }),
                h('button', { type: 'button', class: 'iko-btn', onclick: () => { state.cancelled = true; say('Cancelling…'); } }, 'Cancel'),
            );
        }
        return h('div', { class: 'iko-actions' },
            h('button', { type: 'button', class: 'iko-btn iko-btn-primary iko-go', disabled: !n || !limit(), onclick: () => compress() },
                n > 1 ? `Compress ${n} images to ${targetText()}` : `Compress to ${targetText()}`),
            !n ? h('span', { class: 'iko-muted' }, 'Select an image first.') : null,
        );
    }

    function results() {
        const done = state.files.filter((f) => f.result || f.error);
        if (!done.length) return null;
        const passed = done.filter((f) => f.result?.ok);
        return h('section', { class: 'iko-results', 'aria-labelledby': 'iko-h-results' },
            h('h2', { id: 'iko-h-results', tabindex: '-1' }, done.length > 1 ? `Results: ${passed.length} of ${done.length} under ${done[0].result?.target || targetText()}` : 'Result'),
            done.length > 1 ? h('div', { class: 'iko-actions' },
                h('button', { type: 'button', class: 'iko-btn iko-btn-primary', disabled: !passed.length, onclick: downloadZip },
                    passed.length === done.length ? `Download all (.zip)` : `Download ${passed.length} passing ${passed.length === 1 ? 'image' : 'images'} (.zip)`)) : null,
            done.map(resultCard),
        );
    }

    function resultCard(f) {
        if (f.error) {
            return h('article', { class: 'iko-panel iko-card' }, h('h3', {}, f.name), h('p', { class: 'iko-error' }, f.error));
        }
        const r = f.result;
        const saved = Math.max(0, Math.round((1 - r.blob.size / f.bytes) * 1000) / 10);
        const notes = [];
        if (r.keptOriginal) notes.push('Your image was already under the target, so it was left exactly as it was.');
        // Say why the dimensions changed: the user's limits, the browser safety limit, the target — or several.
        const b = r.base;
        if (b?.limitedByUser) notes.push(`Resized to fit your maximum dimensions: ${dims(f.width, f.height)} → ${dims(b.width, b.height)}.`);
        if (b?.limitedBySafety) notes.push(`Very large image: processed at ${dims(b.width, b.height)}, the largest size browsers handle reliably.`);
        if (r.ok && b && r.width < b.width) notes.push(`To reach ${r.target}, the dimensions were reduced${b.limitedByUser || b.limitedBySafety ? ' further' : ''} from ${dims(b.width, b.height)} to ${dims(r.width, r.height)}.`);
        if (r.webpFallback) notes.push('Your browser cannot create WebP files, so the image was saved as JPEG.');
        if (r.format === 'jpeg' && f.transparent) notes.push('Transparent areas were filled with white (JPEG has no transparency).');
        if (!r.keptOriginal) notes.push('Photo metadata (such as camera details and location) is not copied into the compressed file.');

        const checks = [
            [r.blob.size <= r.limit, `File size: ${formatSize(r.blob.size)} / ≤ ${r.target}`],
            r.maxWidth ? [r.width <= r.maxWidth, `Width: ${r.width} px / ≤ ${r.maxWidth} px`] : [true, `Width: ${r.width} px`],
            r.maxHeight ? [r.height <= r.maxHeight, `Height: ${r.height} px / ≤ ${r.maxHeight} px`] : [true, `Height: ${r.height} px`],
            [true, `Format: ${FORMAT_NAMES[r.format]}`],
        ];
        const all = checks.every(([ok]) => ok);

        const tryLossy = () => h('div', { class: 'iko-actions' },
            state.webp !== false ? h('button', { type: 'button', class: 'iko-btn', onclick: () => compress('webp', state.files.length > 1 ? null : f.id) }, 'Try WebP (keeps transparency)') : null,
            h('button', { type: 'button', class: 'iko-btn', onclick: () => compress('jpeg', state.files.length > 1 ? null : f.id) }, f.transparent ? 'Try JPEG (transparency filled white)' : 'Try JPEG'),
        );
        const failHelp = !r.ok ? h('div', { class: 'iko-help' },
            h('p', {}, h('strong', {}, 'The target could not be reached at the lowest quality and smallest sensible size. '),
                `The smallest result was ${formatSize(r.blob.size)} at ${dims(r.width, r.height)}. Try a larger target size${r.format === 'png' ? ', or a lossy format' : ''}.`),
            r.format === 'png' ? tryLossy() : null,
        ) : null;
        // PNG is lossless: it may pass only by shrinking a lot. WebP/JPEG usually keep far more detail.
        const pngShrunk = r.ok && r.format === 'png' && r.width < f.width * 0.5 ? h('div', { class: 'iko-help iko-help-soft' },
            h('p', {}, h('strong', {}, 'PNG had to shrink a lot to fit. '), `WebP or JPEG would usually keep much larger dimensions at ${r.target}.`),
            tryLossy(),
        ) : null;

        return h('article', { class: `iko-panel iko-card ${r.ok ? 'is-pass' : 'is-fail'}` },
            h('div', { class: 'iko-row' },
                h('h3', {}, f.name),
                badge(r.ok, r.ok ? `Under ${r.target}` : `Could not reach ${r.target}`),
            ),
            h('div', { class: 'iko-compare-table' },
                h('dl', {}, h('dt', {}, 'Original'), h('dd', {}, h('strong', {}, formatSize(f.bytes))), h('dd', {}, dims(f.width, f.height)), h('dd', {}, FORMAT_NAMES[f.format])),
                h('dl', {}, h('dt', {}, r.ok ? 'Optimized' : 'Smallest result'), h('dd', {}, h('strong', {}, formatSize(r.blob.size))), h('dd', {}, dims(r.width, r.height)), h('dd', {}, `${FORMAT_NAMES[r.format]}${r.quality ? ` · quality ${Math.round(r.quality * 100)}%` : ''}`)),
                h('dl', { class: 'iko-saved' }, h('dt', {}, 'Saved'), h('dd', {}, h('strong', {}, `${saved}%`))),
            ),
            r.ok ? h('div', { class: 'iko-check' },
                h('h4', {}, 'Image requirement check'),
                h('ul', {}, checks.map(([ok, text]) => h('li', {}, badge(ok, ok ? 'Pass' : 'Fail'), ` ${text}`))),
                h('p', { class: 'iko-overall' }, badge(all, all ? 'Image meets all requirements' : 'Some requirements are not met')),
            ) : failHelp,
            pngShrunk,
            notes.length ? h('ul', { class: 'iko-notes' }, notes.map((n) => h('li', {}, n))) : null,
            comparison(f),
            h('div', { class: 'iko-actions' },
                r.ok ? h('button', { type: 'button', class: 'iko-btn iko-btn-primary', onclick: () => download(f) }, `Download ${FORMAT_NAMES[r.format]} (${formatSize(r.blob.size)})`)
                    : h('button', { type: 'button', class: 'iko-btn iko-link', onclick: () => download(f) }, 'Download the smallest result anyway'),
            ),
        );
    }

    /** Before/after slider: drag (or use the arrow keys on) the range to reveal the original. */
    function comparison(f) {
        const after = h('img', { src: f.resultUrl, alt: `Compressed version of ${f.name}`, class: 'iko-cmp-after' });
        const before = h('img', { src: f.url, alt: `Original ${f.name}`, class: 'iko-cmp-before' });
        const wrap = h('div', { class: 'iko-cmp-images', style: `aspect-ratio: ${f.width} / ${f.height}` }, after, before);
        const set = (v) => { before.style.clipPath = `inset(0 ${100 - v}% 0 0)`; };
        set(50);
        return h('figure', { class: 'iko-cmp' },
            wrap,
            h('label', { class: 'iko-cmp-label' }, h('span', {}, 'Original'),
                h('input', { type: 'range', min: '0', max: '100', value: '50', 'aria-label': 'Compare original (left) with compressed (right)', oninput: (e) => set(Number(e.target.value)) }),
                h('span', {}, 'Compressed')),
            h('figcaption', { class: 'iko-muted' }, 'Drag the slider to compare. Zoom in on your screen to check fine detail.'),
        );
    }

    render();
    if (preset) say(`Target set to ${targetText()}.`);

    // Can this browser write WebP? (Older Safari returns PNG instead.) Until known, WebP is offered.
    const probe = document.createElement('canvas');
    probe.width = 1;
    probe.height = 1;
    probe.toBlob((blob) => {
        state.webp = !!blob && blob.type === 'image/webp';
        if (!state.webp) {
            if (state.format === 'webp') state.format = 'keep';
            if (!state.busy) render();
        }
    }, 'image/webp');
}
