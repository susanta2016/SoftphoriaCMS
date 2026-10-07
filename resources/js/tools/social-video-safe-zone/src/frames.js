// Browser-only: decodes the user's file locally, samples frames, runs the
// detector in a Web Worker and tracks detections into key elements (spec 5.2, 8).
// Nothing here sends data anywhere: the file is read through an object URL.
import { DECODE_TIMEOUT_MS, MAX_ANALYSIS_SECONDS, MAX_SAMPLES, REF_WIDTH, SAMPLE_INTERVAL_S, SCENE_CUT_DIFF } from './constants.js';
import { detect, thumbDiff } from './detector-core.js';
import { trackDetections } from './tracker.js';

export const ANALYSIS_WIDTH = 270;

export class AnalysisError extends Error {
    constructor(code, message) {
        super(message);
        this.code = code;
    }
}

const aborted = (signal) => {
    if (signal && signal.aborted) throw new AnalysisError('cancelled', 'Analysis cancelled.');
};

function once(target, ok, fail, ms, failCode, failMessage) {
    return new Promise((resolve, reject) => {
        let timer;
        const done = (fn, v) => { clearTimeout(timer); target.removeEventListener(ok, onOk); if (fail) target.removeEventListener(fail, onFail); fn(v); };
        const onOk = () => done(resolve);
        const onFail = () => done(reject, new AnalysisError(failCode, failMessage));
        target.addEventListener(ok, onOk);
        if (fail) target.addEventListener(fail, onFail);
        timer = setTimeout(() => done(reject, new AnalysisError(failCode, failMessage)), ms);
    });
}

const DECODE_HINT = 'This browser cannot decode the video. iPhone HEVC (.mov) files often do not play in Chrome or Firefox on Windows: export it as H.264 MP4, or load a screenshot of the frame instead.';

/** Loads a video element for the file and waits until a first frame is decodable. */
export async function loadVideo(file) {
    const video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.src = URL.createObjectURL(file);
    try {
        await once(video, 'loadeddata', 'error', DECODE_TIMEOUT_MS, 'decode', DECODE_HINT);
    } catch (e) {
        URL.revokeObjectURL(video.src);
        throw e;
    }
    if (!video.videoWidth || !video.videoHeight) {
        URL.revokeObjectURL(video.src);
        throw new AnalysisError('decode', DECODE_HINT);
    }
    return video;
}

export async function loadImage(file) {
    try {
        return await createImageBitmap(file);
    } catch {
        throw new AnalysisError('decode', 'This image could not be decoded. Try a PNG or JPG export.');
    }
}

async function seek(video, t) {
    if (Math.abs(video.currentTime - t) < 1e-3 && video.readyState >= 2) return;
    const p = once(video, 'seeked', 'error', 4000, 'decode', DECODE_HINT);
    video.currentTime = t;
    await p;
}

function grabGray(source, sw, sh, canvas, thumbCanvas) {
    const aw = ANALYSIS_WIDTH, ah = Math.max(1, Math.round((aw * sh) / sw));
    canvas.width = aw;
    canvas.height = ah;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(source, 0, 0, aw, ah);
    const px = ctx.getImageData(0, 0, aw, ah).data;
    const gray = new Uint8Array(aw * ah);
    for (let i = 0, j = 0; j < gray.length; i += 4, j++) gray[j] = (px[i] * 77 + px[i + 1] * 150 + px[i + 2] * 29) >> 8;
    const tctx = thumbCanvas.getContext('2d', { willReadFrequently: true });
    tctx.drawImage(source, 0, 0, 32, 32);
    const tp = tctx.getImageData(0, 0, 32, 32).data;
    const thumb = new Uint8Array(32 * 32);
    for (let i = 0, j = 0; j < thumb.length; i += 4, j++) thumb[j] = (tp[i] * 77 + tp[i + 1] * 150 + tp[i + 2] * 29) >> 8;
    return { gray, aw, ah, thumb };
}

const PLAYBACK_RATE = 8;
const STALL_MS = 3000;

/**
 * Captures the frames at `times` by playing the video fast and muted, which is far
 * quicker than seeking when keyframes are far apart. Calls onFrame(t) synchronously
 * while that frame is presented. Resolves with the times it could not capture (the
 * caller seeks to those), e.g. when the tab is hidden and playback is throttled.
 */
function captureByPlayback(video, times, onFrame, signal) {
    if (!('requestVideoFrameCallback' in HTMLVideoElement.prototype)) return Promise.resolve(times.slice());
    return new Promise((resolve) => {
        let i = 0, finished = false, stall;
        const end = () => {
            if (finished) return;
            finished = true;
            clearTimeout(stall);
            video.pause();
            video.removeEventListener('ended', end);
            video.playbackRate = 1;
            resolve(times.slice(i));
        };
        const arm = () => { clearTimeout(stall); stall = setTimeout(end, STALL_MS); };
        const onVideoFrame = (_now, meta) => {
            if (finished) return;
            if (signal && signal.aborted) return end();
            arm();
            while (i < times.length && times[i] <= meta.mediaTime + 0.02) onFrame(times[i++]);
            if (i >= times.length) return end();
            video.requestVideoFrameCallback(onVideoFrame);
        };
        video.addEventListener('ended', end);
        video.currentTime = 0;
        video.playbackRate = PLAYBACK_RATE;
        video.requestVideoFrameCallback(onVideoFrame);
        arm();
        const p = video.play();
        if (p && p.catch) p.catch(end);
    });
}

function makeDetector() {
    let worker = null;
    try {
        worker = new Worker(new URL('./detector.worker.js', import.meta.url), { type: 'module' });
    } catch {
        worker = null;
    }
    let seq = 0;
    const pending = new Map();
    if (worker) {
        worker.onmessage = (e) => {
            const p = pending.get(e.data.id);
            if (!p) return;
            pending.delete(e.data.id);
            e.data.error ? p.reject(new Error(e.data.error)) : p.resolve(e.data.boxes);
        };
        worker.onerror = (e) => {
            for (const p of pending.values()) p.reject(new Error(e.message || 'worker error'));
            pending.clear();
        };
    }
    return {
        run(gray, w, h) {
            if (!worker) return Promise.resolve(detect(gray, w, h)); // fallback: main thread
            const id = ++seq;
            return new Promise((resolve, reject) => {
                pending.set(id, { resolve, reject });
                worker.postMessage({ id, gray: gray.buffer, width: w, height: h }, [gray.buffer]);
            });
        },
        close() { if (worker) worker.terminate(); },
    };
}

/**
 * Analyses a loaded source.
 * @param {{ kind:'video'|'image', video?:HTMLVideoElement, bitmap?:ImageBitmap }} src
 * @param {{ onProgress?:(done:number,total:number)=>void, signal?:AbortSignal }} opts
 * @returns {Promise<{ kind, width, height, durationS, analysedS, sampleIntervalS, samples:number[], elements:Array, truncated:boolean }>}
 */
export async function analyse(src, opts = {}) {
    const { onProgress, signal } = opts;
    const canvas = document.createElement('canvas');
    const thumbCanvas = document.createElement('canvas');
    thumbCanvas.width = 32;
    thumbCanvas.height = 32;
    const det = makeDetector();
    try {
        if (src.kind === 'image') {
            const b = src.bitmap;
            const { gray, aw, ah } = grabGray(b, b.width, b.height, canvas, thumbCanvas);
            onProgress && onProgress(0, 1);
            const boxes = await det.run(gray, aw, ah);
            onProgress && onProgress(1, 1);
            const k = REF_WIDTH / aw;
            const elements = trackDetections([{ t: 0, boxes: boxes.map((x) => ({ rect: x.rect.map((v) => Math.round(v * k)), confidence: x.confidence })) }], { isImage: true });
            for (const e of elements) { delete e.samples; delete e.fromS; delete e.toS; }
            return { kind: 'image', width: b.width, height: b.height, durationS: null, analysedS: null, sampleIntervalS: null, samples: [0], elements, truncated: false };
        }

        const video = src.video;
        const vw = video.videoWidth, vh = video.videoHeight;
        const durationS = Number.isFinite(video.duration) ? video.duration : 0;
        const analysedS = Math.min(durationS, MAX_ANALYSIS_SECONDS);
        const times = [];
        for (let t = 0; t < analysedS && times.length < MAX_SAMPLES; t += SAMPLE_INTERVAL_S) times.push(Math.round(t * 1000) / 1000);
        if (!times.length) times.push(0);

        const grabs = new Map();
        const pending = [];
        let done = 0;
        const total = times.length;
        // grab synchronously (the frame on screen now), detect asynchronously in the worker
        const grabNow = (t) => {
            const g = grabGray(video, vw, vh, canvas, thumbCanvas);
            const job = det.run(g.gray, g.aw, g.ah).then((boxes) => {
                grabs.set(t, { thumb: g.thumb, boxes, aw: g.aw });
                onProgress && onProgress(++done, total);
            });
            pending.push(job);
            return job;
        };
        const grabAt = async (t) => {
            aborted(signal);
            await seek(video, Math.min(t, Math.max(0, durationS - 0.05)));
            await grabNow(t);
        };
        const missed = await captureByPlayback(video, times, grabNow, signal);
        aborted(signal);
        for (const t of missed) await grabAt(t);
        await Promise.all(pending);
        aborted(signal);
        // scene cuts: add the midpoint of any interval whose thumbnails differ a lot
        const extra = [];
        for (let i = 1; i < times.length && times.length + extra.length < MAX_SAMPLES; i++) {
            if (thumbDiff(grabs.get(times[i - 1]).thumb, grabs.get(times[i]).thumb) > SCENE_CUT_DIFF) extra.push(Math.round(((times[i - 1] + times[i]) / 2) * 1000) / 1000);
        }
        for (const t of extra) await grabAt(t);
        const all = [...times, ...extra].sort((a, b) => a - b);
        const frames = all.map((t) => {
            const g = grabs.get(t);
            const k = REF_WIDTH / g.aw;
            return { t, boxes: g.boxes.map((x) => ({ rect: x.rect.map((v) => Math.round(v * k)), confidence: x.confidence })) };
        });
        const elements = trackDetections(frames, { intervalS: SAMPLE_INTERVAL_S });
        return { kind: 'video', width: vw, height: vh, durationS, analysedS, sampleIntervalS: SAMPLE_INTERVAL_S, samples: all, elements, truncated: durationS > MAX_ANALYSIS_SECONDS };
    } finally {
        det.close();
    }
}
