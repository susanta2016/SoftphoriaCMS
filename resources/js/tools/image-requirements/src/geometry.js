// Shapes and crops. Pure.

export const RATIO_TOLERANCE = 0.01; // 1%: a 1080 × 1351 image still counts as 4:5

const NAMED = [
    [1, 1], [4, 5], [5, 4], [3, 4], [4, 3], [2, 3], [3, 2], [9, 16], [16, 9], [1.91, 1], [2, 1], [3, 1], [4, 1], [21, 9], [2.4, 1],
];

const gcd = (a, b) => (b ? gcd(b, a % b) : a);

/** "4:5", "16:9", "1.91:1" — a familiar name when the shape is within 1% of one. */
export function ratioLabel(width, height) {
    const value = width / height;
    for (const [w, h] of NAMED) {
        if (Math.abs(value / (w / h) - 1) <= RATIO_TOLERANCE) return `${w}:${h}`;
    }
    const g = gcd(Math.round(width), Math.round(height));
    const [w, h] = [Math.round(width) / g, Math.round(height) / g];
    return w <= 50 && h <= 50 ? `${w}:${h}` : `${value.toFixed(2)}:1`;
}

export function orientation(width, height) {
    if (Math.abs(width / height - 1) <= RATIO_TOLERANCE) return 'square';
    return width > height ? 'landscape' : 'portrait';
}

/** "1.91:1", "16:9", "4/5", "1.5" → width ÷ height, or null. */
export function parseRatio(text) {
    const s = String(text ?? '').trim().replace(/\s+/g, '');
    if (!s) return null;
    const m = /^(\d+(?:\.\d+)?)(?:[:/x×](\d+(?:\.\d+)?))?$/i.exec(s);
    if (!m) return null;
    const value = Number(m[1]) / (m[2] === undefined ? 1 : Number(m[2]));
    return Number.isFinite(value) && value > 0 && value < 100 ? value : null;
}

/** Whether a shape is inside an accepted range, with the 1% tolerance. */
export function ratioFits(value, min, max) {
    return value >= min * (1 - RATIO_TOLERANCE) && value <= max * (1 + RATIO_TOLERANCE);
}

/**
 * The part of the image that stays visible when it is cropped into the
 * accepted range (or to `target`), positioned by `focus` (0..1 on each axis,
 * 0.5 = centred).
 *
 * @returns {{ x, y, w, h, ratio, axis: 'width'|'height'|null, removed: number }}
 *   removed: share of the cropped axis that is cut off (0..1)
 */
export function cropRect(width, height, min, max, focus = { x: 0.5, y: 0.5 }) {
    const value = width / height;
    if (ratioFits(value, min, max)) return { x: 0, y: 0, w: width, h: height, ratio: value, axis: null, removed: 0 };
    if (value > max) {
        const w = Math.round(height * max);
        return { x: Math.round((width - w) * clamp(focus.x)), y: 0, w, h: height, ratio: max, axis: 'width', removed: 1 - w / width };
    }
    const h = Math.round(width / min);
    return { x: 0, y: Math.round((height - h) * clamp(focus.y)), w: width, h, ratio: min, axis: 'height', removed: 1 - h / height };
}

const clamp = (v) => Math.min(1, Math.max(0, Number.isFinite(v) ? v : 0.5));
