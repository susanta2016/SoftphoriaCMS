import './social-video-safe-zone/src/styles.css';
import { mount } from './social-video-safe-zone/src/app.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// Social Video Safe Zone Checker — see resources/views/tools/functionalities/social-video-safe-zone.blade.php.
// The checker is the accepted P1a implementation in ./social-video-safe-zone/; this file only mounts it
// and passes its analytics events to the shared tool tracking (consent-gated, never in a preview).
// Events carry no file names, frames, boxes or media — only kinds, buckets, verdicts and error codes.

function report(name, params) {
    switch (name) {
        case 'tool_started':
        case 'tool_completed':
            trackToolEventOnce(name, params);
            break;
        case 'safezone_export':
            trackToolEvent(params.export_type === 'copy' ? 'tool_copy' : 'tool_download', params);
            break;
        case 'safezone_error':
            trackToolEvent('tool_error', params);
            break;
        default:
            // safezone_file_loaded has no site-wide equivalent and is not sent.
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-svsz-root]');
    if (!root) return;

    mount(root, { onEvent: report });
});
