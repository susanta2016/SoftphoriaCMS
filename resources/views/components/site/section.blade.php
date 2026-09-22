{{--
    WEB-101 — the generic public-page section wrapper. Every rendered
    page/contact block sits inside one of these instead of a bespoke
    per-block wrapper, so vertical rhythm/max-width stays consistent across
    every section type without a page-builder.
--}}
@props(['tight' => false])

<section {{ $attributes->class([
    'mx-auto max-w-4xl px-4 sm:px-6',
    $tight ? 'py-6' : 'py-10 sm:py-12',
]) }}>
    {{ $slot }}
</section>
