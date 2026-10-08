import { test } from 'node:test';
import assert from 'node:assert/strict';
import { customProfile, evaluate, evaluateAll, fileNotes, readiness } from '../src/check.js';
import { cropRect, orientation, parseRatio, ratioLabel } from '../src/geometry.js';
import { PROFILES, getProfile } from '../data/profiles.js';
import { img } from './helpers/fixtures.mjs';

const MB = 1024 * 1024;
const check = (result, id) => result.checks.find((c) => c.id === id || c.id.startsWith(`${id}-`));
const statusOf = (image, id) => evaluate(image, getProfile(id)).status;

test('shapes are named and parsed like people write them', () => {
    assert.equal(ratioLabel(1080, 1350), '4:5');
    assert.equal(ratioLabel(1200, 630), '1.91:1');
    assert.equal(ratioLabel(1920, 1080), '16:9');
    assert.equal(ratioLabel(1000, 1001), '1:1');
    assert.equal(ratioLabel(1234, 1000), '1.23:1');
    assert.equal(orientation(1080, 1080), 'square');
    assert.equal(orientation(1080, 1350), 'portrait');
    assert.equal(parseRatio('1.91:1'), 1.91);
    assert.equal(parseRatio('16:9'), 16 / 9);
    assert.equal(parseRatio('4/5'), 0.8);
    assert.equal(parseRatio('1.5'), 1.5);
    assert.equal(parseRatio('wide'), null);
    assert.equal(parseRatio('0:5'), null);
});

test('crop maths: none, horizontal and vertical, positioned by focus', () => {
    assert.equal(cropRect(1080, 1350, 0.8, 0.8).axis, null);
    const wide = cropRect(1920, 1080, 0.8, 0.8);
    assert.deepEqual([wide.axis, wide.w, wide.h, wide.x], ['width', 864, 1080, 528]);
    assert.ok(Math.abs(wide.removed - 0.55) < 0.001);
    assert.equal(cropRect(1920, 1080, 0.8, 0.8, { x: 0, y: 0.5 }).x, 0);
    assert.equal(cropRect(1920, 1080, 0.8, 0.8, { x: 1, y: 0.5 }).x, 1056);
    const tall = cropRect(1080, 1920, 1, 1);
    assert.deepEqual([tall.axis, tall.w, tall.h, tall.y], ['height', 1080, 1080, 420]);
    // Within a range: no crop
    assert.equal(cropRect(1200, 1000, 0.8, 1.91).axis, null);
});

test('Scenario A: a 1080 × 1350 JPEG fits portrait feeds and explains the crops', () => {
    const a = img(1080, 1350, { bytes: 2.4 * MB });
    const all = evaluateAll(a, PROFILES);
    assert.equal(statusOf(a, 'instagram-feed-portrait'), 'pass');
    assert.equal(statusOf(a, 'facebook-feed'), 'pass');
    const square = evaluate(a, getProfile('instagram-feed-square'));
    assert.equal(square.status, 'warning');
    assert.equal(square.headline, 'Works with cropping');
    assert.match(check(square, 'shape').detail, /taller than this format, so about 20% of the height will be cut off at the top and bottom/);
    assert.equal(check(evaluate(a, getProfile('instagram-feed-portrait')), 'resolution').title, 'Resolution: matches the recommended size');
    // YouTube thumbnail: different shape (bars/crop) is a warning, not a failure
    const yt = evaluate(a, getProfile('youtube-thumbnail'));
    assert.equal(yt.status, 'warning');
    assert.match(check(yt, 'shape').detail, /bars at the sides or cropped/);
    assert.ok(all.best.some((r) => r.profile.id === 'instagram-feed-portrait'));
    assert.ok(all.unsuitable.some((r) => r.profile.id === 'youtube-banner'));
});

test('Scenario B: a 720 × 1280 image fits vertical placements but warns about resolution', () => {
    const b = img(720, 1280);
    const story = evaluate(b, getProfile('instagram-story'));
    assert.equal(check(story, 'shape').status, 'pass');
    assert.equal(check(story, 'resolution').status, 'warning');
    assert.equal(check(story, 'resolution').actual, 'Your image is 720 px wide');
    assert.equal(story.status, 'warning');
    const banner = evaluate(b, getProfile('youtube-banner'));
    assert.equal(banner.status, 'fail');
    assert.equal(check(banner, 'minWidth').title, 'Your image is too narrow');
    assert.equal(check(banner, 'minWidth').expected, 'At least 2,048 px wide');
    assert.equal(check(banner, 'minWidth').actual, 'Your image: 720 px wide');
    assert.ok(check(banner, 'minWidth').action);
    assert.equal(check(banner, 'resolution'), undefined, 'no duplicate resolution message when the minimum fails');
});

test('Scenario C: a very large JPEG explains file-size problems per placement', () => {
    const c = img(6000, 4000, { bytes: 35 * MB });
    const banner = evaluate(c, getProfile('youtube-banner'));
    const size = banner.checks.find((x) => x.area === 'File size');
    assert.equal(size.status, 'fail');
    assert.equal(size.title, 'The file is too large');
    assert.equal(size.actual, 'Your file: 35 MB');
    assert.match(size.expected, /^At most 6\.0 MB/);
    assert.match(size.action, /Compress/);
    assert.equal(statusOf(c, 'linkedin-page-cover'), 'fail');
    assert.equal(statusOf(c, 'website-og'), 'fail');
    // Recommended limits warn instead of failing
    const story = evaluate(c, getProfile('instagram-story'));
    assert.equal(story.checks.find((x) => x.area === 'File size').status, 'warning');
    // YouTube thumbnail: under the 50 MB desktop limit, over the 2 MB mobile one
    const thumb = evaluate(img(1280, 720, { bytes: 12 * MB }), getProfile('youtube-thumbnail'));
    assert.deepEqual(thumb.checks.filter((x) => x.area === 'File size').map((x) => x.status), ['pass', 'warning']);
    const r = readiness(c, evaluateAll(c, PROFILES));
    assert.equal(r.items.find((i) => i.id === 'filesize').points, 0);
});

test('Scenario D: transparency is reported with placement-specific advice', () => {
    const d = img(1200, 630, { format: 'png', transparent: 'yes' });
    assert.equal(check(evaluate(d, getProfile('linkedin-post')), 'transparency').status, 'warning');
    assert.equal(check(evaluate(d, getProfile('website-og')), 'transparency').status, 'warning');
    assert.equal(check(evaluate(d, getProfile('website-content')), 'transparency').status, 'info');
    assert.equal(check(evaluate(img(1200, 630, { format: 'png', transparent: 'no' }), getProfile('website-content')), 'transparency'), undefined);
});

test('Scenario E: custom requirements run through the same engine', () => {
    const { profile, errors } = customProfile({ minWidth: '1200', minHeight: '630', ratio: '1.91:1', maxMB: '2', formats: ['jpeg', 'png'] });
    assert.deepEqual(errors, {});
    const ok = evaluate(img(1200, 630, { format: 'png', bytes: MB }), profile);
    assert.equal(ok.status, 'pass');
    const bad = evaluate(img(1000, 1000, { format: 'webp', bytes: 3 * MB }), profile);
    assert.equal(bad.status, 'fail');
    assert.deepEqual(bad.checks.filter((c) => c.status === 'fail').map((c) => c.area).sort(), ['File size', 'Format', 'Shape', 'Size']);
    assert.equal(check(bad, 'format').title, 'WebP files are not accepted here');

    assert.equal(customProfile({ ratio: 'wide' }).errors.ratio, 'Use a shape like 16:9, 4:5 or 1.91:1.');
    assert.equal(customProfile({}).errors.form, 'Enter at least one requirement.');
    assert.equal(customProfile({ minWidth: '2000', maxWidth: '1000' }).errors.maxWidth, 'Maximum width is smaller than the minimum.');
    const max = evaluate(img(3000, 1000), customProfile({ maxWidth: '2000' }).profile);
    assert.equal(check(max, 'maxWidth').title, 'Your image is too wide');
});

test('formats, orientations and exact matches across the profile set', () => {
    for (const [w, h] of [[1080, 1080], [1080, 1350], [1920, 1080]]) {
        for (const format of ['jpeg', 'png', 'webp']) {
            const all = evaluateAll(img(w, h, { format }), PROFILES);
            assert.equal(all.results.length, PROFILES.length);
            for (const r of all.results) assert.ok(['pass', 'warning', 'fail'].includes(r.status));
        }
    }
    // Every profile passes an image made exactly to its recommended size and format
    for (const p of PROFILES) {
        const exact = img(p.frame.width, p.frame.height, { format: p.formats.allowed[0], bytes: 50 * 1024 });
        assert.equal(evaluate(exact, p).status, 'pass', p.id);
    }
    // WebP on a JPG/PNG-only (recommended) placement is a warning, on a hard one a failure
    assert.equal(check(evaluate(img(1080, 1080, { format: 'webp' }), getProfile('instagram-feed-square')), 'format').status, 'warning');
    assert.equal(check(evaluate(img(1512, 256, { format: 'webp' }), getProfile('linkedin-page-cover')), 'format').status, 'fail');
});

test('readiness score: a summary with its own breakdown', () => {
    const good = img(1080, 1350, { bytes: MB });
    const r = readiness(good, evaluateAll(good, PROFILES));
    assert.equal(r.items.length, 4);
    assert.equal(r.score, r.items.reduce((s, i) => s + i.points, 0));
    assert.ok(r.score >= 60 && r.score <= 100);
    const tiny = img(300, 300, { format: 'webp', bytes: 25 * MB });
    assert.ok(readiness(tiny, evaluateAll(tiny, PROFILES)).score < 40);
});

test('file notes: CMYK, GPS location and EXIF rotation', () => {
    const ids = fileNotes({ cmyk: true, exif: { hasGps: true, orientation: 6 } }).map((n) => [n.id, n.status]);
    assert.deepEqual(ids, [['cmyk', 'warning'], ['gps', 'warning'], ['orientation', 'info']]);
    assert.deepEqual(fileNotes({ cmyk: false, exif: null }), []);
});

test('profile data is complete and never over-claims', () => {
    const ids = new Set();
    for (const p of PROFILES) {
        assert.ok(!ids.has(p.id), `duplicate ${p.id}`);
        ids.add(p.id);
        assert.ok(p.platform && p.placement && p.frame && p.ratio, p.id);
        assert.ok(p.sources?.length, `${p.id} has a source`);
        const frameRatio = p.frame.width / p.frame.height;
        assert.ok(frameRatio >= p.ratio.min * 0.99 && frameRatio <= p.ratio.max * 1.01, `${p.id} frame matches its shape`);
        const rules = [p.ratio, p.minWidth, p.minHeight, p.formats, ...(p.maxBytes || []), p.safeZone].filter(Boolean);
        for (const rule of rules) assert.ok(['hard', 'recommended', 'advisory'].includes(rule.level), `${p.id} level`);
        // A hard rule needs an official, linked source
        if (rules.some((rule) => rule.level === 'hard')) {
            assert.ok(p.sources.every((s) => s.url), `${p.id}: hard rules need an official source`);
        }
        if (p.safeZone) {
            const [l, t, rr, b] = p.safeZone.safe;
            assert.ok(l >= 0 && t >= 0 && rr <= 1 && b <= 1 && l < rr && t < b, `${p.id} safe zone`);
        }
    }
    assert.ok(!PROFILES.some((p) => /tiktok/i.test(p.platform)));
});
