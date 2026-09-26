// /tools hub: instant search (name, short description, category) and
// category filter over the server-rendered list of published tools. No
// page reloads and no extra URLs — the canonical /tools stays the only
// indexable hub page.

document.addEventListener('DOMContentLoaded', () => {
    const hub = document.querySelector('[data-tools-hub]');
    if (!hub) return;

    const search = hub.querySelector('[data-tools-search]');
    const chips = [...hub.querySelectorAll('[data-tools-category]')];
    const cards = [...hub.querySelectorAll('[data-tools-list] [data-tool-card]')];
    const featured = hub.querySelector('[data-tools-featured]');
    const empty = hub.querySelector('[data-tools-empty]');
    const status = hub.querySelector('[data-tools-status]');
    let category = '';

    const apply = () => {
        const terms = (search?.value ?? '').trim().toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;

        cards.forEach((card) => {
            const haystack = card.dataset.search ?? '';
            const visible = (!category || card.dataset.category === category)
                && terms.every((term) => haystack.includes(term));
            card.hidden = !visible;
            if (visible) shown++;
        });

        // While searching or filtering, only the matching list is relevant.
        if (featured) featured.hidden = Boolean(category || terms.length);
        if (empty) empty.hidden = shown !== 0;
        if (status) status.textContent = `${shown} ${shown === 1 ? 'tool' : 'tools'} shown`;
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            category = chip.dataset.toolsCategory;
            chips.forEach((other) => other.setAttribute('aria-pressed', String(other === chip)));
            apply();
        });
    });

    search?.addEventListener('input', apply);
    hub.querySelector('[data-tools-search-form]')?.addEventListener('submit', (event) => event.preventDefault());
});
