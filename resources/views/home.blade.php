@php
    $bannerUrl = $hero['media'] ? \Illuminate\Support\Facades\Storage::disk($hero['media']->disk)->url($hero['media']->path) : null;

    // Public Gratitude Journal entries (source = journal, is_public = true)
    // — reuses this existing "Latest Gratitude" display slot rather than a
    // parallel mechanism. Registration-time Light Posts are deliberately
    // excluded here (see HomeController::latestGratitudeEntries()).
    $lightPostColors = ['bg-rose-100 text-rose-700', 'bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700'];
    $comments = $gratitude->values()->map(function ($post, $index) use ($lightPostColors) {
        $name = $post->user?->name ?: 'A Member';
        $initials = collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');

        return [
            'name' => $name,
            'time' => $post->created_at->diffForHumans(),
            'quote' => $post->content,
            'initials' => $initials !== '' ? $initials : '?',
            'color' => $lightPostColors[$index % count($lightPostColors)],
        ];
    });
@endphp

<x-layouts.site :seo="$seo">
    <div class="relative isolate overflow-hidden bg-brand-navy">
        @if ($bannerUrl)
            <img
                src="{{ $bannerUrl }}"
                alt="{{ $hero['media']->alt_text ?: '' }}"
                class="absolute inset-0 z-0 h-full w-full object-cover"
            >
        @endif
        <x-site.header transparent :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative z-20 mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:grid lg:grid-cols-12 lg:gap-10 lg:px-8 lg:pt-40 lg:pb-32">
            <div class="lg:col-span-8">
                <h1 class="max-w-2xl text-[2.5rem] leading-[1.15] font-serif text-brand-navy [text-wrap:balance] sm:text-5xl">
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

                @if ($hero['tertiary_label'])
                    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
                        <button type="button" data-video-modal-toggle class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-gold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand-navy/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            {{ $hero['tertiary_label'] }}
                        </button>
                    @else
                        <a href="{{ $hero['tertiary_url'] }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-navy transition hover:text-brand-gold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-brand-navy/70">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                            {{ $hero['tertiary_label'] }}
                        </a>
                    @endif
                @endif
            </div>

            @if ($community['enabled'])
                <div class="mt-10 lg:col-span-4 lg:mt-0 lg:flex lg:h-full lg:items-end lg:justify-end lg:pb-1">
                    <div class="w-full max-w-xs rounded-2xl bg-white/95 p-6 shadow-xl">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-brand-gold text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5">
                                    <circle cx="9" cy="8" r="2.5"/>
                                    <path d="M4 18c0-2.5 2.2-4.2 5-4.2s5 1.7 5 4.2" stroke-linecap="round"/>
                                    <circle cx="17" cy="9" r="2"/>
                                    <path d="M15 13.3c1.9.4 3.5 1.8 3.5 3.9" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Join Our Community</h2>
                        </div>
                        <p class="mt-3 text-sm text-brand-navy/70">A growing space of hearts and minds united.</p>
                        <a href="{{ route('register.show') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                            Join Now <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($comments->isNotEmpty())
        <div class="relative z-10 mx-auto -mt-14 max-w-7xl px-4 sm:-mt-16 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-5 shadow-xl ring-1 ring-brand-navy/5 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-brand-navy">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 text-brand-gold">
                            <path d="M21 12a8 8 0 1 1-3.2-6.4L21 4l-1 3.5A8 8 0 0 1 21 12Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h2 class="text-xs font-semibold tracking-wider uppercase">Latest Gratitude</h2>
                    </div>
                    <a
                        href="{{ auth()->check() ? route('account.gratitude-journal.index') : route('register.show') }}"
                        class="hidden shrink-0 text-sm font-medium text-brand-gold transition hover:text-brand-navy sm:inline-block"
                    >
                        Join the conversation →
                    </a>
                </div>

                <div class="mt-5 flex items-center gap-2 sm:gap-3" data-gratitude-carousel>
                    @if ($comments->count() > 1)
                        <button
                            type="button" data-gratitude-carousel-prev aria-label="Previous gratitude"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-brand-navy/20 text-brand-navy/60 transition hover:border-brand-gold hover:text-brand-gold"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    @endif

                    <div class="min-w-0 flex-1 overflow-hidden">
                        <ul class="flex gap-4 transition-transform duration-700 ease-in-out" data-gratitude-carousel-track>
                            @foreach ($comments as $comment)
                                <li class="w-full shrink-0 rounded-xl border border-brand-navy/10 p-4 sm:w-[calc(50%-0.5rem)] lg:w-[calc(25%-0.75rem)]" data-gratitude-carousel-item>
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $comment['color'] }}" aria-hidden="true">
                                            {{ $comment['initials'] }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-brand-navy">{{ $comment['name'] }}</p>
                                            <p class="text-xs text-brand-navy/50">{{ $comment['time'] }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-sm text-brand-navy/75">&ldquo;{{ $comment['quote'] }}&rdquo;</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($comments->count() > 1)
                        <button
                            type="button" data-gratitude-carousel-next aria-label="Next gratitude"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-brand-navy/20 text-brand-navy/60 transition hover:border-brand-gold hover:text-brand-gold"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="h-7 bg-white sm:h-10" aria-hidden="true"></div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>

    @if ($hero['tertiary_video'] || $hero['tertiary_embed_url'])
        <div data-video-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
            <div class="relative w-full max-w-3xl">
                <button type="button" data-video-modal-close aria-label="Close video" class="absolute -top-10 right-0 text-white transition hover:text-brand-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                @if ($hero['tertiary_video'])
                    <video data-video-modal-player controls playsinline preload="none" class="aspect-video w-full rounded-lg bg-black">
                        <source src="{{ route('media.watch', $hero['tertiary_video']) }}" type="{{ $hero['tertiary_video']->mime_type }}">
                    </video>
                @else
                    <iframe
                        data-video-modal-iframe
                        data-src="{{ $hero['tertiary_embed_url'] }}"
                        class="aspect-video w-full rounded-lg bg-black"
                        allow="autoplay; fullscreen; picture-in-picture"
                        allowfullscreen
                        frameborder="0"
                    ></iframe>
                @endif
            </div>
        </div>
    @endif
</x-layouts.site>
