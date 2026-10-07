import { test } from 'node:test';
import assert from 'node:assert/strict';
import data from '../data/safezones.v1.js';
import { resolveZones } from '../src/dataset.js';

const ids = (r) => r.zones.map((z) => z.id).sort();
const base = { aspect: '9:16', caption: 'C1', cc: false, soundLine: false, device: 'all' };

test('Instagram C1, CC off: hard zones for top, actions, caption C1, audio, side crop; soft progress', () => {
    const r = resolveZones(data, { ...base, platformId: 'instagram-reels' });
    assert.equal(r.mode, 'scored');
    assert.deepEqual(ids(r), ['ig-actions', 'ig-audio', 'ig-caption-c1', 'ig-progress', 'ig-side-crop-left', 'ig-side-crop-right', 'ig-top']);
});

test('caption length switches the caption zone; C3E adds the soft expanded zones', () => {
    for (const [cap, want] of [['C0', 'ig-caption-c0'], ['C2', 'ig-caption-c2'], ['C3', 'ig-caption-c3']]) {
        const r = resolveZones(data, { ...base, platformId: 'instagram-reels', caption: cap });
        assert.ok(ids(r).includes(want) && ids(r).filter((x) => x.startsWith('ig-caption')).length === 1, cap);
    }
    const e = resolveZones(data, { ...base, platformId: 'instagram-reels', caption: 'C3E' });
    assert.ok(ids(e).includes('ig-caption-c3') && ids(e).includes('ig-expanded-caption') && ids(e).includes('ig-expanded-other'));
    assert.equal(e.zones.find((z) => z.id === 'ig-expanded-caption').severity, 'soft');
});

test('YouTube maps C3 to the C2 zone and C3E to C3 with a notice', () => {
    const r = resolveZones(data, { ...base, platformId: 'youtube-shorts', caption: 'C3' });
    assert.ok(ids(r).includes('yt-caption-c2'));
    const e = resolveZones(data, { ...base, platformId: 'youtube-shorts', caption: 'C3E' });
    assert.ok(ids(e).includes('yt-caption-c2'));
    assert.ok(e.notices.some((n) => n.code === 'caption-mapped'));
});

test('CC adds the auto-caption band; sound line only on YouTube', () => {
    assert.ok(ids(resolveZones(data, { ...base, platformId: 'instagram-reels', cc: true })).includes('ig-platform-captions'));
    const yt = resolveZones(data, { ...base, platformId: 'youtube-shorts', cc: true, soundLine: true });
    assert.ok(ids(yt).includes('yt-platform-captions') && ids(yt).includes('yt-sound-line'));
    const ig = resolveZones(data, { ...base, platformId: 'instagram-reels', soundLine: true });
    assert.ok(!ids(ig).some((x) => x.includes('sound')));
});

test('device views use each phone\'s own rects and drop zones that phone lacks', () => {
    const ios = resolveZones(data, { ...base, platformId: 'instagram-reels', device: 'ios' });
    assert.deepEqual(ios.zones.find((z) => z.id === 'ig-actions').rect, [920, 1400, 1080, 1870]);
    assert.ok(!ids(ios).includes('ig-audio')); // audio tile: Android only
    assert.ok(ids(ios).includes('ig-side-crop-left'));
    const and = resolveZones(data, { ...base, platformId: 'instagram-reels', device: 'android' });
    assert.ok(ids(and).includes('ig-audio'));
    assert.ok(!ids(and).includes('ig-side-crop-left')); // Nokia shows the full width
    const andCc = resolveZones(data, { ...base, platformId: 'instagram-reels', device: 'android', cc: true });
    assert.ok(!ids(andCc).includes('ig-platform-captions')); // not available on the Android test phone
    const ytAnd = resolveZones(data, { ...base, platformId: 'youtube-shorts', device: 'android' });
    assert.deepEqual(ytAnd.zones.find((z) => z.id === 'yt-side-crop-left').rect, [0, 0, 43, 1920]);
});

test('TikTok always uses the provisional union and says so', () => {
    const r = resolveZones(data, { ...base, platformId: 'tiktok', device: 'ios', cc: true });
    assert.equal(r.device, 'all');
    assert.ok(r.zones.every((z) => z.provenance === 'provisional'));
    assert.ok(r.notices.some((n) => n.code === 'provisional'));
    assert.ok(r.notices.some((n) => n.code === 'cc-not-available'));
});

test('presentation modes by aspect ratio', () => {
    const mode = (platformId, aspect) => resolveZones(data, { ...base, platformId, aspect }).mode;
    assert.equal(mode('youtube-shorts', '16:9'), 'not-a-short');
    assert.equal(mode('instagram-reels', '16:9'), 'preview-only');
    assert.equal(mode('instagram-reels', '4:5'), 'preview-only');
    assert.equal(mode('youtube-shorts', '1:1'), 'preview-only');
    assert.equal(mode('instagram-reels', 'other'), 'preview-only');
    assert.equal(mode('tiktok', '9:19.5'), 'preview-only');
    const tall = resolveZones(data, { ...base, platformId: 'instagram-reels', aspect: '9:19.5', caption: 'C2', cc: true });
    assert.equal(tall.mode, 'scored-tall');
    assert.deepEqual(tall.frame, { width: 1080, height: 2340, aspect: '9:19.5' });
    assert.ok(ids(tall).includes('ig-tall-masked-top') && ids(tall).includes('ig-tall-masked-bottom'));
    assert.ok(tall.notices.some((n) => n.code === 'tall-caption') && tall.notices.some((n) => n.code === 'tall-no-extras'));
    assert.equal(resolveZones(data, { ...base, platformId: 'instagram-reels', aspect: '4:5' }).zones.length, 0);
});
