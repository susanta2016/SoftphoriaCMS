import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sniff } from '../src/intake.js';
import { trackDetections } from '../src/tracker.js';
import { detect, thumbDiff } from '../src/detector-core.js';
import { evaluateDetector } from './helpers/eval.mjs';
import { syntheticSet } from './helpers/synth.mjs';

const bytes = (...parts) => {
    const out = [];
    for (const p of parts) typeof p === 'string' ? out.push(...[...p].map((c) => c.charCodeAt(0))) : out.push(...p);
    while (out.length < 32) out.push(0);
    return new Uint8Array(out);
};

test('sniff identifies supported formats by content', () => {
    const cases = [
        [bytes([0x89], 'PNG\r\n'), 'image', 'png'],
        [bytes([0xff, 0xd8, 0xff, 0xe0]), 'image', 'jpeg'],
        [bytes('RIFF', [0, 0, 0, 0], 'WEBPVP8 '), 'image', 'webp'],
        [bytes([0x1a, 0x45, 0xdf, 0xa3]), 'video', 'webm'],
        [bytes([0, 0, 0, 0x20], 'ftypisom'), 'video', 'mp4'],
        [bytes([0, 0, 0, 0x14], 'ftypqt  '), 'video', 'mov'],
        [bytes([0, 0, 0, 0x08], 'wide'), 'video', 'mov'],
    ];
    for (const [b, kind, format] of cases) assert.deepEqual(sniff(b, 1000), { ok: true, kind, format });
});

test('sniff rejects unsupported, SVG, HEIC, empty and oversized files', () => {
    assert.equal(sniff(bytes('<svg xmlns="http://www.w3.org/2000/svg">'), 100).code, 'unsupported');
    assert.equal(sniff(bytes([0, 0, 0, 0x18], 'ftypheic'), 100).code, 'unsupported');
    assert.equal(sniff(bytes('GIF89a'), 100).code, 'unsupported');
    assert.equal(sniff(new Uint8Array(4), 4).code, 'unsupported');
    assert.equal(sniff(bytes([0x89], 'PNG'), 2 * 1024 ** 3).code, 'too-large');
});

test('tracker links boxes across frames and drops short-lived ones', () => {
    const frames = [0, 0.5, 1, 1.5].map((t) => ({
        t,
        boxes: [{ rect: [100 + t * 4, 200, 400 + t * 4, 260], confidence: 0.8 }, ...(t === 0.5 ? [{ rect: [600, 900, 700, 950], confidence: 0.9 }] : [])],
    }));
    const els = trackDetections(frames, { intervalS: 0.5 });
    assert.equal(els.length, 1); // the box seen in one sample only is a passing detail
    assert.equal(els[0].samples.length, 4);
    assert.equal(els[0].fromS, 0);
    assert.equal(els[0].toS, 2);
    assert.equal(els[0].source, 'auto');
    assert.equal(els[0].id, 'a1');
});

test('tracker keeps a single sample when it is an image, and survives a one-sample gap', () => {
    assert.equal(trackDetections([{ t: 0, boxes: [{ rect: [0, 0, 100, 50], confidence: 0.7 }] }], { isImage: true }).length, 1);
    const frames = [
        { t: 0, boxes: [{ rect: [0, 0, 100, 50], confidence: 0.7 }] },
        { t: 0.5, boxes: [] },
        { t: 1, boxes: [{ rect: [2, 0, 102, 50], confidence: 0.7 }] },
    ];
    const els = trackDetections(frames, { intervalS: 0.5 });
    assert.equal(els.length, 1);
    assert.equal(els[0].samples.length, 2);
});

test('detector meets the spec bar on the synthetic set: recall >= 0.9, precision >= 0.8', () => {
    const r = evaluateDetector();
    assert.ok(r.recall >= 0.9, `recall ${r.recall} misses ${JSON.stringify(r.misses)}`);
    assert.ok(r.precision >= 0.8, `precision ${r.precision}`);
});

test('detector generalises to unseen synthetic frames (two more seeds)', () => {
    for (const seedBase of [5000, 9000]) {
        const r = evaluateDetector({ seedBase });
        assert.ok(r.recall >= 0.9 && r.precision >= 0.8, `seed ${seedBase}: recall ${r.recall} precision ${r.precision}`);
    }
});

test('detector is deterministic and finds nothing on flat or text-free frames', () => {
    const f = syntheticSet()[2];
    assert.deepEqual(detect(f.gray, f.width, f.height), detect(f.gray, f.width, f.height));
    const flat = new Uint8Array(270 * 480).fill(128);
    assert.deepEqual(detect(flat, 270, 480), []);
    for (const neg of syntheticSet().filter((x) => x.negative)) assert.equal(detect(neg.gray, neg.width, neg.height).length, 0, neg.id);
});

test('thumbDiff measures mean absolute difference', () => {
    assert.equal(thumbDiff(new Uint8Array([0, 0, 0, 0]), new Uint8Array([0, 10, 20, 30])), 15);
});

test('playback capture never records a later frame as an earlier sample time', async () => {
    const { assignSamples, MAX_FRAME_LAG_S } = await import('../src/frames.js');
    const times = [0, 0.5, 1, 1.5, 2, 2.5, 3];
    // frame shown at 0.1 s: stands for t=0 only
    assert.deepEqual(assignSamples(times, 0, 0.1), { captured: [0], missed: [], next: 1 });
    // the browser skipped ahead to 2.6 s: 0.5-2.0 are missed (to be seeked), 2.5 is captured
    assert.deepEqual(assignSamples(times, 1, 2.6), { captured: [2.5], missed: [0.5, 1, 1.5, 2], next: 6 });
    // a frame slightly before the next sample time (within 20 ms) counts for it
    assert.deepEqual(assignSamples(times, 6, 2.99), { captured: [3], missed: [], next: 7 });
    assert.equal(MAX_FRAME_LAG_S, 0.2);
});
