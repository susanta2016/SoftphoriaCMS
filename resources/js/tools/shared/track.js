// Tool analytics events, sent through the site's existing Analytics &
// Tracking setup (Website Setup → Analytics & Tracking): gtag() when Google
// Analytics 4 runs, otherwise the Tag Manager dataLayer. Both only exist
// after the visitor consented to tracking cookies, so nothing is sent
// without consent — and nothing at all from an admin preview.
//
// Every event carries the stable tool identifiers:
//   tool_id            the tool's slug
//   tool_functionality the functionality key (e.g. px-to-rem)

const page = () => document.querySelector('[data-tool-page]');

const sent = new Set();

export function trackToolEvent(name, params = {}) {
    const root = page();
    if (!root || root.dataset.toolPreview === 'true') return;

    const detail = {
        tool_id: root.dataset.toolSlug,
        tool_functionality: root.dataset.toolFunctionality,
        ...params,
    };

    if (typeof window.gtag === 'function') {
        window.gtag('event', name, detail);
    } else if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({ event: name, ...detail });
    }
}

// For events that should count once per page view (tool_started,
// tool_completed).
export function trackToolEventOnce(name, params = {}) {
    if (sent.has(name)) return;
    sent.add(name);
    trackToolEvent(name, params);
}
