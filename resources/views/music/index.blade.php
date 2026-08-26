@php
    use App\Modules\Music\Support\Duration;
    use Illuminate\Support\Facades\Storage;

    $heroImageUrl = $hero['media'] ? Storage::disk($hero['media']->disk)->url($hero['media']->path) : null;

    $coverUrl = fn ($media) => $media ? Storage::disk($media->disk)->url($media->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    <div @class([
        'relative isolate overflow-hidden',
        'bg-brand-navy' => $heroImageUrl,
        'bg-white' => ! $heroImageUrl,
    ])>
        @if ($heroImageUrl)
            <img src="{{ $heroImageUrl }}" alt="{{ $hero['media']->alt_text ?: '' }}" class="absolute inset-0 z-0 h-full w-full object-cover">
        @endif
        <x-site.header :transparent="(bool) $heroImageUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative z-20 mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:px-8 lg:pt-40 lg:pb-32">
            <span class="text-xs font-semibold tracking-widest text-brand-gold uppercase">Music</span>
            <h1 class="mt-3 max-w-2xl text-[2.5rem] leading-[1.15] font-serif text-brand-navy [text-wrap:balance] sm:text-5xl">
                {{ $hero['heading'] }}
            </h1>

            <div class="my-6 flex items-center gap-3" aria-hidden="true">
                <span class="h-px w-16 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
                <span class="h-px w-16 bg-brand-gold/70"></span>
            </div>

            <p class="max-w-xl text-base leading-relaxed whitespace-pre-line text-brand-navy/80 sm:text-lg">{{ $hero['subheading'] }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-4">
                <a href="{{ $hero['cta_url'] }}" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3.5 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                    {{ $hero['cta_label'] }}
                </a>

                @if ($hero['secondary_cta_label'])
                    <a href="{{ $hero['secondary_cta_url'] }}" class="inline-flex items-center rounded-md border border-brand-navy/40 px-6 py-3.5 text-sm font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-navy hover:bg-white/40">
                        {{ $hero['secondary_cta_label'] }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white">
        @if ($featured)
            @php
                $featuredTracks = $featured instanceof \App\Modules\Music\Models\Album ? $featured->tracks : ($featured->track ? collect([$featured->track]) : collect());
                $featuredType = $featured instanceof \App\Modules\Music\Models\Album ? 'Album' : 'Single';
                $featuredRoute = $featured instanceof \App\Modules\Music\Models\Album ? route('music.albums.show', $featured) : route('music.singles.show', $featured);
            @endphp
            <section id="featured-release" class="mx-auto max-w-7xl scroll-mt-24 px-4 pt-16 sm:px-6 lg:px-8">
                <span class="text-xs font-semibold tracking-widest text-brand-gold uppercase">Featured Release</span>

                <div class="mt-5 grid grid-cols-1 gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <a href="{{ $featuredRoute }}" class="block overflow-hidden rounded-xl shadow-xl ring-1 ring-brand-navy/5">
                            @if ($featured->cover)
                                <img src="{{ $coverUrl($featured->cover) }}" alt="{{ $featured->title }}" class="aspect-square w-full object-cover">
                            @else
                                <div class="flex aspect-square w-full items-center justify-center bg-brand-navy/10 text-brand-navy/40">{{ $featured->title }}</div>
                            @endif
                        </a>

                        @if ($featured->streamingLinks->isNotEmpty())
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($featured->streamingLinks as $link)
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-brand-navy/15 bg-white px-3 py-2 text-xs font-semibold text-brand-navy transition hover:border-brand-gold">
                                        {{ $link->provider->getLabel() }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="lg:col-span-8">
                        <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
                            {{ $featuredType }} &bull; Released {{ $featured->release_date?->format('F Y') }}
                        </span>
                        <h2 class="mt-2 font-serif text-3xl text-brand-navy">{{ $featured->title }}</h2>

                        @if ($featured->description)
                            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-brand-navy/75">{{ str($featured->description)->stripTags() }}</p>
                        @endif

                        @if ($featuredTracks->isNotEmpty())
                            <ol class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                                @foreach ($featuredTracks as $track)
                                    @php
                                        $trackRoute = $featured instanceof \App\Modules\Music\Models\Album ? route('music.tracks.show', $track) : $featuredRoute;
                                    @endphp
                                    <li class="flex items-center gap-4 py-3 text-sm">
                                        <span class="w-5 shrink-0 text-brand-navy/40">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <a href="{{ $trackRoute }}" class="flex-1 truncate text-brand-navy transition hover:text-brand-gold">{{ $track->title }}</a>
                                        @if ($track->lyrics && $track->lyrics->visibility === 'public')
                                            <a href="{{ $trackRoute }}" class="shrink-0 text-xs font-semibold tracking-wide text-brand-gold/70 uppercase hover:text-brand-gold">Lyrics</a>
                                        @endif
                                        <span class="w-12 shrink-0 text-right text-brand-navy/50 tabular-nums">{{ Duration::format($track->duration_seconds) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                            <a href="{{ $featuredRoute }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                                View Listening Page <span aria-hidden="true">→</span>
                            </a>

                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Share</span>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($featuredRoute) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($featuredRoute) }}&text={{ urlencode($featured->title) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <div id="catalogue" data-catalogue-region>
            @include('music.partials.catalogue', ['releases' => $releases, 'filters' => $filters])
        </div>

        @if ($songStories->isNotEmpty())
            @php
                $storyBannerUrl = $storyBanner['media'] ? Storage::disk($storyBanner['media']->disk)->url($storyBanner['media']->path) : null;
            @endphp
            <section
                id="song-stories"
                class="scroll-mt-24 bg-brand-navy bg-cover bg-center bg-no-repeat py-16"
                @style([$storyBannerUrl ? "background-image: url('{$storyBannerUrl}')" : ''])
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <span class="text-xs font-semibold tracking-widest text-brand-gold uppercase">Song Stories</span>
                    <h2 class="mt-2 max-w-xl font-serif text-2xl text-white sm:text-3xl">{{ $storyBanner['heading'] ?: 'Where the songs came from' }}</h2>
                    <p class="mt-3 max-w-xl text-sm text-white/70">{{ $storyBanner['subheading'] ?: 'The moments, memories, and quiet mornings that became these songs.' }}</p>

                    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                        @foreach ($songStories as $story)
                            @php
                                $release = $story->track->album ?: $story->track->single;
                                $storyRoute = $story->track->album
                                    ? route('music.albums.show', $story->track->album)
                                    : route('music.singles.show', $story->track->single);
                            @endphp
                            <a href="{{ $storyRoute }}" class="block rounded-xl bg-white/95 p-5 shadow-xl transition hover:bg-white">
                                <h3 class="font-serif text-lg text-brand-navy">{{ $story->track->title }}</h3>
                                <p class="mt-2 line-clamp-3 text-sm text-brand-navy/70">{{ str($story->content)->stripTags() }}</p>
                                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold">Read the story <span aria-hidden="true">→</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
