import { trackToolEvent } from './track.js';

// Copies text, briefly confirms on the button and in the tool's live region,
// and records a tool_copy event. `what` names the copied item for analytics.
export async function copyText(text, button, what, announce) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Clipboard API unavailable (http, old browser): select-and-copy fallback.
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        if (!ok) {
            announce?.('Copy failed — select the text and copy it manually.');
            return false;
        }
    }

    if (button) {
        const label = button.dataset.label ?? button.textContent;
        button.dataset.label = label;
        button.textContent = 'Copied';
        setTimeout(() => {
            button.textContent = label;
        }, 1600);
    }

    announce?.('Copied to clipboard.');
    trackToolEvent('tool_copy', { copied: what });

    return true;
}
