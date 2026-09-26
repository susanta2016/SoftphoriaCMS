{{--
    WEB-101 — the one Contact Us form markup, shared by the dedicated
    /contact page (resources/views/contact/index.blade.php) and any CMS
    page's `contact_form` section (resources/views/pages/show.blade.php).
    Both post to the exact same route('contact.submit')/ContactController —
    there is no second contact-processing implementation, per the ticket's
    explicit rule. Progressive enhancement (busy state, message counter)
    lives in resources/js/app.js ([data-contact-form]).
--}}
@props(['heading' => null, 'description' => null, 'source' => 'contact_page'])

<div {{ $attributes }}>
    @if ($heading)
        <h2 class="text-2xl font-bold text-brand-navy">{{ $heading }}</h2>
    @endif

    @if ($description)
        <p class="mt-2 text-brand-navy/70">{{ $description }}</p>
    @endif

    <form
        method="POST"
        action="{{ route('contact.submit') }}"
        data-contact-form
        data-lead-form
        class="relative grid gap-5 sm:grid-cols-2 {{ ($heading || $description) ? 'mt-6' : '' }}"
    >
        @csrf
        <x-site.lead-context :source="$source"/>

        {{-- Time trap: when this form was served (encrypted) — see FormTimeTrap / ContactController::store(). --}}
        <input type="hidden" name="{{ \App\Shared\Support\Spam\FormTimeTrap::FIELD }}" value="{{ app(\App\Shared\Support\Spam\FormTimeTrap::class)->issue() }}">

        {{--
            Honeypot: a real visitor never sees or fills this in. A bot that
            blindly fills every field trips it — see ContactController::store().
        --}}
        <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
            <label for="hp_website">Website</label>
            <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
        </div>

        <x-site.form-field name="name" label="Full name" :required="true" maxlength="255" autocomplete="name" placeholder="Jane Doe"/>
        <x-site.form-field name="email" label="Email" type="email" :required="true" maxlength="255" autocomplete="email" placeholder="jane@company.com"/>
        <x-site.form-field name="phone" label="Phone" type="tel" maxlength="30" autocomplete="tel" placeholder="+1 555 010 0100"/>

        <x-site.form-field name="category" label="What can we help with?" type="select">
            <option value="">Choose a topic (optional)</option>
            <option value="project" @selected(old('category') === 'project')>New project</option>
            <option value="general" @selected(old('category') === 'general')>General inquiry</option>
            <option value="support" @selected(old('category') === 'support')>Support</option>
            <option value="feedback" @selected(old('category') === 'feedback')>Feedback</option>
            <option value="other" @selected(old('category') === 'other')>Other</option>
        </x-site.form-field>

        <div class="sm:col-span-2">
            <x-site.form-field name="subject" label="Subject" maxlength="255" placeholder="A short summary"/>
        </div>

        <div class="sm:col-span-2">
            <x-site.form-field
                name="message"
                label="Message"
                type="textarea"
                :rows="6"
                :required="true"
                maxlength="5000"
                placeholder="Tell us about your goals, timeline and anything else we should know."
                data-contact-form-message
            />
            <p class="mt-1 text-right text-xs text-brand-navy/40" aria-hidden="true"><span data-contact-form-count>0</span> / 5000</p>
        </div>

        <div class="flex flex-col gap-4 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-start gap-2 text-xs leading-relaxed text-brand-navy/55">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-px h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Your details are only used to reply to you — never shared or added to a mailing list.</span>
            </p>

            <x-site.button type="submit" size="lg" data-contact-form-submit class="shrink-0 shadow-lg shadow-brand-accent/25 disabled:cursor-wait disabled:opacity-70">
                <svg data-contact-form-spinner xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="hidden h-4 w-4 animate-spin" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                <span>Send Message</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true" data-contact-form-arrow><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </x-site.button>
        </div>
    </form>
</div>
