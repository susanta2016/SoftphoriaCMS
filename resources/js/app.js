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

    // The bottom-left Consent Preferences button: only once a choice has
    // been saved, and never while the banner or Preferences Center is open.
    const revisit = document.querySelector('[data-cookie-revisit]');
    const syncRevisit = () => {
        if (!revisit) return;
        revisit.hidden = !readConsent()
            || !banner.classList.contains('hidden')
            || !preferences.classList.contains('hidden');
    };

    const setBannerOpen = (open) => {
        banner.classList.toggle('hidden', !open);
        syncRevisit();
    };

    const setPreferencesOpen = (open) => {
        preferences.classList.toggle('hidden', !open);
        preferences.classList.toggle('flex', open);
        if (open) setBannerOpen(false);
        syncRevisit();
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
                other.classList.toggle('sm:border-l-brand-accent', isActive);
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
    syncRevisit();
});

// WEB-103 — the homepage Testimonials carousel (resources/views/components/site/blocks/testimonials.blade.php).
// The slides sit side by side in a flex track that's slid with translateX.
// A copy of the first slide is appended, so going "next" from the last slide
// keeps sliding the same way onto that copy — then, once the slide finishes,
// the track jumps back to the real first slide with no transition, making the
// loop seamless (no rewind, no gap). Autoplays every data-autoplay seconds,
// pausing while the pointer or keyboard focus is inside the carousel and never
// autoplaying for prefers-reduced-motion visitors. Without JS the first slide
// shows.
document.addEventListener('DOMContentLoaded', () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-testimonial-slider]').forEach((slider) => {
        const track = slider.querySelector('[data-testimonial-track]');
        const slides = [...slider.querySelectorAll('[data-testimonial-slide]')];
        const dots = [...slider.querySelectorAll('[data-testimonial-dot]')];
        if (!track || slides.length < 2) return;

        const count = slides.length;
        const clone = slides[0].cloneNode(true);
        clone.removeAttribute('data-testimonial-slide');
        clone.setAttribute('aria-hidden', 'true');
        clone.inert = true;
        track.appendChild(clone);

        const delay = Number(slider.dataset.autoplay || 0) * 1000;
        const autoplay = !reducedMotion && delay > 0;
        let position = 0; // 0..count, where count is the first-slide copy
        let timer = null;
        let hovering = false;
        let focused = false;

        const setPosition = (target, animate) => {
            position = target;
            track.style.transition = animate && !reducedMotion ? '' : 'none';
            track.style.transform = `translateX(-${position * 100}%)`;

            const current = position % count;
            slides.forEach((slide, i) => {
                slide.inert = i !== current;
                slide.setAttribute('aria-hidden', String(i !== current));
            });
            dots.forEach((dot, i) => {
                const active = i === current;
                dot.classList.toggle('bg-white', active);
                dot.classList.toggle('bg-transparent', !active);
                if (active) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
        };

        // Instant jump, then force a reflow so the next animated move starts
        // from the jumped-to position rather than being merged with it.
        const jump = (target) => {
            setPosition(target, false);
            void track.offsetWidth;
        };

        const next = () => {
            if (position === count) jump(0);
            setPosition(position + 1, true);
            if (reducedMotion && position === count) jump(0);
        };

        const previous = () => {
            if (position === 0) jump(count);
            setPosition(position - 1, true);
        };

        const goTo = (index) => {
            if (position === count) jump(0);
            setPosition(index, true);
        };

        // Landing on the first-slide copy: swap to the real first slide.
        track.addEventListener('transitionend', (event) => {
            if (event.target === track && event.propertyName === 'transform' && position === count) jump(0);
        });

        // (Re)starts the countdown — also after every manual move, so a slide
        // a visitor just picked always gets the full interval.
        const schedule = () => {
            clearInterval(timer);
            timer = null;
            const running = autoplay && !hovering && !focused;
            track.setAttribute('aria-live', running ? 'off' : 'polite');
            if (running) timer = setInterval(next, delay);
        };

        const withReschedule = (move) => () => {
            move();
            schedule();
        };

        slider.querySelector('[data-testimonial-prev]')?.addEventListener('click', withReschedule(previous));
        slider.querySelector('[data-testimonial-next]')?.addEventListener('click', withReschedule(next));
        dots.forEach((dot, i) => dot.addEventListener('click', withReschedule(() => goTo(i))));

        slider.addEventListener('mouseenter', () => { hovering = true; schedule(); });
        slider.addEventListener('mouseleave', () => { hovering = false; schedule(); });
        // Only keyboard focus pauses — a mouse click on an arrow also focuses it,
        // and that shouldn't keep autoplay stopped after the pointer leaves.
        slider.addEventListener('focusin', (event) => { focused = event.target.matches(':focus-visible'); schedule(); });
        slider.addEventListener('focusout', (event) => {
            if (!slider.contains(event.relatedTarget)) {
                focused = false;
                schedule();
            }
        });

        setPosition(0, false);
        schedule();
    });
});

// Site-wide Contact Us widget (resources/views/components/site/contact-widget.blade.php).
// Hover opens it on mouse devices; a click on the tab pins it open (and is
// the only way on touch). It stays open while the visitor is using the form,
// and submits over fetch() to the /contact endpoint's JSON branch.
document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-contact-widget]');

    if (!widget) return;

    const panel = widget.querySelector('[data-contact-widget-panel]');
    const toggle = widget.querySelector('[data-contact-widget-toggle]');
    const form = widget.querySelector('[data-contact-widget-form]');
    const alertBox = widget.querySelector('[data-contact-widget-alert]');
    const submit = widget.querySelector('[data-contact-widget-submit]');
    const spinner = widget.querySelector('[data-contact-widget-spinner]');
    const success = widget.querySelector('[data-contact-widget-success]');
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    let pinned = false;
    let closeTimer = null;

    const isOpen = () => widget.hasAttribute('data-open');

    const setOpen = (open) => {
        clearTimeout(closeTimer);
        widget.toggleAttribute('data-open', open);
        panel.inert = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (!open) pinned = false;
    };

    // Typed-in text or focus inside the form means the visitor is busy with
    // it — a stray mouse-out must not snap it shut.
    const inUse = () => panel.contains(document.activeElement)
        || [...form.elements].some((el) => el.name && el.name !== '_token' && el.type !== 'hidden' && el.value.trim() !== '');

    if (canHover) {
        widget.addEventListener('mouseenter', () => setOpen(true));
        widget.addEventListener('mouseleave', () => {
            if (pinned || inUse()) return;
            closeTimer = setTimeout(() => setOpen(false), 350);
        });
    }

    toggle.addEventListener('click', () => {
        if (isOpen() && (pinned || !canHover)) {
            setOpen(false);
            return;
        }
        setOpen(true);
        pinned = true;
        widget.querySelector('#cw-name')?.focus({ preventScroll: true });
    });

    widget.querySelector('[data-contact-widget-close]').addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !widget.contains(event.target)) setOpen(false);
    });

    const clearErrors = () => {
        alertBox.classList.add('hidden');
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    };

    form.addEventListener('input', (event) => {
        if (event.target.getAttribute('aria-invalid') !== 'true') return;
        event.target.removeAttribute('aria-invalid');
        form.querySelector(`[data-error-for="${event.target.name}"]`)?.classList.add('hidden');
    });

    const showAlert = (message) => {
        alertBox.textContent = message;
        alertBox.classList.remove('hidden');
    };

    const setBusy = (busy) => {
        submit.disabled = busy;
        spinner.classList.toggle('hidden', !busy);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        if (!form.checkValidity()) {
            [...form.elements].filter((el) => !el.checkValidity()).forEach((el) => {
                el.setAttribute('aria-invalid', 'true');
                const error = form.querySelector(`[data-error-for="${el.name}"]`);
                if (error) {
                    error.textContent = el.validationMessage;
                    error.classList.remove('hidden');
                }
            });
            form.querySelector('[aria-invalid="true"]')?.focus();
            return;
        }

        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                form.reset();
                form.classList.add('hidden');
                success.querySelector('[data-contact-widget-success-text]').textContent = data.message ?? '';
                success.classList.remove('hidden');
                pinned = true;
                return;
            }

            if (response.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([name, messages]) => {
                    form.elements[name]?.setAttribute('aria-invalid', 'true');
                    const error = form.querySelector(`[data-error-for="${name}"]`);
                    if (error) {
                        error.textContent = messages[0];
                        error.classList.remove('hidden');
                    }
                });
                form.querySelector('[aria-invalid="true"]')?.focus();
            } else if (response.status === 429) {
                showAlert('Too many messages in a short time — please wait a minute and try again.');
            } else if (response.status === 419) {
                showAlert('Your session has expired — please refresh the page and try again.');
            } else {
                showAlert('Something went wrong sending your message. Please try again.');
            }
        } catch {
            showAlert('Could not reach the server — please check your connection and try again.');
        } finally {
            setBusy(false);
        }
    });

    widget.querySelector('[data-contact-widget-reset]').addEventListener('click', () => {
        success.classList.add('hidden');
        form.classList.remove('hidden');
        widget.querySelector('#cw-name')?.focus();
    });
});

// Contact details reveal (resources/views/components/site/contact-info.blade.php).
// The page only carries masked values; the real one is fetched on demand
// from ContactController::reveal() and rendered as a link, never as HTML.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-contact-reveal-scope]').forEach((scope) => {
        scope.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-contact-reveal]');
            if (!button || !scope.contains(button)) return;

            const channel = button.dataset.contactReveal;
            const slot = button.closest('[data-contact-reveal-slot]');
            const error = slot.parentElement.querySelector('[data-contact-reveal-error]');

            button.disabled = true;
            error.classList.add('hidden');

            const body = new FormData();
            body.append('channel', channel);
            body.append('_started', scope.dataset.revealToken);

            try {
                const response = await fetch(scope.dataset.revealUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': scope.dataset.csrf,
                    },
                    body,
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.href) {
                    throw new Error(
                        response.status === 429
                            ? 'Too many requests — please wait a minute and try again.'
                            : response.status === 419
                              ? 'Your session has expired — please refresh the page.'
                              : (data.message ?? 'Could not load this detail. Please try again.'),
                    );
                }

                const link = document.createElement('a');
                link.href = data.href;
                link.textContent = data.display;
                link.className = 'break-all text-sm font-semibold text-brand-accent underline decoration-brand-accent/30 underline-offset-4 hover:decoration-brand-accent';
                if (channel === 'whatsapp') {
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                }

                const copy = document.createElement('button');
                copy.type = 'button';
                copy.className = 'rounded-full px-2.5 py-1 text-xs font-semibold text-brand-navy/55 transition hover:bg-brand-sky hover:text-brand-accent';
                copy.textContent = 'Copy';
                copy.setAttribute('aria-label', `Copy ${data.display}`);
                copy.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(data.display);
                        copy.textContent = 'Copied';
                        setTimeout(() => { copy.textContent = 'Copy'; }, 1800);
                    } catch {
                        copy.textContent = 'Copy failed';
                    }
                });

                slot.replaceChildren(link, copy);
                link.focus();
            } catch (err) {
                error.textContent = err.message;
                error.classList.remove('hidden');
                button.disabled = false;
            }
        });
    });
});

// Contact page form (resources/views/components/site/contact-form.blade.php):
// message character counter + a busy state that also blocks double submits.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-contact-form]').forEach((form) => {
        const message = form.querySelector('[data-contact-form-message]');
        const count = form.querySelector('[data-contact-form-count]');
        const submit = form.querySelector('[data-contact-form-submit]');

        if (message && count) {
            const update = () => { count.textContent = String(message.value.length); };
            message.addEventListener('input', update);
            update();
        }

        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            if (submit) {
                submit.disabled = true;
                submit.querySelector('[data-contact-form-spinner]')?.classList.remove('hidden');
                submit.querySelector('[data-contact-form-arrow]')?.classList.add('hidden');
            }
        });
    });
});

// Blog listings (resources/views/blog/partials/page.blade.php): category
// chips, search and pagination swap the [data-async-region] block in place
// via fetch() instead of a full page load. Every link/form is a normal
// crawlable GET, so this is purely an enhancement.
document.addEventListener('DOMContentLoaded', () => {
    const regionOf = () => document.querySelector('[data-async-region]');
    if (!regionOf()) return;

    let controller = null;

    const load = async (url, { push = true, scrollToResults = false } = {}) => {
        const region = regionOf();
        controller?.abort();
        controller = new AbortController();
        region.setAttribute('aria-busy', 'true');
        region.classList.add('transition-opacity', 'opacity-60');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                cache: 'no-store',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(String(response.status));

            const next = new DOMParser()
                .parseFromString(await response.text(), 'text/html')
                .querySelector('[data-async-region]');
            if (!next) throw new Error('No region in response');

            region.replaceWith(next);
            if (next.dataset.pageTitle) document.title = next.dataset.pageTitle;
            if (push) history.pushState({ asyncRegion: true }, '', url);
            if (scrollToResults) document.getElementById('articles')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            if (error.name === 'AbortError') return;
            window.location.href = url; // fall back to a normal navigation
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-async-region] a[data-async-link]');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        load(link.href, { scrollToResults: Boolean(link.closest('nav[aria-label="Pagination"]')) });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-async-region] form[data-async-form]');
        if (!form) return;
        event.preventDefault();
        const url = new URL(form.action);
        const q = new FormData(form).get('q')?.toString().trim();
        if (q) url.searchParams.set('q', q);
        load(url.toString(), { scrollToResults: true });
    });

    history.replaceState({ asyncRegion: true }, '', window.location.href);
    window.addEventListener('popstate', (event) => {
        if (event.state?.asyncRegion) load(window.location.href, { push: false });
    });
});

// Blog post page (resources/views/blog/show.blade.php).
document.addEventListener('DOMContentLoaded', () => {
    const csrf = (form) => form.querySelector('input[name="_token"]')?.value ?? '';
    const jsonHeaders = (form) => ({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(form) });

    // Emoji reactions: toggle over fetch(), update every count in place.
    document.querySelectorAll('[data-blog-reactions] form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const response = await fetch(form.action, { method: 'POST', headers: jsonHeaders(form), body: new FormData(form) });
                if (response.status === 401 || response.status === 419) {
                    window.location.reload();
                    return;
                }
                if (!response.ok) throw new Error(String(response.status));
                const data = await response.json();
                document.querySelectorAll('[data-blog-reactions] [data-reaction]').forEach((el) => {
                    const key = el.dataset.reaction;
                    el.setAttribute('aria-pressed', data.mine.includes(key) ? 'true' : 'false');
                    el.querySelector('[data-reaction-count]').textContent = String(data.counts[key] ?? 0);
                });
            } catch {
                form.submit();
            } finally {
                button.disabled = false;
            }
        });
    });

    // Report (red flag): send over fetch() and confirm in place.
    document.querySelectorAll('form[data-report-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            form.querySelector('button[type="submit"]').disabled = true;
            try {
                const response = await fetch(form.action, { method: 'POST', headers: jsonHeaders(form), body: new FormData(form) });
                const data = await response.json().catch(() => ({}));
                const note = document.createElement('span');
                note.className = 'px-2 py-1 text-xs font-medium ' + (response.ok ? 'text-red-600' : 'text-brand-navy/50');
                note.textContent = response.ok ? '\u{1F6A9} Reported' : (data.message ?? 'Could not send report');
                form.closest('[data-report-menu]').replaceWith(note);
            } catch {
                form.submit();
            }
        });
    });

    // Close an open report menu when clicking elsewhere.
    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-report-menu][open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('form[data-comment-form]').forEach((form) => {
        const body = form.querySelector('textarea[name="body"]');
        const count = form.querySelector('[data-comment-count]');
        const update = () => {
            if (count) count.textContent = String(body.value.length);
        };
        body?.addEventListener('input', update);
        update();
        form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]').disabled = true;
        });
    });

    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const label = button.querySelector('[data-copy-link-label]');
            try {
                await navigator.clipboard.writeText(button.dataset.copyLink);
                label.textContent = 'Copied!';
            } catch {
                label.textContent = 'Copy failed';
            }
            setTimeout(() => {
                label.textContent = 'Copy link';
            }, 1800);
        });
    });

    // Table of contents: highlight the section being read.
    const tocLinks = [...document.querySelectorAll('[data-blog-toc] a')];
    if (tocLinks.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                tocLinks.forEach((a) => a.toggleAttribute('data-active', a.hash === '#' + entry.target.id));
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        new Set(tocLinks.map((a) => decodeURIComponent(a.hash.slice(1)))).forEach((id) => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });
    }
});

// Lead context (resources/views/components/site/lead-context.blade.php):
// every contact form reports the page it was sent from, its title and the
// site the visitor first arrived from (kept for the browsing session), so
// Admin → Contact Requests can tell where each lead came from. Filled in
// the capture phase, before any other submit handler reads the form.
(() => {
    const REFERRER_KEY = 'softphoria.lead_referrer';

    try {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        if (referrer && referrer.host !== window.location.host && !sessionStorage.getItem(REFERRER_KEY)) {
            sessionStorage.setItem(REFERRER_KEY, referrer.href);
        }
    } catch {
        // Storage unavailable (private mode) — the server falls back to Referer.
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('form[data-lead-form]');
        if (!form) return;

        const set = (name, value) => {
            const input = form.querySelector(`[data-lead-field="${name}"]`);
            if (input) input.value = value;
        };

        set('page_url', window.location.href);
        set('page_title', document.title);
        try {
            set('referrer', sessionStorage.getItem(REFERRER_KEY) ?? '');
        } catch {
            set('referrer', '');
        }
    }, true);
})();

// Quick-contact popup (resources/views/components/site/contact-modal.blade.php):
// call-to-action links to the Contact page open it instead of navigating.
// Menus (nav/footer), new-tab clicks and links marked data-no-contact-popup
// keep their normal behaviour.
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('dialog[data-contact-modal]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const form = dialog.querySelector('[data-contact-modal-form]');
    const alertBox = dialog.querySelector('[data-contact-modal-alert]');
    const submit = dialog.querySelector('[data-contact-modal-submit]');
    const spinner = dialog.querySelector('[data-contact-modal-spinner]');
    const success = dialog.querySelector('[data-contact-modal-success]');
    const ctaField = form.querySelector('[data-lead-field="lead_cta"]');
    const contactPath = new URL(dialog.querySelector('a[data-no-contact-popup]').href).pathname.replace(/\/+$/, '');

    const isContactCta = (link) => {
        if (link.hasAttribute('data-no-contact-popup') || link.target === '_blank' || link.hasAttribute('download')) return false;
        if (link.closest('nav, footer, dialog, [data-contact-widget]')) return false;

        const url = new URL(link.href, window.location.href);
        return url.origin === window.location.origin && url.pathname.replace(/\/+$/, '') === contactPath && url.hash === '';
    };

    const reset = () => {
        success.classList.add('hidden');
        form.classList.remove('hidden');
        alertBox.classList.add('hidden');
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (!isContactCta(link)) return;

        event.preventDefault();
        if (!success.classList.contains('hidden')) {
            form.reset();
        }
        reset();
        ctaField.value = (link.getAttribute('aria-label') || link.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        dialog.showModal();
        dialog.querySelector('#cm-name')?.focus();
    });

    dialog.querySelectorAll('[data-contact-modal-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    // A click on the backdrop (the dialog element itself, outside its box) closes it.
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
        if (!inside) dialog.close();
    });

    form.addEventListener('input', (event) => {
        if (event.target.getAttribute('aria-invalid') !== 'true') return;
        event.target.removeAttribute('aria-invalid');
        form.querySelector(`[data-error-for="${event.target.name}"]`)?.classList.add('hidden');
    });

    const showAlert = (message) => {
        alertBox.textContent = message;
        alertBox.classList.remove('hidden');
    };

    const showFieldErrors = (errors) => {
        Object.entries(errors).forEach(([name, messages]) => {
            form.elements[name]?.setAttribute('aria-invalid', 'true');
            const error = form.querySelector(`[data-error-for="${name}"]`);
            if (error) {
                error.textContent = Array.isArray(messages) ? messages[0] : messages;
                error.classList.remove('hidden');
            }
        });
        form.querySelector('[aria-invalid="true"]')?.focus();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        reset();

        if (!form.checkValidity()) {
            const errors = {};
            [...form.elements].filter((el) => el.name && !el.checkValidity()).forEach((el) => {
                errors[el.name] = el.validationMessage;
            });
            showFieldErrors(errors);
            return;
        }

        submit.disabled = true;
        spinner.classList.remove('hidden');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                form.reset();
                form.classList.add('hidden');
                success.querySelector('[data-contact-modal-success-text]').textContent = data.message ?? '';
                success.classList.remove('hidden');
                success.querySelector('[data-contact-modal-close]')?.focus();
            } else if (response.status === 422 && data.errors) {
                showFieldErrors(data.errors);
            } else if (response.status === 429) {
                showAlert('Too many messages in a short time — please wait a minute and try again.');
            } else if (response.status === 419) {
                showAlert('Your session has expired — please refresh the page and try again.');
            } else {
                showAlert('Something went wrong sending your message. Please try again.');
            }
        } catch {
            showAlert('Could not reach the server — please check your connection and try again.');
        } finally {
            submit.disabled = false;
            spinner.classList.add('hidden');
        }
    });
});
