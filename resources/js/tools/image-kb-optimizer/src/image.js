// Browser side: read, decode and re-encode images locally. Nothing here
// sends data anywhere; files are read with object URLs and canvases.

export const FORMAT_NAMES = { jpeg: 'JPEG', png: 'PNG', webp: 'WebP' };
export const EXTENSIONS = { jpeg: 'jpg', png: 'png', webp: 'webp' };
const MIME = { jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

export const MAX_INPUT_BYTES = 50 * 1024 * 1024;
export const MAX_DECODE_PIXELS = 60_000_000; // larger photos can exhaust a phone's memory

export class ImageError extends Error {
    constructor(code, message) {
        super(message);
        this.code = code;
    }
}

const ascii = (b, from, len) => String.fromCharCode(...b.subarray(from, from + len));

/** Format by content (never by file name): 'jpeg' | 'png' | 'webp', or a reason it is not supported. */
export function sniff(b) {
    if (b.length < 12) return { ok: false, code: 'empty', message: 'This file is empty or too short to be an image.' };
    if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return { ok: true, format: 'jpeg' };
    if (b[0] === 0x89 && ascii(b, 1, 3) === 'PNG') return { ok: true, format: 'png' };
    if (ascii(b, 0, 4) === 'RIFF' && ascii(b, 8, 4) === 'WEBP') return { ok: true, format: 'webp' };
    if (ascii(b, 0, 4) === 'GIF8') return { ok: false, code: 'unsupported', message: 'GIF images are not supported. Save it as JPG or PNG first.' };
    if (ascii(b, 4, 4) === 'ftyp') return { ok: false, code: 'unsupported', message: 'HEIC and AVIF photos are not supported. On an iPhone, share or export the photo as JPG first.' };
    return { ok: false, code: 'unsupported', message: 'This is not a JPG, PNG or WebP image.' };
}

/** Reads and decodes one file. The caller releases `url` when done. */
export async function load(file) {
    if (!file || file.size === 0) throw new ImageError('empty', 'This file is empty.');
    if (file.size > MAX_INPUT_BYTES) throw new ImageError('too-large', 'This file is larger than 50 MB, more than a browser can compress reliably. Export a smaller version first.');
    const head = new Uint8Array(await file.slice(0, 32).arrayBuffer());
    const kind = sniff(head);
    if (!kind.ok) throw new ImageError(kind.code, kind.message);

    const url = URL.createObjectURL(file);
    const image = new Image();
    try {
        // load/error rather than decode(): decode() can wait while the tab is hidden.
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = reject;
            image.src = url;
        });
    } catch {
        URL.revokeObjectURL(url);
        throw new ImageError('corrupt', 'This image could not be opened. The file may be damaged or incomplete.');
    }
    const width = image.naturalWidth;
    const height = image.naturalHeight;
    if (!width || !height) {
        URL.revokeObjectURL(url);
        throw new ImageError('corrupt', 'This image could not be opened. The file may be damaged.');
    }
    if (width * height > MAX_DECODE_PIXELS) {
        URL.revokeObjectURL(url);
        throw new ImageError('too-many-pixels', `This image is ${width} × ${height} px — too large to process safely in a browser. Resize it below about 60 megapixels first.`);
    }
    return { image, url, width, height, format: kind.format, transparent: kind.format === 'jpeg' ? false : hasTransparency(image) };
}

/** True when any pixel is noticeably transparent (sampled on a small copy). */
export function hasTransparency(image) {
    const scale = Math.min(1, 512 / Math.max(image.naturalWidth, image.naturalHeight));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(image.naturalWidth * scale));
    c.height = Math.max(1, Math.round(image.naturalHeight * scale));
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(image, 0, 0, c.width, c.height);
    const d = ctx.getImageData(0, 0, c.width, c.height).data;
    for (let i = 3; i < d.length; i += 4) if (d[i] < 250) return true;
    return false;
}

/**
 * An encoder for one image: encode(width, height, format, quality) → Blob.
 * One canvas is reused for every attempt. JPEG has no transparency, so a
 * white background is drawn first. Throws when the browser cannot write the
 * format (e.g. WebP in an old Safari silently returns PNG).
 */
export function encoderFor(image) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const encode = (width, height, format, quality) => new Promise((resolve, reject) => {
        canvas.width = width;
        canvas.height = height;
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.clearRect(0, 0, width, height);
        if (format === 'jpeg') {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
        }
        ctx.drawImage(image, 0, 0, width, height);
        canvas.toBlob((blob) => {
            if (!blob) return reject(new ImageError('encode-failed', 'Your browser could not encode this image. Try a smaller target or another format.'));
            if (blob.type !== MIME[format]) return reject(new ImageError('format-unsupported', `Your browser cannot create ${FORMAT_NAMES[format]} files. Choose JPEG instead.`));
            resolve(blob);
        }, MIME[format], format === 'png' ? undefined : quality);
    });
    encode.release = () => {
        canvas.width = 0;
        canvas.height = 0;
    };
    return encode;
}

/** "holiday-photo.jpeg" → "holiday-photo-50kb.jpg" */
export function outputName(name, targetText, format) {
    const base = (name || 'image').replace(/\.[^.]+$/, '').replace(/[\\/:*?"<>|]+/g, '-').slice(0, 80) || 'image';
    return `${base}-${targetText.replace(/\s+/g, '').toLowerCase()}.${EXTENSIONS[format]}`;
}
