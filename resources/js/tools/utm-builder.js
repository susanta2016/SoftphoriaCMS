import { copyText } from './shared/copy.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// UTM Builder — see resources/views/tools/functionalities/utm-builder.blade.php.
// Errors are shown once a field has been left (blur) or on Copy, never
// while the visitor is still typing.

const REQUIRED = ['utm_source', 'utm_medium', 'utm_campaign'];
const EMPTY_MESSAGE = 'Fill in the website URL, source, medium and campaign name.';

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-utm]');
    if (!tool) return;

    const url = tool.querySelector('[data-utm-url]');
    const fields = [...tool.querySelectorAll('[data-utm-field]')];
    const lowercase = tool.querySelector('[data-utm-lowercase]');
    const result = tool.querySelector('[data-utm-result]');
    const copy = tool.querySelector('[data-utm-copy]');
    const live = tool.querySelector('[data-utm-live]');
    const touched = new Set();

    const announce = (message) => {
        live.textContent = '';
        requestAnimationFrame(() => {
            live.textContent = message;
        });
    };

    const clean = (value) => {
        const trimmed = value.trim();
        return lowercase.checked ? trimmed.toLowerCase().replace(/\s+/g, '_') : trimmed;
    };

    const parseUrl = () => {
        const value = url.value.trim();
        if (!value) return { error: 'Enter the website URL.' };
        try {
            const parsed = new URL(/^[a-z][a-z0-9+.-]*:/i.test(value) ? value : `https://${value}`);
            if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname.includes('.')) {
                return { error: 'Enter a full web address, e.g. https://www.example.com/page.' };
            }
            return { parsed };
        } catch {
            return { error: 'Enter a full web address, e.g. https://www.example.com/page.' };
        }
    };

    const showError = (key, input, message) => {
        const error = tool.querySelector(`[data-utm-error="${key}"]`);
        const visible = Boolean(message) && touched.has(key);
        error.textContent = visible ? message : '';
        error.hidden = !visible;
        input.setAttribute('aria-invalid', visible ? 'true' : 'false');
    };

    const build = () => {
        const { parsed, error } = parseUrl();
        showError('url', url, error);

        let complete = Boolean(parsed);
        fields.forEach((input) => {
            const key = input.dataset.utmField;
            const missing = REQUIRED.includes(key) && !clean(input.value);
            showError(key, input, missing ? 'This field is required.' : null);
            if (missing) complete = false;
        });

        if (!complete) {
            result.textContent = EMPTY_MESSAGE;
            copy.disabled = true;
            return null;
        }

        fields.forEach((input) => {
            const value = clean(input.value);
            if (value) parsed.searchParams.set(input.dataset.utmField, value);
            else parsed.searchParams.delete(input.dataset.utmField);
        });

        const built = parsed.toString();
        result.textContent = built;
        copy.disabled = false;
        trackToolEventOnce('tool_completed');

        return built;
    };

    const blurred = (key) => () => {
        touched.add(key);
        const before = tool.querySelectorAll('[aria-invalid="true"]').length;
        build();
        if (tool.querySelectorAll('[aria-invalid="true"]').length > before) {
            trackToolEvent('tool_error', { field: key });
        }
    };

    url.addEventListener('input', build);
    url.addEventListener('blur', blurred('url'));
    fields.forEach((input) => {
        input.addEventListener('input', build);
        input.addEventListener('blur', blurred(input.dataset.utmField));
    });
    lowercase.addEventListener('change', build);

    copy.addEventListener('click', (event) => {
        const built = build();
        if (built) copyText(built, event.currentTarget, 'url', announce);
    });

    tool.querySelector('[data-utm-reset]').addEventListener('click', () => {
        touched.clear();
        url.value = '';
        fields.forEach((input) => {
            input.value = '';
        });
        build();
        url.focus();
        announce('Cleared.');
    });

    tool.querySelector('[data-utm-form]').addEventListener('submit', (event) => {
        event.preventDefault();
        ['url', ...REQUIRED].forEach((key) => touched.add(key));
        build();
    });
});
