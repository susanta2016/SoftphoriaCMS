// The checker engine. Pure: image facts + a profile in, results out.
// Platform values come only from data/profiles.js (or a custom profile);
// every result explains what is wrong, what is expected and what to do.
import { cropRect, parseRatio, ratioFits, ratioLabel } from './geometry.js';
import { FORMAT_NAMES } from './analyze.js';

export const STATUS_ORDER = { fail: 3, warning: 2, info: 1, pass: 0 };

const LEVEL_STATUS = { hard: 'fail', recommended: 'warning', advisory: 'info' };
const statusFor = (level, ok) => (ok ? 'pass' : LEVEL_STATUS[level] || 'warning');

export const px = (n) => `${Math.round(n).toLocaleString('en-US')} px`;

export function formatBytes(bytes) {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} bytes`;
}

const formatList = (list) => {
    const names = list.map((f) => (f === 'jpeg' ? 'JPG' : FORMAT_NAMES[f] || f.toUpperCase()));
    return names.length > 1 ? `${names.slice(0, -1).join(', ')} or ${names.at(-1)}` : names[0];
};
const fmt = (f) => (f === 'jpeg' ? 'JPG' : FORMAT_NAMES[f] || f.toUpperCase());
const pct = (share) => `${Math.max(1, Math.round(share * 100))}%`;

/** Expected shape as words: "4:5" or "between 4:5 and 1.91:1". */
function shapeText(ratio, frame) {
    const named = (v) => ratioLabel(v * 1000, 1000);
    if (Math.abs(ratio.min - ratio.max) < 1e-9) {
        const r = frame ? ratioLabel(frame.width, frame.height) : named(ratio.min);
        return frame ? `${r} (${frame.width} × ${frame.height} px)` : r;
    }
    return `between ${named(ratio.min)} and ${named(ratio.max)}`;
}

/**
 * @param {{ width, height, bytes, format, transparent: 'yes'|'no'|'unknown', animated }} img
 * @param {object} profile  from data/profiles.js or customProfile()
 * @param {{ x: number, y: number }} [focus]  crop position (0..1)
 */
export function evaluate(img, profile, focus) {
    const checks = [];
    const add = (c) => checks.push(c);

    // Format
    if (profile.formats) {
        const ok = profile.formats.allowed.includes(img.format);
        add({
            id: 'format', area: 'Format', status: statusFor(profile.formats.level, ok),
            title: ok ? `${fmt(img.format)} is accepted` : profile.formats.level === 'hard' ? `${fmt(img.format)} files are not accepted here` : `${fmt(img.format)} may not be accepted here`,
            expected: formatList(profile.formats.allowed), actual: fmt(img.format),
            action: ok ? null : `Save the image as ${formatList(profile.formats.allowed)} and check it again. JPG suits photos; PNG suits graphics and transparency.`,
        });
    }

    // Minimum / maximum dimensions (of the file as uploaded)
    const dims = [
        ['minWidth', img.width, (v) => img.width >= v, 'narrow', 'wide'],
        ['minHeight', img.height, (v) => img.height >= v, 'short', 'tall'],
    ];
    for (const [key, actual, test, word, axis] of dims) {
        const rule = profile[key];
        if (!rule) continue;
        const ok = test(rule.value);
        if (ok) {
            // With a recommended size, the resolution check below covers a pass.
            if (!profile.frame) add({ id: key, area: 'Size', status: 'pass', title: `Your image is ${axis} enough`, expected: `At least ${px(rule.value)} ${axis}`, actual: `Your image: ${px(actual)} ${axis}` });
            continue;
        }
        add({
            id: key, area: 'Size', status: statusFor(rule.level, false),
            title: rule.level === 'hard' ? `Your image is too ${word}` : `Your image is ${word === 'narrow' ? 'narrower' : 'shorter'} than recommended`,
            expected: `At least ${px(rule.value)} ${axis}`, actual: `Your image: ${px(actual)} ${axis}`,
            action: rule.level === 'hard'
                ? 'Use a larger original or export it at a higher resolution. Enlarging a small image makes it look blurry.'
                : 'It will still upload, but the platform will enlarge it, which can look soft. A larger original gives a sharper result.',
        });
    }
    for (const [key, actual, word, axis] of [['maxWidth', img.width, 'wide', 'wide'], ['maxHeight', img.height, 'tall', 'tall']]) {
        const rule = profile[key];
        if (!rule || actual <= rule.value) continue;
        add({
            id: key, area: 'Size', status: statusFor(rule.level, false), title: `Your image is too ${word}`,
            expected: `At most ${px(rule.value)} ${axis}`, actual: `Your image: ${px(actual)} ${axis}`,
            action: `Resize the image so it is no more than ${px(rule.value)} ${axis}.`,
        });
    }

    // Shape and crop
    let crop = null;
    if (profile.ratio) {
        const { min, max, level, onMismatch } = profile.ratio;
        crop = cropRect(img.width, img.height, min, max, focus);
        const fits = ratioFits(img.width / img.height, min, max);
        const expected = shapeText(profile.ratio, profile.frame);
        if (fits) {
            add({ id: 'shape', area: 'Shape', status: 'pass', title: `The shape fits (${ratioLabel(img.width, img.height)})`, expected, actual: ratioLabel(img.width, img.height) });
        } else {
            const wider = crop.axis === 'width';
            const where = wider ? 'left and right edges' : 'top and bottom';
            const detail = onMismatch === 'letterbox'
                ? `Your image is ${wider ? 'wider' : 'taller'} than ${shapeText({ min, max: min }, null)}, so it may be shown with bars at the sides or cropped. If it is cropped, about ${pct(crop.removed)} of the ${wider ? 'width' : 'height'} is lost.`
                : `Your image is ${wider ? 'wider' : 'taller'} than this format, so about ${pct(crop.removed)} of the ${wider ? 'width' : 'height'} will be cut off at the ${where}.`;
            add({
                id: 'shape', area: 'Shape', status: statusFor(level, false), crop: true,
                title: onMismatch === 'letterbox' ? 'Your image has a different shape' : 'Your image has a different shape, so it will be cropped',
                detail, expected, actual: ratioLabel(img.width, img.height),
                action: `Check the crop preview to see what stays visible, or crop the image to ${shapeText({ min: crop.ratio, max: crop.ratio }, null)} yourself so you choose what is kept.`,
            });
        }
    }

    // Resolution: what is left after any crop, against the recommended size
    const minFailed = checks.some((c) => (c.id === 'minWidth' || c.id === 'minHeight') && c.status === 'fail');
    if (profile.frame && !minFailed) {
        const usedWidth = crop ? crop.w : img.width;
        const target = profile.frame.width;
        const after = crop && crop.axis ? 'After cropping, your image is' : 'Your image is';
        if (usedWidth >= target) {
            add({
                id: 'resolution', area: 'Resolution', status: 'pass',
                title: usedWidth === target ? 'Resolution: matches the recommended size' : 'Resolution: good',
                expected: `${px(target)} wide recommended`, actual: `${after} ${px(usedWidth)} wide`,
                detail: usedWidth > target * 2 ? 'It is larger than needed; the platform will resize it down, which is fine.' : null,
            });
        } else {
            add({
                id: 'resolution', area: 'Resolution', status: 'warning', title: 'Resolution: may look softer than recommended',
                expected: `${px(target)} wide recommended`, actual: `${after} ${px(usedWidth)} wide`,
                detail: 'It meets the minimum, but the platform will enlarge it to fill the space.',
                action: 'Use a larger original or export at a higher resolution if you have one.',
            });
        }
    }

    // File size (each limit separately: e.g. YouTube desktop vs mobile app)
    for (const limit of profile.maxBytes || []) {
        const ok = img.bytes <= limit.value;
        add({
            id: `bytes-${limit.value}`, area: 'File size', status: statusFor(limit.level, ok),
            title: ok ? `File size is within the limit (${formatBytes(img.bytes)})` : limit.level === 'hard' ? 'The file is too large' : 'The file is larger than recommended',
            expected: `At most ${formatBytes(limit.value)} — ${limit.label}`, actual: `Your file: ${formatBytes(img.bytes)}`,
            action: ok ? null : 'Compress the image or export it at a slightly lower quality. A JPG saved at 80–90% quality is usually much smaller with no visible difference.',
        });
    }

    // Transparency and animation: placement-specific considerations
    if (img.transparent === 'yes') {
        const keeps = profile.category === 'website' && profile.id !== 'website-og';
        add({
            id: 'transparency', area: 'Transparency', status: keeps ? 'info' : 'warning',
            title: keeps ? 'Transparent areas are kept on websites' : 'Transparent areas may be filled with a solid colour',
            detail: keeps ? 'PNG and WebP keep transparency on web pages; make sure it looks right on your page background.'
                : 'Many platforms convert uploads to JPG, which has no transparency, so transparent areas can turn white or black.',
            action: keeps ? null : 'Place the image on the background colour you want before uploading.',
        });
    }
    if (img.animated === 'yes') {
        add({
            id: 'animation', area: 'Animation', status: 'info', title: 'This is an animated image',
            detail: 'Many placements show only the first frame of an animated PNG or WebP.',
        });
    }

    const worst = checks.reduce((w, c) => (STATUS_ORDER[c.status] > STATUS_ORDER[w] ? c.status : w), 'pass');
    const status = worst === 'info' ? 'pass' : worst;
    const needsCrop = checks.some((c) => c.crop);
    const otherWarnings = checks.some((c) => c.status === 'warning' && !c.crop);
    const headline = status === 'fail' ? 'Not suitable'
        : status === 'pass' ? 'Ready to use'
            : needsCrop && !otherWarnings ? 'Works with cropping' : 'Usable, with recommendations';

    return { profile, status, headline, needsCrop, crop, checks };
}

/** Results for every profile, plus the three "best uses" groups. */
export function evaluateAll(img, profiles) {
    const results = profiles.map((p) => evaluate(img, p));
    return {
        results,
        best: results.filter((r) => r.status === 'pass'),
        adjust: results.filter((r) => r.status === 'warning'),
        unsuitable: results.filter((r) => r.status === 'fail'),
    };
}

/**
 * Whole-image observations (not tied to a placement) and the readiness score.
 * The score is a summary only: format 20, resolution 25, file size 20,
 * placement fit 35 (passes count fully, warnings half).
 */
export function readiness(img, all) {
    const short = Math.min(img.width, img.height);
    const items = [];

    const formatOk = img.format === 'jpeg' || img.format === 'png';
    items.push({
        id: 'format', label: 'Format', status: formatOk ? 'pass' : 'warning', points: formatOk ? 20 : 14, max: 20,
        text: formatOk ? `${fmt(img.format)} is accepted almost everywhere.` : `${fmt(img.format)} works on websites, but some platforms only accept JPG or PNG uploads.`,
    });

    const res = short >= 1080 ? [25, 'pass', 'High enough for most placements; a few banners and covers need more (see the list below).']
        : short >= 720 ? [18, 'warning', 'Fine for most uses, but below the 1080 px many platforms recommend.']
            : short >= 480 ? [10, 'warning', 'Low: several placements will enlarge it and it may look soft.']
                : [4, 'warning', 'Very low: most placements will look blurry.'];
    items.push({ id: 'resolution', label: 'Resolution', status: res[1], points: res[0], max: 25, text: `${img.width} × ${img.height} px. ${res[2]}` });

    const mb = img.bytes / (1024 * 1024);
    const size = mb <= 2 ? [20, 'pass', 'Small enough to upload anywhere quickly.']
        : mb <= 5 ? [16, 'pass', 'Within most limits; a few placements allow only 2–3 MB.']
            : mb <= 8 ? [12, 'warning', 'Larger than several platform limits (2–5 MB).']
                : mb <= 20 ? [6, 'warning', 'Too large for many placements. Compressing it is recommended.']
                    : [0, 'warning', 'Very large: most placements will reject it until it is compressed.'];
    items.push({ id: 'filesize', label: 'File size', status: size[1], points: size[0], max: 20, text: `${formatBytes(img.bytes)}. ${size[2]}` });

    const n = all.results.length || 1;
    const fit = Math.round(35 * (all.best.length + all.adjust.length * 0.5) / n);
    items.push({
        id: 'fit', label: 'Placements', status: all.unsuitable.length === 0 && all.adjust.length === 0 ? 'pass' : 'warning', points: fit, max: 35,
        text: `${all.best.length} ready to use, ${all.adjust.length} usable with changes, ${all.unsuitable.length} not suitable.`,
    });

    const score = items.reduce((s, i) => s + i.points, 0);
    const label = score >= 85 ? 'Ready for most uses' : score >= 60 ? 'Usable, with some changes' : 'Needs work before publishing';
    return { score, label, items };
}

/** Observations about the file itself, shown under "Problems & warnings". */
export function fileNotes(meta) {
    const notes = [];
    if (meta.cmyk) {
        notes.push({ id: 'cmyk', status: 'warning', title: 'This image uses CMYK colours (print)', detail: 'Browsers and social apps expect RGB; CMYK images can show dull or shifted colours.', action: 'Export the image as RGB (sRGB) before publishing.' });
    }
    if (meta.exif?.hasGps) {
        notes.push({ id: 'gps', status: 'warning', title: 'Location information found', detail: 'This image contains GPS location data in its metadata. Many platforms remove it on upload, but websites and email usually keep it.', action: 'If privacy matters, remove the metadata before publishing (most photo apps and editors offer "remove location").' });
    }
    if (meta.exif?.orientation && meta.exif.orientation > 1) {
        notes.push({ id: 'orientation', status: 'info', title: 'Rotated by metadata', detail: 'The photo is stored sideways and turned upright by its EXIF orientation. Browsers and major platforms honour this, and this check uses the upright size.' });
    }
    return notes;
}

/**
 * A profile from the Custom Requirements form. All values are the user's own
 * requirements, so they are hard rules.
 *
 * @returns {{ profile: object|null, errors: Record<string, string> }}
 */
export function customProfile(values) {
    const errors = {};
    const num = (key, label) => {
        const raw = String(values[key] ?? '').trim();
        if (raw === '') return null;
        const n = Number(raw);
        if (!Number.isFinite(n) || n <= 0 || n > 100000) {
            errors[key] = `${label} must be a positive number.`;
            return null;
        }
        return n;
    };
    const minWidth = num('minWidth', 'Minimum width');
    const minHeight = num('minHeight', 'Minimum height');
    const maxWidth = num('maxWidth', 'Maximum width');
    const maxHeight = num('maxHeight', 'Maximum height');
    const maxMB = num('maxMB', 'Maximum file size');
    let ratio = null;
    if (String(values.ratio ?? '').trim() !== '') {
        ratio = parseRatio(values.ratio);
        if (ratio === null) errors.ratio = 'Use a shape like 16:9, 4:5 or 1.91:1.';
    }
    if (minWidth && maxWidth && minWidth > maxWidth) errors.maxWidth = 'Maximum width is smaller than the minimum.';
    if (minHeight && maxHeight && minHeight > maxHeight) errors.maxHeight = 'Maximum height is smaller than the minimum.';
    const formats = (values.formats || []).filter((f) => ['jpeg', 'png', 'webp'].includes(f));

    const rules = [minWidth, minHeight, maxWidth, maxHeight, maxMB, ratio].some((v) => v !== null) || formats.length > 0;
    if (!rules && Object.keys(errors).length === 0) errors.form = 'Enter at least one requirement.';
    if (Object.keys(errors).length) return { profile: null, errors };

    const frame = minWidth && ratio ? { width: minWidth, height: Math.round(minWidth / ratio) } : null;
    return {
        errors,
        profile: {
            id: 'custom', platform: 'Custom', placement: 'Your requirements', category: 'custom',
            frame,
            ratio: ratio ? { min: ratio, max: ratio, level: 'hard', onMismatch: 'crop' } : null,
            minWidth: minWidth ? { value: minWidth, level: 'hard' } : null,
            minHeight: minHeight ? { value: minHeight, level: 'hard' } : null,
            maxWidth: maxWidth ? { value: maxWidth, level: 'hard' } : null,
            maxHeight: maxHeight ? { value: maxHeight, level: 'hard' } : null,
            maxBytes: maxMB ? [{ value: Math.round(maxMB * 1024 * 1024), level: 'hard', label: 'your limit' }] : [],
            formats: formats.length ? { allowed: formats, level: 'hard' } : null,
            sources: [],
        },
    };
}
