import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    BOLD, CIRCLED, DOUBLE_STRUCK, FRAKTUR, FULL_WIDTH, ITALIC, MONOSPACE, NEGATIVE_CIRCLED, SCRIPT, SMALL_CAPS, SUBSCRIPT,
    SUPERSCRIPT, UPSIDE_DOWN,
} from '../src/alphabets.js';
import { countGraphemes, fallbackGraphemes, graphemes } from '../src/graphemes.js';
import {
    CATEGORIES, convert, convertAll, matchesSearch, MAX_INPUT, PRESETS, STYLES, stylesIn, ZALGO_MAX_MARKS,
} from '../src/styles.js';

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789';
const UNASSIGNED_OR_BROKEN = /\p{Cn}|\p{Cs}/u; // unassigned code points or lone surrogates
const FAMILY = '👨‍👩‍👧';
const FLAG = '🇺🇸';
const WAVE = '👋🏽';
const cps = (s) => Array.from(s).map((c) => c.codePointAt(0).toString(16));

test('there are at least 100 styles, with unique ids and genuinely different output', () => {
    assert.ok(STYLES.length >= 100, `${STYLES.length} styles`);
    assert.equal(new Set(STYLES.map((s) => s.id)).size, STYLES.length);
    assert.equal(new Set(STYLES.map((s) => s.name)).size, STYLES.length, 'unique names');
    const outputs = Object.values(convertAll('The quick brown fox jumps over the lazy dog 0123456789'));
    assert.equal(new Set(outputs).size, STYLES.length, 'no two styles give the same output');
});

test('every style produces only assigned code points and no broken surrogates', () => {
    for (const [id, out] of Object.entries(convertAll(`${ALPHABET} .,!?'"()[]{}<>&_-+=`))) {
        assert.doesNotMatch(out, UNASSIGNED_OR_BROKEN, `${id}: ${cps(out).join(' ')}`);
    }
});

test('mathematical alphabets use the correct code points, including the holes', () => {
    assert.equal(BOLD.A, '\u{1D400}');
    assert.equal(BOLD.a, '\u{1D41A}');
    assert.equal(BOLD[0], '\u{1D7CE}');
    assert.equal(ITALIC.h, 'ℎ', 'italic h is the Planck constant');
    assert.equal(ITALIC.g, '\u{1D454}');
    const scriptHoles = { B: 0x212c, E: 0x2130, F: 0x2131, H: 0x210b, I: 0x2110, L: 0x2112, M: 0x2133, R: 0x211b, e: 0x212f, g: 0x210a, o: 0x2134 };
    for (const [letter, point] of Object.entries(scriptHoles)) assert.equal(SCRIPT[letter].codePointAt(0), point, `script ${letter}`);
    assert.equal(SCRIPT.A, '\u{1D49C}');
    assert.equal(SCRIPT.z, '\u{1D4CF}');
    for (const [letter, point] of Object.entries({ C: 0x212d, H: 0x210c, I: 0x2111, R: 0x211c, Z: 0x2128 })) assert.equal(FRAKTUR[letter].codePointAt(0), point, `fraktur ${letter}`);
    for (const [letter, point] of Object.entries({ C: 0x2102, H: 0x210d, N: 0x2115, P: 0x2119, Q: 0x211a, R: 0x211d, Z: 0x2124 })) assert.equal(DOUBLE_STRUCK[letter].codePointAt(0), point, `double-struck ${letter}`);
    assert.equal(DOUBLE_STRUCK[1], '\u{1D7D9}');
    assert.equal(MONOSPACE.z, '\u{1D6A3}');
    assert.equal(MONOSPACE[9], '\u{1D7FF}');
});

test('enclosed, full-width and small alphabets use the correct code points', () => {
    assert.equal(CIRCLED.A, 'Ⓐ');
    assert.equal(CIRCLED.z, 'ⓩ');
    assert.equal(CIRCLED[0], '⓪');
    assert.equal(CIRCLED[9], '⑨');
    assert.equal(NEGATIVE_CIRCLED.a, '🅐', 'capitals only, used for lower case too');
    assert.equal(NEGATIVE_CIRCLED[0], '⓿');
    assert.equal(FULL_WIDTH.A, 'Ａ');
    assert.equal(FULL_WIDTH['~'], '～');
    assert.equal(FULL_WIDTH[' '], '　');
    assert.equal(SUPERSCRIPT[2], '²');
    assert.equal(SUPERSCRIPT.q, undefined, 'no superscript q: left unchanged');
    assert.equal(SUBSCRIPT.b, undefined, 'no subscript b: left unchanged');
    assert.equal(SMALL_CAPS.A, undefined, 'small caps keeps capitals');
    assert.equal(SMALL_CAPS.a, 'ᴀ');
    assert.equal(UPSIDE_DOWN.a, 'ɐ');
});

test('upper and lower case are mapped independently', () => {
    for (const id of ['bold', 'italic', 'script', 'fraktur', 'double-struck', 'monospace', 'circled', 'full-width', 'parenthesized', 'sans-bold']) {
        const upper = convert('ABC', id);
        const lower = convert('abc', id);
        assert.notEqual(upper, lower, id);
        assert.equal(Array.from(upper).length, 3, id);
    }
});

test('numbers are converted where a mapping exists and kept otherwise', () => {
    assert.equal(convert('2026', 'bold'), '𝟐𝟎𝟐𝟔');
    assert.equal(convert('2026', 'double-struck'), '𝟚𝟘𝟚𝟞');
    assert.equal(convert('2026', 'circled'), '②⓪②⑥');
    assert.equal(convert('2026', 'italic'), '2026', 'no italic digits in Unicode');
    assert.equal(convert('10', 'parenthesized'), '⑴0', 'no parenthesized zero');
});

test('spaces, punctuation and line breaks are preserved', () => {
    const input = 'Hi, there!\nSecond line?\n\nFourth.';
    for (const [id, out] of Object.entries(convertAll(input))) {
        assert.equal(out.split('\n').length, 4, `${id} keeps the line count`);
    }
    assert.equal(convert('Hi, there!', 'bold'), '𝐇𝐢, 𝐭𝐡𝐞𝐫𝐞!');
    assert.equal(convert('a\r\nb', 'bold'), '𝐚\n𝐛', 'CRLF becomes a single line break');
});

test('emoji, flags and ZWJ sequences survive every style intact', () => {
    const input = `Hi ${WAVE} ${FAMILY} ${FLAG} ok`;
    for (const [id, out] of Object.entries(convertAll(input))) {
        for (const emoji of [WAVE, FAMILY, FLAG]) assert.ok(out.includes(emoji), `${id} keeps ${emoji}: ${out}`);
    }
});

test('accents and combining marks stay attached', () => {
    assert.equal(convert('é', 'bold'), '𝐞́', 'precomposed é maps its base and keeps the accent');
    assert.equal(convert('é', 'bold'), '𝐞́');
    assert.equal(convert('é', 'circled'), 'é', 'look-alike styles leave accented letters alone');
    assert.equal(convert('ab́c', 'reverse'), 'cb́a', 'reverse keeps the accent on its letter');
    assert.equal(convert('ñ', 'underline'), 'ñ̲');
});

test('text in other scripts is preserved', () => {
    const input = 'Привет नमस्ते مرحبا 你好';
    for (const id of ['bold', 'script', 'fraktur', 'circled', 'full-width', 'small-caps', 'japanese', 'leet']) {
        const out = convert(`A ${input}`, id);
        assert.ok(out.endsWith(id === 'full-width' ? input.replaceAll(' ', '　') : input), `${id}: ${out}`);
    }
    assert.equal(convert('नमस्ते', 'reverse'), graphemes('नमस्ते').reverse().join(''), 'reversed by grapheme, not code unit');
});

test('empty input gives empty output for every style', () => {
    for (const [id, out] of Object.entries(convertAll(''))) assert.equal(out, '', id);
    for (const [id, out] of Object.entries(convertAll('   '))) assert.equal(out.trim(), '', id);
});

test('flips and reversals', () => {
    assert.equal(convert('Hello', 'reverse'), 'olleH');
    assert.equal(convert('Hello World', 'reverse-words'), 'olleH dlroW');
    assert.equal(convert('Hello', 'upside-down'), 'ollǝH');
    assert.equal(convert('one\ntwo', 'upside-down'), 'oʍʇ\nǝuo', 'upside down also turns the line order');
    assert.equal(convert('one\ntwo', 'reverse'), 'eno\nowt', 'reverse keeps the line order');
    assert.equal(convert('bad?', 'mirrored'), '⸮bɒd');
});

test('regional indicators never merge into flags', () => {
    const out = convert('US', 'regional');
    const zwsp = String.fromCodePoint(0x200b);
    assert.equal(out, `🇺${zwsp}🇸${zwsp}`);
    assert.ok(!graphemes(out).includes('🇺🇸'));
});

test('lines run across spaces, marks skip emoji', () => {
    assert.equal(convert('a b', 'underline'), 'a̲ ̲b̲');
    assert.equal(convert(`a ${WAVE}`, 'strikethrough'), `a̶ ̶${WAVE}`);
    assert.equal(convert('a b', 'slash'), 'a̸ b̸', 'slashes skip spaces');
});

test('word and line decorations', () => {
    assert.equal(convert('ab cd', 'hearts-between'), 'a♥b c♥d');
    assert.equal(convert('ab cd', 'spaced'), 'a b   c d');
    assert.equal(convert('one\n\ntwo', 'frame-hearts'), '♡ one ♡\n\n♡ two ♡', 'empty lines stay empty');
    assert.equal(convert('a b c', 'clap'), 'a 👏 b 👏 c');
    assert.equal(convert('hello', 'alternating'), 'hElLo');
    assert.equal(convert('Hello', 'inverted-case'), 'hELLO');
    assert.equal(convert('Elite', 'leet'), '3l173');
});

test('zalgo is deterministic, follows the intensity and stays bounded', () => {
    const low = convert('Hello', 'zalgo', { zalgoLevel: 'low' });
    const high = convert('Hello', 'zalgo', { zalgoLevel: 'high' });
    assert.equal(convert('Hello', 'zalgo', { zalgoLevel: 'low' }), low, 'same input, same output');
    assert.ok(Array.from(high).length > Array.from(low).length);
    assert.ok(convert('Hello there', 'zalgo').startsWith(convert('Hello', 'zalgo')), 'typing more does not change earlier characters');
    assert.equal(graphemes(high).length, 5, 'marks attach to their letters');

    const long = 'x'.repeat(MAX_INPUT);
    for (const id of ['zalgo', 'zalgo-up', 'zalgo-down', 'glitch']) {
        const out = convert(long, id, { zalgoLevel: 'high' });
        assert.ok(Array.from(out).length <= MAX_INPUT + ZALGO_MAX_MARKS, `${id} is capped`);
    }
});

test('long input converts quickly for every style', () => {
    const long = ('Fancy text 123 ✨ café\n').repeat(Math.floor(MAX_INPUT / 22));
    const started = performance.now();
    const out = convertAll(long);
    const elapsed = performance.now() - started;
    assert.equal(Object.keys(out).length, STYLES.length);
    assert.ok(elapsed < 1500, `${Math.round(elapsed)} ms`);
});

test('the grapheme fallback agrees with Intl.Segmenter on tricky input', () => {
    const samples = [`a${WAVE}b`, FAMILY, `${FLAG}🇬🇧`, 'éx', 'a\r\nb', '❤️ ok', '🏴󠁧󠁢󠁳󠁣󠁴󠁿 scotland', 'नमस्ते'.normalize('NFC')];
    for (const sample of samples.slice(0, 7)) assert.deepEqual(fallbackGraphemes(sample), graphemes(sample), sample);
    assert.equal(countGraphemes(`${FAMILY}${FLAG}é`), 3);
});

test('categories, presets and search', () => {
    assert.equal(CATEGORIES.length, 18);
    for (const [id] of CATEGORIES) assert.ok(stylesIn(id).length > 0, `${id} has styles`);
    for (const style of STYLES) for (const category of style.categories) assert.ok(CATEGORIES.some(([id]) => id === category), `${style.id}: ${category}`);
    assert.equal(PRESETS.length, 8);
    for (const preset of PRESETS) {
        assert.ok(preset.note.length > 20);
        for (const id of preset.styles) assert.ok(STYLES.some((s) => s.id === id), `${preset.id}: ${id}`);
    }
    const find = (query) => STYLES.filter((s) => matchesSearch(s, query)).map((s) => s.id);
    assert.ok(find('tiny').includes('superscript'));
    assert.ok(find('cursive').includes('script'));
    assert.ok(find('Gothic').includes('fraktur'));
    assert.ok(find('bubble').includes('circled'));
    assert.ok(find('upside down').includes('upside-down'));
    assert.ok(find('BOLD').includes('bold'));
    assert.deepEqual(find('zzzz-nothing'), []);
    assert.equal(find('').length, STYLES.length);
});
