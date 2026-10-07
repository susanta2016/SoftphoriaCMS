import { test } from 'node:test';
import assert from 'node:assert/strict';
import data from '../data/safezones.v1.js';
import { resolveZones } from '../src/dataset.js';
import { describeFix, suggestFix } from '../src/fixes.js';
import { inside, overlaps } from '../src/geometry.js';
import { prng } from './helpers/synth.mjs';

const frame = { width: 1080, height: 1920 };

test('returns null when the element already clears every zone', () => {
    assert.equal(suggestFix([200, 600, 800, 800], [{ rect: [0, 0, 1080, 190] }], frame), null);
});

test('simple vertical move', () => {
    const fix = suggestFix([300, 1650, 700, 1750], [{ rect: [0, 1700, 1080, 1920] }], frame);
    assert.deepEqual(fix, { dx: 0, dy: -50, scale: 1, rect: [300, 1600, 700, 1700] });
    assert.equal(describeFix(fix), 'Move up 50 px');
});

test('impossible when no position or shrink down to 50% clears the zones', () => {
    const fix = suggestFix([0, 0, 1080, 1920], [{ rect: [0, 0, 1080, 1000] }, { rect: [0, 1000, 1080, 1920] }], frame);
    assert.deepEqual(fix, { impossible: true });
    assert.match(describeFix(fix), /No position/);
});

test('property: every suggested fix clears all hard zones and stays inside the frame (all presets)', () => {
    const rnd = prng(42);
    let checked = 0;
    for (const platformId of ['instagram-reels', 'youtube-shorts', 'tiktok']) {
        for (const caption of ['C0', 'C1', 'C2', 'C3', 'C3E']) {
            for (const cc of [false, true]) {
                for (const device of ['all', 'ios', 'android']) {
                    const r = resolveZones(data, { platformId, aspect: '9:16', caption, cc, soundLine: true, device });
                    const hard = r.zones.filter((z) => z.severity === 'hard');
                    for (let i = 0; i < 25; i++) {
                        const w = 40 + rnd() * 700, h = 30 + rnd() * 400;
                        const x = rnd() * (1080 - w), y = rnd() * (1920 - h);
                        const rect = [x, y, x + w, y + h];
                        const fix = suggestFix(rect, hard, r.frame);
                        if (!fix || fix.impossible) continue;
                        assert.ok(inside(fix.rect, r.frame), `inside ${platformId} ${caption}`);
                        for (const z of hard) assert.ok(!overlaps(fix.rect, z.rect), `${platformId} ${caption} cc=${cc} ${device}: clears ${z.id}`);
                        checked++;
                    }
                }
            }
        }
    }
    assert.ok(checked > 500, `checked ${checked} fixes`);
});

test('describeFix wording', () => {
    assert.equal(describeFix({ dx: -52, dy: -79, scale: 0.98 }), 'Shrink to 98%, move up 79 px, left 52 px');
    assert.equal(describeFix({ dx: 30, dy: 0, scale: 1 }), 'Move right 30 px');
    assert.equal(describeFix(null), '');
});
