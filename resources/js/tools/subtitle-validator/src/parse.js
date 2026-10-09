// SRT and WebVTT parsing into one document model, with structural findings.
//
// The parser is deliberately lenient so one mistake does not hide the rest
// of the file: a timing line always starts a new cue (even without the blank
// line before it), lines that belong to no cue are kept as "raw" items, and
// WebVTT NOTE / STYLE / REGION blocks and header metadata are kept so a
// repair never drops them. Every cue and block records its source line.

import { parseTime } from './timestamp.js';
import { BOM, finding, MAX_CUES } from './rules.js';

const BOM_AT_START = new RegExp(`^${BOM}`);

const INDEX_LINE = /^\s*\d+\s*$/;

/** Splits text into lines and reports the line endings used. */
export function splitLines(text) {
    let crlf = 0;
    let lf = 0;
    let cr = 0;
    for (const match of text.matchAll(/\r\n|\r|\n/g)) {
        if (match[0] === '\r\n') crlf++;
        else if (match[0] === '\n') lf++;
        else cr++;
    }
    const kinds = [[crlf, '\r\n'], [lf, '\n'], [cr, '\r']].filter(([count]) => count > 0);
    const lineEnding = kinds.length ? kinds.sort((a, b) => b[0] - a[0])[0][1] : '\n';

    return { lines: text.split(/\r\n|\r|\n/), lineEnding, mixed: kinds.length > 1 };
}

/**
 * Guesses the format. `certain` is false when the text could be either
 * (dot timestamps without a WEBVTT header and no telling file extension).
 *
 * @returns {{format: 'srt'|'vtt'|null, certain: boolean}}
 */
export function detectFormat(text, fileName = '') {
    const extension = /\.(srt|vtt|webvtt)$/i.exec(fileName)?.[1]?.toLowerCase();
    const fromExtension = extension ? (extension === 'srt' ? 'srt' : 'vtt') : null;
    const lines = text.replace(BOM_AT_START, '').split(/\r\n|\r|\n/);
    const first = lines.find((line) => line.trim() !== '') ?? '';

    if (/^WEBVTT/.test(first)) return { format: 'vtt', certain: true };

    const timingAt = lines.findIndex((line) => line.includes('-->'));
    if (timingAt === -1) return { format: fromExtension, certain: false };
    if (/\d,\d/.test(lines[timingAt])) return { format: 'srt', certain: true };
    if (fromExtension) return { format: fromExtension, certain: true };

    // Dot timestamps, no header: a number line above the timing suggests SRT.
    return { format: timingAt > 0 && INDEX_LINE.test(lines[timingAt - 1]) ? 'srt' : 'vtt', certain: false };
}

/**
 * @param {string} text  decoded file contents (a leading BOM is allowed)
 * @param {'srt'|'vtt'} format
 */
export function parse(text, format) {
    const bom = text.startsWith(BOM);
    const body = bom ? text.slice(1) : text;
    const { lines, lineEnding, mixed } = splitLines(body);
    const doc = { format, bom, lineEnding, mixedLineEndings: mixed, header: null, items: [], cues: [], aborted: false };
    const findings = [];

    if (body.trim() === '') {
        findings.push(finding('file-empty'));
        return { doc, findings };
    }
    if (mixed) findings.push(finding('mixed-line-endings'));

    const ctx = { doc, findings, format, whitespaceLines: [] };
    if (format === 'srt') parseSrt(lines, ctx);
    else parseVtt(lines, ctx);

    if (ctx.whitespaceLines.length) {
        findings.push(finding('whitespace-separator', { line: ctx.whitespaceLines[0], detail: count(ctx.whitespaceLines.length, 'line') }));
    }
    if (!doc.aborted && doc.cues.length === 0) findings.push(finding('no-cues'));

    return { doc, findings };
}

function count(n, noun) {
    return `${n} ${noun}${n === 1 ? '' : 's'}`;
}

function newCue(ctx, timingText, line, extra = {}) {
    const { doc, findings, format } = ctx;
    const n = doc.cues.length + 1;
    const cue = {
        type: 'cue', n, line, index: null, indexLine: null, id: null, idLine: null,
        timing: timingText, malformed: false, startText: null, endText: null,
        start: null, end: null, startCode: null, endCode: null, startFixable: false, endFixable: false,
        arrowFixable: false, settings: '', text: [], textLines: [], ...extra,
    };

    const match = /^\s*(\S+?)\s*-->\s*(\S+)(?:[ \t]+(.*?))?\s*$/.exec(timingText);
    if (!match) {
        cue.malformed = true;
        findings.push(finding('timing-malformed', { cue: n, line }));
    } else {
        const [, startText, endText, settings] = match;
        const start = parseTime(startText, format);
        const end = parseTime(endText, format);
        Object.assign(cue, {
            startText, endText, settings: settings ?? '',
            start: start.ms, end: end.ms, startCode: start.code, endCode: end.code,
            startFixable: start.fixable, endFixable: end.fixable,
        });
        for (const [result, text] of [[start, startText], [end, endText]]) {
            if (result.code) findings.push(finding(result.code, { cue: n, line, detail: text }));
        }
        if (!/\s-->\s/.test(timingText)) {
            cue.arrowFixable = true;
            findings.push(finding('timing-arrow-spacing', { cue: n, line }));
        }
    }

    if (cue.missingBlank) findings.push(finding('missing-blank-line', { cue: n, line }));

    doc.cues.push(cue);
    doc.items.push(cue);
    if (doc.cues.length > MAX_CUES) {
        doc.aborted = true;
        findings.push(finding('too-many-cues'));
    }
    return cue;
}

function stray(ctx, lines, firstLine) {
    ctx.doc.items.push({ type: 'raw', lines: [...lines], line: firstLine });
    const last = firstLine + lines.length - 1;
    ctx.findings.push(finding('stray-text', { line: firstLine, detail: last > firstLine ? `Lines ${firstLine}–${last}` : `Line ${firstLine}` }));
}

function parseSrt(lines, ctx) {
    let cue = null;
    let pending = []; // lines seen outside a cue since the last blank line

    const flush = () => {
        if (pending.length) stray(ctx, pending.map((p) => p.text), pending[0].line);
        pending = [];
    };

    for (let i = 0; i < lines.length && !ctx.doc.aborted; i++) {
        const text = lines[i];
        const line = i + 1;

        if (text.trim() === '') {
            if (text !== '' && i < lines.length - 1) ctx.whitespaceLines.push(line);
            cue = null;
            flush();
            continue;
        }

        if (text.includes('-->')) {
            const extra = {};
            if (cue) {
                // No blank line since the previous cue: its last text line
                // may be this cue's number.
                extra.missingBlank = true;
                if (cue.text.length && INDEX_LINE.test(cue.text.at(-1))) {
                    extra.index = cue.text.pop().trim();
                    extra.indexLine = cue.textLines.pop();
                }
            } else if (pending.length && INDEX_LINE.test(pending.at(-1).text)) {
                const index = pending.pop();
                extra.index = index.text.trim();
                extra.indexLine = index.line;
            }
            flush();
            cue = newCue(ctx, text, line, extra);
            if (extra.index === undefined) ctx.findings.push(finding('srt-missing-index', { cue: cue.n, line }));
            continue;
        }

        if (cue) {
            cue.text.push(text);
            cue.textLines.push(line);
        } else {
            pending.push({ text, line });
        }
    }
    flush();
}

function parseVtt(lines, ctx) {
    const { doc, findings } = ctx;
    let i = 0;
    const first = lines[0] ?? '';

    if (/^WEBVTT/.test(first)) {
        doc.header = { line: first, extra: [] };
        if (!/^WEBVTT(?:[ \t].*)?$/.test(first)) findings.push(finding('vtt-header-invalid', { line: 1 }));
        i = 1;
        while (i < lines.length && lines[i].trim() !== '' && !lines[i].includes('-->')) {
            doc.header.extra.push(lines[i]);
            i++;
        }
        if (i < lines.length && lines[i].includes('-->')) findings.push(finding('missing-blank-line', { line: i + 1, detail: 'After the WEBVTT header' }));
    } else {
        findings.push(finding('vtt-missing-header', { line: 1 }));
    }

    while (i < lines.length && !doc.aborted) {
        if (lines[i].trim() === '') {
            if (lines[i] !== '' && i < lines.length - 1) ctx.whitespaceLines.push(i + 1);
            i++;
            continue;
        }
        const start = i;
        const block = [];
        while (i < lines.length && lines[i].trim() !== '') block.push(lines[i++]);
        vttBlock(ctx, block, start + 1);
    }
}

function vttBlock(ctx, block, firstLine) {
    const { doc, findings } = ctx;
    const head = block[0];

    if (/^NOTE(?:[ \t]|$)/.test(head)) {
        doc.items.push({ type: 'note', lines: block, line: firstLine });
        const arrow = block.findIndex((l) => l.includes('-->'));
        if (arrow !== -1) findings.push(finding('vtt-note-arrow', { line: firstLine + arrow }));
        return;
    }
    if (/^(STYLE|REGION)[ \t]*$/.test(head)) {
        doc.items.push({ type: head.trim().toLowerCase(), lines: block, line: firstLine });
        if (doc.cues.length) findings.push(finding('vtt-block-after-cue', { line: firstLine, detail: head.trim() }));
        return;
    }

    let j = 0;
    const extra = {};
    if (!head.includes('-->')) {
        if (block.length < 2 || !block[1].includes('-->')) {
            stray(ctx, block, firstLine);
            return;
        }
        extra.id = head;
        extra.idLine = firstLine;
        j = 1;
    }

    let cue = newCue(ctx, block[j], firstLine + j, extra);
    for (j++; j < block.length && !doc.aborted; j++) {
        if (block[j].includes('-->')) {
            cue = newCue(ctx, block[j], firstLine + j, { missingBlank: true });
        } else {
            cue.text.push(block[j]);
            cue.textLines.push(firstLine + j);
        }
    }
}
