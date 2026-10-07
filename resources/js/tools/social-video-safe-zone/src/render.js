// Canvas drawing for the preview, the annotated export and overlay templates.
// Colours follow the Okabe-Ito colour-blind-safe palette; hard zones are also
// hatched and soft zones dotted, so colour is never the only cue.

export const COLORS = {
    hard: '#D55E00', // vermillion
    soft: '#56B4E9', // sky blue
    crop: '#3a3a3a',
    element: '#FFFFFF',
    selected: '#F0E442', // yellow
    critical: '#D55E00',
    warning: '#E69F00',
    ok: '#009E73',
    reference: '#F0E442',
};

const patternCache = new Map();
function pattern(ctx, kind, color) {
    const key = `${kind}|${color}`;
    if (patternCache.has(key)) return patternCache.get(key);
    const c = document.createElement('canvas');
    c.width = 16;
    c.height = 16;
    const p = c.getContext('2d');
    p.strokeStyle = color;
    p.fillStyle = color;
    if (kind === 'hatch') {
        p.lineWidth = 3;
        p.beginPath();
        p.moveTo(-4, 20); p.lineTo(20, -4);
        p.moveTo(-4, 4); p.lineTo(4, -4);
        p.moveTo(12, 20); p.lineTo(20, 12);
        p.stroke();
    } else {
        p.beginPath();
        p.arc(8, 8, 2.2, 0, Math.PI * 2);
        p.fill();
    }
    const pat = ctx.createPattern(c, 'repeat');
    patternCache.set(key, pat);
    return pat;
}

function zoneStyle(z) {
    if (z.kind === 'side-crop' || z.kind === 'masked') return { fill: COLORS.crop, kind: 'hatch', stroke: '#000000' };
    return z.severity === 'hard' ? { fill: COLORS.hard, kind: 'hatch', stroke: COLORS.hard } : { fill: COLORS.soft, kind: 'dots', stroke: COLORS.soft };
}

/** Draws zones in reference coordinates (the caller sets the transform). */
export function drawZones(ctx, zones, { opacity = 0.45, labels = true, provisional = false, fontScale = 1 } = {}) {
    for (const z of zones) {
        const [l, t, r, b] = z.rect;
        const s = zoneStyle(z);
        ctx.save();
        ctx.globalAlpha = opacity * 0.55;
        ctx.fillStyle = s.fill;
        ctx.fillRect(l, t, r - l, b - t);
        ctx.globalAlpha = Math.min(1, opacity * 1.2);
        ctx.fillStyle = pattern(ctx, s.kind, s.fill === COLORS.crop ? '#000000' : '#FFFFFF');
        ctx.fillRect(l, t, r - l, b - t);
        ctx.globalAlpha = 1;
        ctx.strokeStyle = s.stroke;
        ctx.lineWidth = 3;
        if (z.severity === 'soft') ctx.setLineDash([12, 8]);
        ctx.strokeRect(l + 1.5, t + 1.5, r - l - 3, b - t - 3);
        ctx.restore();
        if (labels && r - l > 90 && b - t > 34) {
            const text = `${z.label}${provisional || z.provenance === 'provisional' ? ' (provisional)' : ''}`;
            label(ctx, text, l + 8, t + 8, r - l - 16, 22 * fontScale);
        }
    }
}

function label(ctx, text, x, y, maxW, size) {
    ctx.save();
    ctx.font = `600 ${size}px system-ui, sans-serif`;
    let s = text;
    while (s.length > 4 && ctx.measureText(s).width > maxW) s = `${s.slice(0, -2)}…`;
    const w = ctx.measureText(s).width;
    ctx.fillStyle = 'rgba(0,0,0,0.72)';
    ctx.fillRect(x, y, w + 12, size + 10);
    ctx.fillStyle = '#FFFFFF';
    ctx.textBaseline = 'top';
    ctx.fillText(s, x + 6, y + 5);
    ctx.restore();
}

export const HANDLE = 22;

/** Draws element boxes with their numbers; the selected one gets resize handles. */
export function drawElements(ctx, elements, { selectedId = null, issueByElement = {}, numbers = {}, fontScale = 1 } = {}) {
    for (const el of elements) {
        const [l, t, r, b] = el.rect;
        const sev = issueByElement[el.id];
        const color = el.id === selectedId ? COLORS.selected : sev === 'critical' ? COLORS.critical : sev === 'warning' ? COLORS.warning : COLORS.element;
        ctx.save();
        ctx.lineWidth = el.id === selectedId ? 6 : 4;
        ctx.strokeStyle = '#000000';
        ctx.strokeRect(l, t, r - l, b - t);
        ctx.lineWidth = el.id === selectedId ? 3 : 2;
        ctx.strokeStyle = color;
        if (el.source === 'auto') ctx.setLineDash([10, 6]);
        ctx.strokeRect(l, t, r - l, b - t);
        ctx.setLineDash([]);
        const n = String(numbers[el.id] ?? '');
        if (n) {
            const size = 26 * fontScale;
            ctx.font = `700 ${size}px system-ui, sans-serif`;
            const w = Math.max(size + 8, ctx.measureText(n).width + 14);
            const by = t - size - 12 >= 0 ? t - size - 12 : t + 4;
            ctx.fillStyle = color;
            ctx.fillRect(l, by, w, size + 10);
            ctx.fillStyle = '#000000';
            ctx.textBaseline = 'top';
            ctx.fillText(n, l + 7, by + 5);
        }
        if (el.id === selectedId) {
            ctx.fillStyle = COLORS.selected;
            for (const [hx, hy] of [[l, t], [r, t], [l, b], [r, b]]) {
                ctx.fillRect(hx - HANDLE / 2, hy - HANDLE / 2, HANDLE, HANDLE);
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 2;
                ctx.strokeRect(hx - HANDLE / 2, hy - HANDLE / 2, HANDLE, HANDLE);
            }
        }
        ctx.restore();
    }
}

/** Ads guidance (comparison only): the safe rectangle as a dashed outline. */
export function drawReference(ctx, ref, frame) {
    const m = ref.marginsPx;
    if (!m || m.top == null) return;
    ctx.save();
    ctx.setLineDash([16, 10]);
    ctx.lineWidth = 4;
    ctx.strokeStyle = COLORS.reference;
    ctx.strokeRect(m.left, m.top, frame.width - m.left - m.right, frame.height - m.top - m.bottom);
    ctx.restore();
    label(ctx, `Ads guidance from ${ref.publisher.replace(/\s*\(.*\)$/, '')}: comparison only`, m.left + 8, m.top + 8, frame.width - m.left - m.right - 16, 20);
}

/** Draws the current frame letterboxed into the reference frame. */
export function drawSource(ctx, source, sw, sh, frame) {
    ctx.fillStyle = '#000000';
    ctx.fillRect(0, 0, frame.width, frame.height);
    if (!source) return;
    const s = Math.min(frame.width / sw, frame.height / sh);
    const w = sw * s, h = sh * s;
    ctx.drawImage(source, (frame.width - w) / 2, (frame.height - h) / 2, w, h);
}
