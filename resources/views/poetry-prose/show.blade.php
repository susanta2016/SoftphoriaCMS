@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
    $imageUrl = $entry->featuredImageUrl();
    $shareUrl = route('poetry-prose.show', $entry);
@endphp

<x-layouts.site :seo="$seo">
    {{--
        Plain bg-cover with a top-anchored position put the banner's
        lighthouse right behind the fixed header (confirmed by pixel-
        sampling the source image — its tip sits only ~110px into a
        793px-tall photo, leaving very little sky margin once scaled to a
        full-width, short hero strip). Zooming in past cover (300% of the
        container's own height, vs. ~187% for a plain cover fit) and
        anchoring near the right edge pushes the visible lighthouse down
        far enough to clear the header with real margin — verified
        pixel-by-pixel in-browser, not just by eye.
    --}}
    <div
        @class(['relative overflow-hidden bg-no-repeat pt-28 pb-10 sm:pt-32', 'bg-brand-ivory' => ! $bannerUrl])
        @style([$bannerUrl
            ? "background-image: linear-gradient(to right, rgba(251,243,230,.97) 0%, rgba(251,243,230,.94) 30%, rgba(251,243,230,.55) 58%, rgba(251,243,230,.15) 78%), url('{$bannerUrl}'); background-size: auto 300%; background-position: 88% 0%;"
            : ''])
    >
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Light Posts', 'url' => route('poetry-prose.index')],
                ['label' => $entry->title],
            ]"/>

            <p class="mt-4 text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $entry->content_type->getLabel() }}</p>
            <h1 class="mt-2 font-serif text-3xl text-brand-navy sm:text-4xl">{{ $entry->title }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-brand-navy/70">{{ $entry->excerpt(220) }}</p>
        </div>
    </div>

    <div class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    @if ($imageUrl)
                        <div class="aspect-video overflow-hidden rounded-2xl bg-brand-navy/10 shadow-xl ring-1 ring-brand-navy/5">
                            <img src="{{ $imageUrl }}" alt="{{ $entry->title }}" class="h-full w-full object-cover">
                        </div>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-4 text-sm text-brand-navy/60">
                            <span class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4" stroke-linecap="round"/></svg>
                                {{ $entry->publish_at?->format('F j, Y') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ $entry->readingTimeMinutes() }} min read
                            </span>
                            @if ($entry->categories->isNotEmpty())
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/></svg>
                                    {{ $entry->categories->first()->name }}
                                </span>
                            @endif
                            @if (config('features.poetry_prose_comments_enabled') && $reviewCount > 0)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M21 12a8 8 0 1 1-3.2-6.4" stroke-linecap="round"/><path d="M21 4v5h-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $reviewCount }} {{ Str::plural('comment', $reviewCount) }}
                                </span>
                            @endif
                            @if (config('features.poetry_prose_reactions_enabled') && $reactionCount > 0)
                                <span class="inline-flex items-center gap-1.5">
                                    <span aria-hidden="true">🙌</span> {{ $reactionCount }}
                                </span>
                            @endif
                        </div>

                        <div class="inline-flex items-center gap-2">
                            <span class="text-xs font-medium text-brand-navy/50">Share:</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($entry->title) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                            </a>
                        </div>
                    </div>

                    <div class="mt-8 text-base leading-relaxed text-brand-navy/80 [&_p]:mb-5 [&_p]:last:mb-0 [&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-brand-gold [&_blockquote]:pl-5 [&_blockquote]:font-serif [&_blockquote]:text-xl [&_blockquote]:text-brand-navy [&_blockquote]:italic [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-serif [&_h2]:text-2xl [&_h2]:text-brand-navy [&_a]:text-brand-gold [&_a]:underline">
                        {!! $entry->body !!}
                    </div>

                    @if ($entry->tags->isNotEmpty())
                        <div class="mt-8 flex flex-wrap gap-2 border-t border-brand-navy/10 pt-6">
                            @foreach ($entry->tags as $tag)
                                <span class="rounded-full border border-brand-navy/15 px-3 py-1 text-xs text-brand-navy/70">#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($entry->collection)
                        <div class="mt-6 rounded-2xl bg-brand-ivory p-5 ring-1 ring-brand-navy/5">
                            <span class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Part of</span>
                            <a href="{{ route('poetry-prose.index', ['collection' => $entry->collection->slug]) }}" class="mt-1 block font-serif text-lg text-brand-navy transition hover:text-brand-gold">
                                {{ $entry->collection->title }}
                            </a>
                        </div>
                    @endif

                    <div class="mt-14 border-t border-brand-navy/10 pt-10">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-serif text-2xl text-brand-navy">What Readers Are Saying</h2>
                            <div class="flex items-center gap-3">
                                @if (config('features.poetry_prose_comments_enabled') && $reviewCount > 0)
                                    <span class="text-sm text-brand-navy/60">{{ $reviewCount }} {{ Str::plural('comment', $reviewCount) }}</span>
                                @endif

                                {{-- The 🙌 reaction — independent of the comment below; a
                                    member can react without commenting, comment without
                                    reacting, both, or neither. Toggled asynchronously via
                                    resources/js/app.js (data-reaction-*); the real POST
                                    submit here is the no-JS fallback. Disabled for Poetry/
                                    Prose (config('features.poetry_prose_reactions_enabled'),
                                    client-confirmed 2026-09-04) — enforced server-side too,
                                    see PoetryProseReactionController::toggle(). --}}
                                @if (config('features.poetry_prose_reactions_enabled'))
                                    @auth
                                        <form method="POST" action="{{ route('poetry-prose.reactions.toggle', $entry) }}" data-reaction-form>
                                            @csrf
                                            <button
                                                type="submit"
                                                data-reaction-button
                                                aria-pressed="{{ $userReacted ? 'true' : 'false' }}"
                                                @class([
                                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                                    'border-brand-gold bg-brand-gold/10 text-brand-navy' => $userReacted,
                                                    'border-brand-navy/20 text-brand-navy/70 hover:border-brand-gold' => ! $userReacted,
                                                ])
                                            >
                                                <span aria-hidden="true">🙌</span> <span data-reaction-count>{{ $reactionCount }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-full border border-brand-navy/20 px-3 py-1.5 text-sm font-medium text-brand-navy/70 transition hover:border-brand-gold" title="Log in to react">
                                            <span aria-hidden="true">🙌</span> {{ $reactionCount }}
                                        </a>
                                    @endauth
                                @endif
                            </div>
                        </div>

                        @if (config('features.poetry_prose_comments_enabled'))
                            @if ($reviews->isEmpty())
                                <p class="mt-6 text-sm text-brand-navy/60">Be the first to share your thoughts.</p>
                            @else
                                <div class="mt-6 space-y-4">
                                    @foreach ($reviews as $review)
                                        <div class="flex gap-3 rounded-xl border border-brand-navy/10 p-4">
                                            <img src="{{ $review->reviewerAvatarUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm leading-relaxed text-brand-navy/75">{{ $review->content }}</p>
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

                                    <h3 class="font-serif text-lg text-brand-navy">Leave a Comment</h3>
                                    <form method="POST" action="{{ route('poetry-prose.reviews.store', $entry) }}" class="mt-4 space-y-4" data-review-form data-review-max-words="{{ config('features.poetry_prose_comment_max_words') }}" novalidate>
                                        @csrf

                                        {{--
                                            Honeypot spam trap — reuses ContactController::store()'s
                                            exact pattern (same field name, same silent-discard
                                            behavior on the server). Visually hidden off-screen
                                            (never display:none/visibility:hidden, which some bots
                                            detect and skip) and excluded from tab order.
                                        --}}
                                        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                                            <label for="poetry-prose-review-hp-website">Website</label>
                                            <input type="text" id="poetry-prose-review-hp-website" name="hp_website" tabindex="-1" autocomplete="off">
                                        </div>

                                        <div>
                                            <label for="review-content" class="text-xs font-medium text-brand-navy/60">Your Comment</label>
                                            <textarea id="review-content" name="content" rows="3" data-review-content-input class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none" placeholder="Share your thoughts on this piece… (max {{ config('features.poetry_prose_comment_max_words') }} words)">{{ old('content') }}</textarea>
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <p data-review-content-error class="hidden text-xs text-red-600">Please write a few words before submitting your comment.</p>
                                                <p data-review-word-counter class="ml-auto text-xs text-brand-navy/40">0 / {{ config('features.poetry_prose_comment_max_words') }} words</p>
                                            </div>
                                            @error('content')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">
                                            Post Comment
                                        </button>
                                        <p class="text-xs text-brand-navy/50">
                                            @if (config('reviews.reviews_ratings_admin_approval'))
                                                Your comment will appear here once approved.
                                            @endif
                                        </p>
                                    </form>
                                @else
                                    <p class="text-sm text-brand-navy/70">
                                        <a href="{{ route('login') }}" class="font-semibold text-brand-gold hover:text-brand-navy">Log in</a>
                                        or
                                        <a href="{{ route('register.show') }}" class="font-semibold text-brand-gold hover:text-brand-navy">register</a>
                                        to leave a comment.
                                    </p>
                                @endauth
                            </div>
                        @endif
                    </div>

                    @if ($previous || $next)
                        <div class="mt-12 grid grid-cols-1 gap-4 border-t border-brand-navy/10 pt-8 sm:grid-cols-2">
                            @if ($previous)
                                <a href="{{ route('poetry-prose.show', $previous) }}" class="group rounded-md border border-brand-navy/15 px-5 py-4 transition hover:border-brand-gold">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-navy/50 uppercase"><span aria-hidden="true">←</span> Previous</span>
                                    <span class="mt-1 block font-serif text-brand-navy transition group-hover:text-brand-gold">{{ $previous->title }}</span>
                                </a>
                            @else
                                <div></div>
                            @endif

                            @if ($next)
                                <a href="{{ route('poetry-prose.show', $next) }}" class="group rounded-md border border-brand-navy/15 px-5 py-4 text-right transition hover:border-brand-gold">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-navy/50 uppercase">Next <span aria-hidden="true">→</span></span>
                                    <span class="mt-1 block font-serif text-brand-navy transition group-hover:text-brand-gold">{{ $next->title }}</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-28">
                        @include('poetry-prose.partials.sidebar-about')
                        @include('poetry-prose.partials.sidebar-categories')
                        @include('poetry-prose.partials.sidebar-popular')

                        <div class="rounded-2xl border border-brand-navy/10 p-6">
                            <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Share this piece</h2>
                            <div class="mt-4 flex items-center gap-2">
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($entry->title) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
