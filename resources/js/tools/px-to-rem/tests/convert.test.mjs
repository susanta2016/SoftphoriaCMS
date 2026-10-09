import { test } from 'node:test';
import assert from 'node:assert/strict';
import { convertList, format, parseNumber, parseRoot, pxToRem, remToPx, BULK_LIMIT } from '../src/convert.js';

test('px to rem at the default 16px root', () => {
    const cases = [[1, '0.0625'], [2, '0.125'], [4, '0.25'], [8, '0.5'], [10, '0.625'], [12, '0.75'], [14, '0.875'], [16, '1'], [18, '1.125'],
        [20, '1.25'], [24, '1.5'], [28, '1.75'], [32, '2'], [40, '2.5'], [48, '3'], [64, '4'], [80, '5'], [96, '6']];
    for (const [px, rem] of cases) assert.equal(format(pxToRem(px, 16)), rem, `${px}px`);
});

test('rem to px at the default 16px root', () => {
    assert.equal(format(remToPx(1, 16)), '16');
    assert.equal(format(remToPx(1.5, 16)), '24');
    assert.equal(format(remToPx(0.875, 16)), '14');
    assert.equal(format(remToPx(2.25, 16)), '36');
});

test('custom 10px and 18px roots', () => {
    assert.equal(format(pxToRem(24, 10)), '2.4');
    assert.equal(format(pxToRem(14, 10)), '1.4');
    assert.equal(format(pxToRem(96, 10)), '9.6');
    assert.equal(format(remToPx(1.5, 10)), '15');
    assert.equal(format(pxToRem(24, 18)), '1.3333');
    assert.equal(format(pxToRem(18, 18)), '1');
    assert.equal(format(remToPx(1.5, 18)), '27');
});

test('rounding follows the chosen precision and drops trailing zeros', () => {
    assert.equal(format(pxToRem(10, 16), 2), '0.63');
    assert.equal(format(pxToRem(10, 16), 3), '0.625');
    assert.equal(format(pxToRem(1, 18), 2), '0.06');
    assert.equal(format(pxToRem(1, 18), 5), '0.05556');
    assert.equal(format(1.005, 2), '1.01', 'binary fraction does not round down');
    assert.equal(format(1.5, 4), '1.5');
    assert.equal(format(-0.0000001, 4), '0', 'no negative zero');
    assert.equal(format(-1.25, 4), '-1.25');
    assert.equal(format(pxToRem(13.5, 16)), '0.8438');
});

test('numbers are parsed strictly', () => {
    assert.equal(parseNumber('24'), 24);
    assert.equal(parseNumber(' 0.5 '), 0.5);
    assert.equal(parseNumber('.75'), 0.75);
    assert.equal(parseNumber('-8'), -8);
    assert.equal(parseNumber('1.'), 1);
    for (const bad of ['', ' ', 'abc', '1e2', '12px', '1,5', 'NaN', 'Infinity', '--1', '1.2.3']) assert.equal(parseNumber(bad), null, bad);
});

test('the root font size must be between 1 and 200', () => {
    assert.equal(parseRoot('16'), 16);
    assert.equal(parseRoot('10'), 10);
    assert.equal(parseRoot('18.5'), 18.5);
    for (const bad of ['0', '-16', '', 'abc', '0.5', '201']) assert.equal(parseRoot(bad), null, bad);
});

test('a list splits on new lines and commas, with optional units', () => {
    const { items, truncated } = convertList('12, 16px\n24\n\n 1.5 , 32 px,', 'px-rem', 16);
    assert.equal(truncated, false);
    assert.deepEqual(items.map((i) => [i.input, i.output]), [['12', '0.75rem'], ['16px', '1rem'], ['24', '1.5rem'], ['1.5', '0.0938rem'], ['32 px', '2rem']]);
});

test('a list also splits on spaces, so CSS shorthand values convert', () => {
    const out = (text, dir = 'px-rem') => convertList(text, dir, 16).items.map((i) => i.output ?? `ERR ${i.input}`);
    assert.deepEqual(out('12px 16px 24px'), ['0.75rem', '1rem', '1.5rem']);
    assert.deepEqual(out('12 16, 24\n32\t40'), ['0.75rem', '1rem', '1.5rem', '2rem', '2.5rem']);
    assert.deepEqual(out('8 px  16px'), ['0.5rem', '1rem'], 'a unit after a space stays with its number');
    assert.deepEqual(out('1rem 1.5rem', 'rem-px'), ['16px', '24px']);
    assert.deepEqual(out('padding: 8px 16px;'), ['ERR padding:', '0.5rem', '1rem'], 'a pasted declaration: values convert, the property name is reported');
    assert.deepEqual(out('px 8'), ['ERR px', '0.5rem'], 'a unit with no number before it is reported');
});

test('rem to px lists, custom root and precision', () => {
    const { items } = convertList('1rem\n1.5\n0.875rem', 'rem-px', 10, 2);
    assert.deepEqual(items.map((i) => i.output), ['10px', '15px', '8.75px']);
});

test('invalid list items are reported without stopping the others', () => {
    const { items } = convertList('16, abc, 1.5rem, 12pt, 8', 'px-rem', 16);
    assert.deepEqual(items.map((i) => i.ok), [true, false, false, false, true]);
    assert.equal(items[1].error, 'Not a number.');
    assert.match(items[2].error, /rem value — switch the direction/);
    assert.equal(items[3].error, 'Not a number.');
    assert.equal(items[4].output, '0.5rem');
});

test('negative values convert (they are valid for margins and offsets)', () => {
    assert.deepEqual(convertList('-8', 'px-rem', 16).items.map((i) => i.output), ['-0.5rem']);
});

test('empty input gives no items, and very long lists are capped', () => {
    assert.deepEqual(convertList(' \n , ', 'px-rem', 16).items, []);
    const long = convertList(Array.from({ length: BULK_LIMIT + 5 }, (_, i) => i + 1).join(','), 'px-rem', 16);
    assert.equal(long.items.length, BULK_LIMIT);
    assert.equal(long.truncated, true);
});
