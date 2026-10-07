// Generates data/safezones.v1.json (+ an importable data/safezones.v1.js twin)
// from the FINAL P0 measurement data. The P0 workspace is read only.
//
//   node resources/js/tools/social-video-safe-zone/scripts/generate-dataset.mjs [--p0 <dir>] [--check]
//
// --check regenerates in memory and fails if the committed files differ (used by the tests).
//
// Row selection and rounding mirror the P0 completion report (automation/lib/completion.js):
// usable === 'yes', playing, inside the video picture, values = the row's left/top/right/bottom
// (P0 rounds each edge to the nearest 10 px). Every zone records the P0 rows it came from.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = path.join(HERE, '..', 'data');
const args = process.argv.slice(2);
const P0 = args.includes('--p0') ? args[args.indexOf('--p0') + 1] : (process.env.P0_DIR || 'C:/Users/susan/Documents/P0-measurements');
const CHECK = args.includes('--check');

export const DATASET_VERSION = '1.0.0';
const W = 1080;
const CORE = ['S0', 'S1', 'S2', 'S3'];
const SIDE_CROP_MIN = 5; // a visible window narrower than the frame by < 5 px is rounding, not a crop

export function build(p0Dir = P0) {
    const read = (f) => JSON.parse(fs.readFileSync(path.join(p0Dir, 'data', f), 'utf8'));
    const measurementsRaw = fs.readFileSync(path.join(p0Dir, 'data', 'measurements.json'));
    const rows = JSON.parse(measurementsRaw);
    const shots = read('shots.json');
    const sources = read('sources.json');

    const inVideo = (r) => !r.region_kind.includes('outside');
    const isStatusBar = (r) => /status bar/i.test(r.region_label);
    const use = rows.filter((r) => r.usable === 'yes' && r.method === 'grid-read-auto' && r.playback_state === 'playing' && inVideo(r));
    const deviceId = (os, model) => `${os}-${model.toLowerCase().replace(/[^a-z0-9]+/g, '')}`;

    const union = (rs) => [Math.min(...rs.map((r) => r.left)), Math.min(...rs.map((r) => r.top)), Math.max(...rs.map((r) => r.right)), Math.max(...rs.map((r) => r.bottom))];
    const uniq = (a) => [...new Set(a)];

    const parseWindow = (s) => {
        const g = (k) => Number((s.match(new RegExp(`${k}=(-?\\d+)`)) || [])[1]);
        return { left: g('L'), top: g('T'), right: g('R'), bottom: g('B') };
    };

    // One zone from a set of rows: union over all devices + per-device unions.
    const zoneFrom = (rs, { id, kind, severity, label, captionVariant, appliesTo, shape = (r) => r, derivation, platformDevices }) => {
        if (!rs.length) return null;
        const byDevice = {};
        for (const d of uniq(rs.map((r) => deviceId(r.os, r.device_model)))) byDevice[d] = shape(union(rs.filter((r) => deviceId(r.os, r.device_model) === d)));
        const devices = Object.keys(byDevice).sort();
        return {
            id, kind, severity, label,
            ...(captionVariant ? { captionVariant } : {}),
            rect: shape(union(rs)),
            measuredRect: union(rs),
            byDevice,
            appliesTo,
            provenance: devices.length >= platformDevices.length ? 'measured' : 'measured-one-phone',
            devices,
            ...(derivation ? { derivation } : {}),
            p0Rows: uniq(rs.map((r) => r.measurement_id)).sort(),
        };
    };

    const sideCropZones = (prefix, windows, platformDevices, frameH) => {
        // windows: { deviceId: {left,right,top,bottom} } in the post's own pixel space
        const zones = [];
        const left = {}, right = {};
        for (const [d, w] of Object.entries(windows)) {
            if (w.left >= SIDE_CROP_MIN) left[d] = [0, 0, w.left, frameH];
            if (W - w.right >= SIDE_CROP_MIN) right[d] = [w.right, 0, W, frameH];
        }
        const mk = (byDevice, side) => {
            const ds = Object.keys(byDevice).sort();
            if (!ds.length) return;
            const rects = Object.values(byDevice);
            const rect = side === 'left' ? [0, 0, Math.max(...rects.map((r) => r[2])), frameH] : [Math.min(...rects.map((r) => r[0])), 0, W, frameH];
            zones.push({
                id: `${prefix}-side-crop-${side}`, kind: 'side-crop', severity: 'hard',
                label: `Area cut off on taller screens (${side})`,
                rect, byDevice, appliesTo: {}, provenance: 'derived', devices: ds,
                derivation: 'From the visible window of the 9:16 calibration posts (P0 shots.json): the strip outside it is not shown on that phone.',
            });
        };
        mk(left, 'left');
        mk(right, 'right');
        return zones;
    };

    const maskZones = (prefix, windows, frameH) => {
        const top = {}, bottom = {};
        for (const [d, w] of Object.entries(windows)) {
            if (w.top >= SIDE_CROP_MIN) top[d] = [0, 0, W, w.top];
            if (frameH - w.bottom >= SIDE_CROP_MIN) bottom[d] = [0, w.bottom, W, frameH];
        }
        const out = [];
        for (const [side, byDevice] of [['top', top], ['bottom', bottom]]) {
            const ds = Object.keys(byDevice).sort();
            if (!ds.length) continue;
            const rects = Object.values(byDevice);
            const rect = side === 'top' ? [0, 0, W, Math.max(...rects.map((r) => r[3]))] : [0, Math.min(...rects.map((r) => r[1])), W, frameH];
            out.push({
                id: `${prefix}-masked-${side}`, kind: 'masked', severity: 'hard', label: `Area masked by the app (${side})`,
                rect, byDevice, appliesTo: {}, provenance: 'derived', devices: ds,
                derivation: 'From the visible window of the 9:19.5 calibration post (P0 shots.json).',
            });
        }
        return out;
    };

    const windowsFor = (platform, posts) => {
        const out = {};
        for (const s of shots.filter((x) => x.platform === platform && x.status === 'processed' && posts.includes(x.post) && x.visible_window)) {
            const d = deviceId(s.os, s.device_model);
            const w = parseWindow(s.visible_window);
            const cur = out[d];
            out[d] = cur ? { left: Math.max(cur.left, w.left), top: Math.max(cur.top, w.top), right: Math.min(cur.right, w.right), bottom: Math.min(cur.bottom, w.bottom) } : w;
        }
        return out;
    };

    const devicesFor = (platform) => {
        const rs = use.filter((r) => r.platform === platform);
        const windows = windowsFor(platform, ['S0', 'S1', 'S2', 'S3', 'CC']);
        return uniq(rs.map((r) => deviceId(r.os, r.device_model))).sort().map((id) => {
            const dr = rs.filter((r) => deviceId(r.os, r.device_model) === id);
            const w = windows[id];
            return {
                id, os: dr[0].os, model: dr[0].device_model, osVersion: dr[0].os_version,
                appVersions: uniq(dr.map((r) => r.app_version)).sort(),
                visibleWindow: w ? [w.left, w.top, w.right, w.bottom] : null,
            };
        });
    };

    const platformZones = (platform, prefix, { captionScenarios, ccOnly = false }) => {
        const devs = devicesFor(platform);
        const pd = devs.map((d) => d.id);
        const rs = use.filter((r) => r.platform === platform);
        const core = rs.filter((r) => CORE.includes(r.scenario));
        const zones = [];
        const push = (z) => z && zones.push(z);

        // Top: status bar + app top bar, full width down to the lowest measured edge (completion report §4).
        push(zoneFrom(core.filter((r) => r.region_kind === 'top-bar' || isStatusBar(r)), {
            id: `${prefix}-top`, kind: 'top-bar', severity: 'hard', label: 'Status bar and top bar', appliesTo: {}, platformDevices: pd,
            shape: (u) => [0, 0, W, u[3]], derivation: 'Full width from the top of the frame to the lowest measured status-bar / top-bar edge.',
        }));
        // Actions: the icon column, extended to the right frame edge (completion report §4 right margin).
        push(zoneFrom(core.filter((r) => r.region_kind === 'actions'), {
            id: `${prefix}-actions`, kind: 'actions', severity: 'hard', label: 'Like, comment and share buttons', appliesTo: {}, platformDevices: pd,
            shape: (u) => [u[0], u[1], W, u[3]], derivation: 'Measured icon column, extended to the right frame edge.',
        }));
        // Caption block per caption length.
        for (const [variant, scenario, applies] of captionScenarios) {
            push(zoneFrom(rs.filter((r) => r.scenario === scenario && r.region_kind === 'caption'), {
                id: `${prefix}-caption-${variant.toLowerCase()}`, kind: 'caption', severity: 'hard',
                label: 'Username and caption', captionVariant: variant, appliesTo: { captions: applies }, platformDevices: pd,
            }));
        }
        // Other UI inside the picture (Instagram audio tile). Specks under 10 px are classifier noise (completion report §4).
        push(zoneFrom(core.filter((r) => r.region_kind === 'other' && !isStatusBar(r) && r.right_raw - r.left_raw >= 10 && r.bottom_raw - r.top_raw >= 10), {
            id: `${prefix}-audio`, kind: 'audio', severity: 'hard', label: 'Audio tile', appliesTo: {}, platformDevices: pd,
        }));
        // Progress bar: measured as a line at the bottom edge; given a 20 px band so it has an area.
        const prog = rows.filter((r) => r.platform === platform && r.usable === 'yes' && r.playback_state === 'playing' && CORE.includes(r.scenario) && r.region_kind.startsWith('progress') && r.top < 1920);
        push(zoneFrom(prog, {
            id: `${prefix}-progress`, kind: 'progress', severity: 'soft', label: 'Progress bar', appliesTo: {}, platformDevices: pd,
            shape: () => [0, 1900, W, 1920], derivation: 'Measured as a 1-2 px line at y1910; widened to the band y1900-1920 and full width (the bar fills with playback time).',
        }));
        // Expanded caption (Instagram S3E).
        const s3e = rs.filter((r) => r.scenario === 'S3E');
        push(zoneFrom(s3e.filter((r) => r.region_kind === 'caption'), {
            id: `${prefix}-expanded-caption`, kind: 'caption-expanded', severity: 'soft', label: 'Expanded caption (after tapping more)', appliesTo: { captions: ['C3E'] }, platformDevices: pd,
        }));
        push(zoneFrom(s3e.filter((r) => r.region_kind === 'other' && !isStatusBar(r)), {
            id: `${prefix}-expanded-other`, kind: 'caption-expanded', severity: 'soft', label: 'Expanded caption: audio and controls', appliesTo: { captions: ['C3E'] }, platformDevices: pd,
        }));
        // Platform auto-captions (CC on).
        const cc = zoneFrom(rs.filter((r) => r.region_kind === 'platform-captions'), {
            id: `${prefix}-platform-captions`, kind: 'platform-captions', severity: 'hard', label: 'Auto-captions', appliesTo: { cc: true }, platformDevices: pd,
        });
        push(cc);
        // Side crop on taller screens.
        zones.push(...sideCropZones(prefix, windowsFor(platform, ['S0', 'S1', 'S2', 'S3', 'CC']), pd, 1920));
        return { devs, zones, cc };
    };

    // 9:19.5 uploads: measured on the PRES-9x19.5 post (caption C1 only), in its own 1080 x 2340 space.
    const tallZones = (platform, prefix) => {
        const devs = devicesFor(platform).map((d) => d.id);
        const rs = use.filter((r) => r.platform === platform && r.scenario === 'PRES-9X19.5');
        const H = 2340;
        const zones = [];
        const push = (z) => z && zones.push(z);
        push(zoneFrom(rs.filter((r) => r.region_kind === 'top-bar' || isStatusBar(r)), {
            id: `${prefix}-tall-top`, kind: 'top-bar', severity: 'hard', label: 'Status bar and top bar', appliesTo: {}, platformDevices: devs,
            shape: (u) => [0, 0, W, u[3]], derivation: 'Full width from the top of the frame to the lowest measured status-bar / top-bar edge.',
        }));
        push(zoneFrom(rs.filter((r) => r.region_kind === 'actions'), {
            id: `${prefix}-tall-actions`, kind: 'actions', severity: 'hard', label: 'Like, comment and share buttons', appliesTo: {}, platformDevices: devs,
            shape: (u) => [u[0], u[1], W, u[3]], derivation: 'Measured icon column, extended to the right frame edge.',
        }));
        push(zoneFrom(rs.filter((r) => r.region_kind === 'caption'), {
            id: `${prefix}-tall-caption`, kind: 'caption', severity: 'hard', label: 'Username and caption', captionVariant: 'C1', appliesTo: {}, platformDevices: devs,
        }));
        push(zoneFrom(rs.filter((r) => r.region_kind === 'other' && !isStatusBar(r) && r.right_raw - r.left_raw >= 10 && r.bottom_raw - r.top_raw >= 10), {
            id: `${prefix}-tall-audio`, kind: 'audio', severity: 'hard', label: 'Audio tile', appliesTo: {}, platformDevices: devs,
        }));
        const windows = windowsFor(platform, ['PRES-9x19.5']);
        zones.push(...maskZones(`${prefix}-tall`, windows, H));
        zones.push(...sideCropZones(`${prefix}-tall`, windows, devs, H));
        return { frame: { width: W, height: H, aspect: '9:19.5' }, captionMeasured: 'C1', zones };
    };

    const presentationFor = (platform) => {
        const out = {};
        for (const s of shots.filter((x) => x.platform === platform && x.status === 'processed' && /^PRES-/.test(x.post))) {
            const aspect = s.post.replace('PRES-', '').replace('x', ':');
            const w = parseWindow(s.visible_window);
            (out[aspect] ||= { byDevice: {} }).byDevice[deviceId(s.os, s.device_model)] = { presentation: s.presentation, visibleWindow: [w.left, w.top, w.right, w.bottom] };
        }
        return out;
    };

    const ig = platformZones('instagram-reels', 'ig', { captionScenarios: [['C0', 'S0', ['C0']], ['C1', 'S1', ['C1']], ['C2', 'S2', ['C2']], ['C3', 'S3', ['C3', 'C3E']]] });
    const yt = platformZones('youtube-shorts', 'yt', { captionScenarios: [['C0', 'S0', ['C0']], ['C1', 'S1', ['C1']], ['C2', 'S2', ['C2', 'C3']]] });

    // YouTube sound line: provisional, one line (50 px) above the widest caption block.
    const ytCapC2 = yt.zones.find((z) => z.id === 'yt-caption-c2');
    yt.zones.push({
        id: 'yt-sound-line', kind: 'sound-line', severity: 'soft', label: 'Music or sound line',
        rect: [ytCapC2.rect[0], ytCapC2.rect[1] - 50, ytCapC2.rect[2], ytCapC2.rect[1]],
        appliesTo: { soundLine: true }, provenance: 'provisional', devices: [],
        derivation: 'Not measured. Real-feed screenshots show one extra line above the title for videos with music; assumed 50 px tall.',
    });

    const presIg = presentationFor('instagram-reels');
    const presYt = presentationFor('youtube-shorts');
    const behaviour = (platform) => ({
        '9:16': 'scored',
        '9:19.5': 'scored-tall',
        '4:5': 'preview-only',
        '1:1': 'preview-only',
        '16:9': platform === 'youtube-shorts' ? 'not-a-short' : 'preview-only',
    });

    // TikTok: provisional stand-in = union of the two measured platforms (decision 1, approved 2026-10-07).
    const pick = (zs, id) => zs.find((z) => z.id === id);
    const unionRects = (rs) => [Math.min(...rs.map((r) => r[0])), Math.min(...rs.map((r) => r[1])), Math.max(...rs.map((r) => r[2])), Math.max(...rs.map((r) => r[3]))];
    const allCaptions = [...ig.zones, ...yt.zones].filter((z) => z.kind === 'caption').map((z) => z.rect);
    const crops = [...ig.zones, ...yt.zones].filter((z) => z.kind === 'side-crop');
    const tt = (id, kind, label, rect) => ({
        id, kind, severity: 'hard', label, rect, appliesTo: {}, provenance: 'provisional', devices: [],
        derivation: 'TikTok was not measured. Stand-in = union of the measured Instagram Reels and YouTube Shorts zones of this kind.',
    });
    const ttZones = [
        tt('tt-top', 'top-bar', 'Status bar and top bar', unionRects([pick(ig.zones, 'ig-top').rect, pick(yt.zones, 'yt-top').rect])),
        tt('tt-actions', 'actions', 'Like, comment and share buttons', unionRects([pick(ig.zones, 'ig-actions').rect, pick(yt.zones, 'yt-actions').rect])),
        tt('tt-caption', 'caption', 'Username and caption', unionRects(allCaptions)),
        tt('tt-side-crop-left', 'side-crop', 'Area cut off on taller screens (left)', [0, 0, Math.max(...crops.filter((z) => z.id.endsWith('left')).map((z) => z.rect[2])), 1920]),
        tt('tt-side-crop-right', 'side-crop', 'Area cut off on taller screens (right)', [Math.min(...crops.filter((z) => z.id.endsWith('right')).map((z) => z.rect[0])), 0, W, 1920]),
    ];

    const references = sources.map((s) => {
        const n = (re) => { const m = s.used_for.match(re) || s.exact_quote.match(re); return m ? Number(m[1]) : undefined; };
        const margins = s.platform === 'instagram-reels'
            ? { top: n(/top (\d+)/), bottom: n(/bottom (\d+)/), left: n(/sides (\d+)/), right: n(/sides (\d+)/) }
            : { top: n(/top (\d+)/), bottom: n(/bottom (\d+)/), left: n(/left (\d+)/), right: n(/right (\d+)/) };
        return { id: s.source_id, platform: s.platform, publisher: s.publisher, url: s.url, accessedOn: s.accessed_on, context: s.context, marginsPx: margins, note: 'Ads guidance. Comparison only, never scored.' };
    });

    return {
        schema: 'safezones/1',
        datasetVersion: DATASET_VERSION,
        generatedFrom: {
            p0Report: 'P0 completion report, status FINAL, 2026-10-07',
            measurementsSha1: crypto.createHash('sha1').update(measurementsRaw).digest('hex'),
            rowRule: "usable = yes, playing, inside the video picture; edges as recorded by P0 (nearest 10 px)",
        },
        frame: { width: W, height: 1920, aspect: '9:16' },
        captionVariants: {
            C0: 'No caption', C1: 'Short (up to 40 characters)', C2: 'Medium (up to 155 characters)', C3: 'Long, collapsed', C3E: 'Long, expanded after tapping more',
        },
        platforms: [
            {
                id: 'instagram-reels', name: 'Instagram Reels', status: 'measured', devices: ig.devs,
                captionVariants: ['C0', 'C1', 'C2', 'C3', 'C3E'], supportsCc: true, supportsSoundLine: false,
                zones: ig.zones, tall: tallZones('instagram-reels', 'ig'), presentation: { behaviour: behaviour('instagram-reels'), measured: presIg },
                notes: ['Auto-captions band measured on iPhone only: the Android test phone (Instagram 531) has no Captions option (decision 2026-10-07).', 'Expanded caption measured on Android only: iPhone Instagram 450.1.0 shrinks the video instead.'],
                overrides: [],
            },
            {
                id: 'youtube-shorts', name: 'YouTube Shorts', status: 'measured', devices: yt.devs,
                captionVariants: ['C0', 'C1', 'C2', 'C3'], supportsCc: true, supportsSoundLine: true,
                zones: yt.zones, tall: tallZones('youtube-shorts', 'yt'), presentation: { behaviour: behaviour('youtube-shorts'), measured: presYt },
                notes: ['Titles are limited to 100 characters, so the long caption (C3) uses the medium caption zone.', 'A 16:9 upload is not shown as a Short; it plays on the regular watch page.', 'The paused overlay is not shown as a zone: P0 recorded it as one region spanning almost the whole frame (header, chips, centre icon and dimming merged), so its parts cannot be drawn separately.'],
                overrides: [],
            },
            {
                id: 'tiktok', name: 'TikTok', status: 'provisional', devices: [],
                captionVariants: ['C0', 'C1', 'C2', 'C3'], supportsCc: false, supportsSoundLine: false,
                zones: ttZones, tall: null, presentation: { behaviour: { '9:16': 'scored', '9:19.5': 'preview-only', '4:5': 'preview-only', '1:1': 'preview-only', '16:9': 'preview-only' }, measured: {} },
                notes: ['Provisional, not measured: TikTok is blocked in India, so no TikTok screen was measured. Zones are a stand-in built from Instagram Reels and YouTube Shorts; the verdict never shows Pass.'],
                overrides: [],
            },
        ],
        references,
    };
}

const isMain = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
    if (!fs.existsSync(path.join(P0, 'data', 'measurements.json'))) {
        console.error(`P0 data not found under ${P0}. Pass --p0 <dir> or set P0_DIR.`);
        process.exit(2);
    }
    const data = build(P0);
    const json = `${JSON.stringify(data, null, 2)}\n`;
    const js = `// Generated by scripts/generate-dataset.mjs from the FINAL P0 data. Do not edit by hand.\nexport default ${JSON.stringify(data, null, 2)};\n`;
    const jsonPath = path.join(OUT_DIR, 'safezones.v1.json');
    const jsPath = path.join(OUT_DIR, 'safezones.v1.js');
    if (CHECK) {
        const same = fs.existsSync(jsonPath) && fs.readFileSync(jsonPath, 'utf8') === json && fs.readFileSync(jsPath, 'utf8') === js;
        console.log(same ? 'dataset up to date' : 'dataset differs from P0 data: regenerate');
        process.exit(same ? 0 : 1);
    }
    fs.mkdirSync(OUT_DIR, { recursive: true });
    fs.writeFileSync(jsonPath, json);
    fs.writeFileSync(jsPath, js);
    console.log(`wrote ${path.relative(process.cwd(), jsonPath)} (${data.platforms.map((p) => `${p.id}: ${p.zones.length} zones`).join(', ')})`);
}
