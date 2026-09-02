document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.querySelector('[data-light-post-textarea]');
    const counter = document.querySelector('[data-light-post-counter]');

    if (!textarea || !counter) return;

    const maxLength = Number(textarea.getAttribute('maxlength')) || 0;

    const update = () => {
        counter.textContent = `${textarea.value.length} / ${maxLength}`;
    };

    textarea.addEventListener('input', update);
    update();
});

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
    const toggles = Array.from(document.querySelectorAll('[data-video-modal-toggle]'));

    if (!modal || toggles.length === 0) return;

    const player = modal.querySelector('[data-video-modal-player]');
    const iframe = modal.querySelector('[data-video-modal-iframe]');
    const closeButton = modal.querySelector('[data-video-modal-close]');

    const setOpen = (open) => {
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);

        if (open) {
            player?.play();

            // No autoplay param — opening the modal only loads the embed;
            // the user presses Play inside it themselves.
            if (iframe) iframe.src = iframe.dataset.src;
        } else {
            player?.pause();
            if (player) player.currentTime = 0;

            // Clearing (not just pausing) the iframe's src is what actually
            // stops YouTube/Vimeo playback — there's no cross-origin API
            // access to call pause() on an embedded player here.
            if (iframe) iframe.src = '';
        }
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', () => setOpen(true)));
    closeButton?.addEventListener('click', () => setOpen(false));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) setOpen(false);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-cart-added-modal]');

    if (!modal) return;

    const close = () => modal.remove();

    modal.querySelector('[data-cart-added-modal-close]')?.addEventListener('click', close);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
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
    const controlEls = player ? Array.from(player.querySelectorAll('[data-music-player-controls]')) : [];
    const fallbackEl = player?.querySelector('[data-music-player-fallback]');
    const guestEndedEl = player?.querySelector('[data-music-player-guest-ended]');
    const limitReachedEl = player?.querySelector('[data-music-player-limit-reached]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let currentIndex = Math.max(rows.findIndex((row) => row.dataset.musicTrackActive === '1'), 0);
    // Set the moment a completed listen (this page, or discovered via a
    // stream request denied from another tab) reaches the server's daily
    // quota. The single guard every play/next/prev/auto-advance path goes
    // through — see loadTrack() below — so there is no path left that can
    // start another track after this is true, and no race with the
    // reload it schedules.
    let quotaReached = false;

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

    const hideMessages = () => {
        fallbackEl?.classList.add('hidden');
        guestEndedEl?.classList.add('hidden');
        limitReachedEl?.classList.add('hidden');
    };

    const showFallback = (isFallback) => {
        controlEls.forEach((el) => el.classList.toggle('hidden', isFallback));
        if (isFallback) { hideMessages(); fallbackEl?.classList.remove('hidden'); }
    };

    const showLimitReached = () => {
        controlEls.forEach((el) => el.classList.add('hidden'));
        hideMessages();
        limitReachedEl?.classList.remove('hidden');
    };

    // The daily quota was just confirmed reached (either by this page's own
    // completion beacon, or by discovering a live 403 from another tab) —
    // stop playback for good on this page and hand off to a fresh page load
    // so every row's data-music-track-* attributes (and the Buy/Included
    // state, etc.) are rebuilt from the server's current quota state.
    const enterQuotaReachedState = () => {
        if (quotaReached) return;
        quotaReached = true;

        audio?.pause();
        if (audio) {
            audio.removeAttribute('src');
            audio.load();
        }
        setPlayingState(false);
        showLimitReached();

        window.setTimeout(() => window.location.reload(), 2500);
    };

    const loadTrack = (index, autoplay) => {
        if (quotaReached) return;

        const row = rows[index];
        if (!audio || !row) return;

        currentIndex = index;
        rows.forEach((r) => r.classList.toggle('bg-brand-gold/10', r === row));
        if (titleEl) titleEl.textContent = row.dataset.musicTrackTitle || '';

        audio.pause();
        setPlayingState(false);
        hideMessages();

        const src = row.dataset.musicTrackSrc;
        if (!src) {
            audio.removeAttribute('src');
            if (row.dataset.musicTrackLimitReached === '1') showLimitReached(); else showFallback(true);
            return;
        }

        showFallback(false);
        audio.src = src;
        if (autoplay) audio.play().catch(() => {});
    };

    rows.forEach((row, index) => {
        row.querySelector('[data-music-track-play]')?.addEventListener('click', () => loadTrack(index, true));
    });

    playButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!audio || quotaReached) return;
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
    audio?.addEventListener('ended', async () => {
        if (quotaReached) return;

        const row = rows[currentIndex];

        // A guest's own request for this track was already hard-truncated by
        // the server (TrackStreamController) — the clip simply ran out, this
        // is not a real completed listen, so no beacon is ever sent here.
        if (row?.dataset.musicTrackGuestLimited === '1') {
            guestEndedEl?.classList.remove('hidden');
        } else if (row?.dataset.musicTrackCompleteUrl && csrfToken) {
            try {
                const response = await fetch(row.dataset.musicTrackCompleteUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.limit_reached) {
                        enterQuotaReachedState();
                        return;
                    }
                }
            } catch (e) {
                // Recording the listen failed over the network — fall through
                // to the normal auto-advance; TrackStreamController remains
                // the real authority on the next actual stream request
                // either way, so nothing is bypassed by this failing silently.
            }
        }

        if (currentIndex < rows.length - 1) loadTrack(currentIndex + 1, true);
    });
    // A real load/playback failure of the native audio source (bad file,
    // unsupported format, or the daily limit denying the request server-side)
    // — not fired when we deliberately have no src, since audio.src reads
    // back empty once removeAttribute('src') is used.
    audio?.addEventListener('error', async () => {
        if (!audio.src || quotaReached) return;
        setPlayingState(false);

        const row = rows[currentIndex];

        if (row?.dataset.musicTrackLimitReached === '1') {
            showLimitReached();
            return;
        }

        // A registered listener's row whose src was still stale (rendered
        // before the quota was reached, e.g. reached from another tab) can
        // fail for that reason without app.js knowing yet — <audio>'s error
        // event never exposes an HTTP status, so verify with one lightweight
        // HEAD request rather than assume. Guest rows never reach this
        // (they have no complete-url) and keep the existing generic fallback.
        if (row?.dataset.musicTrackCompleteUrl) {
            try {
                const check = await fetch(row.dataset.musicTrackSrc, { method: 'HEAD' });
                if (check.status === 403) {
                    enterQuotaReachedState();
                    return;
                }
            } catch (e) {
                // Couldn't verify the cause — fall through to the generic
                // fallback below rather than guess.
            }
        }

        showFallback(true);
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

    loadTrack(currentIndex, false);
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

document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('[data-music-download-all]'));

    // Each track download is a same-origin GET that responds with
    // Content-Disposition: attachment, so triggering several in quick
    // succession downloads each one without navigating away — staggered so
    // the browser doesn't treat the burst as a popup flood.
    buttons.forEach((button) => {
        const label = button.querySelector('[data-music-download-label]');
        const originalLabel = label?.textContent ?? 'Download';

        let urls = [];
        try {
            urls = JSON.parse(button.dataset.musicDownloadUrls || '[]');
        } catch (e) {
            urls = [];
        }

        if (urls.length === 0) return;

        button.addEventListener('click', () => {
            button.disabled = true;

            urls.forEach((url, index) => {
                window.setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = url;
                    link.rel = 'noopener';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();

                    if (label) label.textContent = `Downloading ${index + 1}/${urls.length}…`;

                    if (index === urls.length - 1) {
                        window.setTimeout(() => {
                            button.disabled = false;
                            if (label) label.textContent = originalLabel;
                        }, 600);
                    }
                }, index * 600);
            });
        });
    });
});

// Client-side guard for the comment form — catches an obviously empty/
// whitespace-only submission before it round-trips to the server. This is
// only a UX nicety; the review controllers (TrackReviewController etc.)
// still re-validate authoritatively (content required), so a JS-disabled
// browser is never left unprotected.
//
// Client-confirmed reversal (2026-09-02): the star-rating widget this used
// to pair with (data-review-rating/data-review-star) was removed — the
// public form no longer collects a rating at all, only a comment. See
// App\Actions\Review\SubmitReviewAction's own docblock.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-review-form]');
    if (!form) return;

    const contentInput = form.querySelector('[data-review-content-input]');
    const contentError = form.querySelector('[data-review-content-error]');

    contentInput?.addEventListener('input', () => contentError?.classList.add('hidden'));

    form.addEventListener('submit', (event) => {
        const hasContent = (contentInput?.value ?? '').trim().length > 0;

        contentError?.classList.toggle('hidden', hasContent);

        if (!hasContent) {
            event.preventDefault();
        }
    });
});

// The 🙌 reaction toggle — async fetch, no full-page reload (client-
// confirmed, 2026-09-02). Shared across Music/Podcast/Poetry-Prose via one
// generic handler and the same data-reaction-* markup on all three pages'
// detail views. Progressive enhancement: without JS (or if the fetch
// throws) the real <form method="POST"> still works — *ReactionController::
// toggle() returns a redirect-back when the request doesn't wantsJson(),
// exactly like before this change, so the feature never breaks for a
// JS-disabled visitor.
//
// A lapsed session (this button only ever renders for an authenticated
// visitor — see @auth in the Blade view — so this can only mean the
// session expired mid-visit) is handled by the same catch below, not a
// dedicated status check: bootstrap/app.php's shouldRenderJsonWhen scopes
// JSON exception rendering to `api/*` paths only, site-wide, so this
// non-/api route always gets the normal HTML redirect-to-login on an
// auth failure regardless of the Accept header sent here. fetch() follows
// that redirect itself, response.json() then throws on the login page's
// HTML, and the catch's form.submit() lands the visitor on the real login
// page exactly as if JS had never run.
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) return;

    const reactedClasses = ['border-brand-gold', 'bg-brand-gold/10', 'text-brand-navy'];
    const idleClasses = ['border-brand-navy/20', 'text-brand-navy/70', 'hover:border-brand-gold'];

    document.querySelectorAll('[data-reaction-form]').forEach((form) => {
        const button = form.querySelector('[data-reaction-button]');
        const countEl = form.querySelector('[data-reaction-count]');
        if (!button || !countEl) return;

        const applyState = (reacted) => {
            button.setAttribute('aria-pressed', reacted ? 'true' : 'false');
            button.classList.remove(...(reacted ? idleClasses : reactedClasses));
            button.classList.add(...(reacted ? reactedClasses : idleClasses));
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            // Prevent rapid repeated clicks/double submission while a
            // request is already in flight — the button stays disabled
            // for the full round trip, not just until the click handler
            // returns.
            if (button.disabled) return;
            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

                const data = await response.json();
                applyState(data.reacted);
                countEl.textContent = data.count;
            } catch (error) {
                // Network/server error, or a non-JSON response (e.g. a
                // followed redirect to the login page's HTML) — fall back
                // to a real form submit so the tap still completes via the
                // no-JS path instead of silently doing nothing.
                form.submit();
                return;
            } finally {
                button.disabled = false;
            }
        });
    });
});

// The All Episodes page's search/topic/duration/release-date/sort/list-grid
// controls, all fetched asynchronously — mirrors Music's own
// [data-catalogue-region] pattern above exactly, just with its own markers
// (data-podcast-episodes-region/-form) since it's a separate page. The one
// structural difference from Music: the filter form lives in the sidebar,
// outside the results region, so its submit is caught via a document-level
// listener rather than one scoped to the region — link clicks (pagination,
// list/grid toggle) stay region-scoped since those links are always inside
// the swapped fragment. The global header search (a distinct, unrelated
// control — see resources/views/components/site/header.blade.php) is never
// touched by this block.
document.addEventListener('DOMContentLoaded', () => {
    const region = document.querySelector('[data-podcast-episodes-region]');
    if (!region) return;

    const fetchAndSwap = async (url, { pushState = true } = {}) => {
        region.setAttribute('aria-busy', 'true');
        region.classList.add('opacity-50', 'pointer-events-none', 'transition-opacity');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

            region.innerHTML = await response.text();
            if (pushState) window.history.pushState({ podcastEpisodes: true }, '', url);
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

    // Document-scoped (not region-scoped): "Clear filters" lives in the
    // sidebar, outside the region, so pagination/list-grid-toggle/clear-
    // filters all need to be caught regardless of which side of the region
    // boundary they're on. Safe because only links whose pathname exactly
    // matches the current page (i.e. pointing back at /podcast/episodes)
    // are ever intercepted — an episode card/row link, or any other page's
    // link, always has a different pathname and navigates normally.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;

        const url = new URL(link.href, window.location.origin);
        if (url.pathname !== window.location.pathname) return;

        event.preventDefault();
        fetchAndSwap(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-podcast-episodes-form]');
        if (!form) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') params.delete(key);
        });

        fetchAndSwap(`${form.action}?${params.toString()}`);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname !== '/podcast/episodes') return;
        fetchAndSwap(window.location.href, { pushState: false });
    });
});

// The Poetry/Prose listing page's search/category/type/sort/list-grid
// controls plus pagination, all fetched asynchronously — mirrors the
// Podcast All Episodes block above, but simpler: the filter form, results,
// and sidebar all live inside ONE region here (data-poetry-prose-results-
// region), so every AJAX swap re-renders the whole toolbar+results+sidebar
// grid together. That keeps the sidebar's active-category highlighting,
// counts, and "Clear filters" link in sync with whatever filters are
// currently applied, without a second region or extra state-syncing code.
document.addEventListener('DOMContentLoaded', () => {
    const region = document.querySelector('[data-poetry-prose-results-region]');
    if (!region) return;

    const fetchAndSwap = async (url, { pushState = true } = {}) => {
        region.setAttribute('aria-busy', 'true');
        region.classList.add('opacity-50', 'pointer-events-none', 'transition-opacity');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

            region.innerHTML = await response.text();
            if (pushState) window.history.pushState({ poetryProseResults: true }, '', url);
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

    // Document-scoped (not region-scoped) so a link outside the region that
    // still points back at /poetry-prose (e.g. the header's own nav link)
    // is left to navigate normally — only same-pathname links get
    // intercepted, and every interactive control (pagination, view toggle,
    // category links, "Clear filters") lives inside the region anyway.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;

        const url = new URL(link.href, window.location.href);
        if (url.pathname !== '/poetry-prose' || !region.contains(link)) return;

        event.preventDefault();
        fetchAndSwap(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-poetry-prose-filters-form]');
        if (!form) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') params.delete(key);
        });

        fetchAndSwap(`${form.action}?${params.toString()}`);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname !== '/poetry-prose') return;
        fetchAndSwap(window.location.href, { pushState: false });
    });
});

// The Inspirational Resources listing page's search/category/sort/list-grid
// controls plus pagination, all fetched asynchronously — mirrors the
// Poetry/Prose block above exactly (one region covering the whole
// toolbar+results+sidebar grid, so the sidebar's active-category
// highlighting, counts, and "Clear filters" link all stay in sync).
document.addEventListener('DOMContentLoaded', () => {
    const region = document.querySelector('[data-inspirational-resources-results-region]');
    if (!region) return;

    const fetchAndSwap = async (url, { pushState = true } = {}) => {
        region.setAttribute('aria-busy', 'true');
        region.classList.add('opacity-50', 'pointer-events-none', 'transition-opacity');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

            region.innerHTML = await response.text();
            if (pushState) window.history.pushState({ inspirationalResourcesResults: true }, '', url);
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

    // Document-scoped (not region-scoped) so a link outside the region that
    // still points back at /inspirational-resources (e.g. the header's own
    // nav link) is left to navigate normally — only same-pathname links get
    // intercepted, and every interactive control (pagination, view toggle,
    // category links, "Clear filters") lives inside the region anyway. A
    // "Read More"/recent-story link goes to /inspirational-resources/{slug},
    // a different pathname, so it's never intercepted here.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;

        const url = new URL(link.href, window.location.href);
        if (url.pathname !== '/inspirational-resources' || !region.contains(link)) return;

        event.preventDefault();
        fetchAndSwap(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-inspirational-resources-filters-form]');
        if (!form) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') params.delete(key);
        });

        fetchAndSwap(`${form.action}?${params.toString()}`);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname !== '/inspirational-resources') return;
        fetchAndSwap(window.location.href, { pushState: false });
    });
});

// The full /search results page's own top-of-page search input + pagination,
// fetched asynchronously — mirrors the [data-inspirational-resources-results-region]
// block above exactly (data-search-region / data-search-page-form).
document.addEventListener('DOMContentLoaded', () => {
    const region = document.querySelector('[data-search-region]');
    if (!region) return;

    const fetchAndSwap = async (url, { pushState = true } = {}) => {
        region.setAttribute('aria-busy', 'true');
        region.classList.add('opacity-50', 'pointer-events-none', 'transition-opacity');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Unexpected status ${response.status}`);

            region.innerHTML = await response.text();
            if (pushState) window.history.pushState({ searchResults: true }, '', url);
        } catch (error) {
            // The async refresh failed — fall back to a real navigation so
            // the user's search/page action still completes.
            window.location.href = url;
            return;
        } finally {
            region.removeAttribute('aria-busy');
            region.classList.remove('opacity-50', 'pointer-events-none', 'transition-opacity');
        }
    };

    // Document-scoped: the page's own search input lives outside the
    // region (data-search-page-form, above it), while pagination links live
    // inside it — both point back at /search, only the query string differs.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;

        const url = new URL(link.href, window.location.href);
        if (url.pathname !== '/search' || !region.contains(link)) return;

        event.preventDefault();
        fetchAndSwap(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-search-page-form]');
        if (!form) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') params.delete(key);
        });

        fetchAndSwap(`${form.action}?${params.toString()}`);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname !== '/search') return;
        fetchAndSwap(window.location.href, { pushState: false });
    });
});

// The global header search control (desktop icon-that-expands-in-place, and
// its mobile-menu counterpart — see resources/views/components/site/
// header.blade.php's two [data-search-control] instances) plus its attached
// debounced autocomplete dropdown. Deliberately the first debounced-fetch
// pattern in this file — every other async block above reacts to a click/
// submit, not a keystroke, so this earns its own small AbortController-based
// implementation rather than reusing fetchAndSwap.
document.addEventListener('DOMContentLoaded', () => {
    const MIN_LENGTH = 2; // mirrors App\Modules\Search\Services\SearchService::MIN_LENGTH
    const DEBOUNCE_MS = 300;
    const TYPE_ICONS = {
        Music: '🎵',
        'Poetry / Prose': '📖',
        'Inspirational Resource': '✨',
        Community: '💬',
        Podcast: '🎧',
    };

    document.querySelectorAll('[data-search-control]').forEach((control) => {
        const toggle = control.querySelector('[data-search-toggle]');
        const panel = control.querySelector('[data-search-panel]');
        const closeBtn = control.querySelector('[data-search-close]');
        const input = control.querySelector('[data-search-input]');
        const suggestions = control.querySelector('[data-search-suggestions]');
        if (!toggle || !panel || !input || !suggestions) return;

        const isDesktop = control.dataset.searchVariant === 'desktop';
        let activeIndex = -1;
        let abortController = null;
        let debounceTimer = null;

        const isOpen = () => !panel.hasAttribute('hidden');

        const openPanel = () => {
            if (isOpen()) return;

            panel.removeAttribute('hidden');
            toggle.setAttribute('aria-expanded', 'true');

            if (isDesktop) {
                // Two-step so the width transition actually animates: the
                // element must render at its closed width (w-0) for at
                // least one frame before swapping to the open width,
                // otherwise the browser has nothing to transition from.
                panel.classList.add('w-0');
                requestAnimationFrame(() => {
                    panel.classList.remove('w-0');
                    panel.classList.add('w-64', 'sm:w-72');
                });
            }

            input.focus();
        };

        const hideSuggestions = () => {
            suggestions.hidden = true;
            suggestions.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };

        const closePanel = () => {
            if (!isOpen()) return;

            hideSuggestions();
            toggle.setAttribute('aria-expanded', 'false');

            if (isDesktop) {
                panel.classList.remove('w-64', 'sm:w-72');
                panel.classList.add('w-0');
                const onDone = (event) => {
                    if (event.propertyName !== 'width') return;
                    panel.setAttribute('hidden', '');
                    panel.removeEventListener('transitionend', onDone);
                };
                panel.addEventListener('transitionend', onDone);
            } else {
                panel.setAttribute('hidden', '');
            }
        };

        toggle.addEventListener('click', () => {
            if (isOpen()) closePanel(); else openPanel();
        });

        closeBtn?.addEventListener('click', () => {
            closePanel();
            toggle.focus();
        });

        document.addEventListener('click', (event) => {
            if (!isOpen() || control.contains(event.target)) return;
            closePanel();
        });

        control.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            if (!suggestions.hidden) {
                hideSuggestions();
            } else {
                closePanel();
                toggle.focus();
            }
        });

        const optionEls = () => Array.from(suggestions.querySelectorAll('[role="option"]'));

        const setActive = (index) => {
            const options = optionEls();
            if (options.length === 0) return;

            activeIndex = ((index % options.length) + options.length) % options.length;
            options.forEach((el, i) => {
                el.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
                el.classList.toggle('bg-brand-gold/10', i === activeIndex);
            });
            input.setAttribute('aria-activedescendant', options[activeIndex].id);
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        };

        const renderSuggestions = (data) => {
            const items = data.suggestions || [];
            suggestions.innerHTML = '';
            activeIndex = -1;

            if (items.length === 0) {
                suggestions.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                return;
            }

            const list = document.createElement('ul');
            list.className = 'divide-y divide-brand-navy/10';

            items.forEach((item, i) => {
                const li = document.createElement('li');
                li.id = `${input.id}-option-${i}`;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                li.className = 'flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-brand-gold/10';

                const icon = document.createElement('span');
                icon.className = 'flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded bg-brand-ivory text-base';
                icon.setAttribute('aria-hidden', 'true');
                if (item.image) {
                    const img = document.createElement('img');
                    img.src = item.image;
                    img.alt = '';
                    img.className = 'h-full w-full object-cover';
                    icon.appendChild(img);
                } else {
                    icon.textContent = TYPE_ICONS[item.type] || '•';
                }

                const text = document.createElement('span');
                text.className = 'min-w-0 flex-1';
                const typeEl = document.createElement('span');
                typeEl.className = 'block text-[11px] font-semibold tracking-wide text-brand-gold uppercase';
                typeEl.textContent = item.type;
                const titleEl = document.createElement('span');
                titleEl.className = 'block truncate text-sm text-brand-navy';
                titleEl.textContent = item.title;
                text.append(typeEl, titleEl);

                li.append(icon, text);
                li.addEventListener('click', () => { window.location.href = item.url; });
                list.appendChild(li);
            });

            const viewAll = document.createElement('a');
            viewAll.href = data.viewAllUrl;
            viewAll.id = `${input.id}-option-${items.length}`;
            viewAll.setAttribute('role', 'option');
            viewAll.setAttribute('aria-selected', 'false');
            viewAll.className = 'block border-t border-brand-navy/10 px-3 py-2 text-center text-sm font-semibold text-brand-gold hover:bg-brand-gold/10';
            viewAll.textContent = 'View all results →';
            list.appendChild(viewAll);

            suggestions.appendChild(list);
            suggestions.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const fetchSuggestions = (query) => {
            abortController?.abort();
            abortController = new AbortController();

            fetch(`/search/suggest?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => { if (data) renderSuggestions(data); })
                .catch(() => {
                    // Aborted (a newer keystroke superseded this request) or
                    // a network error — either way, leave whatever
                    // suggestions are currently showing alone.
                });
        };

        input.addEventListener('input', () => {
            const query = input.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < MIN_LENGTH) {
                abortController?.abort();
                hideSuggestions();
                return;
            }

            debounceTimer = setTimeout(() => fetchSuggestions(query), DEBOUNCE_MS);
        });

        input.addEventListener('keydown', (event) => {
            if (suggestions.hidden) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(activeIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                // Works for both a suggestion <li> (its own click listener
                // navigates via item.url) and the "View all results" <a>
                // (a real href, so .click() navigates it directly).
                optionEls()[activeIndex]?.click();
            }
        });
    });
});
