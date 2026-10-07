// Rectangles are [left, top, right, bottom] in reference-frame pixels, right and
// bottom exclusive (a rect that ends at 1700 and one that starts at 1700 touch
// but do not overlap).

export const width = (r) => Math.max(0, r[2] - r[0]);
export const height = (r) => Math.max(0, r[3] - r[1]);
export const area = (r) => width(r) * height(r);

export function intersect(a, b) {
    const r = [Math.max(a[0], b[0]), Math.max(a[1], b[1]), Math.min(a[2], b[2]), Math.min(a[3], b[3])];
    return r[2] > r[0] && r[3] > r[1] ? r : null;
}

export const overlaps = (a, b) => intersect(a, b) !== null;

// Share of `el` that lies inside `zone`, 0..1.
export function coverage(el, zone) {
    const a = area(el);
    if (a === 0) return 0;
    const i = intersect(el, zone);
    return i ? area(i) / a : 0;
}

// Euclidean distance between two rects; 0 when they touch or overlap.
export function gap(a, b) {
    const dx = Math.max(b[0] - a[2], a[0] - b[2], 0);
    const dy = Math.max(b[1] - a[3], a[1] - b[3], 0);
    return Math.hypot(dx, dy);
}

export function union(rects) {
    return [
        Math.min(...rects.map((r) => r[0])),
        Math.min(...rects.map((r) => r[1])),
        Math.max(...rects.map((r) => r[2])),
        Math.max(...rects.map((r) => r[3])),
    ];
}

export const translate = (r, dx, dy) => [r[0] + dx, r[1] + dy, r[2] + dx, r[3] + dy];

export function scaleAboutCentre(r, s) {
    const cx = (r[0] + r[2]) / 2, cy = (r[1] + r[3]) / 2;
    const hw = (width(r) * s) / 2, hh = (height(r) * s) / 2;
    return [cx - hw, cy - hh, cx + hw, cy + hh];
}

export const inside = (r, frame) => r[0] >= 0 && r[1] >= 0 && r[2] <= frame.width && r[3] <= frame.height;

export function iou(a, b) {
    const i = intersect(a, b);
    if (!i) return 0;
    const ia = area(i);
    return ia / (area(a) + area(b) - ia);
}

export function normalize(r) {
    return [Math.min(r[0], r[2]), Math.min(r[1], r[3]), Math.max(r[0], r[2]), Math.max(r[1], r[3])];
}

export const roundRect = (r) => r.map((v) => Math.round(v));

export function median(values) {
    const s = [...values].sort((x, y) => x - y);
    const m = Math.floor(s.length / 2);
    return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
}
