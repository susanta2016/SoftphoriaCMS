{{--
    WEB-101 design system — a single media card: an image plus optional
    title/description, optionally linking out. Used today by the Gallery
    section's structured items; built generically so a future References/
    portfolio listing can reuse it as-is (item E's stated intent).
--}}
@props(['media' => null, 'title' => null, 'description' => null, 'url' => null])

@php
    $tag = $url ? 'a' : 'div';

    $classes = $attributes->class([
        'group block overflow-hidden rounded-lg border border-brand-navy/10 bg-white shadow-sm transition',
        'hover:shadow-md' => (bool) $url,
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-navy' => (bool) $url,
    ]);
@endphp

<{{ $tag }} @if ($url) href="{{ $url }}" @endif {{ $classes }}>
    @if ($media)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) }}"
            alt="{{ $media->alt_text ?? $title ?? '' }}"
            class="h-48 w-full object-cover"
        >
    @endif

    @if ($title || $description)
        <div class="p-4">
            @if ($title)
                <h3 @class(['text-sm font-semibold text-brand-navy', 'group-hover:text-brand-gold' => (bool) $url])>{{ $title }}</h3>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-brand-navy/70">{{ $description }}</p>
            @endif
        </div>
    @endif
</{{ $tag }}>
