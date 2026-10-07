import { test } from 'node:test';
import assert from 'node:assert/strict';
import { area, coverage, gap, inside, intersect, iou, median, normalize, overlaps, scaleAboutCentre, translate, union } from '../src/geometry.js';
import { classifyAspect, isLowResolution, referenceFrame } from '../src/aspect.js';

test('area, intersect and overlaps use exclusive right/bottom edges', () => {
    const cases = [
        { a: [0, 0, 10, 10], b: [10, 0, 20, 10], i: null }, // touching, no overlap
        { a: [0, 0, 10, 10], b: [5, 5, 15, 15], i: [5, 5, 10, 10] },
        { a: [0, 0, 10, 10], b: [2, 2, 4, 4], i: [2, 2, 4, 4] }, // contained
        { a: [0, 0, 10, 10], b: [20, 20, 30, 30], i: null },
        { a: [0, 0, 0, 10], b: [0, 0, 10, 10], i: null }, // zero width
    ];
    for (const c of cases) {
        assert.deepEqual(intersect(c.a, c.b), c.i, JSON.stringify(c));
        assert.equal(overlaps(c.a, c.b), c.i !== null);
    }
    assert.equal(area([0, 0, 10, 5]), 50);
    assert.equal(area([5, 5, 0, 0]), 0);
});

test('coverage is the share of the element inside the zone', () => {
    const cases = [
        [[0, 0, 10, 10], [0, 0, 10, 10], 1],
        [[0, 0, 10, 10], [0, 5, 10, 20], 0.5],
        [[0, 0, 10, 10], [10, 0, 20, 10], 0],
        [[0, 0, 0, 0], [0, 0, 10, 10], 0],
        [[100, 1650, 980, 1780], [0, 1700, 890, 1900], (790 * 80) / (880 * 130)],
        [[0, 0, 100, 100], [-50, -50, 50, 50], 0.25],
    ];
    for (const [el, z, want] of cases) assert.ok(Math.abs(coverage(el, z) - want) < 1e-9, `${el} in ${z}`);
});

test('gap is euclidean and zero for touching or overlapping rects', () => {
    assert.equal(gap([0, 0, 10, 10], [10, 0, 20, 10]), 0);
    assert.equal(gap([0, 0, 10, 10], [5, 5, 20, 20]), 0);
    assert.equal(gap([0, 0, 10, 10], [20, 0, 30, 10]), 10);
    assert.equal(gap([0, 0, 10, 10], [0, 26, 10, 30]), 16);
    assert.equal(gap([0, 0, 10, 10], [13, 14, 20, 20]), 5);
});

test('union, translate, scale, inside, iou, normalize, median', () => {
    assert.deepEqual(union([[0, 0, 5, 5], [3, -2, 10, 4]]), [0, -2, 10, 5]);
    assert.deepEqual(translate([0, 0, 10, 10], -3, 4), [-3, 4, 7, 14]);
    assert.deepEqual(scaleAboutCentre([0, 0, 100, 50], 0.5), [25, 12.5, 75, 37.5]);
    assert.equal(inside([0, 0, 1080, 1920], { width: 1080, height: 1920 }), true);
    assert.equal(inside([-1, 0, 10, 10], { width: 1080, height: 1920 }), false);
    assert.equal(iou([0, 0, 10, 10], [0, 0, 10, 10]), 1);
    assert.ok(Math.abs(iou([0, 0, 10, 10], [5, 0, 15, 10]) - 1 / 3) < 1e-9);
    assert.deepEqual(normalize([10, 20, 0, 5]), [0, 5, 10, 20]);
    assert.equal(median([3, 1, 2]), 2);
    assert.equal(median([4, 1, 2, 3]), 2.5);
});

test('aspect classification within 1% tolerance', () => {
    const cases = [
        [1080, 1920, '9:16'], [720, 1280, '9:16'], [1080, 1910, '9:16'], [1080, 1900, 'other'], [1080, 1950, 'other'],
        [1080, 2340, '9:19.5'], [1080, 1350, '4:5'], [1080, 1080, '1:1'], [1920, 1080, '16:9'],
        [1080, 1440, 'other'], [0, 100, 'other'],
    ];
    for (const [w, h, want] of cases) assert.equal(classifyAspect(w, h), want, `${w}x${h}`);
    assert.deepEqual(referenceFrame(720, 1280), { width: 1080, height: 1920 });
    assert.deepEqual(referenceFrame(1080, 2340), { width: 1080, height: 2340 });
    assert.equal(isLowResolution(720, 1280), false);
    assert.equal(isLowResolution(540, 960), true);
    assert.equal(isLowResolution(1280, 720), false);
});
