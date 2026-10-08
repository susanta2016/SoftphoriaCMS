import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Cancelled, MIN_LONG_SIDE, QUALITY, SAFE_PIXELS, baseSize, keepFormat, optimize } from '../src/optimize.js';
import { formatSize, limitBytes, parseTargetParam, targetError } from '../src/target.js';

// A fake encoder whose output grows with pixel count and quality, like a real one.
// It records every call so tests can check what the search actually did.
function fakeEncoder({ lossyBpp = (q) => 0.04 + 1.1 * q ** 3, pngBpp = 2.2, overhead = 700 } = {}) {
    const calls = [];
    const encode = async (w, h, format, q) => {
        calls.push({ w, h, format, q });
        const bytes = Math.round(w * h * (format === 'png' ? pngBpp : lossyBpp(q)) + overhead);
        return { size: bytes, type: `image/${format}` };
    };
    return { encode, calls, sizeAt: (w, h, q) => Math.round(w * h * lossyBpp(q) + overhead) };
}

const photo = { width: 4000, height: 3000, bytes: 4_200_000, format: 'jpeg', blob: { size: 4_200_000 } };

test('targets: a maximum in strict 1,000-byte KB; invalid values are rejected in plain words', () => {
    assert.equal(limitBytes(50, 'KB'), 50_000);
    assert.equal(limitBytes(1, 'MB'), 1_000_000);
    assert.equal(limitBytes('1.5', 'MB'), 1_500_000);
    assert.equal(limitBytes(0, 'KB'), null);
    assert.equal(limitBytes(-5, 'KB'), null);
    assert.equal(limitBytes(1, 'KB'), null, 'below 2 KB is not a usable target');
    assert.equal(limitBytes(60, 'MB'), null);
    assert.equal(targetError('', 'KB'), 'Enter a number, for example 50.');
    assert.equal(targetError('abc', 'KB'), 'Enter a number, for example 50.');
    assert.equal(targetError('-3', 'KB'), 'The target must be greater than zero.');
    assert.equal(targetError('1', 'KB'), 'The smallest target is 2 KB.');
    assert.equal(targetError('70', 'MB'), 'The largest target is 50 MB.');
    assert.equal(targetError('50', 'KB'), null);
    assert.deepEqual(parseTargetParam('50kb'), { value: 50, unit: 'KB' });
    assert.deepEqual(parseTargetParam('1MB'), { value: 1, unit: 'MB' });
    assert.equal(parseTargetParam('huge'), null);
    assert.equal(parseTargetParam('0kb'), null);
    assert.equal(formatSize(49_800), '48.6 KB');
    assert.equal(formatSize(2_978_000), '2.84 MB');
});

for (const [kb, label] of [[10, '10 KB'], [20, '20 KB'], [30, '30 KB'], [50, '50 KB'], [100, '100 KB'], [200, '200 KB'], [500, '500 KB'], [1000, '1 MB']]) {
    test(`a large photo is brought under ${label}, never over it`, async () => {
        const { encode } = fakeEncoder();
        const limit = kb * 1000;
        const r = await optimize(photo, { limit, format: 'jpeg' }, encode);
        assert.equal(r.ok, true);
        assert.ok(r.blob.size <= limit, `${r.blob.size} > ${limit}`);
        assert.ok(r.attempts <= 40, `${r.attempts} encodes`);
    });
}

test('quality first: the dimensions are kept when a lower quality is enough', async () => {
    const { encode, calls, sizeAt } = fakeEncoder();
    const img = { width: 1200, height: 800, bytes: 900_000, format: 'jpeg', blob: { size: 900_000 } };
    const limit = 250_000; // fits at the 55% floor without resizing
    const r = await optimize(img, { limit, format: 'jpeg' }, encode);
    assert.equal(r.ok, true);
    assert.deepEqual([r.width, r.height, r.resized], [1200, 800, false]);
    assert.ok(calls.every((c) => c.w === 1200), 'never resized');
    // The highest quality that fits: one step higher would not fit
    assert.ok(sizeAt(1200, 800, r.quality) <= limit);
    assert.ok(sizeAt(1200, 800, r.quality + QUALITY.step) > limit || r.quality + QUALITY.step > QUALITY.max);
});

test('the dimensions are reduced only when the lowest quality still does not fit', async () => {
    const { encode, calls } = fakeEncoder();
    const r = await optimize(photo, { limit: 20_000, format: 'jpeg' }, encode);
    assert.equal(r.ok, true);
    assert.equal(r.resized, true);
    assert.ok(r.width < 4000 && Math.abs(r.width / r.height - 4 / 3) < 0.01, 'aspect ratio kept');
    assert.equal(calls[0].w, 4000, 'first tried at full size');
    assert.equal(calls[0].q, QUALITY.min, 'at the lowest quality before shrinking');
});

test('when shrinking is needed, the largest size that fits is kept (no overshoot)', async () => {
    const bpp = (q) => 0.04 + 1.1 * q ** 3;
    for (const limit of [20_000, 50_000, 200_000, 500_000]) {
        const { encode } = fakeEncoder();
        const r = await optimize(photo, { limit, format: 'jpeg' }, encode);
        // Largest 4:3 width that fits at the lowest quality: w * (3w/4) * bpp(min) + 700 <= limit
        const largest = Math.sqrt(((limit - 700) / bpp(QUALITY.min)) * (4 / 3));
        assert.ok(r.blob.size <= limit);
        assert.ok(r.width >= largest * 0.95, `${limit}: width ${r.width} vs largest ${Math.round(largest)}`);
    }
});

test('an impossible target fails honestly with the smallest result, never below the minimum size', async () => {
    const { encode, calls } = fakeEncoder({ overhead: 6000 });
    const r = await optimize(photo, { limit: 3000, format: 'jpeg' }, encode);
    assert.equal(r.ok, false);
    assert.equal(r.reason, 'target-unreachable');
    assert.ok(r.blob.size > 3000);
    assert.ok(calls.every((c) => Math.max(c.w, c.h) >= MIN_LONG_SIDE));
    assert.ok(r.blob.size === Math.min(...calls.map((c) => Math.round(c.w * c.h * (0.04 + 1.1 * c.q ** 3) + 6000))));
});

test('PNG is lossless: no quality search, only resizing, and it reports when PNG cannot reach the target', async () => {
    const { encode, calls } = fakeEncoder();
    const graphic = { width: 800, height: 600, bytes: 1_100_000, format: 'png', blob: { size: 1_100_000 } };
    const ok = await optimize(graphic, { limit: 500_000, format: 'png' }, encode);
    assert.equal(ok.ok, true);
    assert.equal(ok.quality, null);
    assert.ok(calls.every((c) => c.q === undefined));
    assert.ok(ok.blob.size <= 500_000 && ok.resized);

    const tiny = await optimize({ ...graphic, width: 4000, height: 3000 }, { limit: 5000, format: 'png' }, fakeEncoder().encode);
    assert.equal(tiny.ok, false);
});

test('an image already under the target in the same format is returned untouched', async () => {
    const { encode, calls } = fakeEncoder();
    const small = { width: 300, height: 200, bytes: 18_000, format: 'jpeg', blob: { size: 18_000, original: true } };
    const r = await optimize(small, { limit: 50_000, format: 'jpeg' }, encode);
    assert.equal(r.keptOriginal, true);
    assert.equal(r.blob.original, true);
    assert.equal(calls.length, 0);
    // A different output format, or a size limit, means it is re-encoded
    assert.equal((await optimize(small, { limit: 50_000, format: 'webp' }, encode)).keptOriginal, false);
    assert.equal((await optimize(small, { limit: 50_000, format: 'jpeg', maxWidth: 200 }, encode)).keptOriginal, false);
});

test('max width/height are respected, the aspect ratio is kept and images are never enlarged', () => {
    assert.deepEqual(baseSize(2400, 1600, { maxWidth: 800 }), { width: 800, height: 533, limitedByUser: true, limitedBySafety: false });
    assert.deepEqual(baseSize(2400, 1600, { maxHeight: 400 }), { width: 600, height: 400, limitedByUser: true, limitedBySafety: false });
    assert.deepEqual(baseSize(300, 200, { maxWidth: 800, maxHeight: 800 }), { width: 300, height: 200, limitedByUser: false, limitedBySafety: false });
    const huge = baseSize(12000, 9000);
    assert.ok(huge.width * huge.height <= SAFE_PIXELS && huge.limitedBySafety);
});

test('processing can be cancelled between encodes', async () => {
    const { encode } = fakeEncoder();
    let n = 0;
    await assert.rejects(optimize(photo, { limit: 20_000, format: 'jpeg' }, encode, { cancelled: () => ++n > 3 }), Cancelled);
});

test('"keep the original format" keeps JPEG, PNG and WebP', () => {
    assert.equal(keepFormat('png'), 'png');
    assert.equal(keepFormat('webp'), 'webp');
    assert.equal(keepFormat('jpeg'), 'jpeg');
});
