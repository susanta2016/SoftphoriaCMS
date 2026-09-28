{{--
    Site-wide Contact Us widget: a vertical tab fixed to the right edge that
    flips a compact contact form open on hover (or tap/click/keyboard), after
    the old softphoria.com sticky contact tab. Posts over fetch() to the same
    route('contact.submit') as the /contact page, so it saves a
    ContactRequest and sends the same notification emails — there is no
    second contact-processing path. Behaviour lives in resources/js/app.js
    ([data-contact-widget]); field ids are cw-prefixed so they never clash
    with a full contact form on the same page.

    WEB-103.1: below sm (phones) the side tab would sit over the hero copy,
    so there it is a round icon button at the bottom right (label kept for
    screen readers) and the panel opens above it, and it only fades in
    once the visitor scrolls past the top of the page (app.js/app.css) so it
    never covers the hero's buttons. From sm up it is the original
    right-edge tab. Same markup and JS in both cases.
--}}
@php
    $inputClasses = 'mt-1 block w-full rounded-lg border border-brand-navy/15 bg-brand-mist px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 transition focus:border-brand-accent focus:bg-white focus:ring-2 focus:ring-brand-accent/25 focus:outline-none aria-[invalid=true]:border-red-400';
@endphp

{{-- The wrapper spans the (hidden) panel too, so it ignores the pointer: only the tab, and the panel once open, react to hover/clicks — never the empty space beside the tab. --}}
<div data-contact-widget class="group pointer-events-none fixed right-4 bottom-4 z-40 flex flex-col items-end perspective-distant sm:top-1/2 sm:right-0 sm:bottom-auto sm:-translate-y-1/2 sm:flex-row sm:items-center print:hidden">
    <div
        id="contact-widget-panel"
        data-contact-widget-panel
        role="dialog"
        aria-labelledby="contact-widget-title"
        inert
        class="pointer-events-none invisible mb-3 max-h-[calc(100dvh-6.5rem)] w-[min(21rem,calc(100vw-2rem))] origin-bottom-right overflow-y-auto sm:mr-2 sm:mb-0 sm:max-h-[calc(100dvh-2rem)] sm:w-[min(21rem,calc(100vw-4.5rem))] sm:origin-right rounded-2xl border border-brand-navy/10 bg-white opacity-0 shadow-2xl shadow-brand-navy/20 transition-[transform,opacity,visibility] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] rotate-y-[-75deg] group-data-open:pointer-events-auto group-data-open:visible group-data-open:opacity-100 group-data-open:rotate-y-0 motion-reduce:transition-none"
    >
        <div class="relative bg-gradient-to-br from-brand-navy to-brand-accent px-5 py-4 text-white">
            <p id="contact-widget-title" class="text-lg font-bold">Contact Us</p>
            <p class="mt-0.5 text-xs text-white/75">Send us a message and we'll get back to you.</p>
            <button type="button" data-contact-widget-close aria-label="Close contact form" class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full text-white/80 transition hover:bg-white/15 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
            </button>
        </div>

        <form data-contact-widget-form data-lead-form method="POST" action="{{ route('contact.submit') }}" novalidate class="grid gap-3 px-5 pt-4 pb-5">
            @csrf
            <x-site.lead-context source="widget"/>
            <input type="hidden" name="{{ \App\Shared\Support\Spam\FormTimeTrap::FIELD }}" value="{{ app(\App\Shared\Support\Spam\FormTimeTrap::class)->issue() }}">

            {{-- Honeypot + time trap — see ContactController::store(). --}}
            <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
                <label for="cw-hp_website">Website</label>
                <input type="text" id="cw-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="cw-name" class="text-xs font-semibold text-brand-navy">Name <span class="text-brand-accent" aria-hidden="true">*</span></label>
                <input id="cw-name" name="name" type="text" required maxlength="255" autocomplete="name" aria-describedby="cw-name-error" class="{{ $inputClasses }}">
                <p id="cw-name-error" data-error-for="name" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <div>
                <label for="cw-email" class="text-xs font-semibold text-brand-navy">Email <span class="text-brand-accent" aria-hidden="true">*</span></label>
                <input id="cw-email" name="email" type="email" required maxlength="255" autocomplete="email" aria-describedby="cw-email-error" class="{{ $inputClasses }}">
                <p id="cw-email-error" data-error-for="email" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <div>
                <label for="cw-phone" class="text-xs font-semibold text-brand-navy">Phone</label>
                <input id="cw-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" aria-describedby="cw-phone-error" class="{{ $inputClasses }}">
                <p id="cw-phone-error" data-error-for="phone" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <div>
                <label for="cw-message" class="text-xs font-semibold text-brand-navy">Message <span class="text-brand-accent" aria-hidden="true">*</span></label>
                <textarea id="cw-message" name="message" rows="3" required maxlength="5000" aria-describedby="cw-message-error" class="{{ $inputClasses }} resize-none"></textarea>
                <p id="cw-message-error" data-error-for="message" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <p data-contact-widget-alert role="alert" class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"></p>

            <button type="submit" data-contact-widget-submit class="mt-1 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-accent px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent/40 focus-visible:outline-none disabled:cursor-wait disabled:opacity-70">
                <svg data-contact-widget-spinner xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="hidden h-4 w-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                <span data-contact-widget-submit-label>Send Message</span>
            </button>
            <p class="text-center text-[0.7rem] text-brand-navy/45">See our <a href="/privacy-policy" class="underline hover:text-brand-accent">Privacy Policy</a>.</p>
        </form>

        <div data-contact-widget-success role="status" class="hidden px-5 py-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-sky text-brand-accent">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-6 w-6" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <p class="mt-4 font-bold text-brand-navy">Message sent</p>
            <p data-contact-widget-success-text class="mt-1 text-sm text-brand-navy/65"></p>
            <button type="button" data-contact-widget-reset class="mt-5 text-sm font-semibold text-brand-accent hover:text-brand-accent-dark">Send another message</button>
        </div>
    </div>

    <button
        type="button"
        data-contact-widget-toggle
        aria-expanded="false"
        aria-controls="contact-widget-panel"
        class="pointer-events-auto flex h-13 w-13 items-center justify-center rounded-full bg-brand-accent text-sm font-semibold tracking-wide text-white shadow-lg shadow-brand-navy/30 ring-4 ring-white/70 transition-colors hover:bg-brand-accent-dark group-data-open:bg-brand-navy focus-visible:ring-brand-accent/40 focus-visible:outline-none sm:h-auto sm:w-auto sm:rotate-180 sm:gap-2 sm:rounded-none sm:rounded-r-xl sm:px-2.5 sm:py-4 sm:ring-0 sm:[writing-mode:vertical-rl] sm:focus-visible:ring-2"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 sm:h-4 sm:w-4 sm:rotate-180" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6" stroke-linejoin="round"/></svg>
        <span class="sr-only sm:not-sr-only">Contact Us</span>
    </button>
</div>
