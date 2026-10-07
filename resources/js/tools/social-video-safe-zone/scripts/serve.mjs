// Minimal static server for the P1a demo (no Laravel, no Vite).
//   node resources/js/tools/social-video-safe-zone/scripts/serve.mjs [port]
// Serves only this tool's folder, on 127.0.0.1.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PORT = Number(process.argv[2] || 5178);
const TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.png': 'image/png', '.mp4': 'video/mp4', '.webm': 'video/webm' };

http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    let p = decodeURIComponent(url.pathname);
    if (p === '/') { res.writeHead(302, { Location: '/demo/' }); res.end(); return; }
    if (p.endsWith('/')) p += 'index.html';
    const file = path.resolve(ROOT, `.${p}`);
    if (!file.startsWith(ROOT + path.sep) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); res.end('Not found'); return; }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
    fs.createReadStream(file).pipe(res);
}).listen(PORT, '127.0.0.1', () => console.log(`Safe Zone Checker demo: http://127.0.0.1:${PORT}/demo/`));
