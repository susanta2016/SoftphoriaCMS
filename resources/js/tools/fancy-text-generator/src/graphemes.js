// Splits text into user-perceived characters (grapheme clusters), so a
// transformation never separates an emoji from its skin tone, a flag's two
// regional indicators, a ZWJ family emoji, or a letter from its accents.
//
// Intl.Segmenter does this properly where available (all current browsers,
// Node 16+). The fallback covers the cases that matter for this tool:
// combining marks, variation selectors, emoji modifiers, tag sequences,
// ZWJ joins, regional-indicator pairs and CRLF.

const segmenter = typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function'
    ? new Intl.Segmenter(undefined, { granularity: 'grapheme' })
    : null;

const MARK = /^\p{M}$/u;
const ZWJ = 0x200d;

const isExtender = (cp) => (cp >= 0xfe00 && cp <= 0xfe0f)    // variation selectors
    || (cp >= 0x1f3fb && cp <= 0x1f3ff)                      // emoji skin-tone modifiers
    || (cp >= 0xe0020 && cp <= 0xe007f)                      // emoji tag sequence
    || (cp >= 0xe0100 && cp <= 0xe01ef)                      // variation selectors supplement
    || cp === ZWJ;

const isRegional = (cp) => cp >= 0x1f1e6 && cp <= 0x1f1ff;

export function fallbackGraphemes(text) {
    const out = [];
    let regionalRun = 0;
    for (const char of text) {
        const cp = char.codePointAt(0);
        const last = out.length ? out[out.length - 1] : null;
        const lastCp = last === null ? null : Array.from(last).at(-1).codePointAt(0);
        const joinsPrevious = last !== null && (
            MARK.test(char)
            || isExtender(cp)
            || lastCp === ZWJ
            || (char === '\n' && last === '\r')
            || (isRegional(cp) && regionalRun % 2 === 1)
        );
        if (joinsPrevious) {
            out[out.length - 1] += char;
        } else {
            out.push(char);
        }
        regionalRun = isRegional(cp) ? regionalRun + 1 : 0;
    }
    return out;
}

export function graphemes(text) {
    if (!text) return [];
    if (!segmenter) return fallbackGraphemes(text);
    return Array.from(segmenter.segment(text), (part) => part.segment);
}

// Number of user-perceived characters (the character counter).
export function countGraphemes(text) {
    return graphemes(text).length;
}

const PICTOGRAPHIC = /\p{Extended_Pictographic}|[\u{1F1E6}-\u{1F1FF}]/u;

// Emoji and flags are left alone by styles that add marks or brackets.
export const isEmoji = (g) => PICTOGRAPHIC.test(g);
export const isSpace = (g) => /^\s+$/u.test(g);
export const isLineBreak = (g) => g === '\n' || g === '\r\n' || g === '\r';
