import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { check } from '../src/check.js';
import { detectFormat } from '../src/parse.js';
import { DEFAULT_SETTINGS, MAX_CUES, RULES, FIXES } from '../src/rules.js';
import { parseTime } from '../src/timestamp.js';

const fixture = (name) => readFileSync(new URL(`./fixtures/${name}`, import.meta.url), 'utf8');
const run = (name, options = {}) => check(fixture(name), { fileName: name, ...options });
const found = (result) => result.findings.map((f) => [f.rule, f.cue, f.line]);

test('every rule has a stable shape, and every named fix exists', () => {
    for (const [id, [severity, category, message, suggestion, fix]] of Object.entries(RULES)) {
        assert.match(id, /^[a-z0-9-]+$/);
        assert.ok(['error', 'warning'].includes(severity), id);
        assert.ok(['structure', 'timestamp', 'timing', 'readability', 'encoding'].includes(category), id);
        assert.ok(message && suggestion, id);
        if (fix) assert.ok(FIXES[fix], `${id} → ${fix}`);
    }
});

test('a valid SRT file is clean, with stats', () => {
    const { format, result } = run('valid.srt');
    assert.equal(format, 'srt');
    assert.deepEqual(found(result), []);
    assert.equal(result.score, 100);
    assert.equal(result.status, 'clean');
    assert.equal(result.stats.cues, 3);
    assert.equal(result.stats.first, 1000);
    assert.equal(result.stats.last, 10000);
    assert.equal(result.stats.span, 9000);
    assert.equal(result.stats.maxCpsCue, 2);
});

test('a valid WebVTT file with header metadata, STYLE, NOTE, identifiers and settings is clean', () => {
    const { format, doc, result } = run('valid.vtt');
    assert.equal(format, 'vtt');
    assert.deepEqual(found(result), []);
    assert.deepEqual(doc.cues.map((c) => c.id), ['intro', null, '3']);
    assert.equal(doc.cues[0].start, 1000, 'hours are optional in WebVTT');
    assert.equal(doc.cues[0].settings, 'align:start position:10%');
    assert.deepEqual(doc.items.map((i) => i.type), ['style', 'note', 'cue', 'cue', 'note', 'cue']);
    assert.deepEqual(doc.header, { line: 'WEBVTT - Sample captions', extra: ['Kind: captions', 'Language: en'] });
    assert.deepEqual(doc.cues[1].text, ['Today we check <i>subtitle</i> files', 'before they go online.'], 'multiline text kept as written');
});

test('Unicode, non-Latin scripts and CRLF line endings are read correctly', () => {
    const { doc, result } = run('unicode-crlf.srt');
    assert.deepEqual(found(result), []);
    assert.deepEqual(doc.cues.map((c) => c.text[0]), ['こんにちは、世界。', 'مرحبا بالعالم', 'Привет, мир! 👋']);
    assert.equal(doc.lineEnding, '\r\n');
});

test('a BOM is accepted and remembered', () => {
    const { doc, result } = check(String.fromCodePoint(0xfeff) + fixture('valid.srt'));
    assert.equal(doc.bom, true);
    assert.deepEqual(found(result), []);
});

test('a WebVTT file without its header', () => {
    const { result } = run('missing-header.vtt');
    assert.deepEqual(found(result), [['vtt-missing-header', null, 1]]);
    assert.equal(result.findings[0].fix, 'add-header');
});

test('malformed timestamps are diagnosed precisely', () => {
    const { result } = run('malformed-timestamps.srt');
    assert.deepEqual(found(result).filter(([rule]) => rule !== 'reading-speed'), [
        ['timestamp-separator', 1, 2],
        ['timestamp-hours', 2, 6],
        ['timestamp-precision', 3, 10],
        ['timestamp-invalid', 4, 14],
        ['timestamp-negative', 5, 18],
        ['timing-arrow-spacing', 6, 22],
        ['stray-text', null, 25],
    ]);
    assert.equal(result.findings.find((f) => f.rule === 'stray-text').detail, 'Lines 25–27');
});

test('timestamp parsing per format', () => {
    assert.deepEqual(parseTime('01:02:03,456', 'srt'), { ms: 3723456, code: null, fixable: false });
    assert.deepEqual(parseTime('02:03.456', 'vtt'), { ms: 123456, code: null, fixable: false });
    assert.deepEqual(parseTime('00:02:03.456', 'srt'), { ms: 123456, code: 'timestamp-separator', fixable: true });
    assert.deepEqual(parseTime('02:03,456', 'srt'), { ms: 123456, code: 'timestamp-hours', fixable: true });
    assert.deepEqual(parseTime('0:02:03.456', 'vtt'), { ms: 123456, code: 'timestamp-hours', fixable: true });
    assert.equal(parseTime('100:00:00.000', 'vtt').ms, 360000000, 'more than two hour digits is valid');
    assert.equal(parseTime('00:00:01.0000', 'vtt').code, 'timestamp-precision');
    assert.equal(parseTime('00:00:60.000', 'vtt').code, 'timestamp-invalid');
    assert.equal(parseTime('abc', 'srt').code, 'timestamp-invalid');
    assert.deepEqual(parseTime('-00:00:01.500', 'vtt'), { ms: -1500, code: 'timestamp-negative', fixable: false });
});

test('timing errors and warnings: end before start, zero, order, overlap, short and long', () => {
    const { result } = run('timing.srt');
    assert.deepEqual(found(result), [
        ['end-before-start', 1, 2],
        ['zero-duration', 2, 6],
        ['out-of-order', 3, 10],
        ['short-duration', 5, 18],
        ['reading-speed', 5, 18],
        ['overlap', 5, 18],
        ['long-duration', 6, 22],
    ]);
    assert.equal(result.findings.find((f) => f.rule === 'overlap').severity, 'warning', 'an overlap is never fatal');
    assert.equal(result.findings.find((f) => f.rule === 'overlap').detail, 'Overlaps cue 4 by 1 s');
});

test('the optional minimum gap is off by default and configurable', () => {
    const text = '1\n00:00:01,000 --> 00:00:02,000\nOne.\n\n2\n00:00:02,050 --> 00:00:03,000\nTwo.\n';
    assert.deepEqual(found(check(text).result), []);
    assert.deepEqual(found(check(text, { settings: { ...DEFAULT_SETTINGS, minGap: 0.1 } }).result), [['small-gap', 2, 6]]);
});

test('readability: empty cue, CPS, line length, line count, whitespace, repeats, control characters', () => {
    const { result } = run('readability.srt');
    assert.deepEqual(found(result), [
        ['cue-empty', 1, 2],
        ['reading-speed', 2, 5],
        ['line-too-long', 2, 5],
        ['too-many-lines', 3, 9],
        ['text-whitespace', 4, 15],
        ['duplicate-cue', 6, 23],
        ['control-characters', 7, 27],
    ]);
    assert.equal(result.findings.find((f) => f.rule === 'cue-empty').severity, 'error');
});

test('limits are configurable', () => {
    const relaxed = { ...DEFAULT_SETTINGS, maxCharsPerLine: 100, maxLines: 3, maxCps: 100 };
    const rules = check(fixture('readability.srt'), { settings: relaxed }).result.findings.map((f) => f.rule);
    assert.ok(!rules.includes('line-too-long') && !rules.includes('too-many-lines') && !rules.includes('reading-speed'));
});

test('characters are counted as people read them: tags removed, emoji and CJK count once', () => {
    const vtt = 'WEBVTT\n\n00:00.000 --> 00:01.000\n<v Speaker><b>Hi</b></v> &amp; 👋\n';
    const { result } = check(vtt, { settings: { ...DEFAULT_SETTINGS, maxCharsPerLine: 6, maxCps: 6 } });
    assert.deepEqual(found(result), [], '"Hi & 👋" is 6 characters');
    const srt = '1\n00:00:00,000 --> 00:00:01,000\n<i>{\\an8}字幕です</i>\n';
    assert.deepEqual(found(check(srt, { settings: { ...DEFAULT_SETTINGS, maxCharsPerLine: 4, maxCps: 4 } }).result), []);
});

test('SRT structure: missing blank line, missing number, stray text, duplicates, sequence, whitespace separators', () => {
    const { doc, result } = run('structure.srt');
    assert.deepEqual(found(result).filter(([rule]) => !['reading-speed', 'line-too-long'].includes(rule)), [
        ['missing-blank-line', 2, 5],
        ['srt-missing-index', 3, 8],
        ['stray-text', null, 15],
        ['srt-index-duplicate', 5, 17],
        ['whitespace-separator', null, 20],
        ['srt-index-sequence', 6, 21],
    ]);
    assert.deepEqual(doc.cues.map((c) => c.index), ['1', '2', null, '3', '3', '7']);
    assert.deepEqual(doc.cues[0].text, ['First cue.'], 'the next cue\'s number is not swallowed as text');
});

test('WebVTT structure: invalid settings, note arrows, late STYLE, duplicate ids, missing blank lines', () => {
    const text = [
        'WEBVTT', '', 'a', '00:01.000 --> 00:02.000 align:middle line:85%', 'One.', '', 'NOTE 00:01 --> 00:02', '',
        'STYLE', '::cue {}', '', 'a', '00:03.000 --> 00:04.000', 'Two.', '00:05.000 --> 00:06.000', 'Three.', '',
    ].join('\n');
    const { doc, result } = check(text);
    assert.deepEqual(found(result), [
        ['vtt-cue-setting', 1, 4],
        ['vtt-note-arrow', null, 7],
        ['vtt-block-after-cue', null, 9],
        ['vtt-duplicate-id', 2, 12],
        ['missing-blank-line', 3, 15],
    ]);
    assert.equal(result.findings[0].detail, 'align:middle');
    assert.equal(doc.cues.length, 3);
});

test('an invalid header line is reported', () => {
    assert.deepEqual(found(check('WEBVTTX\n\n00:01.000 --> 00:02.000\nHi.\n', { format: 'vtt' }).result), [['vtt-header-invalid', null, 1]]);
});

test('empty, whitespace-only and non-subtitle input', () => {
    assert.deepEqual(found(check('').result), [['file-empty', null, null]]);
    assert.deepEqual(found(check(' \n\n \t\n').result), [['file-empty', null, null]]);
    const prose = check('Just some text.\nNo timing here.\n', { format: 'srt' }).result;
    assert.deepEqual(found(prose), [['no-cues', null, null], ['stray-text', null, 1]]);
});

test('mixed line endings are reported once', () => {
    assert.deepEqual(found(check('1\r\n00:00:01,000 --> 00:00:02,000\nHi.\n').result), [['mixed-line-endings', null, null]]);
});

test('a very long line does not break parsing', () => {
    const long = 'word '.repeat(20000).trim();
    const { result } = check(`1\n00:00:00,000 --> 00:00:05,000\n${long}\n`);
    assert.deepEqual(found(result).map(([rule]) => rule), ['reading-speed', 'line-too-long']);
});

test('more than 10,000 cues stops with a clear error', () => {
    const cue = (i) => `${i}\n00:00:01,000 --> 00:00:02,000\nCue ${i}\n`;
    const atLimit = check(Array.from({ length: MAX_CUES }, (_, i) => cue(i + 1)).join('\n')).result;
    assert.equal(atLimit.findings.some((f) => f.rule === 'too-many-cues'), false);
    const over = check(Array.from({ length: MAX_CUES + 1 }, (_, i) => cue(i + 1)).join('\n')).result;
    assert.deepEqual(over.findings.map((f) => f.rule).filter((r) => r === 'too-many-cues'), ['too-many-cues']);
    assert.equal(over.stats.cues, 0, 'nothing else is checked');
});

test('format detection', () => {
    assert.deepEqual(detectFormat(fixture('valid.vtt')), { format: 'vtt', certain: true });
    assert.deepEqual(detectFormat(fixture('valid.srt')), { format: 'srt', certain: true });
    assert.deepEqual(detectFormat(fixture('missing-header.vtt'), 'missing-header.vtt'), { format: 'vtt', certain: true });
    assert.deepEqual(detectFormat('1\n00:00:01.000 --> 00:00:02.000\nHi\n'), { format: 'srt', certain: false }, 'dots with a number line: probably SRT, ask');
    assert.deepEqual(detectFormat('00:00:01.000 --> 00:00:02.000\nHi\n'), { format: 'vtt', certain: false });
    assert.deepEqual(detectFormat('no cues', 'x.srt'), { format: 'srt', certain: false });
});

test('score: 100 minus 10 per error and 3 per warning, never below 0', () => {
    const { result } = run('timing.srt');
    assert.equal(result.errors, 3);
    assert.equal(result.warnings, 4);
    assert.equal(result.score, 100 - 30 - 12);
    const many = Array.from({ length: 12 }, (_, i) => `${i + 1}\n00:00:0${i % 10},000 --> 00:00:00,000\nX\n`).join('\n');
    assert.equal(check(many).result.score, 0);
});

test('a Windows-1252 read is flagged so the visitor checks the characters', () => {
    const { result } = check(fixture('valid.srt'), { encoding: 'Windows-1252' });
    assert.deepEqual(found(result), [['encoding-windows-1252', null, null]]);
});
