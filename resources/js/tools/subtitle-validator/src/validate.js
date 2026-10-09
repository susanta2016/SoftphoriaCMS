// Validation: runs every rule over a parsed document and returns the
// findings (one per rule and place), summary statistics, the quality score
// and an overall status.
//
// Quality score (a product scoring model, not an industry standard):
// start at 100, take 10 points per error and 3 per warning, keep it within
// 0–100. It describes format and readability only — never transcription
// accuracy, accessibility compliance or acceptance by any platform.

import { CONTROL_RANGES, DEFAULT_SETTINGS, finding } from './rules.js';

const CONTROL = new RegExp(`[${CONTROL_RANGES}]`);
const VTT_ENTITIES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&nbsp;': ' ', '&lrm;': '', '&rlm;': '' };

/** The text a viewer sees: formatting tags removed, entities decoded. */
export function visibleText(line, format) {
    if (format === 'vtt') {
        return line.replace(/<[^>]*>/g, '').replace(/&(?:amp|lt|gt|nbsp|lrm|rlm);/g, (entity) => VTT_ENTITIES[entity]);
    }
    return line.replace(/<\/?(?:i|b|u|font)(?:\s[^>]*)?>/gi, '').replace(/\{\\[^}]*\}/g, '');
}

/** Characters as people count them: code points, so emoji and CJK count once. */
export const length = (text) => Array.from(text).length;

export const usable = (ms, code, fixable) => ms !== null && (code === null || fixable);
const startOf = (cue) => (usable(cue.start, cue.startCode, cue.startFixable) ? cue.start : null);
const endOf = (cue) => (usable(cue.end, cue.endCode, cue.endFixable) ? cue.end : null);

const VTT_SETTINGS = {
    vertical: /^(rl|lr)$/,
    line: /^(-?\d+|\d+(\.\d+)?%)(,(start|center|end))?$/,
    position: /^\d+(\.\d+)?%(,(line-left|center|line-right))?$/,
    size: /^\d+(\.\d+)?%$/,
    align: /^(start|center|end|left|right)$/,
    region: /^\S+$/,
};

function badVttSettings(settings) {
    return settings.split(/[ \t]+/).filter(Boolean).filter((token) => {
        const at = token.indexOf(':');
        if (at <= 0) return true;
        const name = token.slice(0, at);
        const value = token.slice(at + 1);
        return !VTT_SETTINGS[name]?.test(value);
    });
}

/**
 * @param {object} doc       from parse()
 * @param {Array} parsed     the parser's findings
 * @param {object} settings  rule limits (see DEFAULT_SETTINGS)
 */
export function validate(doc, parsed, settings = DEFAULT_SETTINGS) {
    const limits = { ...DEFAULT_SETTINGS, ...settings };
    const findings = [...parsed];
    const at = (cue, extra = {}) => ({ cue: cue.n, line: cue.line, start: startOf(cue), end: endOf(cue), ...extra });
    const cues = doc.aborted ? [] : doc.cues;

    // Numbering, identifiers and settings
    let previousIndex = null;
    const seenIndexes = new Set();
    const seenIds = new Set();
    for (const cue of cues) {
        if (doc.format === 'srt' && cue.index !== null) {
            const index = Number(cue.index);
            if (seenIndexes.has(index)) findings.push(finding('srt-index-duplicate', at(cue, { line: cue.indexLine, detail: `Number ${index}` })));
            else if (index !== (previousIndex === null ? 1 : previousIndex + 1)) findings.push(finding('srt-index-sequence', at(cue, { line: cue.indexLine, detail: `Number ${index}, expected ${previousIndex === null ? 1 : previousIndex + 1}` })));
            seenIndexes.add(index);
            previousIndex = index;
        }
        if (doc.format === 'vtt' && cue.id !== null) {
            if (seenIds.has(cue.id)) findings.push(finding('vtt-duplicate-id', at(cue, { line: cue.idLine, detail: cue.id })));
            seenIds.add(cue.id);
        }
        if (cue.settings) {
            if (doc.format === 'srt') findings.push(finding('srt-timing-settings', at(cue, { detail: cue.settings })));
            else {
                const bad = badVttSettings(cue.settings);
                if (bad.length) findings.push(finding('vtt-cue-setting', at(cue, { detail: bad.join(' ') })));
            }
        }
    }

    // Timing, then order, overlaps and gaps between consecutive cues
    let previous = null;
    const stats = { cues: cues.length, cpsValues: [], maxCps: null, maxCpsCue: null, first: null, last: null };

    for (const cue of cues) {
        const start = startOf(cue);
        const end = endOf(cue);
        const lines = cue.text.map((line) => visibleText(line, doc.format));
        const chars = lines.reduce((sum, line) => sum + length(line), 0);

        if (cue.text.every((line) => line.trim() === '') ) findings.push(finding('cue-empty', at(cue)));

        if (start !== null && end !== null) {
            const duration = end - start;
            if (duration < 0) findings.push(finding('end-before-start', at(cue)));
            else if (duration === 0) findings.push(finding('zero-duration', at(cue)));
            else {
                if (duration < limits.minDuration * 1000) findings.push(finding('short-duration', at(cue, { detail: `${duration / 1000} s` })));
                if (duration > limits.maxDuration * 1000) findings.push(finding('long-duration', at(cue, { detail: `${duration / 1000} s` })));
                if (chars > 0) {
                    const cps = chars / (duration / 1000);
                    stats.cpsValues.push(cps);
                    if (stats.maxCps === null || cps > stats.maxCps) {
                        stats.maxCps = cps;
                        stats.maxCpsCue = cue.n;
                    }
                    if (cps > limits.maxCps) findings.push(finding('reading-speed', at(cue, { detail: `${Math.round(cps * 10) / 10} characters per second` })));
                }
            }
            stats.first = stats.first === null ? start : Math.min(stats.first, start);
            stats.last = stats.last === null ? end : Math.max(stats.last, end);
        }

        if (start !== null && previous) {
            if (start < previous.start) findings.push(finding('out-of-order', at(cue, { detail: `Previous cue (${previous.cue.n}) starts later` })));
            else if (previous.end !== null && start < previous.end) findings.push(finding('overlap', at(cue, { detail: `Overlaps cue ${previous.cue.n} by ${(previous.end - start) / 1000} s` })));
            else if (limits.minGap > 0 && previous.end !== null && start - previous.end < limits.minGap * 1000) {
                findings.push(finding('small-gap', at(cue, { detail: `${(start - previous.end) / 1000} s after cue ${previous.cue.n}` })));
            }
        }
        if (start !== null) previous = { cue, start, end };

        // Readability
        const longest = lines.reduce((max, line) => Math.max(max, length(line)), 0);
        if (longest > limits.maxCharsPerLine) findings.push(finding('line-too-long', at(cue, { detail: `Longest line: ${longest} characters` })));
        if (cue.text.length > limits.maxLines) findings.push(finding('too-many-lines', at(cue, { detail: `${cue.text.length} lines` })));
        if (cue.text.some((line) => /^[ \t]|[ \t]$/.test(line))) findings.push(finding('text-whitespace', at(cue)));
        if (cue.text.some((line) => CONTROL.test(line))) findings.push(finding('control-characters', at(cue)));
    }

    // Repeated adjacent captions
    for (let i = 1; i < cues.length; i++) {
        const text = (cue) => cue.text.map((line) => visibleText(line, doc.format).trim()).join('\n');
        if (text(cues[i]) !== '' && text(cues[i]) === text(cues[i - 1])) {
            findings.push(finding('duplicate-cue', at(cues[i], { detail: `Same text as cue ${cues[i - 1].n}` })));
        }
    }

    const unique = dedupe(findings).sort((a, b) => (a.line ?? 0) - (b.line ?? 0) || (a.severity === b.severity ? 0 : a.severity === 'error' ? -1 : 1));
    const errors = unique.filter((f) => f.severity === 'error').length;
    const warnings = unique.length - errors;

    return {
        findings: unique,
        errors,
        warnings,
        score: Math.max(0, Math.min(100, 100 - errors * 10 - warnings * 3)),
        status: errors ? 'errors' : warnings ? 'warnings' : 'clean',
        stats: {
            cues: stats.cues,
            first: stats.first,
            last: stats.last,
            span: stats.first === null ? null : stats.last - stats.first,
            averageCps: stats.cpsValues.length ? stats.cpsValues.reduce((a, b) => a + b, 0) / stats.cpsValues.length : null,
            maxCps: stats.maxCps,
            maxCpsCue: stats.maxCpsCue,
        },
    };
}

// One finding per rule and place: a cue with both timestamps in the wrong
// format, or two long lines, is reported once.
function dedupe(findings) {
    const seen = new Set();
    return findings.filter((f) => {
        const key = `${f.rule}|${f.cue ?? ''}|${f.cue === null ? f.line ?? '' : ''}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}
