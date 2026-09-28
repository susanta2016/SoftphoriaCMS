{{--
    WEB-103 — the full-width counterpart to x-site.section: a band whose
    background runs edge to edge while its content sits in the same
    max-w-7xl container as the header/footer. Used by the redesigned
    homepage's blocks (resources/views/components/site/blocks/*). WEB-103
    polish: one shared 64 / 80 / 96px vertical rhythm for every band.
--}}
@props(['background' => 'white', 'anchor' => null])

<section
    @if ($anchor) id="{{ $anchor }}" @endif
    {{ $attributes->class([
        'scroll-mt-24 py-16 sm:py-20 lg:py-24',
        'bg-white' => $background === 'white',
        'bg-brand-mist' => $background === 'tint',
    ]) }}
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </div>
</section>
