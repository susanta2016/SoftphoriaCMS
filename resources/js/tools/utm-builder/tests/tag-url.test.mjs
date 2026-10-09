import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tagUrl } from '../src/tag-url.js';

// The page parses the address the same way (https:// added when missing).
const parse = (raw) => new URL(/^[a-z][a-z0-9+.-]*:/i.test(raw) ? raw : `https://${raw}`);
const tag = (raw, values = {}) => tagUrl(parse(raw), { utm_source: 'newsletter', utm_medium: 'email', utm_campaign: 'spring_sale', ...values });
const UTM = 'utm_source=newsletter&utm_medium=email&utm_campaign=spring_sale';

// The seven failures found on 2026-10-09 with the URLSearchParams version

test('an existing %20 escape is kept, not turned into +', () => {
    assert.equal(tag('https://example.com/search?q=a%20b&tag=c%2Bd'), `https://example.com/search?q=a%20b&tag=c%2Bd&${UTM}`);
});

test('a bare key keeps having no =', () => {
    assert.equal(tag('https://example.com/?flag&x=1'), `https://example.com/?flag&x=1&${UTM}`);
});

test('an unescaped URL inside a parameter value is left as it was', () => {
    assert.equal(tag('https://example.com/?next=/a/b?c=1'), `https://example.com/?next=/a/b?c=1&${UTM}`);
});

test('a ; separator is not escaped into the value', () => {
    assert.equal(tag('https://example.com/?a=1;b=2'), `https://example.com/?a=1;b=2&${UTM}`);
});

test('an escape that is not valid UTF-8 is not corrupted', () => {
    assert.equal(tag('https://example.com/?name=%E9'), `https://example.com/?name=%E9&${UTM}`);
});

test('a blank optional field keeps the UTM value already in the destination', () => {
    assert.equal(tag('https://example.com/?utm_term=keep'), `https://example.com/?utm_term=keep&${UTM}`);
    assert.equal(tag('https://example.com/?utm_content=hero&utm_id=abc'), `https://example.com/?utm_content=hero&utm_id=abc&${UTM}`);
});

test('spaces in values are encoded as %20', () => {
    assert.equal(
        tag('https://example.com', { utm_campaign: 'Spring Sale & More' }),
        'https://example.com/?utm_source=newsletter&utm_medium=email&utm_campaign=Spring%20Sale%20%26%20More',
    );
});

// Behaviour that already worked and must not change

test('https:// is added when missing', () => {
    assert.equal(tag('example.com'), `https://example.com/?${UTM}`);
    assert.equal(tag('www.example.com/landing-page'), `https://www.example.com/landing-page?${UTM}`);
});

test('existing parameters and the fragment are kept, UTMs go before the #', () => {
    assert.equal(tag('https://example.com/page?ref=abc#pricing'), `https://example.com/page?ref=abc&${UTM}#pricing`);
    assert.equal(tag('https://example.com/page#pricing'), `https://example.com/page?${UTM}#pricing`);
});

test('a supplied UTM replaces every existing copy of that key, others are kept', () => {
    assert.equal(
        tag('https://example.com/?utm_source=old&a=1&utm_source=old2&utm_term=keep'),
        `https://example.com/?a=1&utm_term=keep&${UTM}`,
    );
});

test('a supplied optional UTM replaces the existing one', () => {
    assert.equal(tag('https://example.com/?utm_term=old', { utm_term: 'new' }), `https://example.com/?${UTM}&utm_term=new`);
});

test('an encoded or +-spaced copy of a UTM key is still recognised', () => {
    assert.equal(tag('https://example.com/?utm%5Fsource=old&b=2'), `https://example.com/?b=2&${UTM}`);
});

test('all six fields are added in a fixed order', () => {
    assert.equal(
        tag('https://example.com/', { utm_term: 'website design', utm_content: 'hero_button', utm_id: 'abc_123' }),
        `https://example.com/?${UTM}&utm_term=website%20design&utm_content=hero_button&utm_id=abc_123`,
    );
});

test('values are encoded once, never twice', () => {
    assert.equal(tag('https://example.com/', { utm_campaign: '50%_off' }), 'https://example.com/?utm_source=newsletter&utm_medium=email&utm_campaign=50%25_off');
    assert.equal(tag('https://example.com/', { utm_campaign: 'été/2026?x' }), 'https://example.com/?utm_source=newsletter&utm_medium=email&utm_campaign=%C3%A9t%C3%A9%2F2026%3Fx');
});

test('empty pairs from a trailing ? or && do not leave stray separators', () => {
    assert.equal(tag('https://example.com/?'), `https://example.com/?${UTM}`);
    assert.equal(tag('https://example.com/?a=1&&b=2&'), `https://example.com/?a=1&b=2&${UTM}`);
});

test('international domain names and paths are encoded by the URL parser as before', () => {
    assert.equal(tag('https://bücher.example/straße'), `https://xn--bcher-kva.example/stra%C3%9Fe?${UTM}`);
});

test('the URL passed in is not modified', () => {
    const url = parse('https://example.com/?a=1');
    tagUrl(url, { utm_source: 'x', utm_medium: 'y', utm_campaign: 'z' });
    assert.equal(url.href, 'https://example.com/?a=1');
});
