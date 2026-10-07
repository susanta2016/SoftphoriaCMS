// Web Worker: runs the detector off the main thread. One message per frame.
import { detect } from './detector-core.js';

self.onmessage = (e) => {
    const { id, gray, width, height } = e.data;
    try {
        const boxes = detect(new Uint8Array(gray), width, height);
        self.postMessage({ id, boxes });
    } catch (err) {
        self.postMessage({ id, error: String(err && err.message ? err.message : err) });
    }
};
