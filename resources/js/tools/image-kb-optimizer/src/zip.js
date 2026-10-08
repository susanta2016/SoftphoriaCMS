// A minimal ZIP writer (no compression — JPEG, WebP and PNG are already
// compressed), built in the browser so images never leave the device.
// Pure: takes { name, data: Uint8Array } entries, returns the ZIP bytes.

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
});

export function crc32(data) {
    let c = 0xffffffff;
    for (let i = 0; i < data.length; i++) c = CRC_TABLE[(c ^ data[i]) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
}

/** Names made unique within the archive: "photo.jpg", "photo (2).jpg". */
export function uniqueNames(names) {
    const seen = new Map();
    return names.map((name) => {
        const key = name.toLowerCase();
        const count = (seen.get(key) || 0) + 1;
        seen.set(key, count);
        if (count === 1) return name;
        const dot = name.lastIndexOf('.');
        return dot > 0 ? `${name.slice(0, dot)} (${count})${name.slice(dot)}` : `${name} (${count})`;
    });
}

/** @param {Array<{ name: string, data: Uint8Array }>} files */
export function zip(files) {
    const enc = new TextEncoder();
    const names = uniqueNames(files.map((f) => f.name));
    const local = [];
    const central = [];
    let offset = 0;
    const now = new Date();
    const dosTime = (now.getHours() << 11) | (now.getMinutes() << 5) | Math.floor(now.getSeconds() / 2);
    const dosDate = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();

    files.forEach((file, i) => {
        const name = enc.encode(names[i]);
        const crc = crc32(file.data);
        const size = file.data.length;
        const head = new DataView(new ArrayBuffer(30));
        head.setUint32(0, 0x04034b50, true);
        head.setUint16(4, 20, true); // version needed
        head.setUint16(6, 0x0800, true); // UTF-8 names
        head.setUint16(8, 0, true); // stored
        head.setUint16(10, dosTime, true);
        head.setUint16(12, dosDate, true);
        head.setUint32(14, crc, true);
        head.setUint32(18, size, true);
        head.setUint32(22, size, true);
        head.setUint16(26, name.length, true);
        head.setUint16(28, 0, true);
        local.push(new Uint8Array(head.buffer), name, file.data);

        const dir = new DataView(new ArrayBuffer(46));
        dir.setUint32(0, 0x02014b50, true);
        dir.setUint16(4, 20, true);
        dir.setUint16(6, 20, true);
        dir.setUint16(8, 0x0800, true);
        dir.setUint16(10, 0, true);
        dir.setUint16(12, dosTime, true);
        dir.setUint16(14, dosDate, true);
        dir.setUint32(16, crc, true);
        dir.setUint32(20, size, true);
        dir.setUint32(24, size, true);
        dir.setUint16(28, name.length, true);
        dir.setUint32(42, offset, true);
        central.push(new Uint8Array(dir.buffer), name);

        offset += 30 + name.length + size;
    });

    const centralSize = central.reduce((s, p) => s + p.length, 0);
    const end = new DataView(new ArrayBuffer(22));
    end.setUint32(0, 0x06054b50, true);
    end.setUint16(8, files.length, true);
    end.setUint16(10, files.length, true);
    end.setUint32(12, centralSize, true);
    end.setUint32(16, offset, true);

    const parts = [...local, ...central, new Uint8Array(end.buffer)];
    const out = new Uint8Array(parts.reduce((s, p) => s + p.length, 0));
    let p = 0;
    for (const part of parts) {
        out.set(part, p);
        p += part.length;
    }
    return out;
}
