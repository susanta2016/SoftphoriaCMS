// Detector check on the 9 real P0 calibration reference frames (spec 11).
// Each frame carries a code panel (title text + barcode) at a known position;
// the detector should find it (IoU >= 0.5). Grid labels ("y100", "x200") are real
// text too, so other boxes are expected and reported, not counted as errors.
// The spec sets no pass bar for these frames: the result is reported, not gated
// (the gated detector bar is the synthetic set in tests/detector.test.mjs).
//
//   node resources/js/tools/social-video-safe-zone/scripts/eval-detector-p0.mjs [--p0 <dir>]
//
// Uses the P0 workspace's own image decoder (sharp) read-only; nothing is added to this repo.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { detect } from '../src/detector-core.js';
import { iou } from '../src/geometry.js';

const args = process.argv.slice(2);
const P0 = args.includes('--p0') ? args[args.indexOf('--p0') + 1] : (process.env.P0_DIR || 'C:/Users/susan/Documents/P0-measurements');
const require = createRequire(path.join(P0, 'automation', 'package.json'));
const sharp = require('sharp');
const { panelGeometry } = await import(pathToFileURL(path.join(P0, 'automation', 'lib', 'spec.js')).href);

const AW = 270;
const dir = path.join(P0, 'calibration', 'v2-reference');
let found = 0, total = 0;
for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.png')).sort()) {
    const img = sharp(path.join(dir, file));
    const { width, height } = await img.metadata();
    const ah = Math.round((AW * height) / width);
    const gray = new Uint8Array(await img.clone().resize(AW, ah, { fit: 'fill' }).greyscale().raw().toBuffer());
    const k = width / AW;
    const boxes = detect(gray, AW, ah).map((b) => ({ ...b, rect: b.rect.map((v) => Math.round(v * k)) }));
    const p = panelGeometry(width, height);
    const panel = [p.x, p.y, p.x + p.w, p.y + p.h];
    const best = boxes.reduce((a, b) => Math.max(a, iou(b.rect, panel)), 0);
    total++;
    if (best >= 0.5) found++;
    console.log(`${best >= 0.5 ? 'FOUND ' : 'MISSED'} ${file.padEnd(22)} panel IoU ${best.toFixed(2)}  boxes ${boxes.length}`);
}
console.log(`\ncode panel found in ${found} of ${total} reference frames`);

