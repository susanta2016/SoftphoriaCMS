import './image-kb-optimizer/src/styles.css';
import { mount } from './image-kb-optimizer/src/app.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// Exact Image KB Optimizer — see resources/views/tools/functionalities/image-kb-optimizer.blade.php.
// Mounts the tool and passes its events to the shared tool tracking (consent-gated, never in a
// preview). Events carry only counts, the target size, formats and error codes — never file names,
// file sizes of specific images or image data.

function report(name, params) {
    if (name === 'tool_started') trackToolEventOnce(name, params);
    else trackToolEvent(name, params);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-iko-root]');
    if (!root) return;

    mount(root, { onEvent: report });
});
