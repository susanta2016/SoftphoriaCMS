import { test } from 'node:test';
import assert from 'node:assert/strict';
import { crc32, uniqueNames, zip } from '../src/zip.js';

// Reads a stored (uncompressed) ZIP back through its central directory.
function unzip(bytes) {
    const v = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const end = bytes.length - 22;
    assert.equal(v.getUint32(end, true), 0x06054b50, 'end of central directory');
    const count = v.getUint16(end + 10, true);
    let p = v.getUint32(end + 16, true);
    const out = [];
    for (let i = 0; i < count; i++) {
        assert.equal(v.getUint32(p, true), 0x02014b50, 'central directory entry');
        const crc = v.getUint32(p + 16, true);
        const size = v.getUint32(p + 24, true);
        const nameLen = v.getUint16(p + 28, true);
        const localAt = v.getUint32(p + 42, true);
        const name = new TextDecoder().decode(bytes.subarray(p + 46, p + 46 + nameLen));
        assert.equal(v.getUint32(localAt, true), 0x04034b50, 'local header');
        const localNameLen = v.getUint16(localAt + 26, true);
        const data = bytes.subarray(localAt + 30 + localNameLen, localAt + 30 + localNameLen + size);
        assert.equal(crc32(data), crc, `crc of ${name}`);
        out.push({ name, data });
        p += 46 + nameLen;
    }
    return out;
}

test('CRC-32 matches the standard check value', () => {
    assert.equal(crc32(new TextEncoder().encode('123456789')), 0xcbf43926);
});

test('a ZIP of several images reads back byte for byte', () => {
    const files = [
        { name: 'photo-50kb.jpg', data: Uint8Array.from({ length: 3000 }, (_, i) => i % 251) },
        { name: 'logo-50kb.png', data: Uint8Array.from([137, 80, 78, 71, 1, 2, 3]) },
        { name: 'café-50kb.webp', data: new Uint8Array(0) },
    ];
    const back = unzip(zip(files));
    assert.deepEqual(back.map((f) => f.name), files.map((f) => f.name));
    back.forEach((f, i) => assert.deepEqual(Array.from(f.data), Array.from(files[i].data)));
});

test('duplicate names are made unique', () => {
    assert.deepEqual(uniqueNames(['a.jpg', 'A.jpg', 'a.jpg', 'b']), ['a.jpg', 'A (2).jpg', 'a (3).jpg', 'b']);
    const back = unzip(zip([{ name: 'x.jpg', data: new Uint8Array([1]) }, { name: 'x.jpg', data: new Uint8Array([2]) }]));
    assert.deepEqual(back.map((f) => f.name), ['x.jpg', 'x (2).jpg']);
});
