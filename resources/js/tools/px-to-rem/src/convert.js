// PX to REM conversion, rounding and list parsing — no DOM, so it can be
// tested with node --test (resources/js/tools/px-to-rem/tests).

export const DEFAULT_ROOT = 16;
export const ROOT_MIN = 1;
export const ROOT_MAX = 200;
export const PRECISIONS = [2, 3, 4, 5];
export const DEFAULT_PRECISION = 4;
export const BULK_LIMIT = 500;

// Rounds half away from zero to `places` decimals and drops trailing zeros
// (1.5, not 1.5000). The tiny nudge stops binary fractions such as
// 1.005 * 100 = 100.49999… from rounding down; -0 is shown as 0.
export function format(value, places = DEFAULT_PRECISION) {
    const factor = 10 ** places;
    const rounded = (Math.sign(value) * Math.round(Math.abs(value) * factor + 1e-9)) / factor;

    return String(rounded === 0 ? 0 : rounded);
}

/** @returns {number|null} the root font size, or null when it is not usable */
export function parseRoot(raw) {
    const value = parseNumber(raw);

    return value !== null && value >= ROOT_MIN && value <= ROOT_MAX ? value : null;
}

/** A plain decimal number ("24", "-0.5", ".75", "1e2" is rejected), or null. */
export function parseNumber(raw) {
    const text = String(raw ?? '').trim();

    return /^[-+]?(\d+\.?\d*|\.\d+)$/.test(text) ? Number(text) : null;
}

export const pxToRem = (px, root) => px / root;
export const remToPx = (rem, root) => rem * root;

/**
 * Splits a list of values on new lines, commas, spaces and semicolons. A unit
 * written after a space ("32 px") stays with its number.
 */
export function splitList(text) {
    return String(text ?? '')
        .split(/[\s,;]+/)
        .filter(Boolean)
        .reduce((parts, token) => {
            const previous = parts.at(-1);
            if (/^(px|rem)$/i.test(token) && previous !== undefined && parseNumber(previous) !== null) {
                parts[parts.length - 1] = `${previous} ${token}`;
            } else {
                parts.push(token);
            }
            return parts;
        }, []);
}

/**
 * Converts each value of a list (see splitList). `direction` is 'px-rem' or
 * 'rem-px'. A value may carry the unit it is converted from (24px, 1.5rem);
 * the other unit is reported, not guessed.
 *
 * @returns {{items: Array<{input: string, ok: boolean, value?: number, output?: string, error?: string}>, truncated: boolean}}
 */
export function convertList(text, direction, root, places = DEFAULT_PRECISION) {
    const [from, to] = direction === 'rem-px' ? ['rem', 'px'] : ['px', 'rem'];
    const parts = splitList(text);

    const items = parts.slice(0, BULK_LIMIT).map((input) => {
        const match = input.match(/^(.*?)\s*(px|rem)?$/i);
        const unit = match[2]?.toLowerCase();

        if (unit && unit !== from) {
            return { input, ok: false, error: `This is a ${unit} value — switch the direction to convert it.` };
        }

        const number = parseNumber(match[1]);
        if (number === null) {
            return { input, ok: false, error: 'Not a number.' };
        }

        const value = from === 'px' ? pxToRem(number, root) : remToPx(number, root);

        return { input, ok: true, value, output: `${format(value, places)}${to}` };
    });

    return { items, truncated: parts.length > BULK_LIMIT };
}
