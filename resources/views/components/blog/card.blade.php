{{--
    One blog post card, used by the /blog listing, archives, related posts
    and the homepage Latest Insights section. `style` (Blog Settings → Card
    style): elevated | bordered | minimal. `layout`: grid (image on top) |
    list (image beside the text from sm up). The whole card is clickable
    through a stretched title link, so there's exactly one link per card
    for crawlers and screen readers.
--}}
@props([
    'post',
    'style' => 'elevated',
    'layout' => 'grid',
    'showAuthor' => true,
    'showReadingTime' => true,
    'showCategory' => true,
    'headingLevel' => 'h3',
])

@php
    $frame = match ($style) {
        'bordered' => 'rounded-2xl border border-brand-navy/12 bg-white hover:border-brand-accent/40',
        'minimal' => 'rounded-2xl bg-transparent',
        default => 'rounded-2xl border border-brand-navy/5 bg-white shadow-sm shadow-brand-navy/5 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand-navy/10',
    };
    $isList = $layout === 'list';
    $cover = $post->cover;
@endphp

<article @class([
    'group relative flex overflow-hidden transition duration-300',
    $frame,
    'flex-col' => ! $isList,
    'flex-col sm:flex-row sm:items-stretch' => $isList,
])>
    <div @class([
        'relative shrink-0 overflow-hidden',
        'rounded-2xl' => $style === 'minimal',
        'aspect-[16/9]' => ! $isList,
        'aspect-[16/9] sm:aspect-auto sm:w-72 lg:w-80' => $isList,
    ])>
        @if ($cover)
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk($cover->disk)->url($cover->path) }}"
                alt="{{ $cover->alt_text ?: '' }}"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
            >
        @else
            <div class="flex h-full min-h-40 w-full items-center justify-center bg-gradient-to-br from-brand-navy via-brand-navy to-brand-accent" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="h-12 w-12 text-white/70"><path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z"/><path d="M8 9h8M8 12.5h8M8 16h5" stroke-linecap="round"/></svg>
            </div>
        @endif
        @if ($showCategory && $post->category)
            <span class="absolute top-3 left-3 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-brand-accent shadow-sm backdrop-blur">{{ $post->category->name }}</span>
        @endif
    </div>

    <div @class(['flex flex-1 flex-col', 'p-5 sm:p-6' => $style !== 'minimal', 'pt-4' => $style === 'minimal'])>
        <p class="flex flex-wrap items-center gap-x-2 text-xs text-brand-navy/55">
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
            @if ($showReadingTime)
                <span aria-hidden="true">·</span>
                <span>{{ $post->reading_minutes }} min read</span>
            @endif
        </p>

        <{{ $headingLevel }} class="mt-2 text-lg leading-snug font-bold text-brand-navy transition group-hover:text-brand-accent">
            <a href="{{ $post->url() }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:after:rounded-2xl focus-visible:after:ring-2 focus-visible:after:ring-brand-accent">{{ $post->title }}</a>
        </{{ $headingLevel }}>

        @if ($post->excerpt)
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-brand-navy/70">{{ $post->excerpt }}</p>
        @endif

        <div class="mt-auto flex items-center justify-between gap-3 pt-5">
            @if ($showAuthor && $post->author)
                <span class="flex items-center gap-2 text-xs font-medium text-brand-navy/75">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-sky text-[0.65rem] font-bold text-brand-accent" aria-hidden="true">{{ \Illuminate\Support\Str::of($post->author->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                    {{ $post->author->name }}
                </span>
            @else
                <span></span>
            @endif
            <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-accent" aria-hidden="true">
                Read
                <x-site.arrow class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"/>
            </span>
        </div>
    </div>
</article>
