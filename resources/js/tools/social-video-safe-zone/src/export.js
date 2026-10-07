// Exports, all generated in the browser and saved through a download link (spec 9).
import { drawElements, drawReference, drawSource, drawZones } from './render.js';
import { fileName } from './report.js';

export function download(blob, name) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}

const toBlob = (canvas) => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

const FOOTER = 84;

// Settings line in a footer strip below the frame, so it never hides part of the picture.
function stamp(ctx, frame, lines) {
    ctx.save();
    ctx.font = '500 22px system-ui, sans-serif';
    ctx.fillStyle = '#111111';
    ctx.fillRect(0, frame.height, frame.width, FOOTER);
    ctx.fillStyle = '#FFFFFF';
    ctx.textBaseline = 'top';
    lines.forEach((l, i) => ctx.fillText(l, 16, frame.height + 12 + i * 32));
    ctx.restore();
}

/** Current frame + zones + element boxes + numbers at reference resolution, plus a settings footer. */
export async function annotatedFrame({ source, sw, sh, resolved, elements, numbers, issueByElement, settings, datasetVersion }) {
    const { frame, zones, platform } = resolved;
    const c = document.createElement('canvas');
    c.width = frame.width;
    c.height = frame.height + FOOTER;
    const ctx = c.getContext('2d');
    drawSource(ctx, source, sw, sh, frame);
    drawZones(ctx, zones, { opacity: 0.5, provisional: platform.status !== 'measured' });
    drawElements(ctx, elements, { numbers, issueByElement });
    stamp(ctx, frame, [`${platform.name} · caption ${resolved.captionUsed} · CC ${settings.cc ? 'on' : 'off'} · device view ${resolved.device}`, `Safe Zone Checker · dataset ${datasetVersion}${platform.status !== 'measured' ? ' · PROVISIONAL zones' : ''}`]);
    return toBlob(c);
}

/** Zones only on transparency, for use as a guide layer in an editor. */
export async function overlayTemplate({ resolved, datasetVersion, reference }) {
    const { frame, zones, platform } = resolved;
    const c = document.createElement('canvas');
    c.width = frame.width;
    c.height = frame.height;
    const ctx = c.getContext('2d');
    drawZones(ctx, zones, { opacity: 0.7, provisional: platform.status !== 'measured' });
    if (reference) drawReference(ctx, reference, frame);
    ctx.save();
    ctx.font = '600 22px system-ui, sans-serif';
    ctx.fillStyle = 'rgba(0,0,0,0.6)';
    const text = `${platform.name} safe zones · caption ${resolved.captionUsed} · dataset ${datasetVersion}${platform.status !== 'measured' ? ' · PROVISIONAL, not measured' : ''}`;
    ctx.fillRect(0, frame.height / 2 - 20, frame.width, 40);
    ctx.fillStyle = '#FFFFFF';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, frame.width / 2, frame.height / 2);
    ctx.restore();
    return toBlob(c);
}

export const exportNames = {
    annotated: (p, c) => fileName(p, c, 'png').replace('safe-zone_', 'safe-zone_annotated_'),
    overlay: (p, c) => fileName(p, c, 'png').replace('safe-zone_', 'safe-zone_overlay_'),
    report: (c) => fileName('report', c, 'json'),
};
