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
    const header = document.querySelector('[data-transparent-header]');

    if (!header) return;

    const SOLID_THRESHOLD = 24;

    const applyScrollState = () => {
        const solid = window.scrollY > SOLID_THRESHOLD;
        header.classList.toggle('bg-white', solid);
        header.classList.toggle('shadow-sm', solid);
        header.classList.toggle('bg-transparent', !solid);
    };

    applyScrollState();
    window.addEventListener('scroll', applyScrollState, { passive: true });
});

document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-scroll-to-top]');

    if (!button) return;

    const VISIBLE_THRESHOLD = 400;

    const applyScrollState = () => {
        button.classList.toggle('hidden', window.scrollY <= VISIBLE_THRESHOLD);
        button.classList.toggle('flex', window.scrollY > VISIBLE_THRESHOLD);
    };

    applyScrollState();
    window.addEventListener('scroll', applyScrollState, { passive: true });

    button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
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

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-pro-tooltip-toggle]');
    const tooltip = document.querySelector('[data-pro-tooltip]');

    if (!toggle || !tooltip) return;

    const setOpen = (open) => {
        tooltip.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(tooltip.classList.contains('hidden'));
    });

    document.addEventListener('click', (event) => {
        if (!tooltip.classList.contains('hidden') && !tooltip.contains(event.target) && event.target !== toggle) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const target = document.querySelector(toggle.dataset.passwordTarget);
        if (!target) return;

        toggle.addEventListener('click', () => {
            const showing = target.type === 'text';
            target.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', String(!showing));
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-account-sidebar-toggle]');
    const sidebar = document.querySelector('[data-account-sidebar]');

    if (!toggle || !sidebar) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => setOpen(sidebar.classList.contains('hidden')));
});

document.addEventListener('DOMContentLoaded', () => {
    const banner = document.querySelector('[data-cookie-banner]');
    const preferences = document.querySelector('[data-cookie-preferences]');

    if (!banner || !preferences) return;

    const COOKIE_NAME = 'cookie_consent';
    const COOKIE_DAYS = 180;
    const OPTIONAL_CATEGORIES = ['functionality', 'tracking', 'targeting'];

    const readConsent = () => {
        const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
        if (!match) return null;

        try {
            return JSON.parse(decodeURIComponent(match[1]));
        } catch {
            return null;
        }
    };

    const writeConsent = (consent) => {
        const maxAge = COOKIE_DAYS * 24 * 60 * 60;
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE_NAME}=${encodeURIComponent(JSON.stringify(consent))}; path=/; max-age=${maxAge}; SameSite=Lax${secure}`;
    };

    const setBannerOpen = (open) => banner.classList.toggle('hidden', !open);

    const setPreferencesOpen = (open) => {
        preferences.classList.toggle('hidden', !open);
        preferences.classList.toggle('flex', open);
        if (open) setBannerOpen(false);
    };

    const applyConsentToToggles = (consent) => {
        OPTIONAL_CATEGORIES.forEach((category) => {
            const toggle = preferences.querySelector(`[data-cookie-toggle="${category}"]`);
            const label = preferences.querySelector(`[data-cookie-toggle-label="${category}"]`);
            const active = Boolean(consent?.[category]);

            if (toggle) toggle.checked = active;
            if (label) label.textContent = active ? 'Active' : 'Inactive';
        });
    };

    const readTogglesAsConsent = () => {
        const consent = { necessary: true };
        OPTIONAL_CATEGORIES.forEach((category) => {
            consent[category] = preferences.querySelector(`[data-cookie-toggle="${category}"]`)?.checked ?? false;
        });

        return consent;
    };

    preferences.querySelectorAll('[data-cookie-toggle]').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const label = preferences.querySelector(`[data-cookie-toggle-label="${toggle.dataset.cookieToggle}"]`);
            if (label) label.textContent = toggle.checked ? 'Active' : 'Inactive';
        });
    });

    preferences.querySelectorAll('[data-cookie-tab-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const key = trigger.dataset.cookieTabTrigger;

            preferences.querySelectorAll('[data-cookie-tab-trigger]').forEach((other) => {
                const isActive = other === trigger;
                other.classList.toggle('is-active', isActive);
                other.classList.toggle('bg-white', isActive);
                other.classList.toggle('font-semibold', isActive);
                other.classList.toggle('text-brand-navy', isActive);
                other.classList.toggle('text-brand-navy/70', !isActive);
                other.classList.toggle('sm:border-l-brand-gold', isActive);
                other.setAttribute('aria-selected', String(isActive));
            });

            preferences.querySelectorAll('[data-cookie-tab-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.cookieTabPanel !== key);
            });
        });
    });

    document.querySelectorAll('[data-cookie-preferences-open]').forEach((button) => {
        button.addEventListener('click', () => {
            applyConsentToToggles(readConsent() ?? { necessary: true });
            setPreferencesOpen(true);
        });
    });

    document.querySelector('[data-cookie-preferences-close]')?.addEventListener('click', () => {
        setPreferencesOpen(false);
        if (!readConsent()) setBannerOpen(true);
    });

    preferences.addEventListener('click', (event) => {
        if (event.target === preferences) {
            setPreferencesOpen(false);
            if (!readConsent()) setBannerOpen(true);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !preferences.classList.contains('hidden')) {
            setPreferencesOpen(false);
            if (!readConsent()) setBannerOpen(true);
        }
    });

    document.querySelector('[data-cookie-agree]')?.addEventListener('click', () => {
        writeConsent({ necessary: true, functionality: true, tracking: true, targeting: true });
        setBannerOpen(false);
        setPreferencesOpen(false);
    });

    document.querySelector('[data-cookie-decline]')?.addEventListener('click', () => {
        writeConsent({ necessary: true, functionality: false, tracking: false, targeting: false });
        setBannerOpen(false);
        setPreferencesOpen(false);
    });

    document.querySelector('[data-cookie-save]')?.addEventListener('click', () => {
        writeConsent(readTogglesAsConsent());
        setBannerOpen(false);
        setPreferencesOpen(false);
    });

    if (!readConsent()) setBannerOpen(true);
});
