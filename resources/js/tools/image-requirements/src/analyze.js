// Reads what the file itself declares: format (by content, never by name),
// stored dimensions, alpha channel, animation, colour profile, CMYK, DPI and
// EXIF (orientation, camera, date, GPS present). Pure: takes the file's bytes.
// Anything that cannot be read reliably is reported as unknown, never guessed.
// Malformed data never throws: the field is simply left unknown.

const ascii = (b, from, len) => (from + len <= b.length ? String.fromCharCode(...b.subarray(from, from + len)) : '');
const u16be = (b, o) => (b[o] << 8) | b[o + 1];
const u32be = (b, o) => ((b[o] << 24) >>> 0) + (b[o + 1] << 16) + (b[o + 2] << 8) + b[o + 3];
const u16le = (b, o) => b[o] | (b[o + 1] << 8);
const u24le = (b, o) => b[o] | (b[o + 1] << 8) | (b[o + 2] << 16);
const u32le = (b, o) => (b[o] | (b[o + 1] << 8) | (b[o + 2] << 16)) + ((b[o + 3] << 24) >>> 0);

export const SUPPORTED_FORMATS = ['jpeg', 'png', 'webp'];

export const FORMAT_NAMES = { jpeg: 'JPEG', png: 'PNG', webp: 'WebP', gif: 'GIF', bmp: 'BMP', tiff: 'TIFF', heic: 'HEIC', avif: 'AVIF', svg: 'SVG' };
const MIME = { jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', gif: 'image/gif', bmp: 'image/bmp', tiff: 'image/tiff', heic: 'image/heic', avif: 'image/avif', svg: 'image/svg+xml' };

/** The format by its first bytes, or null when it is not an image we recognise. */
export function sniffFormat(b) {
    if (!b || b.length < 12) return null;
    if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return 'jpeg';
    if (b[0] === 0x89 && ascii(b, 1, 3) === 'PNG') return 'png';
    if (ascii(b, 0, 4) === 'RIFF' && ascii(b, 8, 4) === 'WEBP') return 'webp';
    if (ascii(b, 0, 4) === 'GIF8') return 'gif';
    if (ascii(b, 0, 2) === 'BM') return 'bmp';
    if ((b[0] === 0x49 && b[1] === 0x49 && b[2] === 0x2a && b[3] === 0) || (b[0] === 0x4d && b[1] === 0x4d && b[2] === 0 && b[3] === 0x2a)) return 'tiff';
    if (ascii(b, 4, 4) === 'ftyp') {
        const brand = ascii(b, 8, 4);
        if (/^(avif|avis)$/.test(brand)) return 'avif';
        if (/^(heic|heix|hevc|heim|heis|mif1|msf1)$/.test(brand)) return 'heic';
        return null;
    }
    const head = ascii(b, 0, Math.min(b.length, 256)).replace(/^﻿/, '').trimStart().toLowerCase();
    if (head.startsWith('<svg') || (head.startsWith('<?xml') && head.includes('<svg'))) return 'svg';
    return null;
}

export const mimeFor = (format) => MIME[format] || 'application/octet-stream';

/**
 * @param {Uint8Array} b  the whole file
 * @returns {object|null} null when the format is not recognised
 */
export function analyzeBytes(b) {
    const format = sniffFormat(b);
    if (!format) return null;
    const base = {
        format, mime: mimeFor(format), width: null, height: null,
        alphaChannel: 'unknown', animated: 'unknown', bitDepth: null, progressive: null,
        colorProfile: { icc: null, srgb: null }, cmyk: null, dpi: null, exif: null,
    };
    try {
        if (format === 'png') return { ...base, ...readPng(b) };
        if (format === 'jpeg') return { ...base, ...readJpeg(b) };
        if (format === 'webp') return { ...base, ...readWebp(b) };
    } catch {
        return base;
    }
    return base;
}

function readPng(b) {
    const out = { alphaChannel: 'no', animated: 'no', colorProfile: { icc: false, srgb: false } };
    let p = 8;
    let colorType = null;
    let trns = false;
    while (p + 8 <= b.length) {
        const len = u32be(b, p);
        const type = ascii(b, p + 4, 4);
        const d = p + 8;
        if (d + len > b.length) break;
        if (type === 'IHDR' && len >= 13) {
            out.width = u32be(b, d);
            out.height = u32be(b, d + 4);
            out.bitDepth = b[d + 8];
            colorType = b[d + 9];
        } else if (type === 'tRNS') {
            trns = true;
        } else if (type === 'acTL') {
            out.animated = 'yes';
        } else if (type === 'iCCP') {
            out.colorProfile.icc = true;
        } else if (type === 'sRGB') {
            out.colorProfile.srgb = true;
        } else if (type === 'pHYs' && len >= 9 && b[d + 8] === 1) {
            out.dpi = Math.round(u32be(b, d) * 0.0254);
        } else if (type === 'eXIf') {
            out.exif = readExif(b.subarray(d, d + len));
        } else if (type === 'IEND') {
            break;
        }
        p = d + len + 4;
    }
    if (colorType === 4 || colorType === 6 || trns) out.alphaChannel = 'yes';
    out.cmyk = false;
    return out;
}

const SOF = new Set([0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf]);

function readJpeg(b) {
    const out = { alphaChannel: 'no', animated: 'no', colorProfile: { icc: false, srgb: null }, cmyk: false, progressive: false };
    let p = 2;
    while (p + 4 <= b.length) {
        if (b[p] !== 0xff) break;
        const marker = b[p + 1];
        if (marker === 0xff) { p += 1; continue; }
        if (marker === 0xd9 || marker === 0xda) break; // end / start of scan: no more headers
        if (marker >= 0xd0 && marker <= 0xd7) { p += 2; continue; }
        const len = u16be(b, p + 2);
        const d = p + 4;
        if (len < 2 || d + len - 2 > b.length) break;
        if (marker === 0xe0 && ascii(b, d, 5) === 'JFIF\0' && len >= 16) {
            const unit = b[d + 7];
            const x = u16be(b, d + 8);
            if (unit === 1 && x > 0) out.dpi = x;
            if (unit === 2 && x > 0) out.dpi = Math.round(x * 2.54);
        } else if (marker === 0xe1 && ascii(b, d, 6) === 'Exif\0\0') {
            out.exif = readExif(b.subarray(d + 6, d + len - 2));
        } else if (marker === 0xe2 && ascii(b, d, 12) === 'ICC_PROFILE\0') {
            out.colorProfile.icc = true;
        } else if (SOF.has(marker) && len >= 8) {
            out.bitDepth = b[d];
            out.height = u16be(b, d + 1);
            out.width = u16be(b, d + 3);
            out.cmyk = b[d + 5] === 4;
            out.progressive = marker === 0xc2 || marker === 0xc6 || marker === 0xca || marker === 0xce;
        }
        p = d + len - 2;
    }
    if (out.exif && out.exif.dpi && !out.dpi) out.dpi = out.exif.dpi;
    return out;
}

function readWebp(b) {
    const out = { alphaChannel: 'no', animated: 'no', colorProfile: { icc: false, srgb: null }, cmyk: false };
    let p = 12;
    const end = Math.min(b.length, 8 + u32le(b, 4));
    while (p + 8 <= end) {
        const type = ascii(b, p, 4);
        const len = u32le(b, p + 4);
        const d = p + 8;
        if (d + len > b.length) break;
        if (type === 'VP8X' && len >= 10) {
            const flags = b[d];
            if (flags & 0x20) out.colorProfile.icc = true;
            if (flags & 0x10) out.alphaChannel = 'yes';
            if (flags & 0x02) out.animated = 'yes';
            out.width = u24le(b, d + 4) + 1;
            out.height = u24le(b, d + 7) + 1;
        } else if (type === 'VP8 ' && len >= 10 && out.width == null) {
            if (b[d + 3] === 0x9d && b[d + 4] === 0x01 && b[d + 5] === 0x2a) {
                out.width = u16le(b, d + 6) & 0x3fff;
                out.height = u16le(b, d + 8) & 0x3fff;
            }
        } else if (type === 'VP8L' && len >= 5 && b[d] === 0x2f) {
            const bits = u32le(b, d + 1);
            if (out.width == null) {
                out.width = (bits & 0x3fff) + 1;
                out.height = ((bits >>> 14) & 0x3fff) + 1;
            }
            if ((bits >>> 28) & 1) out.alphaChannel = 'yes';
        } else if (type === 'ALPH') {
            out.alphaChannel = 'yes';
        } else if (type === 'ICCP') {
            out.colorProfile.icc = true;
        } else if (type === 'EXIF') {
            const exif = ascii(b, d, 6) === 'Exif\0\0' ? b.subarray(d + 6, d + len) : b.subarray(d, d + len);
            out.exif = readExif(exif);
        }
        p = d + len + (len & 1);
    }
    return out;
}

/**
 * Minimal EXIF (TIFF) reader: orientation, camera, date taken, resolution and
 * whether GPS coordinates are present. The coordinates themselves are never read.
 */
export function readExif(t) {
    if (!t || t.length < 8) return null;
    const le = t[0] === 0x49 && t[1] === 0x49;
    if (!le && !(t[0] === 0x4d && t[1] === 0x4d)) return null;
    const u16 = (o) => (o + 2 <= t.length ? (le ? u16le(t, o) : u16be(t, o)) : null);
    const u32 = (o) => (o + 4 <= t.length ? (le ? u32le(t, o) : u32be(t, o)) : null);
    if (u16(2) !== 42) return null;

    const ifd = (offset) => {
        const tags = new Map();
        const n = u16(offset);
        if (n === null || n > 500) return tags;
        for (let i = 0; i < n; i++) {
            const e = offset + 2 + i * 12;
            if (e + 12 > t.length) break;
            tags.set(u16(e), { type: u16(e + 2), count: u32(e + 4), at: e + 8 });
        }
        return tags;
    };
    const str = (tag) => {
        if (!tag || tag.type !== 2 || tag.count > 256) return null;
        const at = tag.count > 4 ? u32(tag.at) : tag.at;
        if (at === null || at + tag.count > t.length) return null;
        return ascii(t, at, tag.count).replace(/\0+$/, '').trim() || null;
    };
    const short = (tag) => (tag && tag.type === 3 ? u16(tag.at) : null);
    const rational = (tag) => {
        if (!tag || tag.type !== 5) return null;
        const at = u32(tag.at);
        const num = u32(at);
        const den = u32(at + 4);
        return num !== null && den ? num / den : null;
    };

    const ifd0 = ifd(u32(4));
    const exifPtr = ifd0.get(0x8769);
    const gpsPtr = ifd0.get(0x8825);
    const sub = exifPtr ? ifd(u32(exifPtr.at)) : new Map();
    const gps = gpsPtr ? ifd(u32(gpsPtr.at)) : new Map();

    const unit = short(ifd0.get(0x0128)) ?? 2;
    const xres = rational(ifd0.get(0x011a));
    const dpi = xres ? Math.round(unit === 3 ? xres * 2.54 : xres) : null;

    return {
        orientation: short(ifd0.get(0x0112)),
        make: str(ifd0.get(0x010f)),
        model: str(ifd0.get(0x0110)),
        software: str(ifd0.get(0x0131)),
        dateTaken: str(sub.get(0x9003)) || str(ifd0.get(0x0132)),
        dpi,
        hasGps: gps.has(0x0002) && gps.has(0x0004),
    };
}
