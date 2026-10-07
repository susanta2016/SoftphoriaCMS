// Recall / precision of the detector on the synthetic set (IoU >= 0.5 match).
import { detect } from '../../src/detector-core.js';
import { iou } from '../../src/geometry.js';
import { syntheticSet } from './synth.mjs';

export function evaluateDetector({ aw = 270, options = {}, iouMin = 0.5, seedBase = 1000, count = 30 } = {}) {
    const set = syntheticSet(aw, seedBase, count);
    const k = 1080 / aw;
    let truths = 0, matchedTruths = 0, detections = 0, matchedDetections = 0;
    const misses = [], falsePositives = [];
    for (const f of set) {
        const boxes = detect(f.gray, f.width, f.height, options).map((b) => ({ ...b, rectRef: b.rect.map((v) => Math.round(v * k)) }));
        detections += boxes.length;
        truths += f.truths.length;
        const usedB = new Set();
        for (const t of f.truths) {
            let best = -1, bestV = 0;
            boxes.forEach((b, bi) => { const v = iou(t.rectRef, b.rectRef); if (v > bestV && !usedB.has(bi)) { bestV = v; best = bi; } });
            if (best >= 0 && bestV >= iouMin) { usedB.add(best); matchedTruths++; } else misses.push({ frame: f.id, kind: f.kind, type: t.type, sizeRef: t.sizeRef, bestIou: Math.round(bestV * 100) / 100 });
        }
        matchedDetections += usedB.size;
        boxes.forEach((b, bi) => { if (!usedB.has(bi)) falsePositives.push({ frame: f.id, kind: f.kind, rectRef: b.rectRef, confidence: b.confidence }); });
    }
    return {
        recall: truths ? matchedTruths / truths : 1,
        precision: detections ? matchedDetections / detections : 1,
        truths, detections, misses, falsePositives,
    };
}
