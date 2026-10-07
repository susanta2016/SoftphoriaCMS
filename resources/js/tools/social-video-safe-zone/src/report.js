// Report JSON (schema safezone-report/1) and the plain-text summary (spec 9).
// Never includes the original file name or any image data.
import { VERDICT_LABEL } from './scoring.js';

export function buildReport({ data, input, settings, elements, results }) {
    return {
        schema: 'safezone-report/1',
        datasetVersion: data.datasetVersion,
        createdAt: new Date().toISOString(),
        input: {
            kind: input.kind, width: input.width, height: input.height, aspect: input.aspect,
            ...(input.kind === 'video' ? { durationS: round(input.durationS), analysedS: round(input.analysedS) } : {}),
        },
        settings: { platforms: settings.platforms, caption: settings.caption, cc: settings.cc, soundLine: settings.soundLine, deviceView: settings.device },
        elements: elements.map((e) => ({
            id: e.id, type: e.type, source: e.source, ...(e.label ? { label: e.label } : {}),
            ...(e.source === 'auto' ? { confidence: e.confidence } : {}),
            rect: e.rect.map(Math.round),
            ...(e.fromS != null ? { fromS: round(e.fromS), toS: round(e.toS) } : {}),
        })),
        results: results.map((r) => ({
            platform: r.platform, status: r.status, mode: r.mode, score: r.score, verdict: r.verdict,
            issues: r.issues.map((i) => ({
                severity: i.severity, code: i.code, message: i.message,
                ...(i.element ? { element: i.element, zone: i.zone, coverage: i.coverage, timeShare: i.timeShare } : {}),
                ...(i.fix ? { fix: i.fix } : {}),
            })),
        })),
    };
}

const round = (v) => (v == null ? v : Math.round(v * 100) / 100);

export function summaryText(results, data) {
    const lines = ['Social Video Safe Zone Checker'];
    for (const r of results) {
        lines.push(`${r.name}: ${VERDICT_LABEL[r.verdict]}${r.score != null ? ` (score ${r.score})` : ''}${r.status !== 'measured' ? ' - provisional zones' : ''}`);
    }
    const fixes = [];
    for (const r of results) for (const i of r.issues) if (i.fixText && (i.severity === 'critical' || i.severity === 'warning')) fixes.push(`${r.name}: ${i.message}. Fix: ${i.fixText}.`);
    if (fixes.length) {
        lines.push('', 'Top fixes:');
        for (const f of fixes.slice(0, 3)) lines.push(`- ${f}`);
    }
    lines.push('', `Zones: dataset ${data.datasetVersion}, measured on real phones (TikTok provisional).`);
    return lines.join('\n');
}

export function fileName(platformId, caption, ext) {
    const d = new Date();
    const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return `safe-zone_${platformId || 'all'}_${(caption || 'c1').toLowerCase()}_${date}.${ext}`;
}
