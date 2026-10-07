// Dataset contract (spec 11): the committed dataset equals what the generator
// builds from the FINAL P0 data, and its key values match the P0 report.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import data from '../data/safezones.v1.js';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const P0 = process.env.P0_DIR || 'C:/Users/susan/Documents/P0-measurements';
const hasP0 = fs.existsSync(path.join(P0, 'data', 'measurements.json'));
const platform = (id) => data.platforms.find((p) => p.id === id);
const zone = (pid, zid) => platform(pid).zones.find((z) => z.id === zid);

test('JSON file and JS module hold the same dataset', () => {
    const json = JSON.parse(fs.readFileSync(path.join(HERE, '..', 'data', 'safezones.v1.json'), 'utf8'));
    assert.deepEqual(json, data);
});

test('committed dataset equals a fresh build from the P0 data', { skip: !hasP0 && 'P0 workspace not available' }, async () => {
    const { build } = await import('../scripts/generate-dataset.mjs');
    assert.deepEqual(JSON.parse(JSON.stringify(build(P0))), data);
});

test('every zone has provenance, a valid rect inside its frame, and a severity', () => {
    for (const p of data.platforms) {
        const sets = [[p.zones, data.frame], ...(p.tall ? [[p.tall.zones, p.tall.frame]] : [])];
        for (const [zones, frame] of sets) {
            for (const z of zones) {
                assert.ok(['measured', 'measured-one-phone', 'derived', 'provisional'].includes(z.provenance), `${z.id} provenance`);
                assert.ok(['hard', 'soft', 'info'].includes(z.severity), `${z.id} severity`);
                const [l, t, r, b] = z.rect;
                assert.ok(l >= 0 && t >= 0 && r <= frame.width && b <= frame.height && r > l && b > t, `${z.id} rect ${z.rect}`);
                for (const [d, rect] of Object.entries(z.byDevice || {})) assert.ok(p.devices.some((x) => x.id === d) && rect.length === 4, `${z.id} byDevice ${d}`);
                if (z.provenance === 'measured' || z.provenance === 'measured-one-phone') assert.ok(z.p0Rows.length > 0, `${z.id} traces to P0 rows`);
            }
        }
    }
});

test('key values match the FINAL P0 completion report (section 4 margins)', () => {
    // Instagram: top 190, right 160 (actions from x920), bottom 220 (caption from y1700), CC band y1590-1700
    assert.deepEqual(zone('instagram-reels', 'ig-top').rect, [0, 0, 1080, 190]);
    assert.deepEqual(zone('instagram-reels', 'ig-actions').rect, [920, 1120, 1080, 1870]);
    assert.equal(zone('instagram-reels', 'ig-caption-c1').rect[1], 1700);
    assert.deepEqual(zone('instagram-reels', 'ig-platform-captions').rect, [90, 1590, 670, 1700]);
    assert.deepEqual(zone('instagram-reels', 'ig-expanded-caption').rect, [0, 1170, 890, 1900]);
    assert.deepEqual(zone('instagram-reels', 'ig-audio').rect, [910, 1700, 1080, 1900]);
    // YouTube: top 180, right 170 (actions from x910), bottom 190 (caption from y1730), CC band y260-380
    assert.deepEqual(zone('youtube-shorts', 'yt-top').rect, [0, 0, 1080, 180]);
    assert.deepEqual(zone('youtube-shorts', 'yt-actions').rect, [910, 1060, 1080, 1880]);
    assert.equal(zone('youtube-shorts', 'yt-caption-c1').rect[1], 1730);
    assert.deepEqual(zone('youtube-shorts', 'yt-platform-captions').rect, [150, 260, 650, 380]);
});

test('side crop equals the P0 visible windows of the 9:16 posts', () => {
    for (const p of data.platforms.filter((x) => x.status === 'measured')) {
        for (const d of p.devices) {
            const [l, , r] = d.visibleWindow;
            const left = p.zones.find((z) => z.id.endsWith('side-crop-left'));
            const right = p.zones.find((z) => z.id.endsWith('side-crop-right'));
            if (l >= 5) assert.equal(left.byDevice[d.id][2], l, `${p.id} ${d.id} left`);
            else assert.ok(!left || !left.byDevice[d.id], `${p.id} ${d.id} has no left crop`);
            if (1080 - r >= 5) assert.equal(right.byDevice[d.id][0], r, `${p.id} ${d.id} right`);
        }
    }
    assert.deepEqual(zone('instagram-reels', 'ig-side-crop-left').rect, [0, 0, 54, 1920]);
    assert.deepEqual(zone('youtube-shorts', 'yt-side-crop-left').rect, [0, 0, 55, 1920]);
    assert.deepEqual(zone('youtube-shorts', 'yt-side-crop-right').rect, [1027, 0, 1080, 1920]);
});

test('TikTok is provisional throughout and has no device data', () => {
    const tt = platform('tiktok');
    assert.equal(tt.status, 'provisional');
    assert.equal(tt.devices.length, 0);
    for (const z of tt.zones) assert.equal(z.provenance, 'provisional');
    assert.ok(tt.notes.some((n) => /provisional/i.test(n)));
});

test('no overrides exist without a reason', () => {
    for (const p of data.platforms) for (const o of p.overrides) assert.ok(o.reason && o.reason.length > 10, `${p.id} override ${o.zone}`);
});

test('dataset records its version and source', () => {
    assert.match(data.datasetVersion, /^\d+\.\d+\.\d+$/);
    assert.match(data.generatedFrom.measurementsSha1, /^[0-9a-f]{40}$/);
    assert.equal(data.references.length, 2);
    for (const r of data.references) assert.equal(r.context, 'ads');
});
