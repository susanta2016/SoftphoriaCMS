// Reads subtitle file bytes as text without ever replacing characters
// silently. UTF-8 (with or without BOM) and UTF-16 with a BOM are read
// directly; anything else that is not valid UTF-8 is reported with its
// position, and the visitor may explicitly choose to read it as
// Windows-1252 instead (the usual encoding of older Western subtitle files).

export const MAX_BYTES = 5 * 1024 * 1024;

/**
 * @param {Uint8Array} bytes
 * @param {'auto'|'windows-1252'} mode
 * @returns {{ok: true, text: string, encoding: string, bom: boolean}
 *          | {ok: false, code: string, message: string, line?: number}}
 */
export function decodeBytes(bytes, mode = 'auto') {
    if (bytes.length > MAX_BYTES) {
        return { ok: false, code: 'file-too-large', message: 'This file is larger than 5 MiB. Split it into smaller files and check each one.' };
    }

    if (mode === 'windows-1252') {
        return { ok: true, text: new TextDecoder('windows-1252').decode(bytes), encoding: 'Windows-1252', bom: false };
    }

    if (bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
        const result = strictUtf8(bytes.subarray(3));
        return result.ok ? { ...result, bom: true } : result;
    }
    if (bytes[0] === 0xff && bytes[1] === 0xfe) {
        return { ok: true, text: new TextDecoder('utf-16le').decode(bytes.subarray(2)), encoding: 'UTF-16 LE', bom: true };
    }
    if (bytes[0] === 0xfe && bytes[1] === 0xff) {
        return { ok: true, text: new TextDecoder('utf-16be').decode(bytes.subarray(2)), encoding: 'UTF-16 BE', bom: true };
    }

    return strictUtf8(bytes);
}

function strictUtf8(bytes) {
    // Many zero bytes without a BOM: almost certainly UTF-16.
    let zeros = 0;
    for (let i = 0; i < Math.min(bytes.length, 4096); i++) if (bytes[i] === 0) zeros++;
    if (zeros > 0 && zeros >= Math.min(bytes.length, 4096) / 8) {
        return { ok: false, code: 'encoding-utf16-no-bom', message: 'This file looks like UTF-16 without a byte-order mark. Open it in a text editor and save it as UTF-8, then check it again.' };
    }

    try {
        return { ok: true, text: new TextDecoder('utf-8', { fatal: true }).decode(bytes), encoding: 'UTF-8', bom: false };
    } catch {
        return { ok: false, code: 'encoding-invalid-utf8', line: firstInvalidLine(bytes), message: 'This file is not valid UTF-8, so some characters cannot be read safely.' };
    }
}

// Line number (1-based) of the first byte sequence that is not valid UTF-8.
function firstInvalidLine(bytes) {
    let line = 1;
    for (let i = 0; i < bytes.length; i++) {
        const b = bytes[i];
        if (b === 0x0a) { line++; continue; }
        if (b < 0x80) continue;
        const size = b >= 0xf0 && b <= 0xf4 ? 4 : b >= 0xe0 ? 3 : b >= 0xc2 && b <= 0xdf ? 2 : 0;
        if (size === 0) return line;
        for (let k = 1; k < size; k++) if ((bytes[i + k] & 0xc0) !== 0x80) return line;
        try {
            new TextDecoder('utf-8', { fatal: true }).decode(bytes.subarray(i, i + size));
        } catch {
            return line;
        }
        i += size - 1;
    }
    return line;
}
