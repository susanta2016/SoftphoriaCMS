// Links per-frame detections into elements that persist over time (spec 5.2).
import { MIN_TRACK_S, SAMPLE_INTERVAL_S } from './constants.js';
import { iou, median } from './geometry.js';

const MATCH_IOU = 0.5;
const MAX_GAP_SAMPLES = 1; // a box may vanish for one sample (blur, flicker) and still continue

/**
 * @param {Array<{t:number, boxes:Array<{rect:number[], confidence:number}>}>} frames  in time order, rects in reference px
 * @param {{ intervalS?: number, minTrackS?: number, isImage?: boolean }} opts
 * @returns {Array} elements: { id, type:'other', source:'auto', confidence, rect, fromS, toS, samples }
 */
export function trackDetections(frames, opts = {}) {
    const intervalS = opts.intervalS ?? SAMPLE_INTERVAL_S;
    const minTrackS = opts.minTrackS ?? MIN_TRACK_S;
    const tracks = [];
    frames.forEach((frame, fi) => {
        const live = tracks.filter((t) => fi - t.lastIndex <= MAX_GAP_SAMPLES + 1);
        const taken = new Set();
        const pairs = [];
        frame.boxes.forEach((b, bi) => live.forEach((t) => {
            const v = iou(b.rect, t.samples[t.samples.length - 1].rect);
            if (v >= MATCH_IOU) pairs.push({ v, b, bi, t });
        }));
        pairs.sort((x, y) => y.v - x.v);
        const used = new Set();
        for (const p of pairs) {
            if (taken.has(p.bi) || used.has(p.t)) continue;
            taken.add(p.bi);
            used.add(p.t);
            p.t.samples.push({ t: frame.t, rect: p.b.rect, confidence: p.b.confidence });
            p.t.lastIndex = fi;
        }
        frame.boxes.forEach((b, bi) => {
            if (!taken.has(bi)) tracks.push({ samples: [{ t: frame.t, rect: b.rect, confidence: b.confidence }], firstIndex: fi, lastIndex: fi });
        });
    });

    const out = [];
    for (const t of tracks) {
        const fromS = t.samples[0].t;
        const toS = t.samples[t.samples.length - 1].t + intervalS;
        // must be seen in at least two samples spanning >= minTrackS (one sample is a passing detail)
        if (!opts.isImage && (t.samples.length < 2 || t.samples[t.samples.length - 1].t - fromS < minTrackS)) continue;
        const rect = [0, 1, 2, 3].map((k) => Math.round(median(t.samples.map((s) => s.rect[k]))));
        const confidence = Math.round((t.samples.reduce((a, s) => a + s.confidence, 0) / t.samples.length) * 100) / 100;
        out.push({ type: 'other', source: 'auto', confidence, rect, fromS, toS, samples: t.samples.map((s) => ({ t: s.t, rect: s.rect })) });
    }
    out.sort((a, b) => a.rect[1] - b.rect[1] || a.rect[0] - b.rect[0]);
    out.forEach((e, i) => { e.id = `a${i + 1}`; });
    return out;
}
