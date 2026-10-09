// Subtitle timestamps: strict parsing with a diagnosis, and formatting.
//
// SRT  HH:MM:SS,mmm   (hours required)
// VTT  [HH:]MM:SS.mmm (hours optional, at least two digits when present)
//
// parseTime() never guesses silently. It returns the time in milliseconds
// whenever the value is unambiguous (so timing checks can still run), an
// error code when the text is not valid for the format, and `fixable` when
// re-writing it in the correct form keeps exactly the same time.

const PATTERN = /^(-)?(?:(\d+):)?(\d+):(\d+)([.,])(\d+)$/;

/**
 * @param {string} raw
 * @param {'srt'|'vtt'} format
 * @returns {{ms: number|null, code: string|null, fixable: boolean}}
 */
export function parseTime(raw, format) {
    const match = PATTERN.exec(raw);
    if (!match) return { ms: null, code: 'timestamp-invalid', fixable: false };

    const [, minus, hours, minutes, seconds, separator, fraction] = match;

    if (fraction.length !== 3) return { ms: null, code: 'timestamp-precision', fixable: false };
    if (minutes.length !== 2 || seconds.length !== 2 || Number(minutes) > 59 || Number(seconds) > 59) {
        return { ms: null, code: 'timestamp-invalid', fixable: false };
    }

    const ms = (Number(hours ?? 0) * 3600 + Number(minutes) * 60 + Number(seconds)) * 1000 + Number(fraction);

    if (minus) return { ms: -ms, code: 'timestamp-negative', fixable: false };
    if (separator !== (format === 'srt' ? ',' : '.')) return { ms, code: 'timestamp-separator', fixable: true };
    if (format === 'srt' && hours === undefined) return { ms, code: 'timestamp-hours', fixable: true };
    if (hours !== undefined && hours.length < 2) return { ms, code: 'timestamp-hours', fixable: true };

    return { ms, code: null, fixable: false };
}

/** Formats milliseconds (≥ 0) as a timestamp for the format. */
export function formatTime(ms, format) {
    const hours = Math.floor(ms / 3600000);
    const minutes = Math.floor(ms / 60000) % 60;
    const seconds = Math.floor(ms / 1000) % 60;
    const millis = ms % 1000;
    const pad = (n, size = 2) => String(n).padStart(size, '0');

    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}${format === 'srt' ? ',' : '.'}${pad(millis, 3)}`;
}

/** A readable time for reports: HH:MM:SS.mmm, with a minus sign when negative. */
export function displayTime(ms) {
    if (ms === null || ms === undefined) return '';
    return (ms < 0 ? '-' : '') + formatTime(Math.abs(ms), 'vtt');
}

/** A short duration such as 2.5 s or 1 h 2 min 5 s. */
export function displayDuration(ms) {
    if (!Number.isFinite(ms)) return '—';
    const total = Math.round(ms / 100) / 10;
    if (total < 60) return `${total} s`;
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = Math.round(total % 60);
    return [h ? `${h} h` : null, m ? `${m} min` : null, `${s} s`].filter(Boolean).join(' ');
}
