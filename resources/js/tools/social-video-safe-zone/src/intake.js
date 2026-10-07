// File type by content, not by name (spec 5.1). Pure: takes the first bytes.
import { MAX_FILE_BYTES } from './constants.js';

const ascii = (b, from, len) => String.fromCharCode(...b.slice(from, from + len));

/**
 * @param {Uint8Array} bytes  at least the first 32 bytes of the file
 * @param {number} size       file size in bytes
 * @returns {{ ok: true, kind: 'image'|'video', format: string } | { ok: false, code: string, message: string }}
 */
export function sniff(bytes, size) {
    if (size > MAX_FILE_BYTES) return { ok: false, code: 'too-large', message: 'This file is larger than 1 GB, which is more than a browser can analyse reliably. Export a shorter or smaller version.' };
    if (!bytes || bytes.length < 12) return { ok: false, code: 'unsupported', message: 'This file is empty or too short to be a video or image.' };
    if (bytes[0] === 0x89 && ascii(bytes, 1, 3) === 'PNG') return { ok: true, kind: 'image', format: 'png' };
    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return { ok: true, kind: 'image', format: 'jpeg' };
    if (ascii(bytes, 0, 4) === 'RIFF' && ascii(bytes, 8, 4) === 'WEBP') return { ok: true, kind: 'image', format: 'webp' };
    if (bytes[0] === 0x1a && bytes[1] === 0x45 && bytes[2] === 0xdf && bytes[3] === 0xa3) return { ok: true, kind: 'video', format: 'webm' };
    const box = ascii(bytes, 4, 4);
    if (box === 'ftyp') {
        const brand = ascii(bytes, 8, 4);
        if (/^(heic|heix|hevc|mif1|msf1|avif)$/.test(brand)) return { ok: false, code: 'unsupported', message: 'HEIC and AVIF still images are not supported. Export the frame as PNG or JPG.' };
        return { ok: true, kind: 'video', format: brand === 'qt  ' ? 'mov' : 'mp4' };
    }
    if (['moov', 'mdat', 'wide', 'free', 'skip'].includes(box)) return { ok: true, kind: 'video', format: 'mov' };
    const head = ascii(bytes, 0, Math.min(bytes.length, 64)).trimStart().toLowerCase();
    if (head.startsWith('<svg') || head.startsWith('<?xml')) return { ok: false, code: 'unsupported', message: 'SVG files are not accepted. Export the frame as PNG or JPG.' };
    return { ok: false, code: 'unsupported', message: 'Unsupported file. Use MP4, MOV or WebM video, or a PNG, JPG or WebP image.' };
}
