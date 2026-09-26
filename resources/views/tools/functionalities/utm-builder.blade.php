{{--
    UTM Builder (App\Tools\Functionalities\UtmBuilder) — interface only;
    behaviour in resources/js/tools/utm-builder.js. Builds the tagged URL
    live as the fields are filled in; nothing is sent to the server.
--}}
@php
    $fields = [
        ['utm_source', 'Campaign source', true, 'google, newsletter, linkedin', 'Where the traffic comes from.'],
        ['utm_medium', 'Campaign medium', true, 'cpc, email, social', 'The marketing channel.'],
        ['utm_campaign', 'Campaign name', true, 'spring_sale', 'The campaign or promotion.'],
        ['utm_term', 'Campaign term', false, 'website design', 'Paid search keyword (optional).'],
        ['utm_content', 'Campaign content', false, 'hero_button', 'Tells apart ads or links in the same campaign (optional).'],
        ['utm_id', 'Campaign ID', false, 'abc_123', 'Your campaign identifier (optional).'],
    ];
@endphp

<div data-utm class="space-y-6">
    <form class="space-y-5" novalidate data-utm-form>
        <div>
            <label for="utm-url" class="block text-sm font-semibold text-brand-navy">Website URL <span class="text-red-700" aria-hidden="true">*</span></label>
            <input id="utm-url" type="url" inputmode="url" autocomplete="url" required placeholder="https://www.example.com/landing-page"
                   aria-describedby="utm-url-help utm-url-error"
                   class="mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy placeholder:text-brand-navy/35 focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none aria-[invalid=true]:border-red-600"
                   data-utm-url>
            <p id="utm-url-help" class="mt-1.5 text-sm text-brand-navy/60">The full page address, including https://. Existing query parameters are kept.</p>
            <p id="utm-url-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-utm-error="url"></p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($fields as [$name, $label, $required, $placeholder, $help])
                <div>
                    <label for="utm-{{ $name }}" class="block text-sm font-semibold text-brand-navy">
                        {{ $label }} @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif
                        <span class="ms-1 font-mono text-xs font-normal text-brand-navy/50">{{ $name }}</span>
                    </label>
                    <input id="utm-{{ $name }}" type="text" @if ($required) required @endif placeholder="{{ $placeholder }}" autocomplete="off" spellcheck="false"
                           aria-describedby="utm-{{ $name }}-help utm-{{ $name }}-error"
                           class="mt-2 w-full rounded-xl border border-brand-navy/15 bg-white px-4 py-3 text-base text-brand-navy placeholder:text-brand-navy/35 focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/30 focus:outline-none aria-[invalid=true]:border-red-600"
                           data-utm-field="{{ $name }}">
                    <p id="utm-{{ $name }}-help" class="mt-1.5 text-sm text-brand-navy/60">{{ $help }}</p>
                    <p id="utm-{{ $name }}-error" class="mt-1.5 text-sm font-medium text-red-700" hidden data-utm-error="{{ $name }}"></p>
                </div>
            @endforeach
        </div>

        <label class="flex items-start gap-3 text-sm text-brand-navy/80">
            <input type="checkbox" checked class="mt-0.5 h-4 w-4 rounded border-brand-navy/30 text-brand-accent focus:ring-brand-accent" data-utm-lowercase>
            <span>Convert values to lowercase and spaces to underscores <span class="text-brand-navy/55">(recommended — analytics treats “Email” and “email” as different sources)</span></span>
        </label>
    </form>

    <div class="rounded-2xl bg-brand-sky/70 p-5">
        <div class="flex items-center justify-between gap-4">
            <p id="utm-result-label" class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Your campaign URL</p>
        </div>
        <output aria-labelledby="utm-result-label" class="mt-2 block min-h-12 rounded-xl border border-brand-navy/10 bg-white px-4 py-3 font-mono text-sm break-all text-brand-navy" data-utm-result>
            Fill in the website URL, source, medium and campaign name.
        </output>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" disabled class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-accent-dark focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50" data-utm-copy>
                Copy URL
            </button>
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-navy/15 bg-white px-5 py-3 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:outline-none" data-utm-reset>
                Clear
            </button>
        </div>
    </div>
    <p class="sr-only" aria-live="polite" data-utm-live></p>
    <noscript><p class="text-sm text-brand-navy/70">This builder needs JavaScript.</p></noscript>
</div>
