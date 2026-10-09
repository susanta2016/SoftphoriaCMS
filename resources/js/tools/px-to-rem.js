import { copyText } from './shared/copy.js';
import { trackToolEvent, trackToolEventOnce } from './shared/track.js';
import { DEFAULT_PRECISION, convertList, format, parseRoot, pxToRem, remToPx } from './px-to-rem/src/convert.js';

// PX to REM Converter — see resources/views/tools/functionalities/px-to-rem.blade.php.

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-px-rem]');
    if (!tool) return;

    const base = tool.querySelector('[data-px-rem-base]');
    const precision = tool.querySelector('[data-px-rem-precision]');
    const px = tool.querySelector('[data-px-rem-px]');
    const rem = tool.querySelector('[data-px-rem-rem]');
    const result = tool.querySelector('[data-px-rem-result]');
    const live = tool.querySelector('[data-px-rem-live]');
    const baseLabel = tool.querySelector('[data-px-rem-base-label]');
    const cells = [...tool.querySelectorAll('[data-px]')];
    const bulk = tool.querySelector('[data-px-rem-bulk]');
    const list = tool.querySelector('[data-px-rem-list]');
    const results = tool.querySelector('[data-px-rem-results]');
    const summary = tool.querySelector('[data-px-rem-summary]');
    const copyAll = tool.querySelector('[data-px-rem-copy-all]');
    const copyCss = tool.querySelector('[data-px-rem-copy-css]');
    let interacted = false;
    let lastDirection = 'px';
    let bulkOutputs = [];
    let announceTimer;

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

    const places = () => Number(precision.value) || DEFAULT_PRECISION;
    const number = (input) => (input.value.trim() === '' ? NaN : Number(input.value));

    const baseSize = () => {
        const value = parseRoot(base.value);
        setError(base, 'base', value === null ? 'Enter a root font size between 1 and 200.' : null);
        return value;
    };

    const show = (pixels, rems) => {
        result.textContent = `${format(pixels, places())}px = ${format(rems, places())}rem`;
        if (interacted) {
            announce(result.textContent);
            trackToolEventOnce('tool_completed');
        }
    };

    const fromPx = () => {
        lastDirection = 'px';
        const size = baseSize();
        const value = number(px);
        if (size === null) return;
        if (!Number.isFinite(value)) {
            setError(px, 'px', 'Enter a number of pixels.');
            return;
        }
        setError(px, 'px', null);
        setError(rem, 'rem', null);
        rem.value = format(pxToRem(value, size), places());
        show(value, pxToRem(value, size));
    };

    const fromRem = () => {
        lastDirection = 'rem';
        const size = baseSize();
        const value = number(rem);
        if (size === null) return;
        if (!Number.isFinite(value)) {
            setError(rem, 'rem', 'Enter a rem value.');
            return;
        }
        setError(rem, 'rem', null);
        setError(px, 'px', null);
        px.value = format(remToPx(value, size), places());
        show(remToPx(value, size), value);
    };

    const updateTable = () => {
        const size = baseSize();
        if (size === null) return;
        baseLabel.textContent = format(size, places());
        cells.forEach((cell) => {
            cell.textContent = `${format(pxToRem(Number(cell.dataset.px), size), places())}rem`;
        });
    };

    const copyButton = (text) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'shrink-0 rounded-lg border border-brand-navy/15 bg-white px-3 py-1 text-xs font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none';
        button.textContent = 'Copy';
        button.setAttribute('aria-label', `Copy ${text}`);
        button.addEventListener('click', () => copyText(text, button, 'bulk_item', announce));
        return button;
    };

    // Rows are built with textContent only: the pasted text is never parsed as HTML.
    const updateBulk = () => {
        const size = baseSize();
        const direction = tool.querySelector('[data-px-rem-direction]:checked').value;
        results.replaceChildren();
        bulkOutputs = [];

        if (list.value.trim() === '') {
            summary.textContent = 'Results appear here as you type.';
            copyAll.disabled = true;
            copyCss.disabled = true;
            return;
        }
        if (size === null) {
            summary.textContent = 'Fix the root font size to convert the list.';
            copyAll.disabled = true;
            copyCss.disabled = true;
            return;
        }

        const { items, truncated } = convertList(list.value, direction, size, places());
        items.forEach((item) => {
            const row = document.createElement('li');
            row.className = 'flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm';
            const text = document.createElement('span');
            text.className = 'min-w-0 break-all';
            const input = document.createElement('span');
            input.className = 'text-brand-navy/60';
            input.textContent = item.input;
            text.append(input, ' → ');

            if (item.ok) {
                const output = document.createElement('strong');
                output.className = 'font-semibold text-brand-navy';
                output.textContent = item.output;
                text.append(output);
                row.append(text, copyButton(item.output));
                bulkOutputs.push(item.output);
            } else {
                const error = document.createElement('span');
                error.className = 'font-medium text-red-700';
                error.textContent = item.error;
                text.append(error);
                row.append(text);
            }
            results.append(row);
        });

        const failed = items.length - bulkOutputs.length;
        summary.textContent = [
            `${bulkOutputs.length} ${bulkOutputs.length === 1 ? 'value' : 'values'} converted`,
            failed ? `${failed} could not be read` : null,
            truncated ? `only the first ${items.length} are shown` : null,
        ].filter(Boolean).join(', ') + '.';
        copyAll.disabled = bulkOutputs.length === 0;
        copyCss.disabled = copyAll.disabled;

        if (interacted && bulkOutputs.length) trackToolEventOnce('tool_completed');
        clearTimeout(announceTimer);
        announceTimer = setTimeout(() => announce(summary.textContent), 700);
    };

    const refresh = () => {
        interacted = true;
        updateTable();
        if (lastDirection === 'rem') fromRem();
        else fromPx();
        updateBulk();
    };

    px.addEventListener('input', () => {
        interacted = true;
        fromPx();
    });
    rem.addEventListener('input', () => {
        interacted = true;
        fromRem();
    });
    base.addEventListener('input', refresh);
    precision.addEventListener('change', refresh);
    list.addEventListener('input', () => {
        interacted = true;
        updateBulk();
    });
    tool.querySelectorAll('[data-px-rem-direction]').forEach((radio) => radio.addEventListener('change', () => {
        interacted = true;
        updateBulk();
    }));

    tool.querySelector('[data-px-rem-copy]').addEventListener('click', (event) => {
        if (rem.value.trim() === '') return;
        copyText(`${rem.value}rem`, event.currentTarget, 'rem', announce);
    });
    tool.querySelector('[data-px-rem-copy-px]').addEventListener('click', (event) => {
        if (px.value.trim() === '') return;
        copyText(`${px.value}px`, event.currentTarget, 'px', announce);
    });
    copyAll.addEventListener('click', (event) => {
        if (bulkOutputs.length) copyText(bulkOutputs.join('\n'), event.currentTarget, 'bulk_all', announce);
    });

    copyCss.addEventListener('click', (event) => {
        if (bulkOutputs.length) copyText(bulkOutputs.join(' '), event.currentTarget, 'bulk_css', announce);
    });

    tool.querySelector('[data-px-rem-form]').addEventListener('submit', (event) => event.preventDefault());

    bulk.hidden = false;
    updateTable();
    fromPx();
});
