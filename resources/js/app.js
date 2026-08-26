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

document.addEventListener('DOMContentLoaded', () => {
    const player = document.querySelector('[data-music-player]');
    const audio = player?.querySelector('[data-music-player-audio]');
    const rows = Array.from(document.querySelectorAll('[data-music-track-row]'));
    const playButtons = Array.from(document.querySelectorAll('[data-music-player-play]'));

    if (!audio && playButtons.length === 0) return;

    const titleEl = player?.querySelector('[data-music-player-title]');
    const timeEl = player?.querySelector('[data-music-player-time]');
    const seek = player?.querySelector('[data-music-player-seek]');
    const volume = player?.querySelector('[data-music-player-volume]');
    const prevButton = player?.querySelector('[data-music-player-prev]');
    const nextButton = player?.querySelector('[data-music-player-next]');

    let currentIndex = Math.max(rows.findIndex((row) => row.dataset.musicTrackActive === '1'), 0);

    const formatTime = (seconds) => {
        if (!isFinite(seconds) || seconds < 0) return '0:00';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    const setPlayingState = (isPlaying) => {
        playButtons.forEach((button) => {
            button.querySelector('[data-music-player-play-icon]')?.classList.toggle('hidden', isPlaying);
            button.querySelector('[data-music-player-pause-icon]')?.classList.toggle('hidden', !isPlaying);
        });
    };

    const loadTrack = (index, autoplay) => {
        const row = rows[index];
        if (!audio || !row) return;

        currentIndex = index;
        rows.forEach((r) => r.classList.toggle('bg-brand-gold/10', r === row));
        if (titleEl) titleEl.textContent = row.dataset.musicTrackTitle || '';

        const streamUrl = row.dataset.musicTrackStream;
        if (streamUrl) {
            audio.src = streamUrl;
            if (autoplay) audio.play().catch(() => {});
        } else {
            audio.removeAttribute('src');
            setPlayingState(false);
            const externalUrl = row.dataset.musicTrackExternal;
            if (autoplay && externalUrl) window.open(externalUrl, '_blank', 'noopener');
        }
    };

    rows.forEach((row, index) => {
        row.querySelector('[data-music-track-play]')?.addEventListener('click', () => loadTrack(index, true));
    });

    playButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!audio) return;
            if (!audio.src) { loadTrack(currentIndex, true); return; }
            if (audio.paused) audio.play().catch(() => {}); else audio.pause();
        });
    });

    prevButton?.addEventListener('click', () => loadTrack(Math.max(currentIndex - 1, 0), true));
    nextButton?.addEventListener('click', () => loadTrack(Math.min(currentIndex + 1, rows.length - 1), true));

    audio?.addEventListener('play', () => setPlayingState(true));
    audio?.addEventListener('pause', () => setPlayingState(false));
    audio?.addEventListener('timeupdate', () => {
        if (!audio.duration || !timeEl) return;
        if (seek) seek.value = (audio.currentTime / audio.duration) * 100;
        timeEl.textContent = `${formatTime(audio.currentTime)} / ${formatTime(audio.duration)}`;
    });
    audio?.addEventListener('ended', () => {
        if (currentIndex < rows.length - 1) loadTrack(currentIndex + 1, true);
    });
    seek?.addEventListener('input', () => {
        if (audio?.duration) audio.currentTime = (seek.value / 100) * audio.duration;
    });

    if (audio && volume) {
        audio.volume = volume.value / 100;
        volume.addEventListener('input', () => {
            audio.volume = volume.value / 100;
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('[data-music-save-toggle]'));

    // A track's Save state can appear twice on one page (the hero button
    // and the player-bar heart icon) — both share the same save-id, so
    // toggling either one keeps both in sync instead of only the button
    // that was actually clicked.
    const setState = (id, saved) => {
        buttons
            .filter((button) => button.dataset.musicSaveId === id)
            .forEach((button) => {
                button.classList.toggle('border-brand-gold', saved);
                button.classList.toggle('text-brand-gold', saved);
                button.setAttribute('aria-pressed', saved ? 'true' : 'false');
            });
    };

    buttons.forEach((button) => {
        const id = button.dataset.musicSaveId;
        const key = `music-saved-${id}`;

        try {
            setState(id, localStorage.getItem(key) === '1');
        } catch (e) {
            // Private browsing / storage blocked — Save simply doesn't persist.
        }

        button.addEventListener('click', () => {
            try {
                const saved = localStorage.getItem(key) === '1';
                localStorage.setItem(key, saved ? '0' : '1');
                setState(id, !saved);
            } catch (e) {
                // Private browsing / storage blocked — Save simply doesn't persist.
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const region = document.querySelector('[data-catalogue-region]');
    if (!region) return;

    const fetchAndSwap = async (url, { pushState = true } = {}) => {
        region.setAttribute('aria-busy', 'true');
        region.classList.add('opacity-50', 'pointer-events-none', 'transition-opacity');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

            region.innerHTML = await response.text();
            if (pushState) window.history.pushState({ catalogue: true }, '', url);
        } catch (error) {
            // The async refresh failed — fall back to a real navigation so
            // the user's search/filter/sort/page action still completes.
            window.location.href = url;
            return;
        } finally {
            region.removeAttribute('aria-busy');
            region.classList.remove('opacity-50', 'pointer-events-none', 'transition-opacity');
        }
    };

    region.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;

        // Filter pills, "Clear search", and pagination links all point back
        // at this same page (only the query string changes) — those are the
        // ones swapped in place. An album/single card link has a different
        // path and should navigate normally to its listening page.
        const url = new URL(link.href, window.location.origin);
        if (url.pathname !== window.location.pathname) return;

        event.preventDefault();
        fetchAndSwap(link.href);
    });

    region.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-catalogue-form]');
        if (!form) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') params.delete(key);
        });

        fetchAndSwap(`${form.action}?${params.toString()}`);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname !== '/music') return;
        fetchAndSwap(window.location.href, { pushState: false });
    });
});
