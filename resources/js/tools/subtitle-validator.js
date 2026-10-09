import { copyText } from './shared/copy.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';
import { check, SAMPLES } from './subtitle-validator/src/check.js';
import { decodeBytes, MAX_BYTES } from './subtitle-validator/src/decode.js';
import { availableFixes, repair } from './subtitle-validator/src/repair.js';
import { reportCsv, reportText } from './subtitle-validator/src/report.js';
import { BOM, CATEGORIES, DEFAULT_SETTINGS, FIXES } from './subtitle-validator/src/rules.js';
import { displayDuration, displayTime } from './subtitle-validator/src/timestamp.js';

// Subtitle Validator — see resources/views/tools/functionalities/subtitle-validator.blade.php.
//
// Privacy: the file is read with the File API and checked in this tab. It is
// never uploaded, kept in browser storage or logged. Analytics
// events (consent-gated, shared/track.js) carry only the format, the status,
// counts and error codes — never file names, cue text or timings.
// Subtitle text is only ever inserted with textContent, never as HTML.

const PAGE = 200; // findings rendered at a time
const PREVIEW_LINES = 400;
const BOM_AT_START = new RegExp(`^${BOM}`);

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-sv]');
    if (!tool) return;

    const q = (selector) => tool.querySelector(selector);
    const el = {
        file: q('[data-sv-file]'), drop: q('[data-sv-drop]'), fileName: q('[data-sv-file-name]'),
        text: q('[data-sv-text]'), format: q('[data-sv-format]'), detected: q('[data-sv-detected]'),
        error: q('[data-sv-error]'), errorText: q('[data-sv-error-text]'), cp1252: q('[data-sv-cp1252]'),
        settingsError: q('[data-sv-settings-error]'), live: q('[data-sv-live]'),
        results: q('[data-sv-results]'), summary: q('[data-sv-summary]'), findings: q('[data-sv-findings]'),
        clean: q('[data-sv-clean]'), more: q('[data-sv-more]'),
        repair: q('[data-sv-repair]'), safe: q('[data-sv-safe]'), optional: q('[data-sv-optional]'),
        output: q('[data-sv-output]'), previewArea: q('[data-sv-preview-area]'), changes: q('[data-sv-changes]'),
        outputText: q('[data-sv-output-text]'),
    };

    const state = {
        text: '',            // the input exactly as read; never modified
        fileName: '',        // only used for detection and the download name
        bytes: null,         // kept only while an encoding choice is pending
        encoding: 'UTF-8',
        checked: null,       // { format, detected, doc, result }
        settings: { ...DEFAULT_SETTINGS },
        filter: 'all',
        shown: PAGE,
        repaired: null,      // { text, format }
    };

    const say = (message) => {
        el.live.textContent = '';
        requestAnimationFrame(() => { el.live.textContent = message; });
    };
    const h = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const fmtName = (format) => (format === 'srt' ? 'SRT' : 'WebVTT');

    // ---------------------------------------------------------------- input

    const showError = (message, { offerWindows1252 = false } = {}) => {
        el.errorText.textContent = message;
        el.cp1252.hidden = !offerWindows1252;
        el.error.hidden = !message;
    };

    const reset = ({ keepText = false } = {}) => {
        state.checked = null;
        state.repaired = null;
        state.bytes = null;
        el.results.hidden = true;
        el.repair.hidden = true;
        el.previewArea.hidden = true;
        el.detected.textContent = '';
        showError('');
        if (!keepText) {
            state.text = '';
            state.fileName = '';
            state.encoding = 'UTF-8';
            el.text.value = '';
            el.fileName.hidden = true;
        }
    };

    const loadText = (text, { fileName = '', encoding = 'UTF-8', source }) => {
        state.text = text;
        state.fileName = fileName;
        state.encoding = encoding;
        el.text.value = text;
        trackToolEventOnce('tool_started', { input: source });
        run();
    };

    const readFile = async (file, mode = 'auto') => {
        reset();
        if (!/\.(srt|vtt|webvtt)$/i.test(file.name)) {
            showError('Choose an .srt, .vtt or .webvtt file.');
            trackToolEvent('tool_error', { error_code: 'file-type' });
            return;
        }
        if (file.size > MAX_BYTES) {
            showError('This file is larger than 5 MiB. Split it into smaller files and check each one.');
            trackToolEvent('tool_error', { error_code: 'file-too-large' });
            return;
        }
        el.fileName.textContent = `${file.name} · ${(file.size / 1024).toFixed(1)} KB`;
        el.fileName.hidden = false;
        say('Reading the file…');

        let bytes;
        try {
            bytes = new Uint8Array(await file.arrayBuffer());
        } catch {
            showError('The file could not be read. Try choosing it again.');
            trackToolEvent('tool_error', { error_code: 'file-read' });
            return;
        }
        decodeAndLoad(bytes, file.name, mode);
    };

    const decodeAndLoad = (bytes, fileName, mode) => {
        const decoded = decodeBytes(bytes, mode);
        if (!decoded.ok) {
            state.bytes = decoded.code === 'encoding-invalid-utf8' ? bytes : null;
            state.fileName = fileName;
            const where = decoded.line ? ` The first problem is on line ${decoded.line}.` : '';
            const advice = decoded.code === 'encoding-invalid-utf8'
                ? ' Re-save it as UTF-8 in your subtitle editor, or, if it is an older Western European file, read it as Windows-1252 and check the accented characters.'
                : '';
            showError(decoded.message + where + advice, { offerWindows1252: decoded.code === 'encoding-invalid-utf8' });
            trackToolEvent('tool_error', { error_code: decoded.code });
            say(decoded.message);
            return;
        }
        state.bytes = null;
        loadText(decoded.text, { fileName, encoding: decoded.encoding, source: 'file' });
    };

    el.file.addEventListener('change', () => {
        const file = el.file.files?.[0];
        el.file.value = '';
        if (file) readFile(file);
    });

    ['dragenter', 'dragover'].forEach((type) => el.drop.addEventListener(type, (event) => {
        event.preventDefault();
        el.drop.dataset.drag = 'true';
    }));
    ['dragleave', 'drop'].forEach((type) => el.drop.addEventListener(type, (event) => {
        event.preventDefault();
        el.drop.dataset.drag = 'false';
    }));
    el.drop.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (file) readFile(file);
    });

    el.cp1252.addEventListener('click', () => {
        if (state.bytes) decodeAndLoad(state.bytes, state.fileName, 'windows-1252');
    });

    let typing;
    el.text.addEventListener('input', () => {
        clearTimeout(typing);
        typing = setTimeout(() => {
            if (el.text.value === state.text) return;
            if (new Blob([el.text.value]).size > MAX_BYTES) {
                reset({ keepText: true });
                showError('This text is larger than 5 MiB. Check it in smaller parts.');
                return;
            }
            state.text = el.text.value;
            state.encoding = 'UTF-8';
            state.fileName = '';
            el.fileName.hidden = true;
            if (state.text.trim() === '') {
                reset();
                return;
            }
            trackToolEventOnce('tool_started', { input: 'paste' });
            run();
        }, 400);
    });

    el.format.addEventListener('change', () => state.text && run());
    tool.querySelectorAll('[data-sv-sample]').forEach((button) => button.addEventListener('click', () => {
        reset();
        el.format.value = 'auto';
        loadText(SAMPLES[button.dataset.svSample], { fileName: 'sample.srt', source: 'sample' });
    }));
    q('[data-sv-clear]').addEventListener('click', () => {
        reset();
        el.format.value = 'auto';
        el.text.focus();
        say('Cleared.');
    });

    // ------------------------------------------------------------- settings

    const readSettings = () => {
        const next = {};
        for (const input of tool.querySelectorAll('[data-sv-setting]')) {
            const value = Number(input.value);
            const ok = input.value.trim() !== '' && Number.isFinite(value) && value >= Number(input.min) && value <= Number(input.max);
            input.setAttribute('aria-invalid', ok ? 'false' : 'true');
            if (!ok) return { error: `${input.labels[0].textContent} must be between ${input.min} and ${input.max}.` };
            next[input.dataset.svSetting] = value;
        }
        if (next.minDuration >= next.maxDuration) return { error: 'The minimum duration must be shorter than the maximum.' };
        return { settings: next };
    };

    const settingsChanged = () => {
        const { settings, error } = readSettings();
        el.settingsError.textContent = error ?? '';
        el.settingsError.hidden = !error;
        if (error) return;
        state.settings = settings;
        if (state.text) run();
    };
    tool.querySelectorAll('[data-sv-setting]').forEach((input) => input.addEventListener('input', settingsChanged));
    q('[data-sv-reset-settings]').addEventListener('click', () => {
        tool.querySelectorAll('[data-sv-setting]').forEach((input) => { input.value = input.dataset.default; });
        settingsChanged();
        say('Rule settings reset to the defaults.');
    });

    // ------------------------------------------------------------ checking

    const run = () => {
        showError('');
        state.checked = check(state.text, { format: el.format.value, fileName: state.fileName, settings: state.settings, encoding: state.encoding });
        state.repaired = null;
        state.shown = PAGE;
        el.previewArea.hidden = true;

        const { format, detected, result } = state.checked;
        el.detected.textContent = el.format.value !== 'auto'
            ? `Checking as ${fmtName(format)}.`
            : detected.certain ? `Detected: ${fmtName(format)}.` : `Could not tell for certain — checking as ${fmtName(format)}. Choose the format if this is wrong.`;
        if (state.encoding !== 'UTF-8') el.detected.textContent += ` Read as ${state.encoding}.`;

        renderSummary();
        renderFindings();
        renderRepair();
        el.results.hidden = false;
        say(`${statusText(result.status)}: ${plural(result.errors, 'error')}, ${plural(result.warnings, 'warning')}, score ${result.score} out of 100.`);
        trackToolEventOnce('tool_completed', { format, status: result.status });
    };

    const statusText = (status) => ({ clean: 'No issues found', warnings: 'Valid, with warnings', errors: 'Errors found' }[status]);

    const renderSummary = () => {
        const { result } = state.checked;
        const { stats } = result;
        const tone = { clean: 'bg-emerald-50 text-emerald-900', warnings: 'bg-amber-50 text-amber-950', errors: 'bg-red-50 text-red-900' }[result.status];
        const card = (label, value, extra = '', className = 'bg-white text-brand-navy') => {
            const box = h('div', `rounded-2xl border border-brand-navy/10 p-4 ${className}`);
            box.append(h('p', 'text-xs font-semibold tracking-[0.12em] uppercase opacity-70', label), h('p', 'mt-1 text-2xl font-bold', value));
            if (extra) box.append(h('p', 'mt-1 text-sm opacity-80', extra));
            return box;
        };
        const cps = (n) => (n === null ? '—' : String(Math.round(n * 10) / 10));
        el.summary.replaceChildren(
            card('Quality score', `${result.score}/100`, statusText(result.status), tone),
            card('Cues', String(stats.cues)),
            card('Errors', String(result.errors)),
            card('Warnings', String(result.warnings)),
            card('Runs for', displayDuration(stats.span), stats.first === null ? '' : `${displayTime(stats.first)} – ${displayTime(stats.last)}`),
            card('Average reading speed', cps(stats.averageCps), 'characters per second'),
            card('Highest reading speed', cps(stats.maxCps), stats.maxCpsCue ? `cue ${stats.maxCpsCue}` : ''),
            card('Format', fmtName(state.checked.format), state.encoding),
        );
    };

    const sourceLines = () => {
        if (!state.lines || state.lines.text !== state.text) state.lines = { text: state.text, lines: state.text.replace(BOM_AT_START, '').split(/\r\n|\r|\n/) };
        return state.lines.lines;
    };

    // The cue (or lines) a finding points at, with line numbers.
    const excerpt = (f) => {
        const cue = f.cue ? state.checked.doc.cues[f.cue - 1] : null;
        const from = cue ? Math.min(...[cue.indexLine, cue.idLine, cue.line].filter(Boolean)) : f.line;
        const to = cue ? Math.max(cue.line, ...cue.textLines) : f.line + 2;
        const lines = sourceLines();
        const width = String(to).length;
        return lines.slice(from - 1, to).map((line, i) => `${String(from + i).padStart(width)} │ ${line}`).join('\n');
    };

    const findingItem = (f) => {
        const item = h('li', 'rounded-2xl border border-brand-navy/10 bg-white p-4 sm:p-5');
        const top = h('div', 'flex flex-wrap items-center gap-2 text-xs font-semibold');
        top.append(
            h('span', `rounded-full px-2.5 py-1 ${f.severity === 'error' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'}`, f.severity === 'error' ? 'Error' : 'Warning'),
            h('span', 'rounded-full bg-brand-mist px-2.5 py-1 text-brand-navy/80', CATEGORIES[f.category]),
            h('code', 'text-brand-navy/50', f.rule),
        );
        const where = [f.cue ? `Cue ${f.cue}` : null, f.line ? `line ${f.line}` : null, f.start !== null ? `${displayTime(f.start)} → ${displayTime(f.end)}` : null].filter(Boolean).join(' · ');
        item.append(top);
        if (where) item.append(h('p', 'mt-2 text-sm font-semibold text-brand-navy', where));
        item.append(h('p', 'mt-1 text-sm text-brand-navy/80', f.message + (f.detail ? ` (${f.detail})` : '')));
        item.append(h('p', 'mt-1 text-sm text-brand-navy/70', `Suggestion: ${f.suggestion}${f.fix ? ` ${FIXES[f.fix][2] ? 'An optional fix is available below.' : 'Fixed automatically in the corrected file.'}` : ''}`));

        if (f.line) {
            const details = h('details', 'mt-2');
            const summary = h('summary', 'inline-block cursor-pointer rounded-lg py-2 text-sm font-semibold text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none', f.cue ? `Show cue ${f.cue} in the file` : `Show line ${f.line}`);
            const pre = h('pre', 'mt-2 max-h-60 overflow-auto rounded-xl bg-brand-mist p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap text-brand-navy');
            details.append(summary, pre);
            details.addEventListener('toggle', () => { if (details.open && !pre.textContent) pre.textContent = excerpt(f); });
            item.append(details);
        }
        return item;
    };

    const renderFindings = () => {
        const { findings, errors, warnings } = state.checked.result;
        q('[data-sv-count="all"]').textContent = String(findings.length);
        q('[data-sv-count="error"]').textContent = String(errors);
        q('[data-sv-count="warning"]').textContent = String(warnings);

        const list = state.filter === 'all' ? findings : findings.filter((f) => f.severity === state.filter);
        el.findings.replaceChildren(...list.slice(0, state.shown).map(findingItem));
        el.clean.hidden = findings.length > 0;
        const rest = list.length - state.shown;
        el.more.hidden = rest <= 0;
        el.more.textContent = `Show ${Math.min(rest, PAGE)} more (${rest} not shown)`;
    };

    tool.querySelectorAll('[data-sv-filter]').forEach((radio) => radio.addEventListener('change', () => {
        state.filter = radio.value;
        state.shown = PAGE;
        renderFindings();
    }));
    el.more.addEventListener('click', () => {
        const next = el.findings.children.length;
        state.shown += PAGE;
        renderFindings();
        el.findings.children[next]?.querySelector('summary, p')?.setAttribute('tabindex', '-1');
        el.findings.children[next]?.querySelector('summary, p')?.focus();
    });

    // -------------------------------------------------------------- reports

    const download = (text, name, type) => {
        try {
            const url = URL.createObjectURL(new Blob([text], { type }));
            const link = h('a');
            link.href = url;
            link.download = name;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            return true;
        } catch {
            say('The download could not be started. Use the copy button instead.');
            trackToolEvent('tool_error', { error_code: 'download' });
            return false;
        }
    };
    const baseName = () => (state.fileName ? state.fileName.replace(/\.[^.]+$/, '').replace(/[^\p{L}\p{N}._ -]+/gu, '_').slice(0, 80) : 'subtitles') || 'subtitles';

    q('[data-sv-copy-report]').addEventListener('click', (event) => {
        if (state.checked) copyText(reportText(state.checked.result, state.checked.format, state.settings), event.currentTarget, 'report', say);
    });
    tool.querySelectorAll('[data-sv-report]').forEach((button) => button.addEventListener('click', () => {
        if (!state.checked) return;
        const csv = button.dataset.svReport === 'csv';
        const text = csv ? reportCsv(state.checked.result) : reportText(state.checked.result, state.checked.format, state.settings);
        if (download(text, `${baseName()}-report.${csv ? 'csv' : 'txt'}`, csv ? 'text/csv;charset=utf-8' : 'text/plain;charset=utf-8')) {
            trackToolEvent('tool_download', { download_type: csv ? 'report_csv' : 'report_txt' });
        }
    }));

    // --------------------------------------------------------------- repair

    const renderRepair = () => {
        const { doc, result } = state.checked;
        if (doc.aborted || doc.cues.length === 0) {
            el.repair.hidden = true;
            return;
        }
        const fixes = availableFixes(result.findings);
        const safe = fixes.filter((f) => !f.confirm && f.id !== 'trim-whitespace');
        el.safe.replaceChildren(...(safe.length
            ? safe.map((f) => h('li', '', `✓ ${f.label} — ${f.description}`))
            : [h('li', 'text-brand-navy/60', 'Nothing to fix here.')]));

        const optional = fixes.filter((f) => f.confirm || f.id === 'trim-whitespace');
        el.optional.replaceChildren(...(optional.length
            ? optional.map((f) => {
                const label = h('label', 'flex cursor-pointer items-start gap-3 rounded-xl bg-white p-3 text-sm');
                const box = h('input', 'mt-0.5 h-4 w-4 shrink-0 rounded border-brand-navy/30 text-brand-accent focus:ring-brand-accent');
                box.type = 'checkbox';
                box.value = f.id;
                box.dataset.svFix = '';
                const text = h('span', 'text-brand-navy/80');
                text.append(h('strong', 'font-semibold text-brand-navy', f.label), ` (${plural(f.count, 'finding')}) — ${f.description}`);
                label.append(box, text);
                box.addEventListener('change', () => { el.previewArea.hidden = true; });
                return label;
            })
            : [h('p', 'text-sm text-brand-navy/60', 'None needed.')]));
        el.repair.hidden = false;
    };
    el.output.addEventListener('change', () => { el.previewArea.hidden = true; });

    q('[data-sv-preview]').addEventListener('click', () => {
        if (!state.checked) return;
        const { doc } = state.checked;
        const format = el.output.value === 'same' ? state.checked.format : el.output.value;
        const fixes = [...tool.querySelectorAll('[data-sv-fix]:checked')].map((box) => box.value);
        const out = repair(doc, { format, fixes, settings: state.settings });
        const again = check(out.text, { format, settings: state.settings });
        state.repaired = { text: out.text, format };

        const box = el.changes;
        box.replaceChildren();
        box.append(h('p', 'font-semibold text-brand-navy', out.changes.length ? 'Changes in the corrected file' : 'No changes were needed.'));
        if (out.changes.length) {
            const list = h('ul', 'mt-1 list-disc space-y-1 ps-5 text-brand-navy/80');
            list.append(...out.changes.map((c) => h('li', '', c)));
            box.append(list);
        }
        if (out.cautions.length) {
            const list = h('ul', 'mt-3 list-disc space-y-1 ps-5 font-medium text-amber-900');
            list.append(...out.cautions.map((c) => h('li', '', c)));
            box.append(list);
        }
        const r = again.result;
        box.append(h('p', `mt-3 font-semibold ${r.errors ? 'text-red-800' : 'text-emerald-800'}`,
            `Checked again: ${statusText(r.status).toLowerCase()} — ${plural(r.errors, 'error')}, ${plural(r.warnings, 'warning')}, score ${r.score}/100.`));
        if (r.errors) box.append(h('p', 'mt-1 text-brand-navy/70', 'Some problems need a manual fix in your subtitle editor; they were left exactly as they were.'));

        const lines = out.text.split(/\r\n|\r|\n/);
        el.outputText.textContent = lines.slice(0, PREVIEW_LINES).join('\n') + (lines.length > PREVIEW_LINES ? `\n… ${lines.length - PREVIEW_LINES} more lines in the download` : '');
        el.previewArea.hidden = false;
        el.previewArea.focus();
        say(`Corrected file ready: ${plural(r.errors, 'error')} and ${plural(r.warnings, 'warning')} remain.`);
    });

    q('[data-sv-download]').addEventListener('click', () => {
        if (!state.repaired) return;
        const { text, format } = state.repaired;
        const ok = download(text, `${baseName()}-fixed.${format}`, format === 'srt' ? 'application/x-subrip;charset=utf-8' : 'text/vtt;charset=utf-8');
        if (ok) trackToolEvent('tool_download', { download_type: 'subtitle', format });
    });
    q('[data-sv-copy-output]').addEventListener('click', (event) => {
        if (state.repaired) copyText(state.repaired.text, event.currentTarget, 'subtitle', say);
    });

    q('[data-sv-form]').addEventListener('submit', (event) => event.preventDefault());
});
