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

// The account-area Gratitude Journal page (resources/views/account/
// gratitude-journal.blade.php) — one create form plus one hidden edit form
// per existing entry can all be on the page at once, unlike the single
// registration-page textarea/counter pair above, hence querySelectorAll
// scoped per [data-gratitude-entry-form] rather than a single querySelector
// pair.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-gratitude-entry-form]').forEach((form) => {
        const textarea = form.querySelector('[data-gratitude-textarea]');
        const counter = form.querySelector('[data-gratitude-counter]');
        if (!textarea || !counter) return;

        const maxLength = Number(textarea.getAttribute('maxlength')) || 0;
        const update = () => { counter.textContent = `${textarea.value.length} / ${maxLength}`; };

        textarea.addEventListener('input', update);
        update();
    });

    document.querySelectorAll('[data-gratitude-entry]').forEach((entry) => {
        const toggle = entry.querySelector('[data-gratitude-edit-toggle]');
        const cancel = entry.querySelector('[data-gratitude-edit-cancel]');
        const editForm = entry.querySelector('[data-gratitude-edit-form]');
        const display = entry.querySelector('[data-gratitude-entry-display]');
        if (!toggle || !editForm) return;

        toggle.addEventListener('click', () => {
            editForm.classList.toggle('hidden');
            display?.classList.toggle('hidden');
        });

        cancel?.addEventListener('click', () => {
            editForm.classList.add('hidden');
            display?.classList.remove('hidden');
        });
    });

    // "Your Entries" / "Reminder Preference" tabs on the same page — the
    // server picks the initially active tab (see $reminderTabActive in the
    // Blade view, used when the reminder form redirects back with a
    // validation error), this just handles switching after that.
    document.querySelectorAll('[data-gratitude-tabs]').forEach((tabs) => {
        const triggers = Array.from(tabs.querySelectorAll('[data-gratitude-tab-trigger]'));
        const panels = Array.from(tabs.querySelectorAll('[data-gratitude-tab-panel]'));

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const key = trigger.dataset.gratitudeTabTrigger;

                triggers.forEach((t) => {
                    const active = t === trigger;
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                    t.classList.toggle('border-brand-gold', active);
                    t.classList.toggle('font-semibold', active);
                    t.classList.toggle('text-brand-navy', active);
                    t.classList.toggle('border-transparent', !active);
                    t.classList.toggle('font-medium', !active);
                    t.classList.toggle('text-brand-navy/60', !active);
                });

                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.gratitudeTabPanel !== key);
                });
            });
        });
    });
});

// The homepage "Latest Gratitude" carousel (resources/views/home.blade.php)
// — a seamless/infinite loop via a tripled, cloned track (client-confirmed,
// 2026-09-04: the previous modulo-index version left a visible blank gap
// approaching the last card, then a long jump-cut back to the first).
//
// How it works: the real server-rendered cards are cloned (cloneNode, so
// every clone is pixel-identical to the approved design — nothing about
// markup/classes is reconstructed here) into three back-to-back copies —
// [before][middle][after] — and navigation starts in the middle copy. Real
// cards always exist immediately adjacent in both directions, so a normal
// animated step never runs out of content to slide in, which is what
// removes the blank gap.
//
// Rapid-click safety (client-confirmed, 2026-09-04): an earlier version of
// this recentred via a delayed timer *after* a step's transition finished,
// which left a real gap — during a rapid click burst that never leaves the
// timer's own delay free, the index could keep climbing between clicks
// with no correction running at all, and once it drifted far enough to
// exceed the actual number of cloned cards, that render was the blank-space
// bug itself, regardless of what a later correction would have fixed.
// step() below closes that gap structurally rather than timing-wise: every
// single call re-validates its OWN starting position first — if it's
// already sitting at the edge of the safe middle third, it silently
// (transition:none) snaps to the equivalent position in the middle copy
// *before* applying this call's own one-card move. That bounds drift to at
// most one card beyond the safe window after any individual call, for any
// number of calls in any order (Next, Previous, autoplay, all funnel
// through the same step()) — there is no accumulation to correct for,
// because nothing is ever allowed to accumulate in the first place. No
// timer, no while-loop, no dependency on how much time has passed.
//
// Fewer real cards than the widest breakpoint shows at once (lg: 4 visible)
// would otherwise leave empty slots even inside one copy — each copy is
// therefore built from enough repeats of the real set to reach at least 4
// cards before being tripled, so a full row is always occupied at every
// breakpoint regardless of how few Public Gratitude entries exist. A single
// entry is left as one static card — nothing to usefully loop.
//
// A single intervalId per carousel instance is always cleared before a new
// one is set (stopAutoplay() is idempotent), so manual navigation restarts
// the timer without ever accumulating a second interval. Autoplay pauses on
// hover/focus-within and resumes on mouseleave/blur.
document.addEventListener('DOMContentLoaded', () => {
    const AUTOPLAY_MS = 6000;
    const MAX_VISIBLE = 4; // the most cards any breakpoint shows at once (lg:w-[calc(25%-0.75rem)])

    document.querySelectorAll('[data-gratitude-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-gratitude-carousel-track]');
        const originalItems = Array.from(carousel.querySelectorAll('[data-gratitude-carousel-item]'));
        const prevButton = carousel.querySelector('[data-gratitude-carousel-prev]');
        const nextButton = carousel.querySelector('[data-gratitude-carousel-next]');
        if (!track || originalItems.length === 0) return;

        // A single real card has nothing to loop through — leave it as a
        // plain static card rather than building a pointless clone buffer.
        if (originalItems.length <= 1) return;

        const repeats = Math.max(1, Math.ceil(MAX_VISIBLE / originalItems.length));
        const segmentSize = originalItems.length * repeats;

        const buildSegment = (hidden) => {
            const nodes = [];
            for (let r = 0; r < repeats; r++) {
                originalItems.forEach((item) => {
                    const clone = item.cloneNode(true);
                    if (hidden) clone.setAttribute('aria-hidden', 'true');
                    nodes.push(clone);
                });
            }
            return nodes;
        };

        track.innerHTML = '';
        [...buildSegment(true), ...buildSegment(false), ...buildSegment(true)].forEach((node) => track.appendChild(node));
        const items = Array.from(track.children);

        let index = segmentSize; // start at the first card of the middle copy
        let intervalId = null;

        // Measured live (not a fixed breakpoint width) so every step —
        // manual or autoplay — moves by exactly one card's actual rendered
        // width, including its share of the row's gap, at whatever
        // breakpoint is currently active.
        const stepWidth = () => {
            const gap = parseFloat(window.getComputedStyle(track).columnGap || '0');
            return items[0].getBoundingClientRect().width + gap;
        };

        const render = (animate) => {
            track.style.transition = animate ? '' : 'none';
            track.style.transform = `translateX(-${index * stepWidth()}px)`;

            if (!animate) {
                // Force layout so the transition-less jump actually applies
                // now, rather than being coalesced into the next animated
                // frame — which would turn this silent recenter back into
                // a visible jump.
                void track.offsetWidth;
                track.style.transition = '';
            }
        };

        // If the current position is already sitting at (or somehow past)
        // the edge of the safe middle third, snap it — instantly, no
        // transition, invisible since the clone content is identical — to
        // the equivalent position inside the middle copy before this call
        // moves any further. Plain modulo arithmetic (not a loop): whatever
        // multiple of segmentSize the drift amounts to collapses in one
        // step, so this is O(1) regardless of how far index had drifted,
        // and it runs synchronously before every single move, not on a
        // delay — so drift is corrected before it can compound, rather
        // than being allowed to accumulate and cleaned up afterwards.
        const recenterIfNeeded = () => {
            if (index < segmentSize || index >= segmentSize * 2) {
                index = segmentSize + (((index - segmentSize) % segmentSize) + segmentSize) % segmentSize;
                render(false);
            }
        };

        const step = (delta) => {
            recenterIfNeeded();
            index += delta;
            render(true);
        };

        const stopAutoplay = () => {
            if (intervalId === null) return;
            window.clearInterval(intervalId);
            intervalId = null;
        };

        const startAutoplay = () => {
            stopAutoplay();
            intervalId = window.setInterval(() => step(1), AUTOPLAY_MS);
        };

        prevButton?.addEventListener('click', () => {
            step(-1);
            startAutoplay();
        });

        nextButton?.addEventListener('click', () => {
            step(1);
            startAutoplay();
        });

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) startAutoplay();
        });

        window.addEventListener('resize', () => render(false));

        render(false);
        startAutoplay();
    });
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
//
// Client-confirmed (2026-09-04): Poetry/Prose ("Light Posts") is
// word-limited, not character-limited — the form only carries
// data-review-max-words when that module's config
// (features.poetry_prose_comment_max_words) is in play; Music/Podcast forms
// have no such attribute and keep the plain non-empty check above (their
// character limit is still enforced by the textarea's own maxlength
// attribute, unchanged). "Word" is defined identically to the server-side
// App\Rules\MaxWords: any run of non-whitespace characters, so this counter
// never disagrees with what the server will accept/reject.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-review-form]');
    if (!form) return;

    const contentInput = form.querySelector('[data-review-content-input]');
    const contentError = form.querySelector('[data-review-content-error]');
    const wordCounter = form.querySelector('[data-review-word-counter]');
    const maxWordsAttr = form.dataset.reviewMaxWords;
    const maxWords = maxWordsAttr ? parseInt(maxWordsAttr, 10) : null;

    const countWords = (value) => {
        const trimmed = value.trim();
        return trimmed === '' ? 0 : trimmed.split(/\s+/).length;
    };

    const updateWordCounter = () => {
        if (!wordCounter || maxWords === null) return;

        const count = countWords(contentInput?.value ?? '');
        wordCounter.textContent = `${count} / ${maxWords} words`;
        wordCounter.classList.toggle('text-red-600', count > maxWords);
        wordCounter.classList.toggle('text-brand-navy/40', count <= maxWords);
    };

    updateWordCounter();

    contentInput?.addEventListener('input', () => {
        contentError?.classList.add('hidden');
        updateWordCounter();
    });

    form.addEventListener('submit', (event) => {
        const value = contentInput?.value ?? '';
        const hasContent = value.trim().length > 0;
        const withinWordLimit = maxWords === null || countWords(value) <= maxWords;
        const isValid = hasContent && withinWordLimit;

        if (contentError) {
            contentError.textContent = ! hasContent
                ? 'Please write a few words before submitting your comment.'
                : `Comments can be at most ${maxWords} words.`;
            contentError.classList.toggle('hidden', isValid);
        }

        if (!isValid) {
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
        'Light Posts': '📖',
        'Inspirational Resource': '✨',
        Community: '💬',
        Podcast: '🎧',
    };

    // At lg+ (where the primary nav and the desktop search icon are both
    // visible), the expanded panel's width reaches into the same header
    // row the nav's own links occupy — there's no free gap wide enough for
    // a usable input between "Contact Us" and the action-group icons at
    // typical desktop widths. Rather than let the opaque search panel
    // silently paint over and mid-word-clip whichever nav links happen to
    // be underneath it, fade the whole nav out while the desktop search is
    // open so the panel's appearance reads as deliberate, not a rendering
    // glitch. No layout shift: the nav keeps its space, it's just
    // invisible/non-interactive for the moment search is open.
    const primaryNav = document.querySelector('[data-primary-nav]');

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
                // A synchronous forced-reflow (reading offsetWidth) does
                // that step instead of requestAnimationFrame — rAF is
                // suspended entirely while a tab isn't the visible/focused
                // one, which left the panel stuck at 0 width (present in
                // the DOM, invisible) whenever that happened; reading a
                // layout property has no such dependency.
                panel.classList.add('w-0');
                void panel.offsetWidth;
                panel.classList.remove('w-0');
                panel.classList.add('w-64', 'sm:w-72');
                primaryNav?.classList.add('opacity-0', 'pointer-events-none');
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
                primaryNav?.classList.remove('opacity-0', 'pointer-events-none');

                // transitionend (like requestAnimationFrame) doesn't fire
                // while the tab isn't the visible/focused one, which would
                // otherwise leave the panel's [hidden] attribute never
                // restored — a 250ms fallback timer (matching the
                // duration-200 transition) guarantees it happens either way;
                // setAttribute is idempotent so firing both is harmless.
                let done = false;
                const finish = () => {
                    if (done) return;
                    done = true;
                    panel.setAttribute('hidden', '');
                    panel.removeEventListener('transitionend', onDone);
                };
                const onDone = (event) => {
                    if (event.propertyName === 'width') finish();
                };
                panel.addEventListener('transitionend', onDone);
                setTimeout(finish, 250);
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
