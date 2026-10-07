// Heuristic detector for likely text and logos (spec 5.2, decision 2 = no model).
// Input: an 8-bit greyscale frame at analysis size. Output: boxes in analysis px.
// Pure and deterministic: runs in the Web Worker and in Node tests.
//
// Idea: text is dense, high-contrast, short strokes in both orientations. Smooth
// photo areas have weak gradients; flat areas none. We blur away pixel noise,
// mark strong edges, score small cells by edge density and orientation mix,
// close gaps between letters and lines, then keep compact, well-filled groups.

export const DETECTOR_DEFAULTS = {
    cell: 6, // px per cell side
    edgeThreshold: 170, // |gx| + |gy| of the 3x3 Sobel on the blurred image (0..2040); about 64 levels of contrast on a clean edge, which keeps moderate textures (fabric, foliage, fine grids) out
    minDensity: 0.18, // share of strong-edge pixels in a cell
    minVertical: 2, // strong-edge pixels with |gx| > |gy|
    minHorizontal: 1, // strong-edge pixels with |gy| >= |gx|
    closeX: 2, // cells bridged horizontally (letter and word gaps)
    closeY: 1, // cells bridged vertically (line spacing)
    minCells: 3,
    minWidthCells: 2,
    minFill: 0.25,
    maxHeightShare: 0.6,
    maxRun: 40, // edge runs longer than this (px) are lines, borders or grids, not letters
};

// Clears straight edge runs longer than maxRun: horizontal runs of horizontal edges
// and vertical runs of vertical edges (a gap of 1 px does not end a run).
function suppressLines(strong, vert, w, h, maxRun) {
    const clear = (idx) => { for (const i of idx) strong[i] = 0; };
    for (let y = 0; y < h; y++) {
        let run = [], miss = 0;
        for (let x = 0; x <= w; x++) {
            const i = y * w + x;
            if (x < w && strong[i] && !vert[i]) { run.push(i); miss = 0; continue; }
            if (x < w && run.length && miss < 1) { miss++; continue; }
            if (run.length > maxRun) clear(run);
            run = []; miss = 0;
        }
    }
    for (let x = 0; x < w; x++) {
        let run = [], miss = 0;
        for (let y = 0; y <= h; y++) {
            const i = y * w + x;
            if (y < h && strong[i] && vert[i]) { run.push(i); miss = 0; continue; }
            if (y < h && run.length && miss < 1) { miss++; continue; }
            if (run.length > maxRun) clear(run);
            run = []; miss = 0;
        }
    }
}

function blur3(src, w, h) {
    const out = new Uint8Array(w * h);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            let s = 0, n = 0;
            for (let dy = -1; dy <= 1; dy++) {
                const yy = y + dy;
                if (yy < 0 || yy >= h) continue;
                for (let dx = -1; dx <= 1; dx++) {
                    const xx = x + dx;
                    if (xx < 0 || xx >= w) continue;
                    s += src[yy * w + xx];
                    n++;
                }
            }
            out[y * w + x] = (s / n) | 0;
        }
    }
    return out;
}

function closeMask(mask, cw, ch, rx, ry) {
    const dil = (m, r, horizontal) => {
        const o = new Uint8Array(m.length);
        for (let y = 0; y < ch; y++) for (let x = 0; x < cw; x++) {
            let v = 0;
            for (let k = -r; k <= r && !v; k++) {
                const xx = horizontal ? x + k : x, yy = horizontal ? y : y + k;
                if (xx >= 0 && xx < cw && yy >= 0 && yy < ch && m[yy * cw + xx]) v = 1;
            }
            o[y * cw + x] = v;
        }
        return o;
    };
    const ero = (m, r, horizontal) => {
        const o = new Uint8Array(m.length);
        for (let y = 0; y < ch; y++) for (let x = 0; x < cw; x++) {
            let v = 1;
            for (let k = -r; k <= r && v; k++) {
                const xx = horizontal ? x + k : x, yy = horizontal ? y : y + k;
                if (xx >= 0 && xx < cw && yy >= 0 && yy < ch && !m[yy * cw + xx]) v = 0;
            }
            o[y * cw + x] = v;
        }
        return o;
    };
    let m = mask;
    if (rx) m = ero(dil(m, rx, true), rx, true);
    if (ry) m = ero(dil(m, ry, false), ry, false);
    // closing must not remove original cells
    for (let i = 0; i < m.length; i++) m[i] = m[i] | mask[i];
    return m;
}

/**
 * @param {Uint8Array} gray  w*h luminance values
 * @returns {Array<{rect:number[], confidence:number}>} rects in analysis px [l, t, r, b)
 */
export function detect(gray, w, h, options = {}) {
    const o = { ...DETECTOR_DEFAULTS, ...options };
    const b = blur3(gray, w, h);
    const strong = new Uint8Array(w * h);
    const vert = new Uint8Array(w * h);
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const i = y * w + x;
            const gx = (b[i - w + 1] + 2 * b[i + 1] + b[i + w + 1]) - (b[i - w - 1] + 2 * b[i - 1] + b[i + w - 1]);
            const gy = (b[i + w - 1] + 2 * b[i + w] + b[i + w + 1]) - (b[i - w - 1] + 2 * b[i - w] + b[i - w + 1]);
            const ax = gx < 0 ? -gx : gx, ay = gy < 0 ? -gy : gy;
            if (ax + ay >= o.edgeThreshold) {
                strong[i] = 1;
                vert[i] = ax > ay ? 1 : 0;
            }
        }
    }

    suppressLines(strong, vert, w, h, o.maxRun);

    const cs = o.cell, cw = Math.floor(w / cs), ch = Math.floor(h / cs);
    const density = new Float32Array(cw * ch);
    const mask = new Uint8Array(cw * ch);
    for (let cy = 0; cy < ch; cy++) {
        for (let cx = 0; cx < cw; cx++) {
            let n = 0, v = 0;
            for (let y = cy * cs; y < (cy + 1) * cs; y++) for (let x = cx * cs; x < (cx + 1) * cs; x++) {
                const i = y * w + x;
                if (strong[i]) { n++; v += vert[i]; }
            }
            const d = n / (cs * cs);
            density[cy * cw + cx] = d;
            if (d >= o.minDensity && v >= o.minVertical && n - v >= o.minHorizontal) mask[cy * cw + cx] = 1;
        }
    }

    const closed = closeMask(mask, cw, ch, o.closeX, o.closeY);
    const seen = new Uint8Array(cw * ch);
    const boxes = [];
    for (let start = 0; start < closed.length; start++) {
        if (!closed[start] || seen[start]) continue;
        const stack = [start];
        seen[start] = 1;
        let x0 = cw, y0 = ch, x1 = -1, y1 = -1, cells = 0, textCells = 0, dsum = 0;
        while (stack.length) {
            const c = stack.pop();
            const cx = c % cw, cy = (c - cx) / cw;
            cells++;
            if (mask[c]) { textCells++; dsum += density[c]; }
            if (cx < x0) x0 = cx; if (cx > x1) x1 = cx; if (cy < y0) y0 = cy; if (cy > y1) y1 = cy;
            for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
                const nx = cx + dx, ny = cy + dy;
                if (nx < 0 || ny < 0 || nx >= cw || ny >= ch) continue;
                const ni = ny * cw + nx;
                if (closed[ni] && !seen[ni]) { seen[ni] = 1; stack.push(ni); }
            }
        }
        const bw = x1 - x0 + 1, bh = y1 - y0 + 1;
        const fill = textCells / (bw * bh);
        if (textCells < o.minCells || bw < o.minWidthCells || fill < o.minFill || bh * cs > h * o.maxHeightShare) continue;
        // tighten to the strong-edge pixels inside the cell box
        let px0 = w, py0 = h, px1 = -1, py1 = -1;
        for (let y = y0 * cs; y < (y1 + 1) * cs; y++) for (let x = x0 * cs; x < (x1 + 1) * cs; x++) {
            if (!strong[y * w + x]) continue;
            if (x < px0) px0 = x; if (x > px1) px1 = x; if (y < py0) py0 = y; if (y > py1) py1 = y;
        }
        if (px1 < 0) continue;
        const meanDensity = dsum / textCells;
        const confidence = Math.max(0, Math.min(1, 0.5 * Math.min(1, fill / 0.7) + 0.5 * Math.min(1, (meanDensity - o.minDensity) / 0.25 + 0.3)));
        // the blur and the 3x3 gradient each spread an edge by about 1 px: step back in by 1 px
        const rect = px1 - px0 > 3 && py1 - py0 > 3 ? [px0 + 1, py0 + 1, px1, py1] : [px0, py0, px1 + 1, py1 + 1];
        boxes.push({ rect, confidence: Math.round(confidence * 100) / 100 });
    }
    return boxes;
}

// Frame difference on a coarse thumbnail, for scene cuts (0..255).
export function thumbDiff(a, b) {
    let s = 0;
    for (let i = 0; i < a.length; i++) s += Math.abs(a[i] - b[i]);
    return s / a.length;
}
