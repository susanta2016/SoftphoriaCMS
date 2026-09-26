import { copyText } from './shared/copy.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';

// PX to REM Converter — see resources/views/tools/functionalities/px-to-rem.blade.php.

const format = (value) => String(Number(value.toFixed(4)));

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-px-rem]');
    if (!tool) return;

    const base = tool.querySelector('[data-px-rem-base]');
    const px = tool.querySelector('[data-px-rem-px]');
    const rem = tool.querySelector('[data-px-rem-rem]');
    const result = tool.querySelector('[data-px-rem-result]');
    const live = tool.querySelector('[data-px-rem-live]');
    const baseLabel = tool.querySelector('[data-px-rem-base-label]');
    const cells = [...tool.querySelectorAll('[data-px]')];
    let interacted = false;

    const announce = (message) => {
        live.textContent = '';
        requestAnimationFrame(() => {
            live.textContent = message;
        });
    };

    const setError = (input, key, message) => {
        const error = tool.querySelector(`[data-px-rem-error="${key}"]`);
        error.textContent = message ?? '';
        error.hidden = !message;
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (message && interacted) trackToolEvent('tool_error', { field: key });
    };

    const number = (input) => (input.value.trim() === '' ? NaN : Number(input.value));

    const baseSize = () => {
        const value = number(base);
        if (!Number.isFinite(value) || value <= 0 || value > 200) {
            setError(base, 'base', 'Enter a root font size between 1 and 200.');
            return null;
        }
        setError(base, 'base', null);
        return value;
    };

    const show = (pixels, rems) => {
        result.textContent = `${format(pixels)}px = ${format(rems)}rem`;
        if (interacted) {
            announce(result.textContent);
            trackToolEventOnce('tool_completed');
        }
    };

    const fromPx = () => {
        const size = baseSize();
        const value = number(px);
        if (size === null) return;
        if (!Number.isFinite(value)) {
            setError(px, 'px', 'Enter a number of pixels.');
            return;
        }
        setError(px, 'px', null);
        setError(rem, 'rem', null);
        rem.value = format(value / size);
        show(value, value / size);
    };

    const fromRem = () => {
        const size = baseSize();
        const value = number(rem);
        if (size === null) return;
        if (!Number.isFinite(value)) {
            setError(rem, 'rem', 'Enter a rem value.');
            return;
        }
        setError(rem, 'rem', null);
        setError(px, 'px', null);
        px.value = format(value * size);
        show(value * size, value);
    };

    const updateTable = () => {
        const size = baseSize();
        if (size === null) return;
        baseLabel.textContent = format(size);
        cells.forEach((cell) => {
            cell.textContent = `${format(Number(cell.dataset.px) / size)}rem`;
        });
    };

    px.addEventListener('input', () => {
        interacted = true;
        fromPx();
    });
    rem.addEventListener('input', () => {
        interacted = true;
        fromRem();
    });
    base.addEventListener('input', () => {
        interacted = true;
        updateTable();
        fromPx();
    });

    tool.querySelector('[data-px-rem-copy]').addEventListener('click', (event) => {
        if (rem.value.trim() === '') return;
        copyText(`${rem.value}rem`, event.currentTarget, 'rem', announce);
    });

    tool.querySelector('[data-px-rem-form]').addEventListener('submit', (event) => event.preventDefault());

    updateTable();
    fromPx();
});
