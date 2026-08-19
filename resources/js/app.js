document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-mobile-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');

    if (!toggle || !menu) return;

    const iconOpen = toggle.querySelector('[data-mobile-menu-icon-open]');
    const iconClose = toggle.querySelector('[data-mobile-menu-icon-close]');

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
        iconOpen?.classList.toggle('hidden', open);
        iconClose?.classList.toggle('hidden', !open);
    };

    toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));

    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-video-modal]');
    const toggle = document.querySelector('[data-video-modal-toggle]');

    if (!modal || !toggle) return;

    const player = modal.querySelector('[data-video-modal-player]');
    const iframe = modal.querySelector('[data-video-modal-iframe]');
    const closeButton = modal.querySelector('[data-video-modal-close]');

    const setOpen = (open) => {
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);

        if (open) {
            player?.play();

            if (iframe) {
                const src = iframe.dataset.src;
                const separator = src.includes('?') ? '&' : '?';
                iframe.src = `${src}${separator}autoplay=1`;
            }
        } else {
            player?.pause();
            if (player) player.currentTime = 0;

            // Clearing (not just pausing) the iframe's src is what actually
            // stops YouTube/Vimeo playback — there's no cross-origin API
            // access to call pause() on an embedded player here.
            if (iframe) iframe.src = '';
        }
    };

    toggle.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => setOpen(false));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) setOpen(false);
    });
});
