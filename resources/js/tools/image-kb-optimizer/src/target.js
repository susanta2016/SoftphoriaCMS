// Target sizes. Pure.
//
// A target is a MAXIMUM. Sites count 1 KB as 1,000 or 1,024 bytes, so the
// byte limit uses the stricter 1,000: a result under it passes either way.

export const PRESETS = [
    { label: '10 KB', value: 10, unit: 'KB' },
    { label: '20 KB', value: 20, unit: 'KB' },
    { label: '30 KB', value: 30, unit: 'KB' },
    { label: '50 KB', value: 50, unit: 'KB' },
    { label: '100 KB', value: 100, unit: 'KB' },
    { label: '200 KB', value: 200, unit: 'KB' },
    { label: '500 KB', value: 500, unit: 'KB' },
    { label: '1 MB', value: 1, unit: 'MB' },
];

export const MIN_TARGET_BYTES = 2000; // below ~2 KB almost no real image can be encoded
export const MAX_TARGET_BYTES = 50 * 1000 * 1000;

/** Byte limit for a target, or null when it is not a valid target. */
export function limitBytes(value, unit) {
    const n = typeof value === 'number' ? value : Number(String(value ?? '').trim().replace(',', '.'));
    if (!Number.isFinite(n) || n <= 0) return null;
    const bytes = Math.floor(n * (unit === 'MB' ? 1000 * 1000 : 1000));
    return bytes >= MIN_TARGET_BYTES && bytes <= MAX_TARGET_BYTES ? bytes : null;
}

/** Why a custom target is invalid, in plain words, or null when it is fine. */
export function targetError(value, unit) {
    const n = Number(String(value ?? '').trim().replace(',', '.'));
    if (String(value ?? '').trim() === '' || !Number.isFinite(n)) return 'Enter a number, for example 50.';
    if (n <= 0) return 'The target must be greater than zero.';
    const bytes = n * (unit === 'MB' ? 1000 * 1000 : 1000);
    if (bytes < MIN_TARGET_BYTES) return 'The smallest target is 2 KB.';
    if (bytes > MAX_TARGET_BYTES) return 'The largest target is 50 MB.';
    return null;
}

/** "50 KB", "1 MB", "1.5 MB" — how the target is written on screen. */
export function targetLabel(value, unit) {
    return `${Number(value)} ${unit}`;
}

/** "50kb", "1mb", "?target=1.5mb" → { value, unit }; null when absent or invalid. */
export function parseTargetParam(text) {
    const m = /^\s*(\d+(?:\.\d+)?)\s*(kb|mb)\s*$/i.exec(String(text ?? ''));
    if (!m) return null;
    const value = Number(m[1]);
    const unit = m[2].toUpperCase();
    return limitBytes(value, unit) === null ? null : { value, unit };
}

/** File size as people read it on their computer (1 KB = 1,024 bytes). */
export function formatSize(bytes) {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes} bytes`;
}
