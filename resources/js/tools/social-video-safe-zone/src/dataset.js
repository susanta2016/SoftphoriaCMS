// Resolves the zones that apply to one platform under the user's settings.
// Everything comes from safezones.v1 (generated from P0 data); nothing here
// hard-codes a coordinate.

export const DEVICE_VIEWS = ['all', 'ios', 'android'];

export const getPlatform = (data, id) => data.platforms.find((p) => p.id === id) || null;

// Caption variants a platform does not offer fall back to the nearest one it has.
export function normaliseCaption(platform, caption) {
    if (platform.captionVariants.includes(caption)) return caption;
    if (caption === 'C3E') return 'C3';
    return 'C1';
}

function applies(zone, s) {
    const a = zone.appliesTo || {};
    if (a.captions && !a.captions.includes(s.caption)) return false;
    if (a.cc && !s.cc) return false;
    if (a.soundLine && !s.soundLine) return false;
    return true;
}

function rectForView(zone, platform, view) {
    if (view === 'all' || platform.status !== 'measured' || !zone.byDevice) return zone.rect;
    const device = platform.devices.find((d) => d.os === view);
    if (!device) return null;
    return zone.byDevice[device.id] || null;
}

/**
 * @param {object} data      safezones.v1
 * @param {object} settings  { platformId, aspect, caption, cc, soundLine, device }
 * @returns {{ platform, mode, frame, zones, notices, captionUsed, device }}
 *   mode: scored | scored-tall | preview-only | not-a-short
 */
export function resolveZones(data, settings) {
    const platform = getPlatform(data, settings.platformId);
    if (!platform) throw new Error(`Unknown platform ${settings.platformId}`);
    const notices = [];
    const aspect = settings.aspect || '9:16';
    const mode = platform.presentation.behaviour[aspect] || 'preview-only';
    const device = platform.status === 'measured' ? (settings.device || 'all') : 'all';
    let caption = normaliseCaption(platform, settings.caption || 'C1');
    const cc = !!settings.cc && platform.supportsCc;
    const soundLine = !!settings.soundLine && platform.supportsSoundLine;

    if (settings.cc && !platform.supportsCc) notices.push({ code: 'cc-not-available', message: `${platform.name}: no auto-caption data, so the auto-captions setting has no effect.` });
    if (settings.caption && settings.caption !== caption) notices.push({ code: 'caption-mapped', message: `${platform.name} has no "${data.captionVariants[settings.caption]}" layout; using "${data.captionVariants[caption]}".` });

    let frame = data.frame;
    let source = platform.zones;
    if (mode === 'scored-tall') {
        frame = platform.tall.frame;
        source = platform.tall.zones;
        if (caption !== platform.tall.captionMeasured) notices.push({ code: 'tall-caption', message: `For 9:19.5 uploads only the short caption was measured; using it.` });
        if (cc || soundLine) notices.push({ code: 'tall-no-extras', message: 'Auto-captions and sound line were not measured for 9:19.5 uploads.' });
        caption = platform.tall.captionMeasured;
    } else if (mode !== 'scored') {
        source = [];
    }

    const zones = [];
    for (const z of source) {
        if (!applies(z, { caption, cc, soundLine })) continue;
        const rect = rectForView(z, platform, device);
        if (!rect) continue;
        zones.push({ id: z.id, kind: z.kind, severity: z.severity, label: z.label, rect, provenance: z.provenance, devices: z.devices });
    }
    if (platform.status !== 'measured') notices.push({ code: 'provisional', message: `${platform.name} zones are provisional, not measured.` });
    return { platform, mode, frame, zones, notices, captionUsed: caption, device };
}

export const hardZones = (zones) => zones.filter((z) => z.severity === 'hard');
