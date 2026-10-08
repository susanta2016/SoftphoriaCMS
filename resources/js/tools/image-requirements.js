import './image-requirements/src/styles.css';
import { mount } from './image-requirements/src/app.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// Image Requirements Checker — see resources/views/tools/functionalities/image-requirements.blade.php.
// Mounts the checker and passes its events to the shared tool tracking (consent-gated, never in a
// preview). Events carry no file names or image data — only counts, the format and error codes.

function report(name, params) {
    if (name === 'tool_error') trackToolEvent('tool_error', params);
    else trackToolEventOnce(name, params);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-irc-root]');
    if (!root) return;

    mount(root, { onEvent: report });
});
