// Repair: writes a corrected copy of a parsed document. The original text
// is never touched.
//
// Always applied (they never change what is shown or when): the WEBVTT
// header, one blank line between blocks, SRT renumbering, timestamps
// rewritten in the correct form (same times), one kind of line ending.
// Applied only when selected, because they change text, order or timing:
// trim spaces, remove empty cues, remove control characters, sort, merge
// repeated cues, trim overlaps, rewrap long lines.
//
// Content the parser could not read (stray lines, malformed timing lines,
// invalid timestamps, negative times) is written back exactly as it was,
// so nothing is lost and the re-check still reports it.

import { BOM, CONTROL_RANGES, FIXES } from './rules.js';
import { formatTime, parseTime } from './timestamp.js';
import { length, usable, visibleText } from './validate.js';

export const CONFIRM_FIXES = Object.keys(FIXES).filter((id) => FIXES[id][2]);

const CONTROL = new RegExp(`[${CONTROL_RANGES}]`, 'g');

/** Fixes that apply to these findings, with how many findings each covers. */
export function availableFixes(findings) {
    const counts = new Map();
    for (const f of findings) if (f.fix) counts.set(f.fix, (counts.get(f.fix) ?? 0) + 1);
    return [...counts].map(([id, count]) => ({ id, count, label: FIXES[id][0], description: FIXES[id][1], confirm: FIXES[id][2] }));
}

/**
 * @param {object} doc  from parse()
 * @param {{format: 'srt'|'vtt', fixes: Iterable<string>, settings: object}} options
 * @returns {{text: string, changes: string[], cautions: string[]}}
 */
export function repair(doc, { format, fixes = [], settings }) {
    const chosen = new Set(fixes);
    const changes = [];
    const cautions = [];
    const note = (n, text) => n > 0 && changes.push(text.replace('{n}', n).replace(/\{s\}/g, n === 1 ? '' : 's'));

    let items = doc.items.map((item) => (item.type === 'cue' ? { ...item, text: [...item.text] } : item));
    const cues = () => items.filter((item) => item.type === 'cue');

    // Text clean-up
    if (chosen.has('remove-control')) {
        let n = 0;
        for (const cue of cues()) cue.text = cue.text.map((line) => { const clean = line.replace(CONTROL, ''); if (clean !== line) n++; return clean; });
        note(n, 'Removed control characters from {n} line{s}.');
    }
    if (chosen.has('trim-whitespace')) {
        let n = 0;
        for (const cue of cues()) cue.text = cue.text.map((line) => { const clean = line.replace(/^[ \t]+|[ \t]+$/g, ''); if (clean !== line) n++; return clean; });
        note(n, 'Trimmed spaces around {n} text line{s}.');
    }
    // A line emptied above would read as a blank line ending the cue.
    for (const cue of cues()) cue.text = cue.text.filter((line) => line.trim() !== '');

    if (chosen.has('remove-empty')) {
        const before = items.length;
        items = items.filter((item) => item.type !== 'cue' || item.text.length > 0);
        note(before - items.length, 'Removed {n} empty cue{s}.');
    }

    // Order and timing
    const times = (cue) => ({ start: usable(cue.start, cue.startCode, cue.startFixable) ? cue.start : null, end: usable(cue.end, cue.endCode, cue.endFixable) ? cue.end : null });

    if (chosen.has('sort')) {
        const list = cues();
        if (list.some((cue) => times(cue).start === null)) {
            cautions.push('Cues were not sorted, because some start times could not be read. Fix those timestamps first.');
        } else {
            const sorted = [...list].sort((a, b) => a.start - b.start);
            const moved = sorted.filter((cue, i) => cue !== list[i]).length;
            let k = 0;
            items = items.map((item) => (item.type === 'cue' ? sorted[k++] : item));
            note(moved ? 1 : 0, `Sorted the cues by start time (${moved} moved).`);
        }
    }
    if (chosen.has('merge-duplicates')) {
        const drop = new Set();
        let previous = null;
        for (const cue of cues()) {
            const same = previous && cue.text.length && text(cue, doc.format) === text(previous, doc.format);
            const a = previous && times(previous);
            const b = times(cue);
            if (same && a.start !== null && a.end !== null && b.end !== null) {
                previous.end = Math.max(a.end, b.end);
                drop.add(cue);
                continue;
            }
            previous = cue;
        }
        items = items.filter((item) => !drop.has(item));
        note(drop.size, 'Merged {n} repeated cue{s} into the cue before.');
    }
    if (chosen.has('trim-overlaps')) {
        let n = 0;
        const list = cues();
        for (let i = 1; i < list.length; i++) {
            const a = times(list[i - 1]);
            const b = times(list[i]);
            // Only against a next cue whose own timing works: trimming to a broken
            // (zero-length or reversed) cue would just move the problem.
            if (a.start !== null && a.end !== null && b.start !== null && b.end !== null && b.end > b.start && b.start > a.start && b.start < a.end) {
                list[i - 1].end = b.start;
                n++;
            }
        }
        note(n, 'Shortened {n} overlapping cue{s} to end when the next cue starts.');
    }

    // Layout
    if (chosen.has('rewrap')) {
        let n = 0;
        let dialogue = 0;
        for (const cue of cues()) {
            const visible = cue.text.map((line) => length(visibleText(line, doc.format)));
            if (!visible.some((len) => len > settings.maxCharsPerLine) && cue.text.length <= settings.maxLines) continue;
            if (cue.text.some((line) => /^\s*[-–—]/.test(line))) { dialogue++; continue; }
            const wrapped = wrap(cue.text, settings.maxCharsPerLine, doc.format);
            if (wrapped.join('\n') !== cue.text.join('\n')) { cue.text = wrapped; n++; }
        }
        note(n, 'Rewrapped the text of {n} cue{s}.');
        if (dialogue) cautions.push(`${dialogue} dialogue cue${dialogue === 1 ? ' was' : 's were'} not rewrapped, because joining the speakers' lines would change the meaning.`);
    }

    // Always-applied corrections, reported only when they change something
    const source = doc.format;
    const list = cues();
    if (format === 'vtt' && !doc.header) changes.push('Added the WEBVTT header.');
    if (format === 'srt' && list.some((cue, i) => cue.index !== String(i + 1))) changes.push('Numbered the cues 1, 2, 3 … in order.');
    const reformatted = list.filter((cue) => cue.startFixable || cue.endFixable || cue.arrowFixable).length;
    note(reformatted, 'Rewrote the timestamps of {n} cue{s} in the correct format (the times are unchanged).');
    if (list.some((cue) => cue.missingBlank)) changes.push('Added the missing blank lines between cues.');

    // Conversion between formats
    if (format !== source) {
        changes.push(`Converted from ${source === 'srt' ? 'SRT' : 'WebVTT'} to ${format === 'srt' ? 'SRT' : 'WebVTT'}.`);
        if (format === 'srt') {
            const blocks = items.filter((item) => ['note', 'style', 'region'].includes(item.type)).length;
            const settingsCount = list.filter((cue) => cue.settings).length;
            const ids = list.filter((cue) => cue.id !== null).length;
            if (blocks) cautions.push(`${blocks} NOTE, STYLE or REGION block${blocks === 1 ? ' is' : 's are'} removed: SRT has no equivalent.`);
            if (settingsCount) cautions.push(`Cue settings (position, alignment) on ${settingsCount} cue${settingsCount === 1 ? '' : 's'} are removed: SRT has no equivalent.`);
            if (ids) cautions.push(`Cue identifiers on ${ids} cue${ids === 1 ? '' : 's'} are replaced by SRT numbers.`);
            if (doc.header && (doc.header.line.trim() !== 'WEBVTT' || doc.header.extra.length)) cautions.push('The header title and metadata are removed.');
            if (list.some((cue) => cue.text.some((line) => /<(?:v|c|lang|ruby|rt)[s.>]|<d|&(?:amp|lt|gt|nbsp|lrm|rlm);/.test(line)))) {
                cautions.push('WebVTT-only markup, such as <v Speaker> voice tags and entities like &amp;, is kept as written and may show as text in SRT players.');
            }
        }
        if (format === 'vtt' && list.some((cue) => cue.text.some((line) => /<font|\{\\/i.test(line)))) {
            cautions.push('SRT <font> tags and {\\an} position codes are not WebVTT; they are kept as written and may show as text.');
        }
    }
    if (doc.mixedLineEndings) changes.push(`Used ${doc.lineEnding === '\r\n' ? 'Windows (CRLF)' : 'Unix (LF)'} line endings throughout.`);

    return { text: serialize(items, doc, format), changes, cautions };
}

function text(cue, format) {
    return cue.text.map((line) => visibleText(line, format).trim()).join('\n');
}

function timingLine(cue, format, source, keepSettings) {
    if (cue.malformed) return cue.timing;
    // A timestamp that is valid for the output format and still holds the
    // cue's time is kept exactly as written (WebVTT may omit the hours).
    // Others are rewritten when the time is known — including times a
    // confirmed fix changed — and left as they were when it is not.
    const write = (ms, code, fixable, raw) => {
        if (code === null && format === source && parseTime(raw, format).ms === ms) return raw;
        return usable(ms, code, fixable) && ms >= 0 ? formatTime(ms, format) : raw;
    };
    const start = write(cue.start, cue.startCode, cue.startFixable, cue.startText);
    const end = write(cue.end, cue.endCode, cue.endFixable, cue.endText);
    return `${start} --> ${end}${keepSettings && cue.settings ? ` ${cue.settings}` : ''}`;
}

function serialize(items, doc, format) {
    const blocks = [];

    if (format === 'vtt') {
        const header = doc.format === 'vtt' && doc.header ? [doc.header.line, ...doc.header.extra] : ['WEBVTT'];
        blocks.push(header);
    }

    let number = 0;
    for (const item of items) {
        if (item.type === 'cue') {
            number++;
            const keepSettings = format === doc.format;
            if (format === 'srt') blocks.push([String(number), timingLine(item, 'srt', doc.format, keepSettings), ...item.text]);
            else blocks.push([...(item.id !== null && doc.format === 'vtt' ? [item.id] : []), timingLine(item, 'vtt', doc.format, keepSettings), ...item.text]);
        } else if (item.type === 'raw') {
            blocks.push(item.lines);
        } else if (format === 'vtt') {
            blocks.push(item.lines); // NOTE, STYLE, REGION
        }
    }

    const eol = doc.lineEnding;
    return (doc.bom ? BOM : '') + blocks.map((block) => block.join(eol)).join(eol + eol) + eol;
}

// Re-breaks text into lines of at most `max` visible characters, balancing
// two lines where the text fits in two. A single word longer than `max`
// stays on its own line.
export function wrap(lines, max, format) {
    const words = lines.join(' ').split(/\s+/).filter(Boolean);
    const size = (line) => length(visibleText(line, format));

    if (size(words.join(' ')) <= max) return [words.join(' ')];

    let best = null;
    for (let i = 1; i < words.length; i++) {
        const first = words.slice(0, i).join(' ');
        const second = words.slice(i).join(' ');
        if (size(first) <= max && size(second) <= max) {
            const balance = Math.abs(size(first) - size(second));
            if (best === null || balance < best.balance) best = { lines: [first, second], balance };
        }
    }
    if (best) return best.lines;

    const out = [];
    let current = '';
    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;
        if (current && size(candidate) > max) {
            out.push(current);
            current = word;
        } else {
            current = candidate;
        }
    }
    if (current) out.push(current);
    return out;
}
