// The optimisation search. Pure: it never touches a canvas itself, it calls
// `encode(width, height, format, quality)` and reads the ACTUAL size of what
// comes back, so it can be tested with a fake encoder.
//
// Priority (spec 7): 1. meet the target  2. keep the dimensions
// 3. keep the quality  4. shrink the dimensions only when needed.

export const QUALITY = { min: 0.55, max: 0.95, step: 0.02 }; // min: internal encoder floor, not a visual-quality promise
export const MIN_LONG_SIDE = 160; // never shrink below this; smaller is no longer a usable image
export const SAFE_PIXELS = 16_777_216; // 4096 × 4096: a canvas every major browser, including iOS Safari, can handle

export class Cancelled extends Error {}

/** Size that fits inside max width/height and the safe pixel budget, never enlarged. */
export function baseSize(width, height, { maxWidth = null, maxHeight = null } = {}) {
    let scale = 1;
    if (maxWidth && width > maxWidth) scale = Math.min(scale, maxWidth / width);
    if (maxHeight && height > maxHeight) scale = Math.min(scale, maxHeight / height);
    let w = Math.max(1, Math.round(width * scale));
    let h = Math.max(1, Math.round(height * scale));
    const limitedByUser = scale < 1;
    let limitedBySafety = false;
    if (w * h > SAFE_PIXELS) {
        const s = Math.sqrt(SAFE_PIXELS / (w * h));
        w = Math.max(1, Math.floor(w * s));
        h = Math.max(1, Math.floor(h * s));
        limitedBySafety = true;
    }
    return { width: w, height: h, limitedByUser, limitedBySafety };
}

/**
 * @param {{ width, height, bytes, format }} source  the original image
 * @param {{ limit: number, format: 'jpeg'|'webp'|'png', maxWidth?, maxHeight? }} opts
 * @param {(w: number, h: number, format: string, quality?: number) => Promise<Blob>} encode
 * @param {{ cancelled?: () => boolean, onAttempt?: (n: number) => void }} [hooks]
 * @returns {Promise<{ ok, blob, width, height, format, quality, keptOriginal, resized, attempts, reason? }>}
 */
export async function optimize(source, opts, encode, hooks = {}) {
    const base = baseSize(source.width, source.height, opts);
    let attempts = 0;
    const run = async (w, h, q) => {
        if (hooks.cancelled?.()) throw new Cancelled();
        attempts += 1;
        hooks.onAttempt?.(attempts);
        const blob = await encode(w, h, opts.format, q);
        return blob;
    };

    // Already small enough, same format, no resizing asked for: hand back the original untouched.
    if (source.blob && source.format === opts.format && source.bytes <= opts.limit && base.width === source.width && base.height === source.height) {
        return { ok: true, blob: source.blob, width: source.width, height: source.height, format: source.format, quality: null, keptOriginal: true, resized: false, attempts, base };
    }

    const lossy = opts.format !== 'png';
    const sized = (s) => [Math.max(1, Math.round(base.width * s)), Math.max(1, Math.round(base.height * s))];
    let scale = 1;
    let failedScale = null; // the last (larger) scale that did not fit
    let smallest = null;

    for (let round = 0; round < 14; round++) {
        let [w, h] = sized(scale);

        let floor = await run(w, h, lossy ? QUALITY.min : undefined);
        if (!smallest || floor.size < smallest.blob.size) smallest = { blob: floor, width: w, height: h, quality: lossy ? QUALITY.min : null };

        if (floor.size <= opts.limit) {
            // Dimensions before quality: the jump down may have overshot, so search
            // back up between this scale and the last one that failed for the
            // largest size that still fits at the lowest quality (or losslessly).
            if (failedScale !== null) {
                let lo = scale;
                let hi = failedScale;
                for (let i = 0; i < 5 && hi - lo > 0.01; i++) {
                    const mid = (lo + hi) / 2;
                    const [mw, mh] = sized(mid);
                    const blob = await run(mw, mh, lossy ? QUALITY.min : undefined);
                    if (blob.size <= opts.limit) {
                        lo = mid;
                        [w, h, floor] = [mw, mh, blob];
                    } else {
                        hi = mid;
                    }
                }
            }
            if (!lossy) return done(floor, w, h, null);
            // Highest quality that still fits: try the top first, then binary search.
            const top = await run(w, h, QUALITY.max);
            if (top.size <= opts.limit) return done(top, w, h, QUALITY.max);
            let lo = QUALITY.min;
            let hi = QUALITY.max;
            let best = { blob: floor, q: QUALITY.min };
            while (hi - lo > QUALITY.step) {
                const mid = Math.round(((lo + hi) / 2) * 100) / 100;
                const blob = await run(w, h, mid);
                if (blob.size <= opts.limit) {
                    best = { blob, q: mid };
                    lo = mid;
                } else {
                    hi = mid;
                }
            }
            return done(best.blob, w, h, best.q);
        }

        // Too big even at the lowest quality: shrink. File size grows roughly with
        // pixel count, so aim for the area that should fit, with a margin, and
        // always shrink by at least 8% (at most 50%) per round.
        const factor = Math.min(0.92, Math.max(0.5, Math.sqrt(opts.limit / floor.size) * 0.95));
        const next = scale * factor;
        if (Math.max(base.width, base.height) * next < MIN_LONG_SIDE) break;
        failedScale = scale;
        scale = next;
    }

    return {
        ok: false, blob: smallest?.blob ?? null, width: smallest?.width ?? base.width, height: smallest?.height ?? base.height,
        format: opts.format, quality: smallest?.quality ?? null, keptOriginal: false, resized: true, attempts, base,
        reason: 'target-unreachable',
    };

    function done(blob, w, h, quality) {
        return { ok: true, blob, width: w, height: h, format: opts.format, quality, keptOriginal: false, resized: w !== source.width || h !== source.height, attempts, base };
    }
}

/** Output format for "Keep the original format". */
export const keepFormat = (sourceFormat) => (['jpeg', 'png', 'webp'].includes(sourceFormat) ? sourceFormat : 'jpeg');
