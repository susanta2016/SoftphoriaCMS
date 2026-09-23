{{--
    WEB-103 — the redesigned homepage's block header: small eyebrow label,
    heading, optional one-line description, and an optional "View all"
    link that sits to the right of the heading from sm up (below it on
    phones). `dark` flips the text colors for a dark band.
--}}
@props([
    'eyebrow' => null,
    'heading' => null,
    'description' => null,
    'linkLabel' => null,
    'linkUrl' => null,
    'dark' => false,
])

@if ($eyebrow || $heading || $description || ($linkLabel && $linkUrl))
    <div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
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
                    'mt-2 text-2xl leading-tight font-bold whitespace-pre-line sm:text-3xl',
                    'text-brand-navy' => ! $dark,
                    'text-white' => $dark,
                ])>{{ $heading }}</h2>
            @endif
            @if ($description)
                <p @class([
                    'mt-2 text-sm sm:text-base',
                    'text-brand-navy/65' => ! $dark,
                    'text-white/70' => $dark,
                ])>{{ $description }}</p>
            @endif
        </div>
        @if ($linkLabel && $linkUrl)
            <a href="{{ $linkUrl }}" class="group inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-brand-accent transition hover:text-brand-accent-dark">
                {{ $linkLabel }}
                <x-site.arrow class="h-4 w-4 transition group-hover:translate-x-0.5"/>
            </a>
        @endif
    </div>
@endif
