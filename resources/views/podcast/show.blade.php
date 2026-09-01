@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $artworkUrl = $episode->thumbnailUrl();
    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
    $durationLabel = $episode->duration_seconds ? intdiv($episode->duration_seconds, 60).' min' : null;
    $canDownload = $episode->audio !== null;
    $userReview = auth()->check() ? $episode->reviews()->where('user_id', auth()->id())->first() : null;
@endphp

<x-layouts.site :seo="$seo">
    <div
        @class(['relative overflow-hidden bg-cover bg-[position:50%_22%] bg-no-repeat pt-28 pb-10 sm:pt-32', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}')" : ''])
    >
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Podcast', 'url' => route('podcast.index')],
                ['label' => $episode->title],
            ]"/>

            <p class="mt-4 text-xs font-semibold tracking-wide text-brand-gold uppercase">
                @if ($episode->episode_number)Episode {{ $episode->episode_number }} &bull; @endif{{ $episode->publish_date?->format('F j, Y') }}
            </p>
            <h1 class="mt-2 font-serif text-3xl text-brand-navy sm:text-4xl">{{ $episode->title }}</h1>
            @if ($episode->description)
                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-brand-navy/70">{{ str($episode->description)->stripTags()->limit(220) }}</p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-brand-navy/60">
                @if ($durationLabel)
                    <span class="inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $durationLabel }}
                    </span>
                @endif
                @if ($episode->categories->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/></svg>
                        {{ $episode->categories->first()->name }}
                    </span>
                @endif
                @if ($reviewCount > 0)
                    <span class="inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-brand-gold"><path d="m12 2 2.9 6.6 7.1.7-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7-5.4-4.7 7.1-.7Z"/></svg>
                        {{ $averageRating }} ({{ $reviewCount }} {{ Str::plural('rating', $reviewCount) }})
                    </span>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    @if (session('download_error'))
                        <p class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('download_error') }}</p>
                    @endif

                    @if ($embedUrl)
                        <div class="aspect-video overflow-hidden rounded-2xl bg-brand-navy shadow-xl ring-1 ring-brand-navy/5">
                            <iframe
                                src="{{ $embedUrl }}"
                                class="h-full w-full"
                                title="{{ $episode->title }}"
                                allow="autoplay; fullscreen; picture-in-picture"
                                allowfullscreen
                                frameborder="0"
                            ></iframe>
                        </div>
                    @elseif ($artworkUrl)
                        <div class="aspect-video overflow-hidden rounded-2xl bg-brand-navy/10 shadow-xl ring-1 ring-brand-navy/5">
                            <img src="{{ $artworkUrl }}" alt="{{ $episode->title }}" class="h-full w-full object-cover">
                        </div>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        @auth
                            @if ($canDownload)
                                <a href="{{ route('podcast.episodes.download', $episode) }}" class="inline-flex items-center gap-2 rounded-md border border-brand-navy/20 px-5 py-2.5 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M12 4v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Download Audio
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-md border border-brand-navy/20 px-5 py-2.5 text-sm font-medium text-brand-navy/60 transition hover:border-brand-gold hover:text-brand-gold" title="Log in to download">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M12 4v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Log in to Download Audio
                            </a>
                        @endauth

                        <div class="inline-flex items-center gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('podcast.episodes.show', $episode)) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('podcast.episodes.show', $episode)) }}&text={{ urlencode($episode->title) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                            </a>
                        </div>
                    </div>

                    @if ($episode->description)
                        <div class="mt-10">
                            <h2 class="font-serif text-2xl text-brand-navy">About This Episode</h2>
                            <div class="mt-4 text-sm leading-relaxed text-brand-navy/75 [&_p]:mb-4 [&_p]:last:mb-0">
                                {!! $episode->description !!}
                            </div>
                        </div>
                    @endif

                    @if ($episode->tags->isNotEmpty())
                        <div class="mt-8">
                            <h2 class="font-serif text-xl text-brand-navy">Key Themes</h2>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($episode->tags as $tag)
                                    <span class="rounded-full border border-brand-navy/15 px-3 py-1 text-xs text-brand-navy/70">{{ $tag->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-14 border-t border-brand-navy/10 pt-10">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-serif text-2xl text-brand-navy">What Listeners Are Saying</h2>
                            @if ($reviewCount > 0)
                                <span class="inline-flex items-center gap-1.5 text-sm text-brand-navy/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-brand-gold"><path d="m12 2 2.9 6.6 7.1.7-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7-5.4-4.7 7.1-.7Z"/></svg>
                                    {{ $averageRating }} average &bull; {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}
                                </span>
                            @endif
                        </div>

                        @if ($reviews->isEmpty())
                            <p class="mt-6 text-sm text-brand-navy/60">Be the first to share your thoughts.</p>
                        @else
                            <div class="mt-6 space-y-4">
                                @foreach ($reviews as $review)
                                    <div class="flex gap-3 rounded-xl border border-brand-navy/10 p-4">
                                        <img src="{{ $review->reviewerAvatarUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" @class(['h-4 w-4', 'text-brand-gold' => $i <= $review->rating, 'text-brand-navy/15' => $i > $review->rating])><path d="m12 2 2.9 6.6 7.1.7-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7-5.4-4.7 7.1-.7Z"/></svg>
                                                @endfor
                                            </div>
                                            <p class="mt-2 text-sm leading-relaxed text-brand-navy/75">{{ $review->content }}</p>
                                            <p class="mt-2 text-xs text-brand-navy/50">
                                                {{ $review->user?->name ?? 'A Member' }} &middot; {{ $review->created_at->format('M j, Y') }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-8 rounded-xl bg-brand-ivory p-5">
                            @auth
                                @if (session('review_status'))
                                    <p class="mb-4 rounded-md border border-brand-gold/30 bg-white px-4 py-3 text-sm text-brand-navy">{{ session('review_status') }}</p>
                                @endif

                                <h3 class="font-serif text-lg text-brand-navy">{{ $userReview ? 'Update Your Review' : 'Leave a Review' }}</h3>
                                <form method="POST" action="{{ route('podcast.episodes.reviews.store', $episode) }}" class="mt-4 space-y-4" data-review-form novalidate>
                                    @csrf
                                    <div>
                                        <span class="text-xs font-medium text-brand-navy/60">Your Rating</span>
                                        <div data-review-rating class="mt-1.5 flex items-center gap-1">
                                            <input type="hidden" name="rating" data-review-rating-input value="{{ old('rating', $userReview?->rating ?? '') }}">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <button type="button" data-review-star data-value="{{ $i }}" aria-label="{{ $i }} star" class="text-brand-navy/20 transition hover:text-brand-gold/70">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7"><path d="m12 2 2.9 6.6 7.1.7-5.4 4.7 1.7 7-6.3-3.8-6.3 3.8 1.7-7-5.4-4.7 7.1-.7Z"/></svg>
                                                </button>
                                            @endfor
                                        </div>
                                        <p data-review-rating-error class="mt-1 hidden text-xs text-red-600">Please select a rating from 1 to 5 stars.</p>
                                        @error('rating')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="review-content" class="text-xs font-medium text-brand-navy/60">Your Review</label>
                                        <textarea id="review-content" name="content" rows="3" maxlength="300" data-review-content-input class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none" placeholder="Share your thoughts on this episode…">{{ old('content', $userReview?->content) }}</textarea>
                                        <p data-review-content-error class="mt-1 hidden text-xs text-red-600">Please write a few words before submitting your review.</p>
                                        @error('content')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">
                                        Submit Review
                                    </button>
                                    <p class="text-xs text-brand-navy/50">
                                        @if (config('reviews.reviews_ratings_admin_approval'))
                                            Your review will appear here once approved.
                                        @endif
                                    </p>
                                </form>
                            @else
                                <p class="text-sm text-brand-navy/70">
                                    <a href="{{ route('login') }}" class="font-semibold text-brand-gold hover:text-brand-navy">Log in</a>
                                    or
                                    <a href="{{ route('register.show') }}" class="font-semibold text-brand-gold hover:text-brand-navy">register</a>
                                    to leave a rating and review.
                                </p>
                            @endauth
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-28">
                        @if ($podcast)
                            <div class="rounded-2xl bg-brand-ivory p-6 ring-1 ring-brand-navy/5">
                                <h2 class="font-serif text-lg text-brand-navy">About the Podcast</h2>
                                @if ($podcast->description)
                                    <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">{{ str($podcast->description)->stripTags()->limit(240) }}</p>
                                @endif
                                <a href="{{ route('podcast.episodes.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-brand-gold/40 px-4 py-2 text-sm font-semibold text-brand-gold transition hover:bg-brand-gold hover:text-white">
                                    View All Episodes <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        @endif

                        @if ($latest->isNotEmpty())
                            <div class="rounded-2xl border border-brand-navy/10 p-6">
                                <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Latest Episodes</h2>
                                <div class="mt-4 space-y-4">
                                    @foreach ($latest as $item)
                                        @php $itemArtworkUrl = $item->thumbnailUrl(); @endphp
                                        <a href="{{ route('podcast.episodes.show', $item) }}" class="group flex items-center gap-3">
                                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-brand-navy/10">
                                                @if ($itemArtworkUrl)
                                                    <img src="{{ $itemArtworkUrl }}" alt="" class="h-full w-full object-cover">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs text-brand-gold">{{ $item->publish_date?->format('M j, Y') }}</p>
                                                <p class="truncate text-sm font-medium text-brand-navy transition group-hover:text-brand-gold">{{ $item->title }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                <a href="{{ route('podcast.episodes.index') }}" class="mt-5 inline-flex items-center gap-1.5 rounded-md border border-brand-gold/40 px-4 py-2 text-sm font-semibold text-brand-gold transition hover:bg-brand-gold hover:text-white">
                                    Browse All Episodes <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
