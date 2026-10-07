import { ASPECT_TOLERANCE, MIN_HEIGHT, MIN_WIDTH, REF_WIDTH } from './constants.js';

// Height / width of each aspect class the presets know about.
export const ASPECTS = {
    '9:16': 16 / 9,
    '9:19.5': 19.5 / 9,
    '4:5': 5 / 4,
    '1:1': 1,
    '16:9': 9 / 16,
};

export function classifyAspect(w, h) {
    if (!(w > 0 && h > 0)) return 'other';
    const ratio = h / w;
    for (const [name, target] of Object.entries(ASPECTS)) {
        if (Math.abs(ratio - target) / target <= ASPECT_TOLERANCE) return name;
    }
    return 'other';
}

// The reference frame for an upload: width 1080, height kept in proportion.
export function referenceFrame(w, h) {
    return { width: REF_WIDTH, height: Math.round((REF_WIDTH * h) / w) };
}

// Scale factor from source pixels to reference pixels.
export const toReferenceScale = (w) => REF_WIDTH / w;

export const isLowResolution = (w, h) => {
    const portrait = h >= w;
    return portrait ? w < MIN_WIDTH || h < MIN_HEIGHT : h < MIN_WIDTH || w < MIN_HEIGHT;
};
