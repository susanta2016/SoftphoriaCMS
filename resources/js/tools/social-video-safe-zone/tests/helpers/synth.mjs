// Deterministic synthetic frames with known text / logo boxes for detector tests.
// Frames are 8-bit greyscale at analysis size; boxes are in reference px (1080 wide).

export function prng(seed) {
    let s = seed >>> 0 || 1;
    return () => {
        s ^= s << 13; s >>>= 0;
        s ^= s >> 17;
        s ^= s << 5; s >>>= 0;
        return s / 4294967296;
    };
}

function background(kind, w, h, rnd) {
    const g = new Float32Array(w * h);
    if (kind === 'flat') {
        const v = 40 + rnd() * 170;
        g.fill(v);
    } else if (kind === 'gradient') {
        const a = 30 + rnd() * 80, b = 140 + rnd() * 100;
        for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) g[y * w + x] = a + ((b - a) * y) / h;
    } else {
        // photo-like: large soft blobs + sensor noise; busy adds a soft texture
        const blobs = Array.from({ length: 7 }, () => ({ x: rnd() * w, y: rnd() * h, r: (0.15 + rnd() * 0.35) * w, a: (rnd() - 0.5) * 160 }));
        const base = 70 + rnd() * 110;
        for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
            let v = base;
            for (const bl of blobs) v += bl.a * Math.exp(-((x - bl.x) ** 2 + (y - bl.y) ** 2) / (2 * bl.r * bl.r));
            if (kind === 'busy') v += 18 * Math.sin(x / 3.1) * Math.sin(y / 4.3);
            v += (rnd() - 0.5) * 24;
            g[y * w + x] = v;
        }
    }
    return g;
}

const GLYPHS = [
    // strokes as [x0, y0, x1, y1] in a 0..6 x 0..7 glyph grid
    [[2, 0, 4, 7]], // I
    [[0, 0, 2, 7], [0, 5, 6, 7]], // L
    [[0, 0, 6, 2], [2, 0, 4, 7]], // T
    [[0, 0, 2, 7], [0, 0, 6, 2], [0, 3, 5, 4], [0, 5, 6, 7]], // E
    [[0, 0, 2, 7], [4, 0, 6, 7], [0, 0, 6, 2], [0, 5, 6, 7]], // O
    [[0, 0, 2, 7], [4, 0, 6, 7], [0, 3, 6, 4]], // H
    [[0, 0, 2, 7], [4, 0, 6, 7], [0, 5, 6, 7]], // U
    [[0, 0, 2, 7], [0, 0, 6, 2], [0, 5, 6, 7]], // C
    [[0, 0, 2, 7], [0, 0, 6, 2], [0, 3, 5, 4]], // F
    [[0, 0, 2, 7], [0, 0, 5, 2], [4, 0, 6, 4], [0, 3, 5, 4]], // P
];

function fillRect(g, w, h, x0, y0, x1, y1, v) {
    for (let y = Math.max(0, Math.round(y0)); y < Math.min(h, Math.round(y1)); y++)
        for (let x = Math.max(0, Math.round(x0)); x < Math.min(w, Math.round(x1)); x++) g[y * w + x] = v;
}

function meanIn(g, w, x0, y0, x1, y1) {
    let s = 0, n = 0;
    for (let y = Math.round(y0); y < Math.round(y1); y += 2) for (let x = Math.round(x0); x < Math.round(x1); x += 2) { s += g[y * w + x]; n++; }
    return n ? s / n : 128;
}

// Draws a text block; returns its box in analysis px.
function drawText(g, w, h, x, y, fh, lines, words, rnd) {
    const gw = fh * 0.6, unit = gw / 6, vunit = fh / 7, gap = Math.max(1, fh * 0.12), wordGap = fh * 0.5, lineH = fh * 1.3;
    let maxX = x, cy = y;
    const bg = meanIn(g, w, x, y, Math.min(w - 1, x + fh * 10), Math.min(h - 1, y + lineH * lines));
    const ink = bg > 128 ? 15 + rnd() * 30 : 215 + rnd() * 35;
    for (let l = 0; l < lines; l++) {
        let cx = x;
        for (let wd = 0; wd < words; wd++) {
            const n = 2 + Math.floor(rnd() * 5);
            for (let k = 0; k < n; k++) {
                for (const [a, b, c, d] of GLYPHS[Math.floor(rnd() * GLYPHS.length)]) {
                    fillRect(g, w, h, cx + a * unit, cy + b * vunit, cx + c * unit, cy + d * vunit, ink);
                }
                cx += gw + gap;
            }
            cx += wordGap;
        }
        maxX = Math.max(maxX, cx - wordGap - gap);
        cy += lineH;
    }
    return [x, y, maxX, cy - lineH + fh];
}

function drawLogo(g, w, h, cx, cy, r, rnd) {
    const bg = meanIn(g, w, cx - r, cy - r, cx + r, cy + r);
    const fillV = bg > 128 ? 30 : 230;
    for (let y = Math.floor(cy - r); y < cy + r; y++) for (let x = Math.floor(cx - r); x < cx + r; x++) {
        if (x >= 0 && y >= 0 && x < w && y < h && (x - cx) ** 2 + (y - cy) ** 2 <= r * r) g[y * w + x] = fillV;
    }
    const fh = r * 0.8;
    const ink = fillV > 128 ? 20 : 235;
    const gw = fh * 0.6, unit = gw / 6, vunit = fh / 7;
    let x = cx - gw * 1.1;
    for (let k = 0; k < 2; k++) {
        for (const [a, b, c, d] of GLYPHS[Math.floor(rnd() * GLYPHS.length)]) fillRect(g, w, h, x + a * unit, cy - fh / 2 + b * vunit, x + c * unit, cy - fh / 2 + d * vunit, ink);
        x += gw * 1.2;
    }
    return [cx - r, cy - r, cx + r, cy + r];
}

/**
 * The fixed synthetic set (spec 11: 30 frames). Text sizes span 24-120 reference px.
 * @param {number} aw  analysis width (height = aw * 16 / 9)
 */
export function syntheticSet(aw = 270, seedBase = 1000, count = 30) {
    const ah = Math.round((aw * 16) / 9);
    const k = aw / 1080; // reference px -> analysis px
    const out = [];
    const kinds = ['flat', 'gradient', 'photo', 'busy'];
    const sizes = [24, 32, 40, 48, 64, 80, 96, 120];
    for (let i = 0; i < count; i++) {
        const rnd = prng(seedBase + i);
        const negative = i % 30 >= 25;
        const kind = negative ? ['photo', 'busy', 'gradient', 'photo', 'busy'][(i % 30) - 25] : kinds[i % 4];
        const g = background(kind, aw, ah, rnd);
        const truths = [];
        if (!negative) {
            const fh = sizes[i % sizes.length] * k;
            const lines = 1 + (i % 3);
            const words = 2 + (i % 3);
            // headline in the upper half, caption-like block near the bottom
            const y1 = ah * (0.15 + 0.2 * rnd());
            truths.push({ type: 'text', sizeRef: sizes[i % sizes.length], box: drawText(g, aw, ah, aw * 0.08, y1, fh, lines, words, rnd) });
            if (i % 2 === 0) {
                const fh2 = Math.max(24, sizes[(i + 3) % sizes.length] * 0.6) * k;
                truths.push({ type: 'text', sizeRef: Math.round(fh2 / k), box: drawText(g, aw, ah, aw * 0.08, ah * 0.78, fh2, 1, 3, rnd) });
            }
            if (i % 5 === 0) truths.push({ type: 'logo', sizeRef: 120, box: drawLogo(g, aw, ah, aw * 0.8, ah * 0.55, 60 * k, rnd) });
        }
        const gray = new Uint8Array(aw * ah);
        for (let p = 0; p < gray.length; p++) gray[p] = Math.max(0, Math.min(255, Math.round(g[p])));
        out.push({
            id: `synth-${String(i + 1).padStart(2, '0')}`, kind, negative, width: aw, height: ah, gray,
            truths: truths.map((t) => {
                const b = [Math.max(0, t.box[0]), Math.max(0, t.box[1]), Math.min(aw, t.box[2]), Math.min(ah, t.box[3])];
                return { ...t, box: b, rectRef: b.map((v) => Math.round(v / k)) };
            }),
        });
    }
    return out;
}
