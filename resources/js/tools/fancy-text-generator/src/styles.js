// The Fancy Text Generator's styles, categories and platform presets.
//
// Every style is a pure function of the input's grapheme clusters (see
// graphemes.js), so emoji, flags and accented letters are never split.
// Characters a style has no mapping for are kept unchanged, and the input
// itself is never modified.
//
// Known limits (also explained on the page):
// - Reverse, mirrored and upside-down work character by character. They do
//   not understand right-to-left scripts (Arabic, Hebrew): reversing those
//   changes the reading order the bidirectional algorithm then applies.
// - Mirrored and upside-down only replace characters that have a
//   look-alike; everything else is reordered but not flipped.
// - Lower case uses the capital form where Unicode has capitals only
//   (squared, negative circled, regional indicators).

import {
    ALL_SMALL_CAPS, BOLD, BOLD_FRAKTUR, BOLD_ITALIC, BOLD_SCRIPT, CIRCLED, DOUBLE_STRUCK, FRAKTUR, FULL_WIDTH, ITALIC, LEET,
    LOOKALIKES, MIRRORED, MONOSPACE, NEGATIVE_CIRCLED, NEGATIVE_SQUARED, PARENTHESIZED, REGIONAL, SANS, SANS_BOLD,
    SANS_BOLD_ITALIC, SANS_ITALIC, SCRIPT, SMALL_CAPS, SQUARED, SUBSCRIPT, SUPERSCRIPT, UPSIDE_DOWN,
} from './alphabets.js';
import { graphemes, isEmoji, isLineBreak, isSpace } from './graphemes.js';

const cp = (...points) => String.fromCodePoint(...points);
const MARK_ONLY = /^\p{M}+$/u;
const ZWSP = cp(0x200b);

export const MAX_INPUT = 2000; // UTF-16 code units, the textarea's maxlength

// ------------------------------------------------------------ primitives

// Map one grapheme through a table. A letter with accents (é, or e + ◌́)
// maps its base letter and keeps the accents.
function mapGrapheme(g, table, decompose = true) {
    if (Object.hasOwn(table, g)) return table[g];
    const [first, ...rest] = Array.from(g);
    if (rest.length && Object.hasOwn(table, first) && MARK_ONLY.test(rest.join(''))) return table[first] + rest.join('');
    if (decompose) {
        const nfd = g.normalize('NFD');
        if (nfd !== g) {
            const [base, ...marks] = Array.from(nfd);
            if (marks.length && Object.hasOwn(table, base) && MARK_ONLY.test(marks.join(''))) return table[base] + marks.join('');
        }
    }
    return g;
}

const mapAll = (gs, table, decompose = true) => gs.map((g) => mapGrapheme(g, table, decompose));

// Split graphemes into lines (the line breaks themselves are dropped and
// re-inserted by joinLines).
function lines(gs) {
    const out = [[]];
    for (const g of gs) {
        if (isLineBreak(g)) out.push([]);
        else out[out.length - 1].push(g);
    }
    return out;
}
const joinLines = (ls) => ls.map((l) => l.join('')).join('\n');

// Apply a function to each run of non-space graphemes (a "word").
function eachWord(gs, fn) {
    const out = [];
    let word = [];
    const flush = () => { if (word.length) out.push(...fn(word)); word = []; };
    for (const g of gs) {
        if (isSpace(g)) { flush(); out.push(g); } else word.push(g);
    }
    flush();
    return out;
}

// --------------------------------------------------------------- builders

// `decompose` maps accented letters by their base letter (é → 𝐞́); only
// used where an accent on the styled letter still reads naturally.
const map = (table, { decompose = false } = {}) => (gs) => mapAll(gs, table, decompose).join('');

// Combining marks after each character. `spaces` also marks the spaces
// between words, so lines (underline, strikethrough) run continuously
// across a phrase but not into leading or trailing spaces.
const marks = (points, { spaces = false } = {}) => {
    const added = cp(...points);
    return (gs) => joinLines(lines(gs).map((line) => {
        const first = line.findIndex((g) => !isSpace(g));
        const last = line.findLastIndex((g) => !isSpace(g));
        return line.map((g, i) => {
            if (isEmoji(g)) return g;
            if (isSpace(g)) return spaces && g === ' ' && i > first && i < last ? g + added : g;
            return g + added;
        });
    }));
};

// Brackets around each character.
const wrapEach = (open, close) => (gs) => gs.map((g) => (isSpace(g) ? g : open + g + close)).join('');

// A symbol between the characters of each word.
const between = (separator) => (gs) => eachWord(gs, (word) => word.flatMap((g, i) => (i ? [separator, g] : [g]))).join('');

// Decoration around each non-empty line, optionally after another style.
const frame = (left, right, inner = null) => (gs) => joinLines(lines(gs).map((line) => {
    if (line.every(isSpace)) return line;
    const text = inner ? inner(line) : line.join('');
    return [left + text + right];
}));

// ------------------------------------------------------------- transforms

const reverseLines = (gs) => joinLines(lines(gs).map((line) => [...line].reverse()));

const upsideDown = (gs) => joinLines(lines(mapAll(gs, UPSIDE_DOWN, false)).map((line) => [...line].reverse()).reverse());

const mirrored = (gs) => joinLines(lines(mapAll(gs, MIRRORED, false)).map((line) => [...line].reverse()));

const reverseWords = (gs) => eachWord(gs, (word) => [...word].reverse()).join('');

// "a b   c d": one space between letters, three between words.
const spaced = (gs) => joinLines(lines(gs).map((line) => [line.map((g) => (isSpace(g) ? ' ' : g)).join(' ')]));

const vaporwave = (gs) => joinLines(lines(gs).map((line) => [line
    .map((g) => (isSpace(g) ? cp(0x3000) : mapGrapheme(g.toUpperCase(), FULL_WIDTH)))
    .join(' ')]));

const regional = (gs) => gs.map((g) => {
    const mapped = mapGrapheme(g, REGIONAL, false);
    return mapped === g ? g : mapped + ZWSP;
}).join('');

const alternating = (gs) => {
    let upper = false;
    return gs.map((g) => {
        const lowerCase = g.toLowerCase();
        const upperCase = g.toUpperCase();
        if (lowerCase === upperCase) return g; // not a cased letter
        upper = !upper;
        return upper ? lowerCase : upperCase;
    }).join('');
};

const invertCase = (gs) => gs.map((g) => {
    const lowerCase = g.toLowerCase();
    return g === lowerCase ? g.toUpperCase() : lowerCase;
}).join('');

const clap = (gs) => {
    const out = [];
    for (let i = 0; i < gs.length; i++) {
        const g = gs[i];
        if (g === ' ' && out.length && !isSpace(out[out.length - 1]) && gs[i + 1] && !isSpace(gs[i + 1])) out.push(' 👏 ');
        else out.push(g);
    }
    return out.join('');
};

// ----------------------------------------------------------------- zalgo

const range = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => from + i);
export const ZALGO_MARKS = {
    up: [0x030d, 0x030e, 0x0304, 0x0305, 0x033f, 0x0311, 0x0306, 0x0310, 0x0352, 0x0357, 0x0351, 0x0307, 0x0308, 0x030a, 0x0342,
        0x0343, 0x0344, 0x034a, 0x034b, 0x034c, 0x0303, 0x0302, 0x030c, 0x0350, 0x0300, 0x0301, 0x030b, 0x030f, 0x0312, 0x0313,
        0x0314, 0x033d, 0x0309, 0x033e, 0x035b, 0x0346, 0x031a, ...range(0x0363, 0x036f)],
    mid: [0x0315, 0x031b, 0x0340, 0x0341, 0x0358, 0x0321, 0x0322, 0x0327, 0x0328, 0x0334, 0x0335, 0x0336, 0x035c, 0x035d, 0x035e,
        0x035f, 0x0360, 0x0362, 0x0338, 0x0337, 0x0361],
    down: [0x0316, 0x0317, 0x0318, 0x0319, 0x031c, 0x031d, 0x031e, 0x031f, 0x0320, 0x0324, 0x0325, 0x0326, 0x0329, 0x032a, 0x032b,
        0x032c, 0x032d, 0x032e, 0x032f, 0x0330, 0x0331, 0x0332, 0x0333, 0x0339, 0x033a, 0x033b, 0x033c, 0x0345, 0x0347, 0x0348,
        0x0349, 0x034d, 0x034e, 0x0353, 0x0354, 0x0355, 0x0356, 0x0359, 0x035a, 0x0323],
};

// [min, max] marks per character in each zone.
export const ZALGO_LEVELS = {
    low: { up: [1, 2], mid: [0, 1], down: [1, 2] },
    medium: { up: [2, 5], mid: [0, 2], down: [2, 5] },
    high: { up: [5, 10], mid: [1, 3], down: [5, 10] },
};
export const DEFAULT_ZALGO_LEVEL = 'medium';

// Hard cap on marks added to one output, whatever the input length.
export const ZALGO_MAX_MARKS = 6000;

// Small deterministic generator: the same input always gives the same
// output, so earlier characters do not flicker while the visitor types.
function rng(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const zalgo = (zones, salt) => (gs, { zalgoLevel = DEFAULT_ZALGO_LEVEL } = {}) => {
    const level = ZALGO_LEVELS[zalgoLevel] ?? ZALGO_LEVELS[DEFAULT_ZALGO_LEVEL];
    let budget = ZALGO_MAX_MARKS;
    return gs.map((g, index) => {
        if (budget <= 0 || isSpace(g) || isEmoji(g)) return g;
        const random = rng(Math.imul(index + 1, 2654435761) ^ salt);
        let added = '';
        for (const zone of zones) {
            const [min, max] = level[zone];
            const count = Math.min(budget, min + Math.floor(random() * (max - min + 1)));
            for (let i = 0; i < count; i++) added += cp(ZALGO_MARKS[zone][Math.floor(random() * ZALGO_MARKS[zone].length)]);
            budget -= count;
        }
        return g + added;
    }).join('');
};

// A lighter, fixed glitch: one or two overlay strokes on most characters.
const GLITCH_MARKS = [0x0334, 0x0335, 0x0336, 0x0337, 0x0338, 0x0321, 0x0322, 0x0315, 0x031b];
const glitch = (gs) => {
    let budget = ZALGO_MAX_MARKS;
    return gs.map((g, index) => {
        if (budget <= 0 || isSpace(g) || isEmoji(g)) return g;
        const random = rng(Math.imul(index + 7, 2246822519));
        if (random() < 0.25) return g;
        const count = 1 + Math.floor(random() * 2);
        budget -= count;
        let added = '';
        for (let i = 0; i < count; i++) added += cp(GLITCH_MARKS[Math.floor(random() * GLITCH_MARKS.length)]);
        return g + added;
    }).join('');
};

// ------------------------------------------------------------- categories

export const CATEGORIES = [
    ['all', 'All Styles'],
    ['popular', 'Popular'],
    ['bold', 'Bold'],
    ['italic', 'Italic'],
    ['script', 'Cursive & Script'],
    ['gothic', 'Gothic & Old English'],
    ['bubble', 'Bubble'],
    ['small', 'Small Text'],
    ['monospace', 'Monospace'],
    ['double-struck', 'Double Struck'],
    ['fraktur', 'Fraktur'],
    ['enclosed', 'Circled & Squared'],
    ['upside-down', 'Upside Down'],
    ['reversed', 'Reversed & Mirrored'],
    ['full-width', 'Full Width'],
    ['lines', 'Underline & Strike'],
    ['glitch', 'Glitch & Zalgo'],
    ['decorative', 'Decorative'],
];

// ------------------------------------------------------------------ styles
// [id, name, categories, transform, keywords]
// Keywords are extra search terms ("tiny" finds superscript).

const S = (id, name, categories, transform, keywords = '') => ({ id, name, categories, transform, keywords });

const L = LOOKALIKES;
const D = { decompose: true };
const box = cp(0x0332, 0x0305);

export const STYLES = [
    // Mathematical alphabets
    S('bold', 'Bold', ['popular', 'bold'], map(BOLD, D), 'serif heavy strong math'),
    S('italic', 'Italic', ['popular', 'italic'], map(ITALIC, D), 'serif slanted math'),
    S('bold-italic', 'Bold Italic', ['bold', 'italic'], map(BOLD_ITALIC, D), 'serif slanted heavy'),
    S('sans', 'Sans-Serif', ['decorative'], map(SANS, D), 'clean plain math'),
    S('sans-bold', 'Sans-Serif Bold', ['popular', 'bold'], map(SANS_BOLD, D), 'heavy strong clean'),
    S('sans-italic', 'Sans-Serif Italic', ['italic'], map(SANS_ITALIC, D), 'slanted clean'),
    S('sans-bold-italic', 'Sans-Serif Bold Italic', ['bold', 'italic'], map(SANS_BOLD_ITALIC, D), 'heavy slanted clean'),
    S('script', 'Script', ['popular', 'script'], map(SCRIPT, D), 'cursive handwriting calligraphy fancy'),
    S('bold-script', 'Bold Script', ['popular', 'script', 'bold'], map(BOLD_SCRIPT, D), 'cursive handwriting calligraphy fancy heavy'),
    S('fraktur', 'Fraktur', ['popular', 'gothic', 'fraktur'], map(FRAKTUR, D), 'gothic old english blackletter medieval'),
    S('bold-fraktur', 'Bold Fraktur', ['gothic', 'fraktur', 'bold'], map(BOLD_FRAKTUR, D), 'gothic old english blackletter medieval heavy'),
    S('double-struck', 'Double Struck', ['popular', 'double-struck'], map(DOUBLE_STRUCK, D), 'outline blackboard hollow'),
    S('monospace', 'Monospace', ['monospace'], map(MONOSPACE, D), 'typewriter code fixed width'),

    // Enclosed and bubble alphabets
    S('circled', 'Bubble', ['popular', 'bubble', 'enclosed'], map(CIRCLED), 'circled circle round'),
    S('negative-circled', 'Black Bubble', ['bubble', 'enclosed'], map(NEGATIVE_CIRCLED), 'negative circled filled dark circle'),
    S('parenthesized', 'Parenthesized', ['enclosed'], map(PARENTHESIZED), 'brackets round'),
    S('squared', 'Squared', ['enclosed'], map(SQUARED), 'boxed square'),
    S('negative-squared', 'Black Squared', ['popular', 'enclosed'], map(NEGATIVE_SQUARED), 'negative squared filled box'),
    S('regional', 'Regional Indicators', ['enclosed', 'decorative'], regional, 'flag letters emoji'),

    // Width and size
    S('full-width', 'Full Width', ['popular', 'full-width'], map(FULL_WIDTH, D), 'wide aesthetic'),
    S('vaporwave', 'Vaporwave', ['full-width', 'decorative'], vaporwave, 'aesthetic wide spaced'),
    S('superscript', 'Tiny Text', ['popular', 'small'], map(SUPERSCRIPT), 'superscript small mini'),
    S('subscript', 'Subscript', ['small'], map(SUBSCRIPT), 'tiny small below'),
    S('small-caps', 'Small Caps', ['popular', 'small'], map(SMALL_CAPS, D), 'smallcaps tiny capitals'),
    S('all-small-caps', 'All Small Caps', ['small'], map(ALL_SMALL_CAPS, D), 'smallcaps tiny capitals'),

    // Flips and order
    S('upside-down', 'Upside Down', ['popular', 'upside-down', 'reversed'], upsideDown, 'flip flipped rotated'),
    S('mirrored', 'Mirrored', ['reversed'], mirrored, 'mirror backwards flipped'),
    S('reverse', 'Reversed', ['reversed'], reverseLines, 'backwards reverse'),
    S('reverse-words', 'Reversed Words', ['reversed'], reverseWords, 'backwards each word'),

    // Lines and marks
    S('underline', 'Underline', ['popular', 'lines'], marks([0x0332], { spaces: true }), 'underlined'),
    S('double-underline', 'Double Underline', ['lines'], marks([0x0333], { spaces: true }), 'underlined'),
    S('strikethrough', 'Strikethrough', ['popular', 'lines'], marks([0x0336], { spaces: true }), 'strike crossed line through'),
    S('slash', 'Slashed', ['lines'], marks([0x0338]), 'slash strike crossed'),
    S('overline', 'Overline', ['lines'], marks([0x0305], { spaces: true }), 'line above'),
    S('double-overline', 'Double Overline', ['lines'], marks([0x033f], { spaces: true }), 'lines above'),
    S('lines-above-below', 'Lines Above & Below', ['lines'], marks([0x0332, 0x0305], { spaces: true }), 'boxed underline overline'),
    S('wavy-underline', 'Wavy Underline', ['lines'], marks([0x0330]), 'wave tilde'),
    S('wavy-strike', 'Wavy Strikethrough', ['lines'], marks([0x0334]), 'wave tilde strike'),
    S('dotted-below', 'Dotted Underline', ['lines', 'decorative'], marks([0x0324]), 'dots dotted'),
    S('dot-above', 'Dotted Above', ['decorative'], marks([0x0307]), 'dots dotted'),
    S('cross-above', 'Crossed Above', ['decorative'], marks([0x033d]), 'x cross'),
    S('arrow-below', 'Arrows Below', ['decorative'], marks([0x034e]), 'arrow up'),
    S('ring-below', 'Rings Below', ['decorative'], marks([0x0325]), 'circle ring'),
    S('asterisk-below', 'Asterisks Below', ['decorative'], marks([0x0359]), 'stars asterisk'),
    S('lined-box', 'Lined Boxes', ['lines', 'enclosed', 'decorative'], wrapEach(`[${box}`, `${box}]`), 'box brackets underline'),

    // Glitch
    S('zalgo', 'Zalgo', ['popular', 'glitch'], zalgo(['up', 'mid', 'down'], 0x5a1), 'creepy cursed glitch spooky'),
    S('zalgo-up', 'Zalgo Rising', ['glitch'], zalgo(['up'], 0x5a2), 'creepy cursed above'),
    S('zalgo-down', 'Zalgo Dripping', ['glitch'], zalgo(['down'], 0x5a3), 'creepy cursed below melting'),
    S('glitch', 'Glitch', ['glitch'], glitch, 'corrupted broken distorted'),

    // Look-alike letters from other scripts
    S('cyrillic', 'Faux Cyrillic', ['decorative'], map(L.cyrillic), 'russian look-alike'),
    S('greek', 'Greek Style', ['decorative'], map(L.greek), 'neat look-alike'),
    S('japanese', 'Japanese Style', ['popular', 'decorative'], map(L.japanese), 'asian chinese kanji look-alike'),
    S('canadian', 'Syllabics Style', ['decorative'], map(L.canadian), 'canadian look-alike'),
    S('squiggle', 'Squiggle', ['decorative'], map(L.squiggle), 'thai look-alike'),
    S('currency', 'Currency', ['decorative'], map(L.currency), 'money dollar look-alike'),
    S('yi', 'Yi Style', ['decorative'], map(L.yi), 'asian look-alike'),
    S('ethiopic', 'Ethiopic Style', ['decorative'], map(L.ethiopic), 'african look-alike'),
    S('cherokee', 'Cherokee Style', ['decorative'], map(L.cherokee), 'look-alike'),
    S('hooked', 'Hooked', ['decorative'], map(L.hooked), 'look-alike curly'),
    S('stroked', 'Stroked', ['decorative'], map(L.stroked), 'crossed bar look-alike'),
    S('accented', 'Accented', ['decorative'], map(L.accented), 'accents diacritics'),
    S('rock-dots', 'Rock Dots', ['decorative'], map(L.rockDots), 'umlaut metal dots'),

    // Playful
    S('alternating', 'Alternating Caps', ['decorative'], alternating, 'mocking spongebob mixed case'),
    S('inverted-case', 'Inverted Case', ['decorative'], invertCase, 'swap case'),
    S('leet', 'Leetspeak', ['decorative'], map(LEET), '1337 gamer hacker numbers'),
    S('spaced', 'Spaced', ['decorative'], spaced, 'wide letter spacing'),
    S('clap', 'Clap Between Words', ['decorative'], clap, 'emoji clapping'),

    // Brackets around each character
    S('lenticular', 'Lenticular Brackets', ['enclosed', 'decorative'], wrapEach('【', '】'), 'brackets japanese'),
    S('corner-brackets', 'Corner Brackets', ['enclosed', 'decorative'], wrapEach('『', '』'), 'brackets japanese'),
    S('tortoise-brackets', 'White Lenticular Brackets', ['enclosed', 'decorative'], wrapEach('〖', '〗'), 'brackets'),
    S('white-parentheses', 'Double Parentheses', ['enclosed', 'decorative'], wrapEach('⦅', '⦆'), 'brackets'),

    // A symbol between letters
    S('hearts-between', 'Hearts Between', ['decorative'], between('♥'), 'love'),
    S('stars-between', 'Stars Between', ['decorative'], between('★'), 'star'),
    S('flowers-between', 'Flowers Between', ['decorative'], between('❀'), 'flower floral'),
    S('sparkles-between', 'Sparkles Between', ['decorative'], between('✦'), 'sparkle star'),
    S('dots-between', 'Dots Between', ['decorative'], between('•'), 'bullet dot'),
    S('tildes-between', 'Waves Between', ['decorative'], between('~'), 'tilde wave'),

    // Frames around each line
    S('frame-wings', 'Royal Wings', ['popular', 'decorative'], frame('꧁༺ ', ' ༻꧂'), 'frame wings gaming name'),
    S('frame-shooting-stars', 'Shooting Stars', ['popular', 'decorative'], frame('★彡 ', ' 彡★'), 'frame star'),
    S('frame-retro', 'Retro Swirl', ['decorative'], frame('•°¯`•• ', ' ••´¯°•'), 'frame 90s'),
    S('frame-flowers', 'Flower Frame', ['decorative'], frame('✿ ', ' ✿'), 'frame flower'),
    S('frame-hearts', 'Heart Frame', ['decorative'], frame('♡ ', ' ♡'), 'frame love'),
    S('frame-aesthetic-stars', 'Aesthetic Stars', ['decorative'], frame('⋆｡°✩ ', ' ✩°｡⋆'), 'frame star aesthetic'),
    S('frame-butterfly', 'Butterfly Wings', ['decorative'], frame('ʚ ', ' ɞ'), 'frame butterfly cute'),
    S('frame-bars', 'Sound Bars', ['decorative'], frame('▁▂▄▅▆▇█ ', ' █▇▆▅▄▂▁'), 'frame bars music'),
    S('frame-sparkle-trail', 'Sparkle Trail', ['decorative'], frame('✧･ﾟ: *✧･ﾟ:* ', ' *:･ﾟ✧*:･ﾟ✧'), 'frame sparkle aesthetic'),
    S('frame-classic', 'Classic Swirl', ['decorative'], frame('×º°”˜`”°º× ', ' ×º°”˜`”°º×'), 'frame'),
    S('frame-swords', 'Crossed Swords', ['decorative'], frame('⚔ ', ' ⚔'), 'frame gaming clan'),
    S('frame-gradient', 'Gradient Blocks', ['decorative'], frame('░▒▓ ', ' ▓▒░'), 'frame blocks'),
    S('frame-fire', 'Fire', ['decorative'], frame('🔥 ', ' 🔥'), 'frame emoji flame hot'),
    S('frame-sparkles-emoji', 'Sparkles', ['decorative'], frame('✨ ', ' ✨'), 'frame emoji aesthetic'),
    S('frame-kaomoji', 'Kaomoji Hug', ['decorative'], frame('(っ◔◡◔)っ ♥ ', ' ♥'), 'frame cute face'),
    S('frame-divider', 'Divider Lines', ['decorative'], frame('──── ', ' ────'), 'frame line heading'),
    S('frame-gamer', 'Gamer Tag', ['decorative'], frame('xX ', ' Xx'), 'frame gaming username xx'),
    S('frame-crown', 'Crown', ['decorative'], frame('♛ ', ' ♛'), 'frame king queen'),
    S('frame-music', 'Music Notes', ['decorative'], frame('♪♫ ', ' ♫♪'), 'frame song'),
    S('frame-wave', 'Sound Wave', ['decorative'], frame('ıllıllı ', ' ıllıllı'), 'frame music'),
    S('frame-bow', 'Ribbon', ['decorative'], frame('୨୧ ', ' ୨୧'), 'frame bow cute aesthetic'),
    S('frame-moon', 'Moon', ['decorative'], frame('☾ ', ' ☽'), 'frame night'),
    S('frame-brackets', 'Lenticular Title', ['decorative'], frame('【 ', ' 】'), 'frame brackets heading'),
    S('frame-skull', 'Skulls', ['decorative'], frame('☠ ', ' ☠'), 'frame gaming spooky'),
    S('frame-checker', 'Checker Blocks', ['decorative'], frame('▀▄▀▄ ', ' ▄▀▄▀'), 'frame blocks retro'),
    S('frame-butterflies', 'Butterflies', ['decorative'], frame('Ƹ̵̡Ӝ̵̨̄Ʒ ', ' Ƹ̵̡Ӝ̵̨̄Ʒ'), 'frame butterfly'),
];

export const STYLE_COUNT = STYLES.length;

// ---------------------------------------------------------------- presets
// Suggestions, not compatibility guarantees: platforms change what they
// accept and how they display it.

export const PRESETS = [
    {
        id: 'instagram-bio', label: 'Instagram Bio',
        styles: ['sans-bold', 'bold-script', 'small-caps', 'double-struck', 'sans-italic', 'frame-aesthetic-stars', 'frame-sparkles-emoji', 'dots-between', 'frame-bow', 'superscript'],
        note: 'Keep your name and username in plain letters: people search for you by them. Decorative characters can use more of the bio limit than they appear to.',
    },
    {
        id: 'instagram-caption', label: 'Instagram Caption',
        styles: ['sans-bold', 'sans-italic', 'bold', 'small-caps', 'frame-divider', 'frame-sparkles-emoji', 'frame-hearts'],
        note: 'Use styled text for a heading line, not the whole caption. Hashtags only work in plain letters.',
    },
    {
        id: 'facebook', label: 'Facebook',
        styles: ['sans-bold', 'sans-italic', 'bold', 'bold-script', 'underline', 'strikethrough'],
        note: 'Fine for posts and comments. Facebook profile names must use standard characters, so styled names are usually rejected.',
    },
    {
        id: 'tiktok', label: 'TikTok',
        styles: ['sans-bold', 'bold-script', 'small-caps', 'vaporwave', 'frame-shooting-stars', 'japanese', 'circled'],
        note: 'Use it in your bio or display name. Keep the words people search for in plain letters in your captions.',
    },
    {
        id: 'whatsapp', label: 'WhatsApp',
        styles: ['script', 'double-struck', 'circled', 'upside-down', 'sans-bold', 'monospace'],
        note: 'WhatsApp already has *bold*, _italic_, ~strikethrough~ and ```monospace``` formatting, which screen readers handle better. Use these styles for decoration.',
    },
    {
        id: 'discord', label: 'Discord',
        styles: ['small-caps', 'sans-bold', 'bold-fraktur', 'full-width', 'frame-swords', 'frame-wings', 'zalgo-up'],
        note: 'Good for nicknames and channel or server names. Discord messages already support **bold**, *italic* and __underline__. Servers may moderate heavy Zalgo text.',
    },
    {
        id: 'x', label: 'X / Twitter',
        styles: ['sans-bold', 'sans-italic', 'bold', 'bold-script', 'double-struck', 'monospace'],
        note: 'X counts many styled characters as two toward the post limit, and screen readers may read them letter by letter or skip them.',
    },
    {
        id: 'gaming', label: 'Gaming Usernames',
        styles: ['leet', 'frame-gamer', 'alternating', 'small-caps', 'all-small-caps', 'frame-wings', 'frame-swords', 'full-width'],
        note: 'Many games only accept letters, numbers and a few symbols in usernames. Leetspeak, Gamer Tag and Alternating Caps use ordinary keyboard characters, so they are the most likely to be accepted.',
    },
];

// ----------------------------------------------------------------- engine

// Converts `text` with every requested style. Segments the input once.
export function convertAll(text, ids = null, options = {}) {
    const gs = graphemes(String(text ?? '').replace(/\r\n?/g, '\n'));
    const list = ids ? STYLES.filter((s) => ids.includes(s.id)) : STYLES;
    return Object.fromEntries(list.map((s) => [s.id, s.transform(gs, options)]));
}

// Input normalised to \n line breaks and split into grapheme clusters;
// segment once and pass the result to convertSegmented for many styles.
export const segment = (text) => graphemes(String(text ?? '').replace(/\r\n?/g, '\n'));

export function convertSegmented(gs, id, options = {}) {
    const style = STYLES.find((s) => s.id === id);
    if (!style) throw new Error(`Unknown style: ${id}`);
    return style.transform(gs, options);
}

export function convert(text, id, options = {}) {
    return convertSegmented(segment(text), id, options);
}

export function stylesIn(category) {
    return category === 'all' ? STYLES : STYLES.filter((s) => s.categories.includes(category));
}

const normalise = (s) => s.toLowerCase().normalize('NFKD').replace(/\p{M}/gu, '').replace(/[^a-z0-9]+/g, ' ').trim();

// Search by style name, keywords or category name.
export function matchesSearch(style, query) {
    const q = normalise(query);
    if (!q) return true;
    const categories = style.categories.map((id) => CATEGORIES.find(([cid]) => cid === id)?.[1] ?? '').join(' ');
    const haystack = normalise(`${style.name} ${style.keywords} ${categories}`);
    return q.split(' ').every((word) => haystack.includes(word));
}
