{{--
    The portfolio call-to-action card — the /portfolio listing and every
    /portfolio/{slug} detail page. Links to the existing Contact page (the
    Contact Popup feature opens its quick form in place when switched on),
    so there's no second contact form.
--}}
@props(['heading' => 'Have a project like these in mind?'])

<div {{ $attributes->class(['flex flex-col items-start justify-between gap-6 rounded-3xl bg-white p-8 shadow-sm sm:flex-row sm:items-center']) }}>
    <div>
        <h2 class="text-xl font-bold text-brand-navy">{{ $heading }}</h2>
        <p class="mt-1 text-brand-navy/65">Tell us about it — we'll reply with practical next steps.</p>
    </div>
    <a href="{{ route('contact.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-accent-dark">
        Start a project <x-site.arrow class="h-4 w-4"/>
    </a>
</div>
