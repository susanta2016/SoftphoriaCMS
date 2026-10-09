import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { check, SAMPLES } from '../src/check.js';
import { decodeBytes, MAX_BYTES } from '../src/decode.js';
import { reportCsv, reportText } from '../src/report.js';
import { DEFAULT_SETTINGS } from '../src/rules.js';

const utf8 = (text) => new TextEncoder().encode(text);
const fixture = (name) => readFileSync(new URL(`./fixtures/${name}`, import.meta.url), 'utf8');

test('UTF-8, with and without BOM, including non-Latin text', () => {
    assert.deepEqual(decodeBytes(utf8('Привет 字幕')), { ok: true, text: 'Привет 字幕', encoding: 'UTF-8', bom: false });
    const bom = decodeBytes(new Uint8Array([0xef, 0xbb, 0xbf, ...utf8('Hi')]));
    assert.deepEqual(bom, { ok: true, text: 'Hi', encoding: 'UTF-8', bom: true });
});

test('UTF-16 with a BOM is read; without one it is reported', () => {
    const le = new Uint8Array([0xff, 0xfe, 0x48, 0x00, 0x69, 0x00]);
    assert.deepEqual(decodeBytes(le), { ok: true, text: 'Hi', encoding: 'UTF-16 LE', bom: true });
    const be = new Uint8Array([0xfe, 0xff, 0x00, 0x48, 0x00, 0x69]);
    assert.equal(decodeBytes(be).text, 'Hi');
    assert.equal(decodeBytes(new Uint8Array([0x48, 0x00, 0x69, 0x00])).code, 'encoding-utf16-no-bom');
});

test('invalid UTF-8 is never silently replaced; its line is reported; Windows-1252 only on request', () => {
    const latin1 = new Uint8Array([...utf8('1\n00:00:01,000 --> 00:00:02,000\nCaf'), 0xe9, ...utf8('\n')]);
    const auto = decodeBytes(latin1);
    assert.equal(auto.ok, false);
    assert.equal(auto.code, 'encoding-invalid-utf8');
    assert.equal(auto.line, 3);
    assert.ok(!('text' in auto));
    const chosen = decodeBytes(latin1, 'windows-1252');
    assert.equal(chosen.text.split('\n')[2], 'Café');
    assert.equal(chosen.encoding, 'Windows-1252');
});

test('files above 5 MiB are refused before decoding', () => {
    assert.equal(decodeBytes(new Uint8Array(MAX_BYTES + 1)).code, 'file-too-large');
    assert.equal(decodeBytes(utf8('x'.repeat(1000))).ok, true);
});

test('the samples behave as labelled', () => {
    assert.equal(check(SAMPLES.valid).result.status, 'clean');
    const broken = check(SAMPLES.broken).result;
    assert.equal(broken.status, 'errors');
    assert.deepEqual([...new Set(broken.findings.map((f) => f.rule))].sort(), [
        'cue-empty', 'duplicate-cue', 'end-before-start', 'line-too-long', 'overlap', 'reading-speed', 'srt-index-sequence', 'timestamp-separator',
    ]);
});

test('CSV report: one row per finding, quoted, formula-safe, no subtitle text', () => {
    const { result } = check(fixture('timing.srt'));
    const csv = reportCsv(result);
    const rows = csv.trim().split('\r\n');
    assert.equal(rows.length, result.findings.length + 1);
    assert.equal(rows[0], '"Severity","Category","Rule","Cue","Line","Start","End","Issue","Detail","Suggestion"');
    assert.ok(rows[1].startsWith('"Error","Timing","end-before-start","1","2","00:00:05.000","00:00:04.000",'));
    assert.ok(!csv.includes('Ends before it starts.'), 'cue text is not exported');
    const negative = reportCsv(check(fixture('malformed-timestamps.srt')).result);
    assert.ok(negative.includes('"\'-00:00:01,000"'), 'a cell starting with - is not read as a formula');
});

test('text report: summary, rules used and every finding', () => {
    const { result } = check(fixture('timing.srt'));
    const text = reportText(result, 'srt', DEFAULT_SETTINGS);
    assert.match(text, /Quality score: 58\/100/);
    assert.match(text, /Errors: 3\r\nWarnings: 4/);
    assert.match(text, /Rules: max 42 characters per line, 2 lines per cue, 20 characters per second; cues 0\.8–7 s\./);
    assert.match(text, /7\. \[Warning · Timing · long-duration\] Cue 6, line 22, 00:00:09\.000 --> 00:00:20\.000/);
    assert.match(text, /does not check accuracy against the audio or guarantee acceptance by any platform/);
    assert.ok(!text.includes('On screen for far too long.'));
});
