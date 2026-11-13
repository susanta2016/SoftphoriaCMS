{{--
    Public renderer for a CMS Page using PageTemplate::Inclusive (see
    PageContentRenderer). Hero = the Page's own title, summary and featured
    image. Below it, one block per enabled Featured Content section whose
    Module is Music, Poetry/Prose or Podcast, in the admin-set section
    order, each showing that module's latest published records (built by
    App\Shared\Support\Pages\InclusivePageContent — nothing is copied).

    Cards reuse each module's existing card partial and link to the
    existing detail pages only: no players, cart or download buttons, so
    every existing guest/registered-user rule stays enforced where it is.
    No pagination — each block has a View all link to the module's own
    listing page instead.
--}}
@php
    use Illuminate\Support\Facades\Storage;

    $heroImage = $page->featuredImage;
    $heroImageUrl = $heroImage ? Storage::disk($heroImage->disk)->url($heroImage->path) : null;

    // Same shared label every Podcast view passes to the episode card.
    $durationLabel = \App\Modules\Podcast\Support\EpisodeDuration::label(...);
@endphp

<x-layouts.site :seo="$seo">
    <div @class([
        'relative isolate overflow-hidden',
        'bg-brand-navy' => $heroImageUrl,
        'bg-white' => ! $heroImageUrl,
    ]) data-inclusive-hero>
        @if ($heroImageUrl)
            <img src="{{ $heroImageUrl }}" alt="{{ $heroImage->alt_text ?: '' }}" class="absolute inset-0 z-0 h-full w-full object-cover">
            <div class="absolute inset-0 z-10 bg-gradient-to-r from-white/90 via-white/70 to-white/20" aria-hidden="true"></div>
        @endif

        @if ($showChrome)
            <x-site.header :transparent="(bool) $heroImageUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>
        @endif

        <div class="relative z-20 mx-auto max-w-7xl px-4 pt-32 pb-20 sm:px-6 lg:px-8 lg:pt-40 lg:pb-28">
            {{-- Inside the hero content (below the fixed site header), so it is never hidden under the header at any width. --}}
            @unless ($page->status->value === 'published')
                <p class="mb-6 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 shadow-sm" data-preview-notice>
                    Preview only — this page is currently <strong>{{ $page->status->getLabel() }}</strong> and is not visible to the public.
                </p>
            @endunless

            <h1 class="max-w-3xl text-[2.5rem] leading-[1.15] font-serif text-brand-navy [text-wrap:balance] sm:text-5xl">
                {{ $page->title }}
            </h1>

            <div class="my-6 flex items-center gap-3" aria-hidden="true">
                <span class="h-px w-16 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
                <span class="h-px w-16 bg-brand-gold/70"></span>
            </div>

            @if ($page->summary)
                <p class="max-w-2xl text-base leading-relaxed whitespace-pre-line text-brand-navy/80 sm:text-lg">{{ $page->summary }}</p>
            @endif
        </div>
    </div>

    @forelse ($inclusiveSections as $section)
        <section
            id="inclusive-{{ str_replace('_', '-', $section['module']) }}"
            data-inclusive-section="{{ $section['module'] }}"
            @class([
                'scroll-mt-24 py-16 sm:py-20',
                'bg-white' => $loop->odd,
                'bg-brand-navy/[0.03]' => $loop->even,
            ])
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <h2 class="font-serif text-2xl text-brand-navy sm:text-3xl">{{ $section['heading'] }}</h2>
                        <span class="mt-3 block h-px w-16 bg-brand-gold/70" aria-hidden="true"></span>
                        @if ($section['subheading'])
                            <p class="mt-4 text-sm leading-relaxed whitespace-pre-line text-brand-navy/70 sm:text-base">{{ $section['subheading'] }}</p>
                        @endif
                    </div>

                    <a href="{{ $section['view_all_url'] }}" class="hidden items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy sm:inline-flex" data-inclusive-view-all>
                        {{ $section['view_all_label'] }} <span aria-hidden="true">→</span>
                    </a>
                </div>

                @switch($section['module'])
                    @case('music')
                        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($section['items'] as $release)
                                @include('music.partials.release-card', ['release' => $release, 'showFeaturedBadge' => true])
                            @endforeach
                        </div>
                        @break

                    @case('poetry_prose')
                        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($section['items'] as $entry)
                                @include('poetry-prose.partials.entry-card', ['entry' => $entry])
                            @endforeach
                        </div>
                        @break

                    @case('podcast')
                        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($section['items'] as $episode)
                                @include('podcast.partials.episode-card', ['episode' => $episode, 'durationLabel' => $durationLabel])
                            @endforeach
                        </div>
                        @break
                @endswitch

                <div class="mt-8 sm:hidden">
                    <a href="{{ $section['view_all_url'] }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-brand-gold px-5 py-3 text-sm font-semibold text-brand-gold transition hover:bg-brand-gold hover:text-white">
                        {{ $section['view_all_label'] }} <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>
    @empty
        <section class="bg-white py-20">
            <p class="mx-auto max-w-xl px-4 text-center text-sm text-brand-navy/60">New music, writing and podcasts are on their way — please check back soon.</p>
        </section>
    @endforelse

    @if ($showChrome)
        <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
    @endif
</x-layouts.site>
