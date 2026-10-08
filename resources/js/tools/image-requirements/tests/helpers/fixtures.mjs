// Minimal, structurally valid image headers for the analyzer tests.
// They carry real chunk/segment layouts but no pixel data (the analyzer only
// reads headers; decoding is the browser's job).

const bytes = (...parts) => {
    const out = [];
    for (const p of parts) {
        if (typeof p === 'string') out.push(...[...p].map((c) => c.charCodeAt(0)));
        else if (typeof p === 'number') out.push(p);
        else out.push(...p);
    }
    return Uint8Array.from(out);
};
const be16 = (n) => [(n >> 8) & 255, n & 255];
const be32 = (n) => [(n >>> 24) & 255, (n >> 16) & 255, (n >> 8) & 255, n & 255];
const le24 = (n) => [n & 255, (n >> 8) & 255, (n >> 16) & 255];
const le32 = (n) => [n & 255, (n >> 8) & 255, (n >> 16) & 255, (n >>> 24) & 255];

const pngChunk = (type, data = []) => bytes(be32(data.length), type, data, [0, 0, 0, 0]);

/** @param {{ width, height, colorType?, bitDepth?, chunks?: Array<[string, number[]]> }} o */
export function png({ width, height, colorType = 2, bitDepth = 8, chunks = [] }) {
    return bytes(
        [0x89], 'PNG\r\n', [0x1a, 0x0a],
        pngChunk('IHDR', [...be32(width), ...be32(height), bitDepth, colorType, 0, 0, 0]),
        ...chunks.map(([type, data]) => pngChunk(type, data)),
        pngChunk('IDAT', [0, 0, 0, 0]),
        pngChunk('IEND'),
    );
}
export const pHYsDpi = (dpi) => ['pHYs', [...be32(Math.round(dpi / 0.0254)), ...be32(Math.round(dpi / 0.0254)), 1]];

/** A big-endian TIFF/EXIF block with orientation and, optionally, GPS. */
export function exif({ orientation = 1, gps = false, make = null } = {}) {
    const entries = [];
    const extra = [];
    const ifd0Count = 1 + (gps ? 1 : 0) + (make ? 1 : 0);
    const ifd0Size = 2 + ifd0Count * 12 + 4;
    let extraOffset = 8 + ifd0Size;
    entries.push([...be16(0x0112), ...be16(3), ...be32(1), ...be16(orientation), 0, 0]);
    if (make) {
        const text = [...make].map((c) => c.charCodeAt(0)).concat([0]);
        entries.push([...be16(0x010f), ...be16(2), ...be32(text.length), ...be32(extraOffset)]);
        extra.push(...text);
        extraOffset += text.length;
    }
    if (gps) {
        entries.push([...be16(0x8825), ...be16(4), ...be32(1), ...be32(extraOffset)]);
        // GPS IFD: latitude (0x0002) and longitude (0x0004), values not needed
        extra.push(...be16(2), ...be16(0x0002), ...be16(5), ...be32(3), ...be32(0), ...be16(0x0004), ...be16(5), ...be32(3), ...be32(0), ...be32(0));
    }
    entries.sort((a, b) => (a[0] << 8 | a[1]) - (b[0] << 8 | b[1]));
    return bytes('MM', [0, 42], be32(8), be16(ifd0Count), ...entries, be32(0), extra);
}

/** @param {{ width, height, components?, progressive?, dpi?, exifBlock?, icc? }} o */
export function jpeg({ width, height, components = 3, progressive = false, dpi = null, exifBlock = null, icc = false }) {
    const seg = (marker, data) => bytes([0xff, marker], be16(data.length + 2), data);
    const parts = [[0xff, 0xd8]];
    if (dpi) parts.push(seg(0xe0, bytes('JFIF', [0], [1, 1], [1], be16(dpi), be16(dpi), [0, 0])));
    if (exifBlock) parts.push(seg(0xe1, bytes('Exif', [0, 0], exifBlock)));
    if (icc) parts.push(seg(0xe2, bytes('ICC_PROFILE', [0], [1, 1], [0, 0, 0, 0])));
    const comps = [];
    for (let i = 1; i <= components; i++) comps.push(i, 0x11, 0);
    parts.push(seg(progressive ? 0xc2 : 0xc0, bytes([8], be16(height), be16(width), [components], comps)));
    parts.push(seg(0xda, bytes([1, 1, 0, 0, 63, 0])));
    parts.push([0xff, 0xd9]);
    return bytes(...parts);
}

/** WebP with a VP8X header. flags: { alpha, animated, icc } */
export function webpExtended({ width, height, alpha = false, animated = false, icc = false }) {
    const flags = (icc ? 0x20 : 0) | (alpha ? 0x10 : 0) | (animated ? 0x02 : 0);
    const vp8x = bytes('VP8X', le32(10), [flags, 0, 0, 0], le24(width - 1), le24(height - 1));
    return bytes('RIFF', le32(4 + vp8x.length), 'WEBP', vp8x);
}

/** Lossless WebP (VP8L) with the alpha hint bit. */
export function webpLossless({ width, height, alpha = false }) {
    const bits = ((width - 1) & 0x3fff) | (((height - 1) & 0x3fff) << 14) | ((alpha ? 1 : 0) << 28);
    const chunk = bytes('VP8L', le32(5), [0x2f], le32(bits >>> 0));
    return bytes('RIFF', le32(4 + chunk.length + 1), 'WEBP', chunk, [0]);
}

/** Image facts as the app passes them to the engine. */
export const img = (width, height, overrides = {}) => ({ width, height, bytes: 1024 * 1024, format: 'jpeg', transparent: 'no', animated: 'no', ...overrides });
