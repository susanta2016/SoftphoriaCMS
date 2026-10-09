// Report export as CSV or plain text. Reports describe the findings and
// never include the subtitle text itself.

import { CATEGORIES } from './rules.js';
import { displayDuration, displayTime } from './timestamp.js';

const COLUMNS = ['Severity', 'Category', 'Rule', 'Cue', 'Line', 'Start', 'End', 'Issue', 'Detail', 'Suggestion'];

const row = (f) => [
    f.severity === 'error' ? 'Error' : 'Warning',
    CATEGORIES[f.category],
    f.rule,
    f.cue ?? '',
    f.line ?? '',
    displayTime(f.start),
    displayTime(f.end),
    f.message,
    f.detail ?? '',
    f.suggestion,
];

// Quotes every cell, and stops spreadsheet apps reading a cell as a formula.
function cell(value) {
    let text = String(value);
    if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
}

export function reportCsv(result) {
    return [COLUMNS, ...result.findings.map(row)].map((cells) => cells.map(cell).join(',')).join('\r\n') + '\r\n';
}

export function summaryLines(result, format) {
    const { stats } = result;
    const status = { clean: 'No issues found', warnings: 'Valid, with warnings', errors: 'Errors found' }[result.status];
    return [
        `Format: ${format === 'srt' ? 'SRT' : 'WebVTT'}`,
        `Status: ${status}`,
        `Quality score: ${result.score}/100 (100 minus 10 per error and 3 per warning)`,
        `Cues: ${stats.cues}`,
        `Errors: ${result.errors}`,
        `Warnings: ${result.warnings}`,
        `Runs from ${displayTime(stats.first) || '—'} to ${displayTime(stats.last) || '—'} (${displayDuration(stats.span)})`,
        `Average reading speed: ${stats.averageCps === null ? '—' : `${round(stats.averageCps)} characters per second`}`,
        `Highest reading speed: ${stats.maxCps === null ? '—' : `${round(stats.maxCps)} characters per second (cue ${stats.maxCpsCue})`}`,
    ];
}

export function reportText(result, format, settings) {
    const limits = `Rules: max ${settings.maxCharsPerLine} characters per line, ${settings.maxLines} lines per cue, ${settings.maxCps} characters per second; cues ${settings.minDuration}–${settings.maxDuration} s${settings.minGap > 0 ? `; gap at least ${settings.minGap} s` : ''}.`;
    const lines = ['Subtitle validation report — Softphoria Subtitle Validator', '', ...summaryLines(result, format), limits, ''];

    if (!result.findings.length) lines.push('No issues found.');
    result.findings.forEach((f, i) => {
        const where = [f.cue ? `Cue ${f.cue}` : null, f.line ? `line ${f.line}` : null, f.start !== null ? `${displayTime(f.start)} --> ${displayTime(f.end)}` : null].filter(Boolean).join(', ');
        lines.push(`${i + 1}. [${f.severity === 'error' ? 'Error' : 'Warning'} · ${CATEGORIES[f.category]} · ${f.rule}]${where ? ` ${where}` : ''}`);
        lines.push(`   ${f.message}${f.detail ? ` (${f.detail})` : ''}`);
        lines.push(`   Suggestion: ${f.suggestion}`);
    });
    lines.push('', 'The score covers format and readability only. It does not check accuracy against the audio or guarantee acceptance by any platform.');

    return lines.join('\r\n') + '\r\n';
}

const round = (n) => Math.round(n * 10) / 10;
