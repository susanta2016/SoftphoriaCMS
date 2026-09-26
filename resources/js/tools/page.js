import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// Loaded on every tool page (never elsewhere): the generic tool events, so
// each functionality only has to report what's specific to it
// (tool_completed, tool_copy, tool_error, tool_download).
//
//   tool_view      the page was viewed (or, if consent comes later, once it does)
//   tool_started   first interaction with the tool itself
//   tool_cta_click the call to action / related service was clicked

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-tool-page]');
    if (!root) return;

    // After every other DOMContentLoaded handler, so the consent-gated
    // analytics scripts (app.js) have been activated first.
    const sendView = () => {
        if (typeof window.gtag === 'function' || Array.isArray(window.dataLayer)) {
            trackToolEventOnce('tool_view');
        }
    };
    setTimeout(sendView, 0);
    document.addEventListener('cookie-consent:changed', () => setTimeout(sendView, 0));

    const app = root.querySelector('[data-tool-app]');
    if (app) {
        const started = () => trackToolEventOnce('tool_started');
        app.addEventListener('input', started, { once: true });
        app.addEventListener('change', started, { once: true });
        app.addEventListener('click', (event) => {
            if (event.target.closest('button')) started();
        });
    }

    root.addEventListener('click', (event) => {
        const cta = event.target.closest('[data-tool-cta]');
        if (cta) {
            trackToolEvent('tool_cta_click', { cta: cta.dataset.toolCta, link_url: cta.href });
        }
    });
});
