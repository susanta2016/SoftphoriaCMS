{{--
    WEB-103 — the redesigned homepage's block header: small eyebrow label,
    heading, optional one-line description, and an optional "View all"
    link that sits to the right of the heading from sm up (below it on
    phones), styled as a small outlined pill so it reads as an action.
    `dark` flips the text colors for a dark band.
--}}
@props([
    'eyebrow' => null,
    'heading' => null,
    'description' => null,
    'linkLabel' => null,
    'linkUrl' => null,
    'dark' => false,
])

@php
    // Drop a "View all…" link to a frontend feature switched off in
    // Features Activation, so the heading never points at a 404.
    if ($linkUrl && app(\App\Shared\Support\Features\Features::class)->hidesLink($linkUrl)) {
        $linkUrl = null;
    }
@endphp

@if ($eyebrow || $heading || $description || ($linkLabel && $linkUrl))
    <div {{ $attributes->class(['flex flex-col items-start gap-5 sm:flex-row sm:items-end sm:justify-between']) }}>
        <div class="max-w-3xl">
            @if ($eyebrow)
                <p @class([
                    'text-xs font-semibold tracking-[0.18em] uppercase',
                    'text-brand-accent' => ! $dark,
                    'text-brand-accent-light' => $dark,
                ])>{{ $eyebrow }}</p>
            @endif
            @if ($heading)
                <h2 @class([
                    'mt-3 text-[1.75rem] leading-tight font-bold tracking-tight whitespace-pre-line sm:text-3xl lg:text-[2.25rem]',
                    'text-brand-navy' => ! $dark,
                    'text-white' => $dark,
                ])>{{ $heading }}</h2>
            @endif
            @if ($description)
                <p @class([
                    'mt-3 text-base leading-relaxed',
                    'text-brand-muted' => ! $dark,
                    'text-white/75' => $dark,
                ])>{{ $description }}</p>
            @endif
        </div>
        @if ($linkLabel && $linkUrl)
            <a href="{{ $linkUrl }}" @class([
                'group inline-flex shrink-0 items-center gap-1.5 rounded-md border px-4 py-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                'border-brand-accent/30 bg-white text-brand-accent hover:border-brand-accent hover:bg-brand-accent hover:text-white focus-visible:outline-brand-accent' => ! $dark,
                'border-white/25 text-white hover:border-white/60 hover:bg-white/10 focus-visible:outline-white' => $dark,
            ])>
                {{ $linkLabel }}
                <x-site.arrow class="h-4 w-4 transition group-hover:translate-x-0.5"/>
            </a>
        @endif
    </div>
@endif
