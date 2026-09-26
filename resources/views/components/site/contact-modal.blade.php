{{--
    Quick-contact popup. Any call-to-action link to the Contact page
    ("Start a Conversation", "Let's Talk", "Get a free consultation"…)
    opens this instead of leaving the page — see [data-contact-modal] in
    resources/js/app.js, which also skips navigation menus and the footer.
    A native <dialog> (focus trap, Esc to close, inert background). Posts
    over fetch() to the same route('contact.submit') JSON branch as the side
    widget, with the standard honeypot + time trap and the lead context
    (page URL/title, button clicked, referrer). Included by the site layout
    while the Contact Popup feature is on.
--}}
@php
    $inputClasses = 'mt-1.5 block w-full rounded-xl border border-brand-navy/10 bg-brand-mist px-4 py-3 text-sm text-brand-navy placeholder:text-brand-navy/40 transition focus:border-brand-accent focus:bg-white focus:ring-4 focus:ring-brand-accent/15 focus:outline-none aria-[invalid=true]:border-red-400';
@endphp

<dialog
    data-contact-modal
    aria-labelledby="contact-modal-title"
    class="m-auto w-[min(34rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-3xl bg-white p-0 text-brand-navy shadow-2xl shadow-brand-navy/30 backdrop:bg-brand-navy-dark/60 backdrop:backdrop-blur-sm open:animate-[contact-modal-in_0.25s_ease-out] print:hidden"
>
    <div class="relative overflow-hidden bg-gradient-to-br from-brand-navy via-brand-navy to-brand-accent px-6 pt-7 pb-6 text-white sm:px-8">
        <div class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-brand-accent/50 blur-3xl" aria-hidden="true"></div>
        <p class="relative text-xs font-semibold tracking-[0.18em] text-white/70 uppercase">Quick contact</p>
        <h2 id="contact-modal-title" class="relative mt-2 text-2xl font-bold">Let's start a conversation</h2>
        <p class="relative mt-1.5 text-sm text-white/75">Tell us a little about what you need — we'll get back to you personally.</p>
        <button type="button" data-contact-modal-close aria-label="Close" class="absolute top-4 right-4 flex h-9 w-9 items-center justify-center rounded-full text-white/80 transition hover:bg-white/15 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
        </button>
    </div>

    <form data-contact-modal-form data-lead-form method="POST" action="{{ route('contact.submit') }}" novalidate class="grid gap-4 px-6 pt-6 pb-7 sm:grid-cols-2 sm:px-8">
        @csrf
        <x-site.lead-context source="popup"/>
        <input type="hidden" name="{{ \App\Shared\Support\Spam\FormTimeTrap::FIELD }}" value="{{ app(\App\Shared\Support\Spam\FormTimeTrap::class)->issue() }}">
        <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
            <label for="cm-hp_website">Website</label>
            <input type="text" id="cm-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="cm-name" class="text-sm font-semibold">Name <span class="text-brand-accent" aria-hidden="true">*</span></label>
            <input id="cm-name" name="name" type="text" required maxlength="255" autocomplete="name" aria-describedby="cm-name-error" class="{{ $inputClasses }}">
            <p id="cm-name-error" data-error-for="name" class="mt-1 hidden text-xs text-red-600"></p>
        </div>
        <div>
            <label for="cm-email" class="text-sm font-semibold">Email <span class="text-brand-accent" aria-hidden="true">*</span></label>
            <input id="cm-email" name="email" type="email" required maxlength="255" autocomplete="email" aria-describedby="cm-email-error" class="{{ $inputClasses }}">
            <p id="cm-email-error" data-error-for="email" class="mt-1 hidden text-xs text-red-600"></p>
        </div>
        <div class="sm:col-span-2">
            <label for="cm-phone" class="text-sm font-semibold">Phone <span class="font-normal text-brand-navy/45">(optional)</span></label>
            <input id="cm-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" aria-describedby="cm-phone-error" class="{{ $inputClasses }}">
            <p id="cm-phone-error" data-error-for="phone" class="mt-1 hidden text-xs text-red-600"></p>
        </div>
        <div class="sm:col-span-2">
            <label for="cm-message" class="text-sm font-semibold">How can we help? <span class="text-brand-accent" aria-hidden="true">*</span></label>
            <textarea id="cm-message" name="message" rows="4" required maxlength="5000" aria-describedby="cm-message-error" placeholder="A few lines about your project, goals or timeline." class="{{ $inputClasses }} resize-y"></textarea>
            <p id="cm-message-error" data-error-for="message" class="mt-1 hidden text-xs text-red-600"></p>
        </div>

        <p data-contact-modal-alert role="alert" class="hidden rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 sm:col-span-2"></p>

        <div class="flex flex-col-reverse items-center gap-3 sm:col-span-2 sm:flex-row sm:justify-between">
            <a href="{{ route('contact.index') }}" data-no-contact-popup class="text-sm font-medium text-brand-navy/55 transition hover:text-brand-accent">Prefer the full form?</a>
            <button type="submit" data-contact-modal-submit class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-accent/25 transition hover:bg-brand-accent-dark disabled:cursor-wait disabled:opacity-70 sm:w-auto">
                <svg data-contact-modal-spinner xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="hidden h-4 w-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                Send message
                <x-site.arrow class="h-4 w-4"/>
            </button>
        </div>
        <p class="text-center text-xs text-brand-navy/45 sm:col-span-2">We use your details only to respond to your enquiry and never sell them. See our <a href="/privacy-policy" class="font-semibold text-brand-accent hover:text-brand-accent-dark">Privacy Policy</a>.</p>
    </form>

    <div data-contact-modal-success role="status" class="hidden px-6 py-12 text-center sm:px-8">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-sky text-brand-accent">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-7 w-7" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <p class="mt-4 text-lg font-bold">Message sent</p>
        <p data-contact-modal-success-text class="mt-1 text-sm text-brand-navy/65"></p>
        <button type="button" data-contact-modal-close class="mt-6 rounded-xl bg-brand-sky px-5 py-2.5 text-sm font-semibold text-brand-accent transition hover:bg-brand-accent hover:text-white">Close</button>
    </div>
</dialog>
