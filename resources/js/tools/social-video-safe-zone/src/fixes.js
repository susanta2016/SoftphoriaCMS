import { FIX_SCALE_COST_PER_UNIT, FIX_SCALE_MIN, FIX_SCALE_STEP } from './constants.js';
import { inside, overlaps, scaleAboutCentre, translate } from './geometry.js';

const clears = (r, zones) => zones.every((z) => !overlaps(r, z.rect));

// Moves that put an edge of the box on an edge of a zone or of the frame.
function candidates(lo, hi, zones, axis, limit) {
    const out = new Set([0, -lo, limit - hi]);
    for (const z of zones) {
        const zlo = z.rect[axis], zhi = z.rect[axis + 2];
        out.add(zlo - hi); // move before the zone
        out.add(zhi - lo); // move after the zone
    }
    return [...out];
}

function bestMove(r, zones, frame) {
    let best = null;
    const dxs = candidates(r[0], r[2], zones, 0, frame.width);
    const dys = candidates(r[1], r[3], zones, 1, frame.height);
    for (const dx of dxs) {
        for (const dy of dys) {
            const cost = Math.abs(dx) + Math.abs(dy);
            if (best && cost >= best.cost) continue;
            const m = translate(r, dx, dy);
            if (inside(m, frame) && clears(m, zones)) best = { dx, dy, cost };
        }
    }
    return best;
}

// Round a move away from zero, so rounding never pushes a box back into a zone.
const outward = (v) => (v < 0 ? Math.floor(v) : Math.ceil(v));

/**
 * Smallest change (at most one horizontal plus one vertical move, optionally a
 * shrink about the centre) that clears every hard zone and stays inside the frame.
 * 1% of shrinking costs as much as 10 px of movement.
 *
 * @returns null when the box already clears all zones; { impossible: true } when
 *   no change works; otherwise { dx, dy, scale, rect }.
 */
export function suggestFix(rect, zones, frame) {
    if (!zones.length || clears(rect, zones)) return null;
    let best = null;
    for (let i = 0; ; i++) {
        const scale = Math.round((1 - i * FIX_SCALE_STEP) * 100) / 100;
        if (scale < FIX_SCALE_MIN) break;
        const shrinkCost = (1 - scale) * FIX_SCALE_COST_PER_UNIT;
        if (best && shrinkCost >= best.cost) break;
        const r = scaleAboutCentre(rect, scale);
        const m = bestMove(r, zones, frame);
        if (!m) continue;
        const cost = m.cost + shrinkCost;
        if (!best || cost < best.cost) best = { dx: m.dx, dy: m.dy, scale, cost, base: r };
    }
    if (!best) return { impossible: true };
    let dx = outward(best.dx), dy = outward(best.dy);
    let moved = translate(best.base, dx, dy);
    if (!inside(moved, frame) || !clears(moved, zones)) {
        // Rounding outward hit the frame edge: fall back to the exact move.
        dx = best.dx;
        dy = best.dy;
        moved = translate(best.base, dx, dy);
    }
    return { dx, dy, scale: best.scale, rect: moved };
}

export function describeFix(fix) {
    if (!fix) return '';
    if (fix.impossible) return 'No position clears every zone; reduce the element size a lot or remove it.';
    const parts = [];
    if (fix.scale < 1) parts.push(`shrink to ${Math.round(fix.scale * 100)}%`);
    if (fix.dy) parts.push(`move ${fix.dy < 0 ? 'up' : 'down'} ${Math.abs(Math.round(fix.dy))} px`);
    if (fix.dx) parts.push(`${fix.dy ? '' : 'move '}${fix.dx < 0 ? 'left' : 'right'} ${Math.abs(Math.round(fix.dx))} px`.trim());
    const s = parts.join(', ');
    return s.charAt(0).toUpperCase() + s.slice(1);
}
