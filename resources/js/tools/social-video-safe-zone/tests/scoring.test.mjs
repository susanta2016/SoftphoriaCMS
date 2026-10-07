import { test } from 'node:test';
import assert from 'node:assert/strict';
import data from '../data/safezones.v1.js';
import { resolveZones } from '../src/dataset.js';
import { evaluatePlatform } from '../src/scoring.js';
import { overlaps, translate, scaleAboutCentre } from '../src/geometry.js';

const settings = { aspect: '9:16', caption: 'C1', cc: false, soundLine: false, device: 'all' };
const resolve = (platformId, extra = {}) => resolveZones(data, { ...settings, platformId, ...extra });
const video = { kind: 'video', durationS: 24.5, sampleIntervalS: 0.5 };
const user = (id, type, rect, extra = {}) => ({ id, type, source: 'user', confidence: 1, rect, fromS: 0, toS: 24.5, ...extra });

test('spec worked example: Instagram C1 headline scores 45, Fail, critical, fix clears every hard zone', () => {
    const r = resolve('instagram-reels');
    const res = evaluatePlatform(r, [user('e1', 'text', [100, 1650, 980, 1780])], video);
    assert.equal(res.score, 45);
    assert.equal(res.verdict, 'fail');
    const issue = res.issues.find((i) => i.element === 'e1');
    assert.equal(issue.severity, 'critical');
    assert.equal(issue.zone, 'ig-caption-c1');
    assert.equal(issue.coverage, 0.552);
    // The spec text said "up 80 px, in 60 px"; with the iPhone side-crop zone (x0-54)
    // that move is blocked, so the smallest fix is a 2% shrink plus a smaller move.
    assert.deepEqual(issue.fix, { dx: -52, dy: -79, scale: 0.98 });
    const fixed = translate(scaleAboutCentre([100, 1650, 980, 1780], issue.fix.scale), issue.fix.dx, issue.fix.dy);
    for (const z of r.zones.filter((x) => x.severity === 'hard')) assert.ok(!overlaps(fixed, z.rect), `fix clears ${z.id}`);
});

test('a well-placed element passes with score 100', () => {
    const res = evaluatePlatform(resolve('instagram-reels'), [user('e1', 'text', [200, 600, 800, 800])], video);
    assert.equal(res.score, 100);
    assert.equal(res.verdict, 'pass');
    assert.equal(res.issues.length, 0);
});

test('TikTok never returns Pass', () => {
    const res = evaluatePlatform(resolve('tiktok'), [user('e1', 'text', [200, 600, 800, 800])], video);
    assert.equal(res.score, 100);
    assert.equal(res.verdict, 'needs-review');
});

test('YouTube 16:9 fails as not a Short; 4:5 and 1:1 are not scored', () => {
    const yt = evaluatePlatform(resolve('youtube-shorts', { aspect: '16:9' }), [], video);
    assert.equal(yt.verdict, 'fail');
    assert.equal(yt.score, null);
    assert.ok(yt.issues[0].severity === 'critical' && /will not appear as a Short/.test(yt.issues[0].message));
    for (const aspect of ['4:5', '1:1']) {
        const r = evaluatePlatform(resolve('instagram-reels', { aspect }), [], video);
        assert.equal(r.verdict, 'not-scored');
        assert.equal(r.issues[0].severity, 'warning');
    }
    assert.equal(evaluatePlatform(resolve('instagram-reels', { aspect: '16:9' }), [], video).verdict, 'not-scored');
});

test('severity rules: small hard overlap warns, tiny overlap is a notice, soft zones warn', () => {
    const r = resolve('instagram-reels');
    // 17% inside the action column: warning
    const warn = evaluatePlatform(r, [user('e1', 'text', [820, 1200, 940, 1300])], video);
    assert.equal(warn.issues[0].severity, 'warning');
    assert.equal(warn.verdict, 'needs-review');
    // under 5%: notice, verdict unaffected by it
    const tiny = evaluatePlatform(r, [user('e1', 'text', [500, 191 - 4, 800, 400])], video);
    assert.equal(tiny.issues[0].severity, 'notice');
    // soft zone (expanded caption) only
    const soft = evaluatePlatform(resolve('instagram-reels', { caption: 'C3E' }), [user('e1', 'text', [100, 1300, 500, 1400])], video);
    assert.equal(soft.issues[0].severity, 'warning');
    assert.equal(soft.issues[0].code, 'covered-soft');
    assert.equal(soft.issues[0].fix, null);
});

test('proximity: within 16 px of a hard zone without touching gives a notice', () => {
    const r = resolve('instagram-reels');
    const near = evaluatePlatform(r, [user('e1', 'text', [300, 1600, 700, 1690])], video); // 10 px above the caption
    assert.equal(near.issues[0].code, 'proximity');
    assert.equal(near.issues[0].severity, 'notice');
    assert.equal(near.verdict, 'pass');
    const far = evaluatePlatform(r, [user('e1', 'text', [300, 1600, 700, 1680])], video); // 20 px away
    assert.equal(far.issues.length, 0);
});

test('critical needs 1 s: a brief overlap of important text is only a warning', () => {
    const r = resolve('instagram-reels');
    const samples = [
        { t: 0, rect: [300, 1720, 700, 1800] }, // covered by the caption
        { t: 0.5, rect: [300, 800, 700, 880] },
        { t: 1.0, rect: [300, 800, 700, 880] },
        { t: 1.5, rect: [300, 800, 700, 880] },
    ];
    const res = evaluatePlatform(r, [user('e1', 'text', [300, 800, 700, 880], { samples, fromS: 0, toS: 2 })], { ...video, durationS: 2 });
    const issue = res.issues.find((i) => i.element === 'e1');
    assert.equal(issue.severity, 'warning');
    assert.equal(issue.timeShare, 0.25);
});

test('detections: unlabelled auto boxes weigh 0.5 x confidence; low confidence is only a notice', () => {
    const r = resolve('instagram-reels');
    const auto = (confidence) => ({ id: 'a1', type: 'other', source: 'auto', confidence, rect: [100, 1700, 500, 1800], fromS: 0, toS: 24.5 });
    const hi = evaluatePlatform(r, [auto(0.8)], video);
    assert.equal(hi.issues[0].severity, 'warning');
    assert.equal(hi.score, Math.round(100 * (1 - 0.5 * 0.8 * 1)));
    const lo = evaluatePlatform(r, [auto(0.4)], video);
    assert.equal(lo.issues[0].severity, 'notice');
});

test('product of element risks, each capped at 0.9', () => {
    const r = resolve('instagram-reels');
    const covered = [100, 1720, 500, 1800]; // fully inside the C1 caption zone
    const one = evaluatePlatform(r, [user('e1', 'text', covered)], video);
    assert.equal(one.score, 10); // risk 1.0 capped at 0.9
    const two = evaluatePlatform(r, [user('e1', 'text', covered), user('e2', 'logo', [930, 1200, 1000, 1260])], video);
    assert.equal(two.score, Math.round(100 * 0.1 * (1 - 0.8)));
    assert.equal(two.verdict, 'fail');
});

test('images count as always on screen; low resolution adds a notice only', () => {
    const r = resolve('youtube-shorts');
    const res = evaluatePlatform(r, [user('e1', 'face', [400, 1740, 600, 1860], { fromS: undefined, toS: undefined })], { kind: 'image', lowResolution: true });
    assert.equal(res.issues.find((i) => i.element === 'e1').severity, 'critical');
    assert.ok(res.issues.some((i) => i.code === 'low-resolution' && i.severity === 'notice'));
});

test('9:19.5 uploads are scored against the tall zones including masked strips', () => {
    const r = resolve('instagram-reels', { aspect: '9:19.5' });
    const res = evaluatePlatform(r, [user('e1', 'text', [950, 2200, 1050, 2300])], { kind: 'image' }); // only in the masked bottom strip
    assert.equal(res.verdict, 'fail');
    assert.equal(res.issues.find((i) => i.element === 'e1').zoneKind, 'masked');
});
