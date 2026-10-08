// Generates the downloadable safe-zone overlay PNGs for the SEO guide pages
// (public/downloads/social-video-safe-zone/) from the checker's own dataset,
// through the checker's own resolver: no zone or margin is typed by hand.
//
//   node resources/js/tools/social-video-safe-zone/scripts/generate-overlays.mjs           # write the files
//   node resources/js/tools/social-video-safe-zone/scripts/generate-overlays.mjs --check   # fail if they differ
//
// Each overlay is 1080 x 1920, transparent, in the checker's default view
// (short caption, auto-captions off, both test phones). A "-preview" twin,
// half size on a solid background, is what the guide pages show inline.
// The combined file uses Instagram Reels and YouTube Shorts only: TikTok is
// provisional and never part of it. tests/overlays.test.mjs runs --check.
//
// Drawing mirrors src/render.js drawZones() (colours, hatch, dots, dashed
// soft edges) without a canvas, so it needs nothing beyond Node.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath, pathToFileURL } from 'node:url';
import data from '../data/safezones.v1.js';
import { resolveZones } from '../src/dataset.js';
import { COLORS } from '../src/render.js';

const HERE = path.dirname(fileURLToPath(import.meta.url));
export const OUT_DIR = path.resolve(HERE, '..', '..', '..', '..', '..', 'public', 'downloads', 'social-video-safe-zone');

const SETTINGS = { aspect: '9:16', caption: 'C1', cc: false, soundLine: false, device: 'all' };
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** "October 2026", from the P0 report date recorded in the dataset. */
export function observedMonth(dataset = data) {
    const m = /(\d{4})-(\d{2})-\d{2}/.exec(dataset.generatedFrom.p0Report);
    if (!m) throw new Error('No date in generatedFrom.p0Report');
    return `${MONTHS[Number(m[2]) - 1]} ${m[1]}`;
}

/**
 * Margins a creator keeps clear, derived from the resolved zones: top = the top
 * bar's lower edge, right = the action column's width from the frame edge,
 * bottom = from the caption/audio row's upper edge. Side-crop and the progress
 * bar are drawn but are not part of these three numbers (the guides explain them).
 */
export function margins(zones, frame) {
    const of = (kinds) => zones.filter((z) => kinds.includes(z.kind));
    const top = Math.max(...of(['top-bar']).map((z) => z.rect[3]));
    const right = frame.width - Math.min(...of(['actions']).map((z) => z.rect[0]));
    const bottom = frame.height - Math.min(...of(['caption', 'audio']).map((z) => z.rect[1]));
    if (![top, right, bottom].every(Number.isFinite)) throw new Error('Could not derive margins');
    return { top, right, bottom };
}

export function resolved(platformId) {
    const r = resolveZones(data, { ...SETTINGS, platformId });
    if (r.platform.status !== 'measured') throw new Error(`${platformId} is not measured`);
    return r;
}

/** The three Phase 1 overlays: what each draws and the margins it states. */
export function overlaySpecs() {
    const ig = resolved('instagram-reels');
    const yt = resolved('youtube-shorts');
    const igM = margins(ig.zones, ig.frame);
    const ytM = margins(yt.zones, yt.frame);
    const combined = { top: Math.max(igM.top, ytM.top), right: Math.max(igM.right, ytM.right), bottom: Math.max(igM.bottom, ytM.bottom) };
    return [
        { name: 'softphoria-instagram-reels-safe-zone-1080x1920', title: 'INSTAGRAM REELS SAFE ZONES', platforms: ['instagram-reels'], zones: ig.zones, labels: true, margins: igM },
        { name: 'softphoria-youtube-shorts-safe-zone-1080x1920', title: 'YOUTUBE SHORTS SAFE ZONES', platforms: ['youtube-shorts'], zones: yt.zones, labels: true, margins: ytM },
        { name: 'softphoria-reels-shorts-combined-safe-zone-1080x1920', title: 'REELS + SHORTS COMBINED', subtitle: 'TIKTOK NOT INCLUDED', platforms: ['instagram-reels', 'youtube-shorts'], zones: [...ig.zones, ...yt.zones], labels: false, margins: combined },
    ];
}

// --- Raster --------------------------------------------------------------

function canvas(width, height) {
    return { width, height, px: new Float64Array(width * height * 4) }; // premultiplied-free RGBA, 0..1
}

const hex = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16) / 255);

function blend(c, x, y, rgb, a) {
    if (x < 0 || y < 0 || x >= c.width || y >= c.height || a <= 0) return;
    const i = (y * c.width + x) * 4;
    const da = c.px[i + 3];
    const oa = a + da * (1 - a);
    for (let k = 0; k < 3; k++) c.px[i + k] = (rgb[k] * a + c.px[i + k] * da * (1 - a)) / oa;
    c.px[i + 3] = oa;
}

function fillRect(c, l, t, r, b, rgb, a, test = null) {
    for (let y = Math.max(0, t); y < Math.min(c.height, b); y++) {
        for (let x = Math.max(0, l); x < Math.min(c.width, r); x++) {
            if (!test || test(x, y)) blend(c, x, y, rgb, a);
        }
    }
}

const hatch = (x, y) => (x + y) % 16 < 3;
const dots = (x, y) => (x % 16 - 8) ** 2 + (y % 16 - 8) ** 2 <= 5;

function strokeRect(c, l, t, r, b, rgb, width, dashed) {
    const on = (p) => !dashed || p % 20 < 12;
    fillRect(c, l, t, r, t + width, rgb, 1, (x) => on(x - l));
    fillRect(c, l, b - width, r, b, rgb, 1, (x) => on(x - l));
    fillRect(c, l, t, l + width, b, rgb, 1, (_, y) => on(y - t));
    fillRect(c, r - width, t, r, b, rgb, 1, (_, y) => on(y - t));
}

// 5 x 7 bitmap font: only the characters the overlays use (anything else throws).
const GLYPHS = {
    A: '01110100011000111111100011000110001', B: '11110100011000111110100011000111110', C: '01110100011000010000100001000101110',
    D: '11110100011000110001100011000111110', E: '11111100001000011110100001000011111', F: '11111100001000011110100001000010000',
    G: '01110100011000010111100011000101111', H: '10001100011000111111100011000110001', I: '01110001000010000100001000010001110',
    J: '00111000100001000010000101001001100', K: '10001100101010011000101001001010001', L: '10000100001000010000100001000011111',
    M: '10001110111010110101100011000110001', N: '10001100011100110101100111000110001', O: '01110100011000110001100011000101110',
    P: '11110100011000111110100001000010000', Q: '01110100011000110001101011001001101', R: '11110100011000111110101001001010001',
    S: '01111100001000001110000010000111110', T: '11111001000010000100001000010000100', U: '10001100011000110001100011000101110',
    V: '10001100011000110001100010101000100', W: '10001100011000110101101011010101010', X: '10001100010101000100010101000110001',
    Y: '10001100010101000100001000010000100', Z: '11111000010001000100010001000011111',
    0: '01110100011001110101110011000101110', 1: '00100011000010000100001000010001110', 2: '01110100010000100010001000100011111',
    3: '11111000100010000010000011000101110', 4: '00010001100101010010111110001000010', 5: '11111100001111000001000011000101110',
    6: '00110010001000011110100011000101110', 7: '11111000010001000100010000100001000', 8: '01110100011000101110100011000101110',
    9: '01110100011000101111000010001001100',
    ' ': '00000000000000000000000000000000000', '.': '00000000000000000000000000110001100', ',': '00000000000000000000011000010001000',
    ':': '00000011000110000000011000110000000', '-': '00000000000000011111000000000000000', '+': '00000001000010011111001000010000000',
    '(': '00010001000100001000010000010000010', ')': '01000001000001000010000100010001000', '/': '00001000100001000100010000100010000',
    '·': '00000000000000000100000000000000000', '×': '00000100010101000100010101000100000',
};

const advance = (scale) => 6 * scale;
export const textWidth = (s, scale) => s.length * advance(scale) - scale;

function drawText(c, s, x, y, scale, rgb) {
    [...s].forEach((ch, n) => {
        const g = GLYPHS[ch];
        if (!g) throw new Error(`No glyph for "${ch}"`);
        for (let row = 0; row < 7; row++) {
            for (let col = 0; col < 5; col++) {
                if (g[row * 5 + col] === '1') fillRect(c, x + n * advance(scale) + col * scale, y + row * scale, x + n * advance(scale) + (col + 1) * scale, y + (row + 1) * scale, rgb, 1);
            }
        }
    });
}

function wrap(text, maxWidth, scale) {
    const lines = [];
    for (const word of text.split(' ')) {
        const last = lines.at(-1);
        if (last !== undefined && textWidth(`${last} ${word}`, scale) <= maxWidth) lines[lines.length - 1] = `${last} ${word}`;
        else lines.push(word);
    }
    return lines;
}

/** A dark box holding white lines of text, like render.js label(). */
function labelBox(c, lines, x, y, scale) {
    const lineH = 9 * scale;
    const w = Math.max(...lines.map((l) => textWidth(l, scale))) + 4 * scale;
    fillRect(c, x, y, x + w, y + lines.length * lineH + 2 * scale, [0, 0, 0], 0.72);
    lines.forEach((l, i) => drawText(c, l, x + 2 * scale, y + 2 * scale + i * lineH, scale, [1, 1, 1]));
}

function zoneStyle(z) {
    if (z.kind === 'side-crop' || z.kind === 'masked') return { fill: hex(COLORS.crop), pattern: hatch, ink: [0, 0, 0], stroke: [0, 0, 0] };
    return z.severity === 'hard'
        ? { fill: hex(COLORS.hard), pattern: hatch, ink: [1, 1, 1], stroke: hex(COLORS.hard) }
        : { fill: hex(COLORS.soft), pattern: dots, ink: [1, 1, 1], stroke: hex(COLORS.soft) };
}

const OPACITY = 0.7; // export.js overlayTemplate()

/** Renders one overlay as RGBA bytes (transparent background). */
export function renderOverlay(spec, frame = data.frame) {
    const c = canvas(frame.width, frame.height);
    for (const z of spec.zones) {
        const [l, t, r, b] = z.rect;
        const s = zoneStyle(z);
        fillRect(c, l, t, r, b, s.fill, OPACITY * 0.55);
        fillRect(c, l, t, r, b, s.ink, Math.min(1, OPACITY * 1.2), s.pattern);
        strokeRect(c, l, t, r, b, s.stroke, 3, z.severity === 'soft');
    }
    if (spec.labels) {
        for (const z of spec.zones) {
            const [l, t, r, b] = z.rect;
            if (r - l > 90 && b - t > 34) labelBox(c, wrap(z.label.toUpperCase(), r - l - 24, 2), l + 8, t + 8, 2);
        }
    }

    const m = spec.margins;
    const safe = [0, m.top, frame.width - m.right, frame.height - m.bottom];
    strokeRect(c, safe[0] + 4, safe[1] + 4, safe[2] - 4, safe[3] - 4, hex(COLORS.ok), 6, false);
    labelBox(c, ['SAFE AREA', `TOP ${m.top} · RIGHT ${m.right} · BOTTOM ${m.bottom} PX`], safe[0] + 16, safe[1] + 16, 2);

    const lines = [[spec.title, 3], ...(spec.subtitle ? [[spec.subtitle, 3]] : []), [`${frame.width}×${frame.height} · SHORT CAPTION · AUTO-CAPTIONS OFF`, 2],
        ['GREY HATCHED EDGES: CUT OFF ON SOME TALLER SCREENS', 2],
        [`SOFTPHORIA · OBSERVED ${observedMonth().toUpperCase()} · DATASET ${data.datasetVersion}`, 2], ['OBSERVED MEASUREMENTS, NOT AN OFFICIAL SPECIFICATION', 2]];
    const bandH = lines.reduce((h, [, s]) => h + 9 * s, 0) + 24;
    const bandT = Math.round(frame.height / 2 - bandH / 2);
    fillRect(c, 0, bandT, frame.width, bandT + bandH, [0, 0, 0], 0.6);
    let y = bandT + 12;
    for (const [text, scale] of lines) {
        drawText(c, text, Math.round((frame.width - textWidth(text, scale)) / 2), y, scale, [1, 1, 1]);
        y += 9 * scale;
    }
    return toBytes(c);
}

/** Half-size, opaque version for showing inline on a page. */
export function renderPreview(overlay, frame = data.frame, background = '#334155') {
    const bg = hex(background);
    const w = frame.width / 2;
    const h = frame.height / 2;
    const out = new Uint8Array(w * h * 4);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const acc = [0, 0, 0];
            for (const [dx, dy] of [[0, 0], [1, 0], [0, 1], [1, 1]]) {
                const i = ((2 * y + dy) * frame.width + 2 * x + dx) * 4;
                const a = overlay[i + 3] / 255;
                for (let k = 0; k < 3; k++) acc[k] += (overlay[i + k] / 255) * a + bg[k] * (1 - a);
            }
            const o = (y * w + x) * 4;
            for (let k = 0; k < 3; k++) out[o + k] = Math.round((acc[k] / 4) * 255);
            out[o + 3] = 255;
        }
    }
    return { width: w, height: h, rgba: out };
}

function toBytes(c) {
    const out = new Uint8Array(c.px.length);
    for (let i = 0; i < c.px.length; i++) out[i] = Math.round(c.px[i] * 255);
    return out;
}

// --- PNG (RGBA, 8-bit, filter 0 on every row) ----------------------------

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
});
const crc32 = (buf) => {
    let c = 0xffffffff;
    for (const b of buf) c = CRC_TABLE[(c ^ b) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
};

function chunk(type, body) {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(body.length);
    const tb = Buffer.concat([Buffer.from(type, 'ascii'), body]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(tb));
    return Buffer.concat([len, tb, crc]);
}

export function encodePng(width, height, rgba) {
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0);
    ihdr.writeUInt32BE(height, 4);
    ihdr.set([8, 6, 0, 0, 0], 8);
    const raw = Buffer.alloc(height * (width * 4 + 1));
    for (let y = 0; y < height; y++) raw.set(rgba.subarray(y * width * 4, (y + 1) * width * 4), y * (width * 4 + 1) + 1);
    return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

/** Decodes a PNG written by encodePng() back to RGBA bytes. */
export function decodePng(buf) {
    let p = 8;
    let width = 0;
    let height = 0;
    const idat = [];
    while (p < buf.length) {
        const len = buf.readUInt32BE(p);
        const type = buf.toString('ascii', p + 4, p + 8);
        const body = buf.subarray(p + 8, p + 8 + len);
        if (type === 'IHDR') {
            width = body.readUInt32BE(0);
            height = body.readUInt32BE(4);
            if (body[8] !== 8 || body[9] !== 6) throw new Error('Expected 8-bit RGBA');
        }
        if (type === 'IDAT') idat.push(body);
        p += 12 + len;
    }
    const raw = zlib.inflateSync(Buffer.concat(idat));
    const rgba = new Uint8Array(width * height * 4);
    for (let y = 0; y < height; y++) {
        if (raw[y * (width * 4 + 1)] !== 0) throw new Error('Unexpected PNG filter');
        rgba.set(raw.subarray(y * (width * 4 + 1) + 1, (y + 1) * (width * 4 + 1)), y * width * 4);
    }
    return { width, height, rgba };
}

/** Every file this script owns: name -> { width, height, rgba }. */
export function buildAll() {
    const files = {};
    for (const spec of overlaySpecs()) {
        const rgba = renderOverlay(spec);
        files[`${spec.name}.png`] = { width: data.frame.width, height: data.frame.height, rgba };
        files[`${spec.name}-preview.png`] = renderPreview(rgba);
    }
    return files;
}

/** Names of files that are missing or whose pixels differ from a fresh build. */
export function drift(dir = OUT_DIR) {
    const stale = [];
    const expected = buildAll();
    for (const [name, img] of Object.entries(expected)) {
        const file = path.join(dir, name);
        if (!fs.existsSync(file)) {
            stale.push(name);
            continue;
        }
        const got = decodePng(fs.readFileSync(file));
        if (got.width !== img.width || got.height !== img.height || Buffer.compare(Buffer.from(got.rgba), Buffer.from(img.rgba)) !== 0) stale.push(name);
    }
    const extra = fs.existsSync(dir) ? fs.readdirSync(dir).filter((f) => f.endsWith('.png') && !(f in expected)) : [];
    return [...stale, ...extra];
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
    if (process.argv.includes('--check')) {
        const stale = drift();
        if (stale.length) {
            console.error(`Overlay PNGs differ from the dataset: ${stale.join(', ')}. Run generate-overlays.mjs.`);
            process.exit(1);
        }
        console.log('Overlay PNGs match the dataset.');
    } else {
        fs.mkdirSync(OUT_DIR, { recursive: true });
        for (const [name, img] of Object.entries(buildAll())) fs.writeFileSync(path.join(OUT_DIR, name), encodePng(img.width, img.height, img.rgba));
        for (const spec of overlaySpecs()) console.log(spec.name, spec.platforms.join('+'), JSON.stringify(spec.margins));
    }
}
