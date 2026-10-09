import { copyText } from './shared/copy.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';
import { categoryLabel, countLabel, ctaFor, GROUP_TITLES, reportFileName, reportText, scoreBand, SEVERITY_LABELS } from './website-seo-checker/src/report.js';

// Website SEO/Metadata Pre-launch Checker — see
// resources/views/tools/functionalities/website-seo-checker.blade.php.
//
// Sends the address to the audit endpoint and renders the JSON report.
// Everything that came from the audited website (titles, URLs, evidence) is
// inserted with textContent only — never as HTML. Analytics events
// (consent-gated, shared/track.js) carry only status, score band and error
// codes, never the address or the findings.

const CLIENT_TIMEOUT_MS = 75_000;

const PROGRESS_STEPS = [
    'Fetching the page…',
    'Reading the title, description and canonical…',
    'Checking robots directives and robots.txt…',
    'Looking for the XML sitemap…',
    'Checking the social sharing tags and image…',
    'Testing a sample of links…',
    'Putting the report together…',
];

const SEVERITY_STYLES = {
    critical: { badge: 'bg-red-100 text-red-800', card: 'border-red-200', dot: 'bg-red-500' },
    warning: { badge: 'bg-amber-100 text-amber-900', card: 'border-amber-200', dot: 'bg-amber-500' },
    passed: { badge: 'bg-emerald-100 text-emerald-800', card: 'border-brand-navy/10', dot: 'bg-emerald-500' },
    not_checked: { badge: 'bg-slate-100 text-slate-700', card: 'border-brand-navy/10', dot: 'bg-slate-400' },
};

const READINESS_STYLES = {
    blocked: 'border border-red-200 bg-red-50 text-red-950',
    review: 'border border-amber-200 bg-amber-50 text-amber-950',
    ready: 'border border-emerald-200 bg-emerald-50 text-emerald-950',
    incomplete: 'border border-slate-200 bg-slate-50 text-slate-900',
};

const ERROR_TITLES = {
    rate_limited: 'Please wait a moment',
    server_error: 'Something went wrong on our side',
    expired: 'This page has expired',
};

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-seo]');
    if (!tool) return;

    const q = (selector) => tool.querySelector(selector);
    const el = {
        form: q('[data-seo-form]'), url: q('[data-seo-url]'), submit: q('[data-seo-submit]'), fieldError: q('[data-seo-field-error]'),
        error: q('[data-seo-error]'), errorTitle: q('[data-seo-error-title]'), errorText: q('[data-seo-error-text]'),
        progress: q('[data-seo-progress]'), progressText: q('[data-seo-progress-text]'), elapsed: q('[data-seo-elapsed]'),
        live: q('[data-seo-live]'), report: q('[data-seo-report]'), meta: q('[data-seo-meta]'),
        score: q('[data-seo-score]'), scoreSuffix: q('[data-seo-score-suffix]'),
        readiness: q('[data-seo-readiness]'), readinessLabel: q('[data-seo-readiness-label]'), readinessSummary: q('[data-seo-readiness-summary]'),
        counts: q('[data-seo-counts]'), categories: q('[data-seo-categories]'), groups: q('[data-seo-groups]'),
        previewWrap: q('[data-seo-preview-wrap]'), preview: q('[data-seo-preview]'),
        cta: q('[data-seo-cta]'), ctaHeading: q('[data-seo-cta-heading]'), ctaText: q('[data-seo-cta-text]'), ctaLink: q('[data-seo-cta-link]'),
        copy: q('[data-seo-copy]'), download: q('[data-seo-download]'), again: q('[data-seo-again]'),
    };

    const state = { report: null, category: null, busy: false };

    const say = (message) => {
        el.live.textContent = '';
        requestAnimationFrame(() => { el.live.textContent = message; });
    };
    const h = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    };
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const dateText = (iso) => {
        const date = new Date(iso);
        return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    };

    // ------------------------------------------------------------ requests

    const showError = (title, message) => {
        el.errorTitle.textContent = title;
        el.errorText.textContent = message;
        el.error.hidden = false;
        say(`${title}. ${message}`);
    };

    const fieldError = (message) => {
        el.fieldError.textContent = message ?? '';
        el.fieldError.hidden = !message;
        el.url.setAttribute('aria-invalid', message ? 'true' : 'false');
    };

    let timer = null;
    const setBusy = (busy) => {
        state.busy = busy;
        el.submit.disabled = busy;
        el.submit.textContent = busy ? 'Checking…' : 'Check Website';
        el.progress.hidden = !busy;
        clearInterval(timer);
        if (!busy) return;

        const started = Date.now();
        let step = 0;
        el.progressText.textContent = PROGRESS_STEPS[0];
        el.elapsed.textContent = '0 s';
        say('Checking the website. This can take up to 40 seconds.');
        timer = setInterval(() => {
            const seconds = Math.round((Date.now() - started) / 1000);
            el.elapsed.textContent = `${seconds} s`;
            const next = Math.min(PROGRESS_STEPS.length - 1, Math.floor(seconds / 3));
            if (next !== step) {
                step = next;
                el.progressText.textContent = PROGRESS_STEPS[step];
            }
        }, 500);
    };

    const audit = async () => {
        if (state.busy) return;

        el.error.hidden = true;
        fieldError('');
        const value = el.url.value.trim();
        if (!value) {
            fieldError('Enter the address of the page to check.');
            el.url.focus();
            return;
        }

        const data = new FormData(el.form);
        data.set('url', value);
        trackToolEventOnce('tool_started', { intent: data.get('intent') });
        trackToolEvent('tool_audit_started', { intent: data.get('intent') });
        setBusy(true);
        el.report.hidden = true;

        const controller = new AbortController();
        const abort = setTimeout(() => controller.abort(), CLIENT_TIMEOUT_MS);

        let response;
        let payload = null;
        try {
            response = await fetch(el.form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
                credentials: 'same-origin',
                signal: controller.signal,
            });
            payload = await response.json().catch(() => null);
        } catch (error) {
            setBusy(false);
            clearTimeout(abort);
            const timedOut = error?.name === 'AbortError';
            showError('The check couldn\'t be completed', timedOut
                ? 'It took too long to get a result. Please try again.'
                : 'We couldn\'t reach our server. Check your connection and try again.');
            trackToolEvent('tool_error', { error_code: timedOut ? 'client_timeout' : 'network' });
            return;
        }
        clearTimeout(abort);
        setBusy(false);

        if (response.status === 419) {
            showError(ERROR_TITLES.expired, 'Reload the page and run the check again.');
            trackToolEvent('tool_error', { error_code: 'expired' });
            return;
        }

        if (!response.ok || !payload || payload.status === 'error') {
            const code = payload?.error?.code ?? `http_${response.status}`;
            const message = payload?.error?.message ?? payload?.message ?? 'Please try again in a moment.';
            if (response.status === 422) {
                fieldError(message);
                el.url.focus();
                say(message);
            } else {
                showError(ERROR_TITLES[code] ?? 'The check couldn\'t be completed', message);
            }
            trackToolEvent('tool_error', { error_code: code });
            return;
        }

        if (payload.status === 'unavailable') {
            showError('This address couldn\'t be checked', payload.error?.message ?? 'Please check the address and try again.');
            trackToolEvent('tool_error', { error_code: payload.error?.code ?? 'unavailable' });
            return;
        }

        state.report = payload;
        state.category = null;
        render();
        trackToolEvent('tool_completed', {
            status: payload.readiness?.level,
            score_band: scoreBand(payload.score),
            blockers: payload.counts?.critical ?? 0,
            intent: payload.intent,
        });
    };

    el.form.addEventListener('submit', (event) => {
        event.preventDefault();
        audit();
    });
    el.url.addEventListener('input', () => fieldError(''));

    // ------------------------------------------------------------ rendering

    const render = () => {
        const report = state.report;

        // Meta line: what was checked, when, and how it answered.
        el.meta.replaceChildren();
        const checked = h('p', 'break-all');
        checked.append(h('span', 'font-semibold text-brand-navy', 'Checked: '), document.createTextNode(report.final_url));
        el.meta.append(checked);
        if (report.final_url !== report.submitted_url) {
            el.meta.append(h('p', 'break-all', `Entered: ${report.submitted_url} (${plural(report.http.redirects.length, 'redirect')})`));
        }
        el.meta.append(h('p', null, [
            `HTTP ${report.http.status}`,
            `server response ${report.http.response_ms} ms`,
            report.intent === 'staging' ? 'checked as a staging copy' : 'checked as the live site',
            dateText(report.audited_at),
        ].join(' · ')));

        // Score + readiness.
        el.score.textContent = report.score === null ? '—' : String(report.score);
        el.scoreSuffix.textContent = report.score === null ? 'Not scored — the page couldn\'t be read' : 'out of 100 · not a Google score';
        el.readiness.className = `rounded-2xl p-6 ${READINESS_STYLES[report.readiness.level] ?? READINESS_STYLES.incomplete}`;
        el.readinessLabel.textContent = report.readiness.label;
        el.readinessSummary.textContent = report.readiness.summary;
        el.counts.replaceChildren(...['critical', 'warning', 'passed', 'not_checked'].map((severity) => {
            const chip = h('span', `inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${SEVERITY_STYLES[severity].badge}`);
            chip.append(h('span', `h-2 w-2 rounded-full ${SEVERITY_STYLES[severity].dot}`), document.createTextNode(countLabel(report.counts[severity], severity)));
            return chip;
        }));

        renderCategories();
        renderGroups();
        renderPreview();

        const cta = ctaFor(report);
        el.ctaHeading.textContent = cta.heading;
        el.ctaText.textContent = cta.text;
        el.ctaLink.textContent = cta.label;
        el.ctaLink.dataset.toolCta = `report-${cta.key}`;

        el.report.hidden = false;
        el.report.focus({ preventScroll: true });
        el.report.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        say(`Check complete. ${report.readiness.label}. ${report.readiness.summary}`);
    };

    const renderCategories = () => {
        const report = state.report;
        el.categories.replaceChildren(...report.categories.filter((c) => c.checked).map((category) => {
            const pressed = state.category === category.key;
            const button = h('button', `flex flex-col items-start gap-2 rounded-2xl border bg-white p-4 text-left transition hover:border-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none ${pressed ? 'border-brand-accent ring-2 ring-brand-accent/30' : 'border-brand-navy/10'}`);
            button.type = 'button';
            button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            button.append(h('span', 'text-sm font-semibold text-brand-navy', category.label));
            const chips = h('span', 'flex flex-wrap gap-1.5');
            for (const severity of ['critical', 'warning', 'passed']) {
                if (category.counts[severity] > 0) {
                    chips.append(h('span', `rounded-full px-2 py-0.5 text-xs font-semibold ${SEVERITY_STYLES[severity].badge}`, `${category.counts[severity]} ${SEVERITY_LABELS[severity].toLowerCase()}${severity === 'passed' ? '' : category.counts[severity] === 1 ? '' : 's'}`));
                }
            }
            button.append(chips);
            button.addEventListener('click', () => {
                state.category = pressed ? null : category.key;
                renderCategories();
                renderGroups();
                say(state.category ? `Showing ${category.label} only.` : 'Showing all categories.');
            });
            return button;
        }));
    };

    const findingItem = (finding, open) => {
        const report = state.report;
        const style = SEVERITY_STYLES[finding.severity];
        const item = h('li');
        const details = h('details', `group rounded-2xl border bg-white shadow-sm ${style.card}`);
        details.open = open;

        const summary = h('summary', 'flex cursor-pointer list-none items-start gap-3 rounded-2xl px-5 py-4 focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none [&::-webkit-details-marker]:hidden');
        summary.append(h('span', `mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${style.dot}`));
        const head = h('span', 'min-w-0 flex-1');
        head.append(h('span', 'block font-semibold text-brand-navy', finding.title));
        const tags = h('span', 'mt-1 flex flex-wrap gap-1.5 text-xs');
        tags.append(h('span', `rounded-full px-2 py-0.5 font-semibold ${style.badge}`, SEVERITY_LABELS[finding.severity]));
        tags.append(h('span', 'rounded-full bg-brand-mist px-2 py-0.5 font-medium text-brand-navy/70', categoryLabel(report, finding.category)));
        head.append(tags);
        summary.append(head, h('span', 'mt-1 shrink-0 text-sm font-semibold text-brand-accent group-open:hidden', 'Details'));
        details.append(summary);

        const body = h('div', 'space-y-3 border-t border-brand-navy/10 px-5 py-4 text-sm leading-relaxed text-brand-navy/80');
        if (finding.evidence.length) {
            body.append(h('p', 'font-semibold text-brand-navy', 'What we found'));
            const list = h('ul', 'space-y-1');
            finding.evidence.forEach((line) => list.append(h('li', 'rounded-lg bg-brand-mist/70 px-3 py-1.5 font-mono text-xs break-all text-brand-navy', line)));
            body.append(list);
        }
        const section = (label, text) => {
            const p = h('p');
            p.append(h('span', 'font-semibold text-brand-navy', `${label}: `), document.createTextNode(text));
            return p;
        };
        body.append(section('Why it matters', finding.why));
        if (finding.severity !== 'passed' || finding.fix !== 'Nothing to do.') body.append(section(finding.severity === 'passed' ? 'Tip' : 'How to fix', finding.fix));
        if (finding.limitation) body.append(section('Note', finding.limitation));
        details.append(body);
        item.append(details);
        return item;
    };

    const renderGroups = () => {
        const report = state.report;
        const visible = report.findings.filter((f) => !state.category || f.category === state.category);
        el.groups.replaceChildren();

        for (const severity of ['critical', 'warning', 'passed', 'not_checked']) {
            const group = visible.filter((f) => f.severity === severity);
            if (!group.length) continue;

            const heading = `${GROUP_TITLES[severity]} (${group.length})`;
            const list = h('ol', 'space-y-3');
            group.forEach((finding) => list.append(findingItem(finding, severity === 'critical')));

            if (severity === 'critical' || severity === 'warning') {
                const section = h('section', 'space-y-3');
                section.setAttribute('aria-label', heading);
                section.append(h('p', `text-lg font-bold ${severity === 'critical' ? 'text-red-800' : 'text-brand-navy'}`, heading), list);
                el.groups.append(section);
            } else {
                const wrap = h('details', 'group/section rounded-2xl border border-brand-navy/10 bg-brand-mist/40');
                const summary = h('summary', 'cursor-pointer list-none rounded-2xl px-5 py-4 text-lg font-bold text-brand-navy focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none [&::-webkit-details-marker]:hidden', heading);
                const inner = h('div', 'px-3 pb-3 sm:px-4 sm:pb-4');
                inner.append(list);
                wrap.append(summary, inner);
                el.groups.append(wrap);
            }
        }

        if (!visible.some((f) => f.severity === 'critical' || f.severity === 'warning')) {
            el.groups.prepend(h('p', 'rounded-2xl bg-emerald-50 p-5 text-sm font-medium text-emerald-900', state.category ? 'No blockers or warnings in this category.' : 'No blockers or warnings in the checks we ran.'));
        }
    };

    const renderPreview = () => {
        const preview = state.report.preview;
        el.previewWrap.hidden = !preview;
        if (!preview) return;

        const placeholder = (text) => h('span', 'italic text-brand-navy/40', text);
        const image = h('div', 'flex aspect-[1.91/1] items-center justify-center bg-brand-mist text-sm text-brand-navy/50');
        if (preview.image) {
            const img = h('img', 'h-full w-full object-cover');
            img.alt = '';
            img.loading = 'lazy';
            img.referrerPolicy = 'no-referrer';
            img.src = preview.image;
            image.append(img);
        } else {
            image.append(placeholder('No share image (og:image) found'));
        }

        const text = h('div', 'space-y-1 border-t border-brand-navy/10 p-4');
        text.append(h('p', 'text-xs tracking-wide text-brand-navy/50 uppercase', preview.site_name ? `${preview.domain} · ${preview.site_name}` : preview.domain));
        const title = h('p', 'font-semibold text-brand-navy');
        title.append(preview.title ? document.createTextNode(preview.title) : placeholder('No title found'));
        const description = h('p', 'line-clamp-2 text-sm text-brand-navy/70');
        description.append(preview.description ? document.createTextNode(preview.description) : placeholder('No description found'));
        text.append(title, description);

        el.preview.replaceChildren(image, text);
    };

    // ------------------------------------------------------------ export

    el.copy.addEventListener('click', () => {
        if (!state.report) return;
        copyText(reportText(state.report, { dateText: dateText(state.report.audited_at) }), el.copy, 'report', say);
    });

    el.download.addEventListener('click', () => {
        if (!state.report) return;
        const blob = new Blob([reportText(state.report, { dateText: dateText(state.report.audited_at) })], { type: 'text/plain;charset=utf-8' });
        const link = h('a');
        link.href = URL.createObjectURL(blob);
        link.download = reportFileName(state.report);
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        say('Report downloaded.');
        trackToolEvent('tool_download', { download_type: 'report_txt' });
    });

    el.again.addEventListener('click', () => {
        el.url.focus();
        el.url.select();
        el.url.scrollIntoView({ behavior: 'auto', block: 'center' });
    });
});
