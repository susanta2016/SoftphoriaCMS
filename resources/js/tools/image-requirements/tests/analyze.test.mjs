import { test } from 'node:test';
import assert from 'node:assert/strict';
import { analyzeBytes, readExif, sniffFormat } from '../src/analyze.js';
import { exif, jpeg, pHYsDpi, png, webpExtended, webpLossless } from './helpers/fixtures.mjs';

const text = (s) => Uint8Array.from([...s].map((c) => c.charCodeAt(0)));

test('formats are recognised by content, not by name', () => {
    assert.equal(sniffFormat(png({ width: 1, height: 1 })), 'png');
    assert.equal(sniffFormat(jpeg({ width: 1, height: 1 })), 'jpeg');
    assert.equal(sniffFormat(webpExtended({ width: 1, height: 1 })), 'webp');
    assert.equal(sniffFormat(text('GIF89a\0\0\0\0\0\0')), 'gif');
    assert.equal(sniffFormat(text('\0\0\0\x18ftypheic\0\0\0\0')), 'heic');
    assert.equal(sniffFormat(text('\0\0\0\x18ftypavif\0\0\0\0')), 'avif');
    assert.equal(sniffFormat(text('<?xml version="1.0"?><svg xmlns="x"></svg>')), 'svg');
    assert.equal(sniffFormat(text('%PDF-1.7 not an image')), null);
    assert.equal(sniffFormat(new Uint8Array(4)), null);
});

test('PNG: size, alpha channel, tRNS, animation, DPI, colour profile', () => {
    const rgba = analyzeBytes(png({ width: 1080, height: 1350, colorType: 6 }));
    assert.equal(rgba.width, 1080);
    assert.equal(rgba.height, 1350);
    assert.equal(rgba.alphaChannel, 'yes');
    assert.equal(rgba.animated, 'no');

    assert.equal(analyzeBytes(png({ width: 10, height: 10, colorType: 2 })).alphaChannel, 'no');
    assert.equal(analyzeBytes(png({ width: 10, height: 10, colorType: 2, chunks: [['tRNS', [0, 0, 0, 0, 0, 0]]] })).alphaChannel, 'yes');
    assert.equal(analyzeBytes(png({ width: 10, height: 10, chunks: [['acTL', [0, 0, 0, 2, 0, 0, 0, 0]]] })).animated, 'yes');
    const meta = analyzeBytes(png({ width: 10, height: 10, chunks: [pHYsDpi(300), ['sRGB', [0]]] }));
    assert.equal(meta.dpi, 300);
    assert.equal(meta.colorProfile.srgb, true);
    assert.equal(meta.cmyk, false);
});

test('JPEG: size, CMYK, progressive, DPI, ICC and EXIF', () => {
    const plain = analyzeBytes(jpeg({ width: 1080, height: 1350, dpi: 72 }));
    assert.deepEqual([plain.width, plain.height, plain.dpi, plain.cmyk, plain.progressive, plain.alphaChannel], [1080, 1350, 72, false, false, 'no']);

    const cmyk = analyzeBytes(jpeg({ width: 400, height: 300, components: 4, progressive: true, icc: true }));
    assert.equal(cmyk.cmyk, true);
    assert.equal(cmyk.progressive, true);
    assert.equal(cmyk.colorProfile.icc, true);

    const photo = analyzeBytes(jpeg({ width: 4032, height: 3024, exifBlock: exif({ orientation: 6, gps: true, make: 'Apple' }) }));
    assert.equal(photo.exif.orientation, 6);
    assert.equal(photo.exif.hasGps, true);
    assert.equal(photo.exif.make, 'Apple');
    assert.equal(analyzeBytes(jpeg({ width: 10, height: 10, exifBlock: exif() })).exif.hasGps, false);
});

test('WebP: extended (VP8X) and lossless (VP8L) headers', () => {
    const ext = analyzeBytes(webpExtended({ width: 1200, height: 630, alpha: true, animated: true, icc: true }));
    assert.deepEqual([ext.width, ext.height, ext.alphaChannel, ext.animated, ext.colorProfile.icc], [1200, 630, 'yes', 'yes', true]);

    const lossless = analyzeBytes(webpLossless({ width: 800, height: 600, alpha: true }));
    assert.deepEqual([lossless.width, lossless.height, lossless.alphaChannel], [800, 600, 'yes']);
    assert.equal(analyzeBytes(webpLossless({ width: 800, height: 600 })).alphaChannel, 'no');
});

test('truncated or corrupt data never throws; unknown fields stay unknown', () => {
    const cut = jpeg({ width: 1080, height: 1350 }).subarray(0, 6);
    const r = analyzeBytes(Uint8Array.from([...cut, 0, 0, 0, 0, 0, 0]));
    assert.equal(r.format, 'jpeg');
    assert.equal(r.width, null);

    const badPng = png({ width: 10, height: 10 });
    badPng.fill(0xff, 16, 24); // break the IHDR length/type area
    assert.doesNotThrow(() => analyzeBytes(badPng));

    assert.equal(readExif(Uint8Array.from([1, 2, 3])), null);
    assert.equal(readExif(text('XX\0*\0\0\0\x08')), null);
});
