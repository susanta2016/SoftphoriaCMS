{{--
    WEB-101 — the one Contact Us form markup, shared by the dedicated
    /contact page (resources/views/contact/index.blade.php) and any CMS
    page's `contact_form` section (resources/views/pages/show.blade.php).
    Both post to the exact same route('contact.submit')/ContactController —
    there is no second contact-processing implementation, per the ticket's
    explicit rule.
--}}
@props(['heading' => null, 'description' => null])

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
        class="grid gap-5 {{ ($heading || $description) ? 'mt-6' : '' }}"
    >
        @csrf

        {{--
            Honeypot: a real visitor never sees or fills this in. A bot that
            blindly fills every field trips it — see ContactController::store().
        --}}
        <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
            <label for="hp_website">Website</label>
            <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
        </div>

        <x-site.form-field name="name" label="Name" :required="true"/>
        <x-site.form-field name="email" label="Email" type="email" :required="true"/>
        <x-site.form-field name="phone" label="Phone" type="tel"/>
        <x-site.form-field name="subject" label="Subject"/>

        <x-site.form-field name="category" label="Category" type="select">
            <option value="">Select a category (optional)</option>
            <option value="general" @selected(old('category') === 'general')>General Inquiry</option>
            <option value="support" @selected(old('category') === 'support')>Support</option>
            <option value="feedback" @selected(old('category') === 'feedback')>Feedback</option>
            <option value="other" @selected(old('category') === 'other')>Other</option>
        </x-site.form-field>

        <x-site.form-field name="message" label="Message" type="textarea" :rows="6" :required="true"/>

        <div>
            <x-site.button type="submit">Send Message</x-site.button>
        </div>
    </form>
</div>
