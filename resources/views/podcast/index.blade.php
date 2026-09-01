@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
    $featuredThumbnailUrl = $featured?->thumbnailUrl();

    $durationLabel = fn (?int $seconds): ?string => $seconds ? intdiv($seconds, 60).' min' : null;

    // Decorative only — Category has no icon field, so each topic card cycles
    // through a small fixed set of icons by position rather than showing the
    // same generic tag glyph on every card.
    $topicIcons = [
        '<path d="M12 21c-4-3-7-7-7-11a7 7 0 0 1 14 0c0 4-3 8-7 11Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 12v6" stroke-linecap="round"/>',
        '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M5 5l1.5 1.5M17.5 17.5 19 19M3 12h2M19 12h2M5 19l1.5-1.5M17.5 6.5 19 5" stroke-linecap="round"/>',
        '<path d="M12 21V10M12 10c0-4 3-7 7-7 0 4-3 7-7 7Zm0 0C12 6 9 3 5 3c0 4 3 7 7 7Z" stroke-linecap="round" stroke-linejoin="round"/>',
        '<circle cx="9" cy="8" r="2.5"/><path d="M4 18c0-2.5 2.2-4.2 5-4.2s5 1.7 5 4.2" stroke-linecap="round"/><circle cx="17" cy="9" r="2"/><path d="M15 13.3c1.9.4 3.5 1.8 3.5 3.9" stroke-linecap="round"/>',
        '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.6 10.8c.5.4.8 1 .8 1.7V16h5.6v-.5c0-.7.3-1.3.8-1.7A6 6 0 0 0 12 3Z" stroke-linecap="round" stroke-linejoin="round"/>',
    ];
@endphp

<x-layouts.site :seo="$seo">
    <div
        @class(['relative overflow-hidden bg-cover bg-center bg-no-repeat', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}')" : ''])
    >
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:px-8 lg:pt-40 lg:pb-32">
            <div class="max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Podcast</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">
                    {{ $podcast?->title ?? 'Podcast' }}
                </h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                @if ($podcast?->description)
                    <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">
                        {{ str($podcast->description)->stripTags()->limit(220) }}
                    </p>
                @endif

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    @if ($featured)
                        <a href="{{ route('podcast.episodes.show', $featured) }}" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                            Latest Episode
                        </a>
                    @endif
                    <a href="{{ route('podcast.episodes.index') }}" class="inline-flex items-center rounded-md border border-brand-navy/40 px-6 py-3.5 text-sm font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-gold hover:text-brand-gold">
                        Browse All Episodes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($featured)
                <div class="grid grid-cols-1 gap-8 rounded-3xl bg-brand-ivory p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8 md:grid-cols-2">
                    <div class="relative aspect-video overflow-hidden rounded-2xl bg-brand-navy/10 md:aspect-auto">
                        @if ($featuredThumbnailUrl)
                            <img src="{{ $featuredThumbnailUrl }}" alt="{{ $featured->title }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full min-h-48 w-full items-center justify-center text-brand-navy/40">{{ $featured->title }}</div>
                        @endif

                        @if ($featuredEmbedUrl)
                            <button type="button" data-video-modal-toggle aria-label="Listen to {{ $featured->title }}" class="absolute inset-0 flex items-center justify-center">
                                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-white text-brand-gold shadow-lg transition hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </button>
                        @else
                            <a href="{{ route('podcast.episodes.show', $featured) }}" aria-label="Listen to {{ $featured->title }}" class="absolute inset-0 flex items-center justify-center">
                                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-white text-brand-gold shadow-lg transition hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </a>
                        @endif
                    </div>

                    <div class="flex flex-col justify-center">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-brand-gold uppercase">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="m12 2 2.9 6.6 7.1.7-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7-5.4-4.7 7.1-.7Z"/></svg>
                            Featured Episode
                        </span>
                        <p class="mt-3 text-xs font-medium text-brand-navy/50 uppercase">
                            @if ($featured->episode_number)Episode {{ $featured->episode_number }} &bull; @endif{{ $featured->publish_date?->format('M j, Y') }}
                        </p>
                        <h2 class="mt-2 font-serif text-2xl text-brand-navy sm:text-3xl">{{ $featured->title }}</h2>
                        @if ($featured->description)
                            <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">{{ str($featured->description)->stripTags()->limit(160) }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-brand-navy/60">
                            @if ($durationLabel($featured->duration_seconds))
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $durationLabel($featured->duration_seconds) }}
                                </span>
                            @endif
                            @if ($featured->categories->isNotEmpty())
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/></svg>
                                    {{ $featured->categories->first()->name }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-5">
                            @if ($featuredEmbedUrl)
                                <button type="button" data-video-modal-toggle class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                                    Listen Now
                                </button>
                            @else
                                <a href="{{ route('podcast.episodes.show', $featured) }}" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                                    Listen Now
                                </a>
                            @endif
                            <a href="{{ route('podcast.episodes.show', $featured) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                                View Episode Details <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-center text-sm text-brand-navy/60">No episodes published yet — check back soon.</p>
            @endif

            @if ($latest->isNotEmpty())
                <div class="mt-14">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Latest Episodes</h2>
                        <a href="{{ route('podcast.episodes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-gold transition hover:text-brand-navy">
                            View All Episodes <span aria-hidden="true">→</span>
                        </a>
                    </div>

                    <ul class="mt-5 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                        @foreach ($latest as $episode)
                            @include('podcast.partials.episode-row', ['episode' => $episode, 'durationLabel' => $durationLabel])
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($topics->isNotEmpty())
                <div class="mt-16">
                    <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Browse by Topics</h2>
                    <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($topics as $row)
                            <a href="{{ route('podcast.episodes.index', ['topic' => $row['category']->slug]) }}" class="group rounded-2xl border border-brand-navy/10 bg-white p-5 text-center shadow-sm transition hover:border-brand-gold/40 hover:shadow-md">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mx-auto h-6 w-6 text-brand-gold">{!! $topicIcons[$loop->index % count($topicIcons)] !!}</svg>
                                <p class="mt-3 text-sm font-semibold text-brand-navy transition group-hover:text-brand-gold">{{ $row['category']->name }}</p>
                                <p class="mt-1 text-xs text-brand-navy/50">{{ $row['count'] }} {{ Str::plural('Episode', $row['count']) }}</p>
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-6 text-center">
                        <a href="{{ route('podcast.episodes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                            View All Topics <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>

    @if ($featuredEmbedUrl)
        <div data-video-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
            <div class="relative w-full max-w-3xl">
                <button type="button" data-video-modal-close aria-label="Close video" class="absolute -top-10 right-0 text-white transition hover:text-brand-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <iframe
                    data-video-modal-iframe
                    data-src="{{ $featuredEmbedUrl }}"
                    class="aspect-video w-full rounded-lg bg-black"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    frameborder="0"
                ></iframe>
            </div>
        </div>
    @endif
</x-layouts.site>
