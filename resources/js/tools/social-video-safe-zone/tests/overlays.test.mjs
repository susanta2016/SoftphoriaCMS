// Downloadable overlay PNGs (SEO guides): mandatory drift check. The committed
// PNGs must equal, pixel for pixel, what scripts/generate-overlays.mjs builds
// from the checker's dataset, and the margins the guide pages publish must be
// the ones that dataset yields. If either fails, regenerate the PNGs and
// re-check the guide page copy before publishing.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { OUT_DIR, decodePng, drift, encodePng, observedMonth, overlaySpecs } from '../scripts/generate-overlays.mjs';

const EXPECTED_FILES = [
    'softphoria-instagram-reels-safe-zone-1080x1920.png',
    'softphoria-instagram-reels-safe-zone-1080x1920-preview.png',
    'softphoria-youtube-shorts-safe-zone-1080x1920.png',
    'softphoria-youtube-shorts-safe-zone-1080x1920-preview.png',
    'softphoria-reels-shorts-combined-safe-zone-1080x1920.png',
    'softphoria-reels-shorts-combined-safe-zone-1080x1920-preview.png',
];

test('committed overlay PNGs match a fresh build from the dataset (no drift)', () => {
    assert.deepEqual(drift(), []);
    assert.deepEqual(fs.readdirSync(OUT_DIR).filter((f) => f.endsWith('.png')).sort(), [...EXPECTED_FILES].sort());
});

test('margins published on the guide pages are the ones the dataset yields', () => {
    const byName = Object.fromEntries(overlaySpecs().map((s) => [s.name, s]));
    assert.deepEqual(byName['softphoria-instagram-reels-safe-zone-1080x1920'].margins, { top: 190, right: 160, bottom: 220 });
    assert.deepEqual(byName['softphoria-youtube-shorts-safe-zone-1080x1920'].margins, { top: 180, right: 170, bottom: 190 });
    assert.deepEqual(byName['softphoria-reels-shorts-combined-safe-zone-1080x1920'].margins, { top: 190, right: 170, bottom: 220 });
    assert.equal(observedMonth(), 'October 2026');
});

test('the combined overlay uses measured Reels and Shorts zones only, never TikTok', () => {
    const combined = overlaySpecs().find((s) => s.name.includes('combined'));
    assert.deepEqual(combined.platforms, ['instagram-reels', 'youtube-shorts']);
    assert.ok(combined.zones.every((z) => z.id.startsWith('ig-') || z.id.startsWith('yt-')));
    assert.ok(combined.zones.every((z) => z.provenance !== 'provisional'));
});

test('overlays are 1080 x 1920 with a transparent centre; previews are half size and opaque', () => {
    const overlay = decodePng(fs.readFileSync(path.join(OUT_DIR, EXPECTED_FILES[0])));
    assert.equal(overlay.width, 1080);
    assert.equal(overlay.height, 1920);
    assert.equal(overlay.rgba[(700 * 1080 + 400) * 4 + 3], 0, 'safe area is see-through');
    const preview = decodePng(fs.readFileSync(path.join(OUT_DIR, EXPECTED_FILES[1])));
    assert.equal(preview.width, 540);
    assert.equal(preview.height, 960);
    assert.ok(preview.rgba.every((v, i) => i % 4 !== 3 || v === 255));
});

test('the PNG encoder round-trips', () => {
    const rgba = new Uint8Array([255, 0, 0, 255, 0, 255, 0, 128, 0, 0, 255, 0, 10, 20, 30, 40]);
    assert.deepEqual(decodePng(encodePng(2, 2, rgba)).rgba, rgba);
});
