// Pure helpers for the Website SEO/Metadata Pre-launch Checker report
// (no DOM), so they can be tested with `node --test`. The report object is
// the JSON from App\Tools\SeoChecker\Auditor.

export const SEVERITIES = ['critical', 'warning', 'passed', 'not_checked'];

export const SEVERITY_LABELS = {
    critical: 'Launch blocker',
    warning: 'Warning',
    passed: 'Passed',
    not_checked: 'Not checked',
};

export const GROUP_TITLES = {
    critical: 'Launch blockers',
    warning: 'Warnings',
    passed: 'Passed checks',
    not_checked: 'Not checked',
};

const COUNT_NOUNS = {
    critical: ['launch blocker', 'launch blockers'],
    warning: ['warning', 'warnings'],
    passed: ['passed check', 'passed checks'],
    not_checked: ['not checked', 'not checked'],
};

// "1 warning", "2 warnings", "0 launch blockers".
export function countLabel(count, severity) {
    const [one, many] = COUNT_NOUNS[severity];
    return `${count} ${count === 1 ? one : many}`;
}

export function categoryLabel(report, key) {
    return report.categories?.find((category) => category.key === key)?.label ?? key;
}

// Coarse band for analytics only — never the URL or the findings.
export function scoreBand(score) {
    if (score === null || score === undefined) return 'none';
    if (score >= 90) return '90-100';
    if (score >= 75) return '75-89';
    if (score >= 50) return '50-74';
    return '0-49';
}

// Which Softphoria service the report suggests, from what was found.
export function ctaFor(report) {
    const findings = report.findings ?? [];
    const has = (severity, categories) => findings.some((f) => f.severity === severity && (!categories || categories.includes(f.category)));
    const builder = report.platform === 'elementor' ? 'Elementor' : 'WordPress';

    if (has('critical')) {
        return {
            key: 'pre-launch-review',
            heading: 'Launch blockers found — want them fixed before go-live?',
            text: 'Softphoria reviews websites before launch and fixes problems like these — noindex settings, robots.txt rules, canonicals, redirects and HTTPS — so the site can be found from day one.',
            label: 'Request a pre-launch review',
        };
    }
    if (report.platform && has('warning', ['performance', 'structure', 'metadata'])) {
        return {
            key: 'wordpress-optimization',
            heading: `Running ${builder}? Let's tune it before launch.`,
            text: `We optimise ${builder} sites — theme and page-builder output, caching, images and SEO plugin settings — for speed and clean, consistent metadata.`,
            label: `Get ${builder} optimisation help`,
        };
    }
    if (has('warning', ['metadata', 'social'])) {
        return {
            key: 'seo-implementation',
            heading: 'Want the metadata done properly across the whole site?',
            text: 'We implement titles, descriptions, canonical URLs, social sharing tags and structured data on every page, and set them up so they stay correct as content changes.',
            label: 'Talk to us about SEO implementation',
        };
    }
    if (has('warning')) {
        return {
            key: 'technical-seo',
            heading: 'Need help with the remaining technical SEO issues?',
            text: 'We fix crawling, sitemap, redirect, structure and server issues like the ones in this report, and check the rest of the site for the same problems.',
            label: 'Get technical SEO help',
        };
    }
    return {
        key: 'launch-project',
        heading: 'Planning a new website or a redesign?',
        text: 'Softphoria builds fast, search-ready websites and checks details like these before every launch.',
        label: 'Discuss your project',
    };
}

const indent = (text) => `   ${text}`;

// Plain-text report for Copy and Download (no HTML, so it is safe anywhere).
export function reportText(report, { dateText } = {}) {
    const lines = [];
    const date = dateText ?? report.audited_at;

    lines.push('Website SEO/Metadata Pre-launch Checker — report');
    lines.push('');
    lines.push(`Page checked: ${report.final_url ?? report.submitted_url}`);
    if (report.final_url && report.final_url !== report.submitted_url) lines.push(`Address entered: ${report.submitted_url}`);
    lines.push(`Checked as: ${report.intent === 'staging' ? 'a staging or pre-launch copy' : 'the live site'}`);
    lines.push(`Date: ${date}`);

    if (report.status !== 'complete') {
        lines.push('');
        lines.push(`The check couldn't be completed: ${report.error?.message ?? 'unknown error'}`);
        return `${lines.join('\n')}\n`;
    }

    lines.push(`HTTP status: ${report.http.status} · Server response: ${report.http.response_ms} ms`);
    lines.push(`Checklist score: ${report.score === null ? 'not scored' : `${report.score}/100`} (not a Google score)`);
    lines.push(`Result: ${report.readiness.label} — ${report.readiness.summary}`);

    for (const severity of SEVERITIES) {
        const group = report.findings.filter((f) => f.severity === severity);
        if (!group.length) continue;

        lines.push('');
        lines.push(`${GROUP_TITLES[severity].toUpperCase()} (${group.length})`);

        group.forEach((finding, index) => {
            const prefix = `[${categoryLabel(report, finding.category)}] ${finding.title}`;
            if (severity === 'passed') {
                lines.push(`- ${prefix}${finding.evidence[0] ? ` — ${finding.evidence[0]}` : ''}`);
                return;
            }
            lines.push(`${index + 1}. ${prefix}`);
            finding.evidence.forEach((item) => lines.push(indent(`Found: ${item}`)));
            lines.push(indent(`Why it matters: ${finding.why}`));
            lines.push(indent(`How to fix: ${finding.fix}`));
            if (finding.limitation) lines.push(indent(`Note: ${finding.limitation}`));
        });
    }

    lines.push('');
    lines.push('The score weighs each check (passed = full, warning = half, blocker = none; not-checked items are left out) and is capped at 59 when there is a launch blocker. It reflects these checks only — not Google rankings or indexing. Real page speed (Core Web Vitals) was not measured.');

    return `${lines.join('\n')}\n`;
}

export function reportFileName(report, now = new Date()) {
    let host = 'website';
    try {
        host = new URL(report.final_url ?? report.submitted_url).hostname.replace(/[^a-z0-9.-]/gi, '') || host;
    } catch {
        // keep the default
    }
    const day = now.toISOString().slice(0, 10);
    return `seo-pre-launch-report-${host}-${day}.txt`;
}
