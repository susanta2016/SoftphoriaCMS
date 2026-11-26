import { copyText } from './shared/copy.js';
import { trackToolEventOnce } from './shared/track.js';
import { countGraphemes } from './fancy-text-generator/src/graphemes.js';
import {
    CATEGORIES, convertSegmented, DEFAULT_ZALGO_LEVEL, matchesSearch, PRESETS, segment, STYLES, ZALGO_LEVELS,
} from './fancy-text-generator/src/styles.js';

// Fancy Text Generator — see resources/views/tools/functionalities/fancy-text-generator.blade.php.
//
// Privacy: the text is converted in this tab only. It is never sent to the
// server, put in browser storage or included in analytics events (which
// carry only what was copied: "style", "all" or "symbol"). Favourites are
// stored as style ids in localStorage, when the browser allows it.
// Output is only ever inserted with textContent, never as HTML.

const SAMPLE = 'Fancy Text 123';
const FAVOURITES_KEY = 'softphoria.fancy-text.favourites';
const ZALGO_STYLES = new Set(['zalgo', 'zalgo-up', 'zalgo-down']);
// Cards show at most four lines, so previews convert only the start of a
// long text; Copy always converts all of it.
const PREVIEW_GRAPHEMES = 240;

// localStorage can be missing or throw (privacy modes, blocked storage):
// favourites then last for this visit only.
const storage = (() => {
    try {
        const probe = '__softphoria_probe__';
        window.localStorage.setItem(probe, probe);
        window.localStorage.removeItem(probe);
        return window.localStorage;
    } catch {
        return null;
    }
})();

function loadFavourites() {
    try {
        const ids = JSON.parse(storage?.getItem(FAVOURITES_KEY) ?? '[]');
        return new Set(Array.isArray(ids) ? ids.filter((id) => STYLES.some((s) => s.id === id)) : []);
    } catch {
        return new Set();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const tool = document.querySelector('[data-ft]');
    if (!tool) return;

    const q = (selector) => tool.querySelector(selector);
    const el = {
        input: q('[data-ft-input]'), count: q('[data-ft-count]'), copyAll: q('[data-ft-copy-all]'),
        search: q('[data-ft-search]'), preset: q('[data-ft-preset]'), presetNote: q('[data-ft-preset-note]'),
        categories: q('[data-ft-categories]'), status: q('[data-ft-status]'), grid: q('[data-ft-grid]'), empty: q('[data-ft-empty]'),
        spotlight: q('[data-ft-spotlight]'), spotlightName: q('[data-ft-spotlight-name]'), spotlightOutput: q('[data-ft-spotlight-output]'),
        live: q('[data-ft-live]'),
    };

    const state = {
        text: '',
        category: 'all',
        preset: '',
        query: '',
        zalgoLevel: DEFAULT_ZALGO_LEVEL,
        favourites: loadFavourites(),
        segmented: null, // { full, preview, truncated } for the current text
        outputs: new Map(), // style id → full output for the current text
        previews: new Map(), // style id → preview output
        random: null,
    };

    const say = (message) => {
        el.live.textContent = '';
        requestAnimationFrame(() => { el.live.textContent = message; });
    };
    const h = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    const segments = () => {
        if (!state.segmented) {
            const full = segment(state.text || SAMPLE);
            const truncated = full.length > PREVIEW_GRAPHEMES;
            state.segmented = { full, truncated, preview: truncated ? full.slice(0, PREVIEW_GRAPHEMES) : full };
        }
        return state.segmented;
    };
    const options = () => ({ zalgoLevel: state.zalgoLevel });
    const output = (style) => {
        if (!state.outputs.has(style.id)) state.outputs.set(style.id, convertSegmented(segments().full, style.id, options()));
        return state.outputs.get(style.id);
    };
    const previewOf = (style) => {
        const { preview, truncated } = segments();
        if (!truncated) return output(style);
        if (!state.previews.has(style.id)) state.previews.set(style.id, convertSegmented(preview, style.id, options()) + ' …');
        return state.previews.get(style.id);
    };

    // ------------------------------------------------------------- cards

    const SVG = 'http://www.w3.org/2000/svg';
    const star = () => {
        const svg = document.createElementNS(SVG, 'svg');
        const attrs = { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', class: 'h-5 w-5', 'aria-hidden': 'true' };
        for (const [key, value] of Object.entries(attrs)) svg.setAttribute(key, value);
        const path = document.createElementNS(SVG, 'path');
        path.setAttribute('d', 'm12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8L12 3Z');
        path.setAttribute('stroke-linejoin', 'round');
        svg.append(path);
        return svg;
    };
    const cards = new Map();

    const buildCard = (style) => {
        const item = h('li', 'flex flex-col gap-2 rounded-2xl border border-brand-navy/10 bg-white p-4 shadow-sm shadow-brand-navy/5 transition hover:border-brand-accent/40');
        item.dataset.style = style.id;

        const top = h('div', 'flex items-center gap-1');
        const name = h('p', 'me-auto text-sm font-semibold text-brand-navy/70', style.name);
        const favourite = h('button', 'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-brand-navy/40 transition hover:bg-brand-sky hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none aria-pressed:text-amber-500 aria-pressed:[&_svg]:fill-current');
        favourite.type = 'button';
        favourite.dataset.ftFavourite = style.id;
        favourite.setAttribute('aria-label', `Favourite: ${style.name}`);
        favourite.append(star());

        const preview = h('p', 'line-clamp-4 min-h-[2.25rem] cursor-copy text-xl leading-relaxed break-words whitespace-pre-wrap text-brand-navy');
        preview.dataset.ftOutput = '';
        // Decorative Unicode reads badly aloud; the copy button names the style instead.
        preview.setAttribute('aria-hidden', 'true');

        const bottom = h('div', 'flex flex-wrap items-center gap-2');
        if (ZALGO_STYLES.has(style.id)) {
            const label = h('label', 'flex items-center gap-2 text-sm text-brand-navy/70');
            const select = h('select', 'rounded-lg border border-brand-navy/15 bg-white py-1.5 ps-2 pe-8 text-sm text-brand-navy focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none');
            select.dataset.ftZalgo = '';
            for (const level of Object.keys(ZALGO_LEVELS)) {
                const option = h('option', '', level[0].toUpperCase() + level.slice(1));
                option.value = level;
                option.selected = level === state.zalgoLevel;
                select.append(option);
            }
            label.append('Intensity', select);
            bottom.append(label);
        }
        const copy = h('button', 'inline-flex min-h-11 min-w-20 items-center justify-center rounded-xl bg-brand-accent px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50', 'Copy');
        copy.type = 'button';
        copy.dataset.ftCopy = style.id;
        copy.setAttribute('aria-label', `Copy ${style.name}`);
        top.append(name, favourite, copy);

        item.append(top, preview);
        if (bottom.childElementCount) item.append(bottom);
        cards.set(style.id, { item, preview, copy, favourite });
        return item;
    };

    el.grid.replaceChildren(...STYLES.map(buildCard));

    // -------------------------------------------------- categories/presets

    const chipClass = 'inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border border-brand-navy/15 bg-white px-3 py-1.5 text-sm font-semibold whitespace-nowrap text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none aria-pressed:border-brand-accent aria-pressed:bg-brand-accent aria-pressed:text-white';
    const chips = new Map();
    const addChip = (id, label) => {
        const chip = h('button', chipClass, label);
        chip.type = 'button';
        chip.dataset.ftCategory = id;
        chip.setAttribute('aria-pressed', String(id === state.category));
        chips.set(id, chip);
        el.categories.append(chip);
        return chip;
    };
    for (const [id, label] of CATEGORIES) addChip(id, label);
    const favouritesChip = addChip('favourites', 'Favourites');
    const favouritesCount = h('span', 'rounded-full bg-brand-navy/10 px-1.5 text-xs tabular-nums');
    favouritesChip.append(favouritesCount);

    for (const preset of PRESETS) {
        const option = h('option', '', preset.label);
        option.value = preset.id;
        el.preset.append(option);
    }

    // ------------------------------------------------------------ rendering

    const visibleStyles = () => {
        let list;
        if (state.preset) {
            const preset = PRESETS.find((p) => p.id === state.preset);
            list = preset.styles.map((id) => STYLES.find((s) => s.id === id));
        } else if (state.category === 'favourites') {
            list = STYLES.filter((s) => state.favourites.has(s.id));
        } else {
            list = state.category === 'all' ? STYLES : STYLES.filter((s) => s.categories.includes(state.category));
        }
        return list.filter((s) => matchesSearch(s, state.query));
    };

    const render = ({ announce = false } = {}) => {
        const visible = visibleStyles();
        const shown = new Set(visible.map((s) => s.id));
        const empty = state.text === '';

        // Presets keep their own order; otherwise the engine's order.
        if (state.preset) el.grid.append(...visible.map((s) => cards.get(s.id).item));

        for (const style of STYLES) {
            const card = cards.get(style.id);
            card.item.hidden = !shown.has(style.id);
            if (card.item.hidden) continue;
            card.preview.textContent = previewOf(style);
            card.preview.classList.toggle('opacity-50', empty);
            card.copy.disabled = empty;
        }

        const total = STYLES.length;
        let status;
        if (state.preset) status = `${plural(visible.length, 'suggested style')} for ${PRESETS.find((p) => p.id === state.preset).label}.`;
        else status = visible.length === total ? `Showing all ${total} styles.` : `Showing ${visible.length} of ${total} styles.`;
        if (empty) status += ' Type above to see your own text.';
        el.status.textContent = status;

        el.empty.hidden = visible.length > 0;
        if (!visible.length) {
            el.empty.textContent = state.category === 'favourites' && !state.query && !state.preset
                ? 'No favourites yet. Select the star on any style to keep it here.'
                : 'No styles match your search. Try another word, such as bold, cursive or tiny.';
        }
        el.copyAll.disabled = empty || visible.length === 0;
        if (announce) say(visible.length ? `${plural(visible.length, 'style')} shown.` : el.empty.textContent);
    };

    const renderFavourites = () => {
        for (const [id, card] of cards) card.favourite.setAttribute('aria-pressed', String(state.favourites.has(id)));
        favouritesCount.textContent = String(state.favourites.size);
    };

    const restoreOrder = () => el.grid.append(...STYLES.map((s) => cards.get(s.id).item));

    // --------------------------------------------------------------- input

    const updateCount = () => {
        const n = countGraphemes(state.text);
        const atLimit = el.input.value.length >= Number(el.input.maxLength);
        el.count.textContent = `${n.toLocaleString()} character${n === 1 ? '' : 's'}${atLimit ? ' (limit reached)' : ''}`;
    };

    const textChanged = () => {
        state.segmented = null;
        state.outputs.clear();
        state.previews.clear();
        updateCount();
        render();
        if (state.random) showRandom(state.random, { focus: false });
    };

    let frame = 0;
    el.input.addEventListener('input', () => {
        state.text = el.input.value;
        if (state.text) trackToolEventOnce('tool_started', { input: 'type' });
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(textChanged);
    });

    q('[data-ft-clear]').addEventListener('click', () => {
        el.input.value = '';
        state.text = '';
        state.random = null;
        el.spotlight.hidden = true;
        textChanged();
        el.input.focus();
        say('Text cleared.');
    });

    q('[data-ft-form]').addEventListener('submit', (event) => event.preventDefault());

    // ------------------------------------------------------------- filters

    el.categories.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-ft-category]');
        if (!chip) return;
        state.category = chip.dataset.ftCategory;
        for (const [id, c] of chips) c.setAttribute('aria-pressed', String(id === state.category));
        if (state.preset) {
            state.preset = '';
            el.preset.value = '';
            el.presetNote.hidden = true;
            restoreOrder();
        }
        render({ announce: true });
    });

    let searching;
    el.search.addEventListener('input', () => {
        state.query = el.search.value;
        render();
        clearTimeout(searching);
        searching = setTimeout(() => render({ announce: true }), 500);
    });
    el.search.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });

    el.preset.addEventListener('change', () => {
        state.preset = el.preset.value;
        const preset = PRESETS.find((p) => p.id === state.preset);
        el.presetNote.hidden = !preset;
        el.presetNote.textContent = preset ? `${preset.label}: ${preset.note}` : '';
        if (preset) {
            state.category = 'all';
            for (const [id, c] of chips) c.setAttribute('aria-pressed', String(id === 'all'));
        } else {
            restoreOrder();
        }
        render({ announce: true });
    });

    // ------------------------------------------------------- copy / favourite

    el.grid.addEventListener('click', (event) => {
        const copy = event.target.closest('[data-ft-copy]');
        const favourite = event.target.closest('[data-ft-favourite]');
        const preview = event.target.closest('[data-ft-output]');

        if (copy || (preview && state.text)) {
            const id = copy?.dataset.ftCopy ?? preview.closest('[data-style]').dataset.style;
            const style = STYLES.find((s) => s.id === id);
            copyText(output(style), cards.get(id).copy, 'style', (message) => say(message.startsWith('Copied') ? `${style.name} copied to clipboard.` : message));
        }

        if (favourite) {
            const id = favourite.dataset.ftFavourite;
            const name = STYLES.find((s) => s.id === id).name;
            const adding = !state.favourites.has(id);
            if (adding) state.favourites.add(id); else state.favourites.delete(id);
            try {
                storage?.setItem(FAVOURITES_KEY, JSON.stringify([...state.favourites]));
            } catch {
                // Storage full or blocked: keep the favourite for this visit.
            }
            renderFavourites();
            if (state.category === 'favourites') render();
            say(adding ? `${name} added to favourites.` : `${name} removed from favourites.`);
        }
    });

    el.grid.addEventListener('change', (event) => {
        if (!event.target.matches('[data-ft-zalgo]')) return;
        state.zalgoLevel = event.target.value;
        for (const select of el.grid.querySelectorAll('[data-ft-zalgo]')) select.value = state.zalgoLevel;
        for (const id of ZALGO_STYLES) {
            state.outputs.delete(id);
            state.previews.delete(id);
        }
        render();
        say(`Zalgo intensity: ${state.zalgoLevel}.`);
    });

    el.copyAll.addEventListener('click', () => {
        const visible = visibleStyles();
        if (!state.text || !visible.length) return;
        const text = visible.map((s) => `${s.name}\n${output(s)}`).join('\n\n');
        copyText(text, el.copyAll, 'all', (message) => say(message.startsWith('Copied') ? `${plural(visible.length, 'style')} copied to clipboard.` : message));
    });

    // -------------------------------------------------------------- random

    const showRandom = (style, { focus = true } = {}) => {
        state.random = style;
        el.spotlightName.textContent = style.name;
        el.spotlightOutput.textContent = previewOf(style);
        el.spotlight.hidden = false;
        q('[data-ft-spotlight-copy]').disabled = !state.text;
        if (focus) {
            el.spotlight.focus({ preventScroll: true });
            el.spotlight.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            say(`Random style: ${style.name}.`);
        }
    };

    const pickRandom = () => {
        const pool = visibleStyles().length ? visibleStyles() : STYLES;
        const choices = pool.length > 1 ? pool.filter((s) => s !== state.random) : pool;
        showRandom(choices[Math.floor(Math.random() * choices.length)]);
    };
    q('[data-ft-random]').addEventListener('click', pickRandom);
    q('[data-ft-random-again]').addEventListener('click', pickRandom);
    q('[data-ft-spotlight-copy]').addEventListener('click', (event) => {
        if (!state.random || !state.text) return;
        const { name } = state.random;
        copyText(output(state.random), event.currentTarget, 'style', (message) => say(message.startsWith('Copied') ? `${name} copied to clipboard.` : message));
    });

    // ------------------------------------------------------------- symbols

    const symbols = q('[data-ft-symbols]');
    symbols.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-ft-symbol]');
        if (!button) return;
        const symbol = button.dataset.ftSymbol;
        const ok = await copyText(symbol, null, 'symbol', (message) => say(message.startsWith('Copied') ? `${button.title} copied: ${symbol}` : message));
        if (!ok) return;
        button.dataset.copied = 'true';
        setTimeout(() => { delete button.dataset.copied; }, 1200);
    });

    const symbolSearch = q('[data-ft-symbol-search]');
    const symbolGroups = [...symbols.querySelectorAll('[data-ft-symbol-group]')];
    symbolSearch.addEventListener('input', () => {
        const words = symbolSearch.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        let total = 0;
        for (const group of symbolGroups) {
            const groupMatches = words.every((w) => group.dataset.label.includes(w));
            let shown = 0;
            for (const item of group.querySelectorAll('[data-ft-symbol-item]')) {
                const symbol = item.querySelector('[data-ft-symbol]').dataset.ftSymbol;
                const match = groupMatches || words.every((w) => item.dataset.name.includes(w) || symbol.includes(w));
                item.hidden = !match;
                if (match) shown++;
            }
            group.hidden = shown === 0;
            total += shown;
        }
        q('[data-ft-symbol-empty]').hidden = total > 0;
    });
    symbolSearch.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });

    // --------------------------------------------------------------- start

    renderFavourites();
    if (el.input.value) state.text = el.input.value; // restored by the browser on back/forward
    updateCount();
    render();

    // Exposed for the browser test pass only; holds no user data.
    tool.dataset.ftStyles = String(STYLES.length);
    tool.dataset.ftReady = 'true';
});
