// Coverage, risk, score, verdict and issues for one platform (spec 5.3 and 6).
// Pure functions: no DOM, so the same code runs in the browser and in Node tests.
import {
    CRITICAL_COVERAGE, CRITICAL_DURATION_S, DETECTION_CONFIDENCE, ELEMENT_WEIGHTS, FAIL_SCORE,
    IMPORTANT_TYPES, PASS_SCORE, PROXIMITY_PX, RISK_CAP, SAMPLE_INTERVAL_S, SEVERITY_FACTOR, WARNING_COVERAGE,
} from './constants.js';
import { coverage, gap, union } from './geometry.js';
import { describeFix, suggestFix } from './fixes.js';

export const TYPE_LABEL = { text: 'Text', logo: 'Logo', face: 'Face', product: 'Product', other: 'Detected content' };
const SEVERITY_RANK = { critical: 3, warning: 2, notice: 1 };

const trusted = (el) => el.source === 'user';
const confidenceOf = (el) => (trusted(el) ? 1 : el.confidence ?? 0);

function samplesOf(el, input) {
    if (el.samples && el.samples.length) return el.samples;
    return [{ t: el.fromS ?? 0, rect: el.rect }];
}

// Seconds a set of samples stands for. Images count as "always on screen".
function secondsFor(count, el, input) {
    if (input.kind !== 'video') return Infinity;
    if (el.samples && el.samples.length) return count * (input.sampleIntervalS || SAMPLE_INTERVAL_S);
    return Math.max(0, (el.toS ?? input.durationS ?? 0) - (el.fromS ?? 0));
}

const pct = (v) => `${Math.round(v * 100)}%`;
function durationText(seconds, share, input) {
    if (input.kind !== 'video') return '';
    if (share >= 0.99) return 'the whole time it is on screen';
    return `${Math.round(seconds * 10) / 10} s`;
}

/** Per-element overlap measures against the resolved zones. */
export function measureElement(el, zones, input) {
    const samples = samplesOf(el, input);
    const perZone = zones.map((z) => {
        const covs = samples.map((s) => coverage(s.rect, z.rect));
        return { zone: z, max: Math.max(...covs), covs };
    });
    const scored = perZone.filter((p) => SEVERITY_FACTOR[p.zone.severity] > 0);
    const anyCov = samples.map((_, i) => scored.some((p) => p.covs[i] > 0));
    const hard = perZone.filter((p) => p.zone.severity === 'hard');
    const soft = perZone.filter((p) => p.zone.severity === 'soft');
    const worstHard = hard.reduce((a, p) => (!a || p.max > a.max ? p : a), null);
    const worstSoft = soft.reduce((a, p) => (!a || p.max > a.max ? p : a), null);
    const criticalSamples = samples.filter((_, i) => hard.some((p) => p.covs[i] >= CRITICAL_COVERAGE)).length;
    const overlappedSamples = anyCov.filter(Boolean).length;
    const timeShare = overlappedSamples / samples.length;
    const maxWeighted = Math.max(0, ...scored.map((p) => SEVERITY_FACTOR[p.zone.severity] * p.max));
    const risk = (ELEMENT_WEIGHTS[el.type] ?? ELEMENT_WEIGHTS.other) * confidenceOf(el) * maxWeighted * timeShare;
    return {
        samples, perZone, worstHard, worstSoft, timeShare, risk,
        criticalSeconds: secondsFor(criticalSamples, el, input),
        overlapSeconds: secondsFor(overlappedSamples, el, input),
        envelope: union(samples.map((s) => s.rect)),
    };
}

function elementIssue(el, name, m, zones, frame, input) {
    const hardCov = m.worstHard ? m.worstHard.max : 0;
    const softCov = m.worstSoft ? m.worstSoft.max : 0;
    const isTrusted = trusted(el);
    const important = isTrusted && IMPORTANT_TYPES.includes(el.type);
    const lowConfidence = !isTrusted && confidenceOf(el) < DETECTION_CONFIDENCE;
    const when = durationText(m.overlapSeconds, m.timeShare, input);
    const tail = when ? `, ${when}` : '';
    const hard = zones.filter((z) => z.severity === 'hard');

    let severity = null, zone = null, cov = 0, message = '';
    if (hardCov > 0) {
        zone = m.worstHard.zone;
        cov = hardCov;
        if (hardCov >= CRITICAL_COVERAGE) {
            if (important && m.criticalSeconds >= CRITICAL_DURATION_S) severity = 'critical';
            else severity = lowConfidence ? 'notice' : 'warning';
        } else if (hardCov >= WARNING_COVERAGE) {
            severity = lowConfidence ? 'notice' : 'warning';
        } else {
            severity = 'notice';
        }
        message = zone.kind === 'side-crop' || zone.kind === 'masked'
            ? `${name} is ${pct(hardCov)} inside the ${zone.label.toLowerCase()}${tail ? ` (${when})` : ''}`
            : `${name} hidden by ${zone.label.toLowerCase()} (${pct(hardCov)}${tail})`;
    } else if (softCov > 0) {
        zone = m.worstSoft.zone;
        cov = softCov;
        severity = lowConfidence ? 'notice' : 'warning';
        message = `${name} may be covered by ${zone.label.toLowerCase()} (${pct(softCov)}${tail})`;
    } else {
        const near = hard.map((z) => ({ z, d: gap(m.envelope, z.rect) })).filter((x) => x.d < PROXIMITY_PX).sort((a, b) => a.d - b.d)[0];
        if (!near) return null;
        zone = near.z;
        severity = 'notice';
        message = `${name} is within ${Math.round(near.d)} px of ${zone.kind === 'side-crop' || zone.kind === 'masked' ? 'the ' : ''}${zone.label.toLowerCase()}`;
    }

    const fix = hardCov > 0 ? suggestFix(m.envelope, hard, frame) : null;
    return {
        severity, code: hardCov > 0 ? 'covered-hard' : softCov > 0 ? 'covered-soft' : 'proximity',
        element: el.id, zone: zone.id, zoneKind: zone.kind,
        coverage: Math.round(cov * 1000) / 1000, timeShare: Math.round(m.timeShare * 1000) / 1000,
        message, fix: fix && !fix.impossible ? { dx: fix.dx, dy: fix.dy, scale: fix.scale } : fix,
        fixText: describeFix(fix),
    };
}

export function elementNames(elements) {
    const counts = {};
    const names = {};
    for (const el of elements) {
        counts[el.type] = (counts[el.type] || 0) + 1;
        names[el.id] = el.label || `${TYPE_LABEL[el.type] || 'Element'} ${counts[el.type]}`;
    }
    return names;
}

/**
 * Scores one platform.
 * @param {object} resolved  output of resolveZones()
 * @param {Array}  elements  key elements in reference-frame pixels
 * @param {object} input     { kind: 'image' | 'video', durationS, sampleIntervalS, lowResolution }
 */
export function evaluatePlatform(resolved, elements, input) {
    const { platform, mode, frame, zones } = resolved;
    const issues = resolved.notices.map((n) => ({ severity: 'notice', code: n.code, message: n.message }));
    if (input.lowResolution) issues.push({ severity: 'notice', code: 'low-resolution', message: 'Low resolution (below 720 x 1280); platforms may soften it.' });

    if (mode === 'not-a-short') {
        issues.unshift({ severity: 'critical', code: 'not-a-short', message: `A 16:9 video will not appear as a Short on ${platform.name}; it plays on the regular watch page.` });
        return { platform: platform.id, name: platform.name, status: platform.status, mode, score: null, verdict: 'fail', issues, elements: [] };
    }
    if (mode === 'preview-only') {
        issues.unshift({ severity: 'warning', code: 'not-scored', message: `${platform.name} shows this aspect ratio letterboxed or cropped; placement differs by phone, so it is not scored. Use 9:16 for a full check.` });
        return { platform: platform.id, name: platform.name, status: platform.status, mode, score: null, verdict: 'not-scored', issues, elements: [] };
    }

    const names = elementNames(elements);
    const perElement = [];
    let product = 1;
    for (const el of elements) {
        const m = measureElement(el, zones, input);
        product *= 1 - Math.min(RISK_CAP, m.risk);
        const issue = elementIssue(el, names[el.id], m, zones, frame, input);
        if (issue) issues.push(issue);
        perElement.push({ id: el.id, name: names[el.id], risk: Math.round(m.risk * 1000) / 1000 });
    }
    const score = Math.round(100 * product);
    const has = (s) => issues.some((i) => i.severity === s);
    let verdict;
    if (has('critical') || score < FAIL_SCORE) verdict = 'fail';
    else if (score < PASS_SCORE || has('warning') || platform.status !== 'measured') verdict = 'needs-review';
    else verdict = 'pass';
    issues.sort((a, b) => SEVERITY_RANK[b.severity] - SEVERITY_RANK[a.severity]);
    return { platform: platform.id, name: platform.name, status: platform.status, mode, score, verdict, issues, elements: perElement };
}

export const VERDICT_LABEL = { pass: 'Pass', 'needs-review': 'Needs review', fail: 'Fail', 'not-scored': 'Not scored' };
