import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { check } from '../src/check.js';
import { availableFixes, CONFIRM_FIXES, repair, wrap } from '../src/repair.js';
import { DEFAULT_SETTINGS } from '../src/rules.js';

const fixture = (name) => readFileSync(new URL(`./fixtures/${name}`, import.meta.url), 'utf8');
// Parses in the detected format, writes `format` (default: the same).
const fix = (text, { format, fixes = [], settings = DEFAULT_SETTINGS } = {}) => {
    const first = check(text);
    const to = format ?? first.format;
    const out = repair(first.doc, { format: to, fixes, settings });
    return { ...out, recheck: check(out.text, { format: to }).result };
};
const rules = (result) => result.findings.map((f) => f.rule);

test('only meaning-changing fixes need confirmation', () => {
    assert.deepEqual(CONFIRM_FIXES.sort(), ['merge-duplicates', 'remove-control', 'remove-empty', 'rewrap', 'sort', 'trim-overlaps']);
});

test('available fixes come from the findings, with counts', () => {
    const { result } = check(fixture('structure.srt'));
    assert.deepEqual(availableFixes(result.findings).map((f) => [f.id, f.count, f.confirm]), [['separators', 2], ['renumber', 3], ['rewrap', 1]].map(([id, n]) => [id, n, id === 'rewrap']));
});

test('a valid file round-trips unchanged', () => {
    for (const name of ['valid.srt', 'valid.vtt', 'unicode-crlf.srt']) {
        const text = fixture(name);
        assert.equal(fix(text).text, text, name);
    }
    const bom = String.fromCodePoint(0xfeff) + fixture('valid.srt');
    assert.equal(fix(bom).text, bom, 'the BOM is kept');
});

test('missing WebVTT header: added, then the file is clean', () => {
    const out = fix(fixture('missing-header.vtt'));
    assert.ok(out.text.startsWith('WEBVTT\n\n00:00:01.000 --> '));
    assert.deepEqual(out.changes, ['Added the WEBVTT header.']);
    assert.deepEqual(rules(out.recheck), []);
});

test('SRT structure: renumbered and separated; stray text kept so nothing is lost', () => {
    const out = fix(fixture('structure.srt'));
    assert.match(out.text, /^1\n00:00:01,000 --> 00:00:02,000\nFirst cue\.\n\n2\n00:00:03,000/);
    assert.match(out.text, /\n\nStray text after a blank line inside a cue\.\n\n5\n/);
    assert.deepEqual(out.text.match(/^\d+$/gm), ['1', '2', '3', '4', '5', '6']);
    assert.ok(!rules(out.recheck).some((r) => ['missing-blank-line', 'srt-missing-index', 'srt-index-duplicate', 'srt-index-sequence', 'whitespace-separator'].includes(r)));
    assert.ok(rules(out.recheck).includes('stray-text'), 'still reported for the visitor to fix');
});

test('timestamps: format fixed without changing times; unreadable and negative ones left exactly as written', () => {
    const out = fix(fixture('malformed-timestamps.srt'));
    for (const line of ['00:00:01,000 --> 00:00:03,000', '00:00:04,000 --> 00:00:06,000', '00:00:10,000 --> 00:00:11,000',
        '00:00:07,5 --> 00:00:08,000', '00:61:00,000 --> 00:62:00,000', '-00:00:01,000 --> 00:00:02,000', '00:00:12,000 to 00:00:13,000']) {
        assert.ok(out.text.includes(`\n${line}\n`), line);
    }
    assert.deepEqual(rules(out.recheck).filter((r) => r !== 'reading-speed'), ['timestamp-precision', 'timestamp-invalid', 'timestamp-negative', 'stray-text']);
});

test('confirmed fixes: empty cues, control characters, repeats and rewrapping', () => {
    const out = fix(fixture('readability.srt'), { fixes: ['remove-empty', 'remove-control', 'merge-duplicates', 'rewrap', 'trim-whitespace'] });
    assert.deepEqual(out.changes.slice(0, 5), [
        'Removed control characters from 1 line.',
        'Trimmed spaces around 1 text line.',
        'Removed 1 empty cue.',
        'Merged 1 repeated cue into the cue before.',
        'Rewrapped the text of 2 cues.',
    ]);
    assert.ok(out.text.includes('00:00:13,000 --> 00:00:17,000\nRepeated words.'), 'the merged cue covers both');
    assert.ok(out.text.includes('\nPadded text\n'));
    assert.ok(!/[\u0007]/.test(out.text));
    const after = rules(out.recheck);
    for (const rule of ['cue-empty', 'duplicate-cue', 'control-characters', 'text-whitespace', 'line-too-long']) assert.ok(!after.includes(rule), rule);
});

test('without confirmation, nothing that changes text, order or timing is touched', () => {
    const text = fixture('readability.srt');
    const out = fix(text);
    assert.ok(out.text.includes('\n  Padded text  \n'));
    assert.ok(out.text.includes('Bell\u0007character.'));
    assert.equal(out.text.match(/Repeated words\./g).length, 2);
    assert.ok(out.text.includes('00:00:01,000 --> 00:00:03,000\n\n2\n'), 'the empty cue stays');
    const timing = fix(fixture('timing.srt'));
    assert.equal(timing.text, fixture('timing.srt'), 'timing and order unchanged');
});

test('sorting and trimming overlaps, only when chosen', () => {
    const text = '1\n00:00:05,000 --> 00:00:07,000\nB\n\n2\n00:00:01,000 --> 00:00:06,000\nA\n';
    const out = fix(text, { fixes: ['sort', 'trim-overlaps'] });
    assert.equal(out.text, '1\n00:00:01,000 --> 00:00:05,000\nA\n\n2\n00:00:05,000 --> 00:00:07,000\nB\n');
    assert.deepEqual(rules(out.recheck), []);
});

test('a changed time is written out, and untouched valid WebVTT times keep their short form', () => {
    const out = fix('WEBVTT\n\n00:01.000 --> 00:05.000\nA\n\n00:03.000 --> 00:06.000\nB\n', { fixes: ['trim-overlaps'] });
    assert.equal(out.text, 'WEBVTT\n\n00:01.000 --> 00:00:03.000\nA\n\n00:03.000 --> 00:06.000\nB\n');
    assert.deepEqual(rules(out.recheck), []);
});

test('overlaps are not trimmed against a cue whose own timing is broken', () => {
    const text = '1\n00:00:01,000 --> 00:00:05,000\nA\n\n2\n00:00:03,000 --> 00:00:03,000\nB\n';
    assert.equal(fix(text, { fixes: ['trim-overlaps'] }).text, text);
});

test('sorting is refused when some start times cannot be read', () => {
    const out = fix('1\n00:00:05,000 --> 00:00:06,000\nB\n\n2\n00:00:01,5 --> 00:00:02,000\nA\n', { fixes: ['sort'] });
    assert.match(out.cautions[0], /not sorted/);
});

test('rewrapping balances two lines and leaves dialogue alone', () => {
    assert.deepEqual(wrap(['This sentence is long enough to need two lines here.'], 42, 'srt'), ['This sentence is long enough', 'to need two lines here.']);
    assert.deepEqual(wrap(['Short', 'text'], 42, 'srt'), ['Short text']);
    const dialogue = '1\n00:00:00,000 --> 00:00:05,000\n- Is this line far too long to fit on one line?\n- Yes it is.\n';
    const out = fix(dialogue, { fixes: ['rewrap'] });
    assert.equal(out.text, dialogue);
    assert.match(out.cautions[0], /dialogue cue was not rewrapped/);
});

test('WebVTT to SRT: converted, with every removed construct explained', () => {
    const out = fix(fixture('valid.vtt'), { format: 'srt' });
    assert.equal(out.text, '1\n00:00:01,000 --> 00:00:03,500\n<v Narrator>Welcome to the channel.</v>\n\n2\n00:00:04,000 --> 00:00:07,000\nToday we check <i>subtitle</i> files\nbefore they go online.\n\n3\n00:00:07,500 --> 00:00:10,000\nLet\'s get started &amp; enjoy.\n');
    assert.equal(out.cautions.length, 5);
    assert.deepEqual(rules(out.recheck), []);
});

test('SRT to WebVTT: valid WebVTT', () => {
    const out = fix(fixture('valid.srt'), { format: 'vtt' });
    assert.ok(out.text.startsWith('WEBVTT\n\n00:00:01.000 --> 00:00:03.500\nWelcome'));
    assert.deepEqual(rules(check(out.text, { format: 'vtt' }).result), []);
});

test('mixed line endings become the most common one', () => {
    const out = fix('1\r\n00:00:01,000 --> 00:00:02,000\r\nHi.\n');
    assert.equal(out.text, '1\r\n00:00:01,000 --> 00:00:02,000\r\nHi.\r\n');
    assert.deepEqual(rules(out.recheck), []);
});

test('the original text is never modified', () => {
    const text = fixture('readability.srt');
    const copy = String(text);
    const { doc } = check(text);
    const before = JSON.stringify(doc);
    repair(doc, { format: 'srt', fixes: CONFIRM_FIXES, settings: DEFAULT_SETTINGS });
    assert.equal(text, copy);
    assert.equal(JSON.stringify(doc), before, 'the parsed document is not mutated either');
});
