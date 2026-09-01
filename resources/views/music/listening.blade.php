@php
    use App\Modules\Music\Support\Duration;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $coverUrl = $release['cover'] ? Storage::disk($release['cover']->disk)->url($release['cover']->path) : null;
    $topBannerUrl = $topBanner ? Storage::disk($topBanner->disk)->url($topBanner->path) : null;
    $isSingleTrack = in_array($release['type'], ['single', 'track'], true);
    $onlyTrack = $isSingleTrack ? $release['tracks']->first() : null;
    $userReview = ($isSingleTrack && $onlyTrack && auth()->check())
        ? $onlyTrack->reviews()->where('user_id', auth()->id())->first()
        : null;

    $genres = $release['tracks']->flatMap(fn ($track) => $track->categories->pluck('name'))->unique()->values();

    // Native playback only — see App\Http\Controllers\Music\TrackStreamController.
    // A track without its own uploaded audio file has no playback source at
    // all; external MusicStreamingLink URLs (still stored/admin-editable)
    // are never used as the <audio> source. Guests never get a src once
    // today's registered daily quota is reached for them (n/a — quota only
    // applies to authenticated users); a registered user who has already
    // reached today's quota gets no src and a distinct "come back tomorrow"
    // message instead of "audio unavailable".
    $trackPlayback = function (?\App\Modules\Music\Models\Track $track) use ($release) {
        if (! $track || ! $track->audio_media_id) {
            return ['src' => null, 'guest_limited' => false, 'limit_reached' => false, 'complete_url' => null];
        }

        $listening = $release['listening'];

        if (! $listening['is_guest'] && $listening['daily_limit_reached']) {
            return ['src' => null, 'guest_limited' => false, 'limit_reached' => true, 'complete_url' => null];
        }

        $guestLimited = $listening['is_guest']
            && $track->duration_seconds
            && $track->duration_seconds > $listening['guest_limit_seconds'];

        return [
            'src' => route('music.tracks.stream', $track),
            'guest_limited' => $guestLimited,
            'limit_reached' => false,
            'complete_url' => $listening['is_guest'] ? null : route('music.tracks.listen-complete', $track),
        ];
    };

    $embedUrl = null;
    if ($release['embed_video_url']) {
        if (preg_match('#youtu\.be/([\w-]+)#', $release['embed_video_url'], $m) || preg_match('#youtube\.com/(?:watch\?v=|embed/|shorts/)([\w-]+)#', $release['embed_video_url'], $m)) {
            $embedUrl = "https://www.youtube.com/embed/{$m[1]}?rel=0";
        }
    }
@endphp

<x-layouts.site :seo="$seo">
    <x-site.header :transparent="(bool) $topBannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    @if (session('cart_added'))
        <div data-cart-added-modal class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl">
                <p class="text-sm text-brand-navy/80">{{ session('cart_added') }}</p>
                <p class="mt-1 text-sm font-semibold text-brand-navy">Go to checkout now?</p>
                <div class="mt-5 flex items-center justify-center gap-3">
                    <a href="{{ route('checkout.show') }}" class="inline-flex items-center rounded-md bg-brand-gold px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">Yes</a>
                    <button type="button" data-cart-added-modal-close class="inline-flex items-center rounded-md border border-brand-navy/20 px-5 py-2.5 text-sm font-semibold text-brand-navy transition hover:border-brand-gold">No</button>
                </div>
            </div>
        </div>
    @endif

    <div
        class="bg-white bg-cover bg-center bg-no-repeat pt-28 pb-14"
        @style([$topBannerUrl ? "background-image: linear-gradient(to right, rgba(255,255,255,.85), rgba(255,255,255,.55)), url('{$topBannerUrl}')" : ''])
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <a href="{{ $release['parent_route'] ?? route('music.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-gold transition hover:text-brand-navy">
                <span aria-hidden="true">←</span> Back to {{ $release['parent_label'] ?? 'Music' }}
            </a>

            <div class="mt-6 grid grid-cols-1 gap-10 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div class="aspect-square overflow-hidden rounded-2xl shadow-xl ring-1 ring-brand-navy/5">
                        @if ($coverUrl)
                            <img src="{{ $coverUrl }}" alt="{{ $release['title'] }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-brand-navy/10 text-brand-navy/40">{{ $release['title'] }}</div>
                        @endif
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
                        {{ ucfirst($release['type']) }} &bull; Released {{ $release['release_date']?->format('F Y') }}
                    </span>
                    <h1 class="mt-2 font-serif text-4xl text-brand-navy">{{ $release['title'] }}</h1>

                    @if ($release['parent_label'])
                        <p class="mt-1 text-sm text-brand-navy/60">
                            From the album
                            <a href="{{ $release['parent_route'] }}" class="font-semibold text-brand-gold hover:text-brand-navy">{{ $release['parent_label'] }}</a>
                        </p>
                    @endif

                    @if ($release['description'])
                        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-brand-navy/75">{{ str($release['description'])->stripTags() }}</p>
                    @endif

                    @php
                        $purchaseIsForParent = $release['purchase'] === null && $release['parent_purchase'] !== null;
                        $purchase = $purchaseIsForParent ? $release['parent_purchase'] : $release['purchase'];
                        $purchaseType = $purchaseIsForParent ? $release['parent_purchase_type'] : $release['purchase_type'];
                        $purchaseSlug = $purchaseIsForParent ? $release['parent_purchase_slug'] : $release['purchase_slug'];
                    @endphp

                    @if (session('cart_notice'))
                        <p class="mt-4 rounded-md border border-brand-gold/30 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">{{ session('cart_notice') }}</p>
                    @endif

                    @if (session('cart_error') || session('download_error'))
                        <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('cart_error') ?? session('download_error') }}</p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        @if ($release['stream_track'])
                            <button
                                type="button"
                                data-music-player-play
                                class="inline-flex items-center gap-2 rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light"
                            >
                                <svg data-music-player-play-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                                <svg data-music-player-pause-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="hidden h-3.5 w-3.5"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                                Play Now
                            </button>
                        @endif

                        <button type="button" data-music-save-toggle data-music-save-id="{{ $release['type'] }}-{{ $release['model']->public_id }}" aria-pressed="false" class="inline-flex items-center gap-2 rounded-md border border-brand-navy/20 px-5 py-3 text-sm font-medium text-brand-navy transition hover:border-brand-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3Zm-4.4 15.55-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05Z"/></svg>
                            Save
                        </button>

                        <div class="inline-flex items-center gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($release['show_route']) }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode($release['show_route']) }}&text={{ urlencode($release['title']) }}" target="_blank" rel="noopener" aria-label="Share on X" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M18.9 3H21l-6.6 7.5L22 21h-6.8l-4.7-6.2L5 21H3l7.1-8-8-10h6.9l4.3 5.7L18.9 3Z"/></svg>
                            </a>
                        </div>
                    </div>

                    @if ($purchase && $purchase['state'] !== 'not_ready')
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            @if ($purchase['state'] === 'buy')
                                <form method="POST" action="{{ route('cart.add') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $purchaseType }}">
                                    <input type="hidden" name="slug" value="{{ $purchaseSlug }}">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-navy px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-navy/90">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M3 4h2l1.6 9.6a2 2 0 0 0 2 1.7h8a2 2 0 0 0 2-1.7L20 8H6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="16.5" cy="19.5" r="1.3"/></svg>
                                        {{ $purchaseType === 'track' ? 'Purchase Track' : ($purchaseIsForParent ? 'Buy the Album' : 'Buy') }} — ${{ number_format($purchase['price'], 2) }}
                                    </button>
                                </form>
                            @elseif ($purchase['state'] === 'owned')
                                <span class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-5 py-3 text-sm font-semibold text-brand-navy">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $purchaseIsForParent ? 'You own this album' : 'You own this' }}
                                </span>
                            @elseif ($purchase['state'] === 'included')
                                @if ($isSingleTrack && $release['stream_track']?->audio_media_id)
                                    <a href="{{ route('music.tracks.download', $release['stream_track']) }}" class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-5 py-3 text-sm font-semibold text-brand-navy transition hover:bg-brand-gold/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M12 4v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Download
                                    </a>
                                @elseif (! $isSingleTrack && $release['tracks']->contains(fn ($track) => $track->audio_media_id !== null))
                                    @php
                                        $downloadableTrackUrls = $release['tracks']
                                            ->filter(fn ($track) => $track->audio_media_id !== null)
                                            ->map(fn ($track) => route('music.tracks.download', $track))
                                            ->values();
                                    @endphp
                                    <button
                                        type="button"
                                        data-music-download-all
                                        data-music-download-urls="{{ $downloadableTrackUrls->toJson(JSON_UNESCAPED_SLASHES) }}"
                                        class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-5 py-3 text-sm font-semibold text-brand-navy transition hover:bg-brand-gold/20 disabled:opacity-60"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M12 4v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span data-music-download-label>Download</span>
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-5 py-3 text-sm font-semibold text-brand-navy">
                                        Included with your Pro Membership
                                    </span>
                                @endif
                            @endif
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <div class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="font-serif text-2xl text-brand-navy">{{ $isSingleTrack ? 'About the Song' : 'About the Album' }}</h2>

                    @php
                        $aboutDescription = $isSingleTrack ? ($onlyTrack?->description ?: $release['description']) : $release['description'];
                    @endphp
                    @if ($aboutDescription)
                        <p class="mt-4 text-sm leading-relaxed text-brand-navy/75">{{ str($aboutDescription)->stripTags() }}</p>
                    @endif

                    @php
                        $propertyIcon = fn (string $path) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4 shrink-0 text-brand-gold">'.$path.'</svg>';
                        $icons = [
                            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4" stroke-linecap="round"/>',
                            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/>',
                            'note' => '<path d="M9 18V5l11-2v13" stroke-linecap="round" stroke-linejoin="round"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
                            'pen' => '<path d="M12 20h9" stroke-linecap="round"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" stroke-linecap="round" stroke-linejoin="round"/>',
                            'mic' => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v4M9 21h6" stroke-linecap="round" stroke-linejoin="round"/>',
                            'tag' => '<path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/>',
                            'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" stroke-linejoin="round"/>',
                        ];
                    @endphp

                    <dl class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10 text-sm">
                        <div class="flex items-center justify-between py-3">
                            <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['calendar']) !!} Release Date</dt>
                            <dd class="font-medium text-brand-navy">{{ $release['release_date']?->format('F Y') }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['clock']) !!} Length</dt>
                            <dd class="font-medium text-brand-navy">{{ Duration::format($release['total_duration_seconds']) }}</dd>
                        </div>
                        @if ($genres->isNotEmpty())
                            <div class="flex items-center justify-between py-3">
                                <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['note']) !!} Genre</dt>
                                <dd class="font-medium text-brand-navy">{{ $genres->join(' · ') }}</dd>
                            </div>
                        @endif
                        @if ($isSingleTrack && $onlyTrack?->written_by)
                            <div class="flex items-center justify-between py-3">
                                <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['pen']) !!} Written by</dt>
                                <dd class="font-medium text-brand-navy">{{ $onlyTrack->written_by }}</dd>
                            </div>
                        @endif
                        @if ($isSingleTrack && $onlyTrack?->produced_by)
                            <div class="flex items-center justify-between py-3">
                                <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['mic']) !!} Produced by</dt>
                                <dd class="font-medium text-brand-navy">{{ $onlyTrack->produced_by }}</dd>
                            </div>
                        @endif
                        @if ($isSingleTrack && $onlyTrack?->isrc)
                            <div class="flex items-center justify-between py-3">
                                <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['tag']) !!} ISRC</dt>
                                <dd class="font-medium text-brand-navy">{{ $onlyTrack->isrc }}</dd>
                            </div>
                        @endif
                        @if (! $isSingleTrack)
                            <div class="flex items-center justify-between py-3">
                                <dt class="flex items-center gap-2.5 text-brand-navy/60">{!! $propertyIcon($icons['list']) !!} Tracks</dt>
                                <dd class="font-medium text-brand-navy">{{ $release['tracks']->count() }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($isSingleTrack && $onlyTrack?->lyrics && $onlyTrack->lyrics->visibility === 'public')
                    <div>
                        <h2 class="font-serif text-2xl text-brand-navy">Lyrics</h2>
                        <div class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-brand-navy/10 bg-white p-5 text-sm leading-relaxed whitespace-pre-line text-brand-navy/80">
                            {{ $onlyTrack->lyrics->content }}
                        </div>
                    </div>
                @endif
            </div>

            @if ($isSingleTrack && $release['stream_track'])
                @php $heroPlayback = $trackPlayback($release['stream_track']); @endphp
                <div
                    data-music-track-row
                    data-music-track-active="1"
                    data-music-track-title="{{ $release['stream_track']->title }}"
                    @if ($heroPlayback['src']) data-music-track-src="{{ $heroPlayback['src'] }}" @endif
                    @if ($heroPlayback['complete_url']) data-music-track-complete-url="{{ $heroPlayback['complete_url'] }}" @endif
                    @if ($heroPlayback['guest_limited']) data-music-track-guest-limited="1" @endif
                    @if ($heroPlayback['limit_reached']) data-music-track-limit-reached="1" @endif
                    class="hidden"
                ></div>
            @endif

            @if ($release['stream_track'])
                <div data-music-player class="mx-auto mt-10 w-4/5 rounded-xl border border-brand-navy/10 bg-[#fff6ec] p-4 sm:p-5">
                    <audio data-music-player-audio preload="none"></audio>

                    <div class="grid grid-cols-[1fr_auto] items-center gap-x-2 gap-y-4 lg:grid-cols-[1fr_auto_1fr] lg:gap-x-4">
                        <div class="flex min-w-0 items-center gap-2 lg:gap-3">
                            <div class="flex min-w-0 flex-1 items-center gap-2 lg:w-52 lg:flex-none lg:gap-3">
                                @if ($coverUrl)
                                    <img src="{{ $coverUrl }}" alt="" class="h-10 w-10 shrink-0 rounded-md object-cover lg:h-12 lg:w-12">
                                @endif
                                <p data-music-player-title class="min-w-0 flex-1 truncate text-sm font-semibold text-brand-navy">{{ $release['stream_track']->title }}</p>
                            </div>
                            <button type="button" data-music-save-toggle data-music-save-id="{{ $release['type'] }}-{{ $release['model']->public_id }}" aria-pressed="false" aria-label="Save" class="shrink-0 text-brand-navy/60 transition hover:text-brand-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 lg:h-6 lg:w-6"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3Zm-4.4 15.55-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05Z"/></svg>
                            </button>
                        </div>

                        <div data-music-player-controls class="flex items-center gap-1.5 justify-self-center lg:gap-3">
                            @if (! $isSingleTrack)
                                <button type="button" data-music-player-prev aria-label="Previous track" class="text-brand-navy/60 transition hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M5 5h2v14H5zM18 5v14l-9-7z"/></svg>
                                </button>
                            @endif
                            <button type="button" data-music-player-play aria-label="Play/pause" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-brand-gold text-white transition hover:bg-brand-gold-light lg:h-14 lg:w-14">
                                <svg data-music-player-play-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 lg:h-7 lg:w-7"><path d="M8 5v14l11-7z"/></svg>
                                <svg data-music-player-pause-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="hidden h-5 w-5 lg:h-7 lg:w-7"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                            </button>
                            @if (! $isSingleTrack)
                                <button type="button" data-music-player-next aria-label="Next track" class="text-brand-navy/60 transition hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M6 5v14l9-7zM17 5h2v14h-2z"/></svg>
                                </button>
                            @endif
                        </div>

                        <div data-music-player-controls class="col-span-2 flex items-center justify-end gap-3 lg:col-span-1 lg:justify-self-end">
                            <p data-music-player-time class="shrink-0 text-xs text-brand-navy/50 tabular-nums lg:mr-[60px]">0:00 / {{ Duration::format($release['stream_track']->duration_seconds) }}</p>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0 text-brand-navy/60"><path d="M3 10v4h4l5 4V6L7 10H3Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M16.5 8.5a5 5 0 0 1 0 7" stroke-linecap="round"/></svg>
                            <input type="range" data-music-player-volume min="0" max="100" value="100" aria-label="Volume" class="h-1 w-20 shrink-0 accent-brand-gold">
                        </div>
                    </div>

                    <div data-music-player-controls class="mt-4">
                        <input type="range" data-music-player-seek min="0" max="100" value="0" class="h-1 w-full accent-brand-gold lg:ml-[220px] lg:w-1/2">
                    </div>

                    <p data-music-player-fallback class="mt-4 hidden text-sm text-brand-navy/70">
                        Audio unavailable for this track.
                    </p>
                    <p data-music-player-guest-ended class="mt-4 hidden rounded-md border border-brand-gold/30 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                        You've reached the {{ $release['listening']['guest_limit_seconds'] }}-second preview limit. <a href="{{ route('register.show') }}" class="font-semibold text-brand-gold hover:text-brand-navy">Register</a> or <a href="{{ route('login') }}" class="font-semibold text-brand-gold hover:text-brand-navy">log in</a> to keep listening.
                    </p>
                    <p data-music-player-limit-reached class="mt-4 hidden rounded-md border border-brand-gold/30 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                        You've reached your {{ $release['listening']['daily_limit'] }} {{ \Illuminate\Support\Str::plural('listen', $release['listening']['daily_limit']) }} for today. Please come back tomorrow.
                    </p>
                </div>
            @endif

            @if ($isSingleTrack && ($onlyTrack?->songStory || $embedUrl))
                <div class="mt-14 grid grid-cols-1 gap-10 lg:grid-cols-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-serif text-2xl text-brand-navy">Song Story</h2>
                            @if ($embedUrl)
                                <button type="button" data-video-modal-toggle aria-label="Watch video" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-brand-navy/15 text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><rect x="2.5" y="5.5" width="14" height="13" rx="2"/><path d="M16.5 10.5 21 7.5v9l-4.5-3Z" stroke-linejoin="round"/></svg>
                                </button>
                            @endif
                        </div>
                        @if ($onlyTrack?->songStory)
                            <p class="mt-4 text-sm leading-relaxed text-brand-navy/75">{{ $onlyTrack->songStory->content }}</p>
                        @endif
                    </div>

                    @if ($onlyTrack->credits->isNotEmpty())
                        <div>
                            <h2 class="font-serif text-2xl text-brand-navy">Credits</h2>
                            <dl class="mt-4 divide-y divide-brand-navy/10 border-y border-brand-navy/10 text-sm">
                                @foreach ($onlyTrack->credits as $credit)
                                    <div class="flex items-center justify-between py-3">
                                        <dt class="text-brand-navy/60">{{ $credit->role }}</dt>
                                        <dd class="font-medium text-brand-navy">{{ $credit->name }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            @endif

            @if ($isSingleTrack && $onlyTrack)
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
                            <form method="POST" action="{{ route('music.tracks.reviews.store', $onlyTrack) }}" class="mt-4 space-y-4" data-review-form novalidate>
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
                                    <textarea id="review-content" name="content" rows="3" maxlength="{{ config('reviews.max_length') }}" data-review-content-input class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none" placeholder="Share your thoughts on this song…">{{ old('content', $userReview?->content) }}</textarea>
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
            @endif

            @if (! $isSingleTrack && $release['tracks']->isNotEmpty())
                <div class="mt-14">
                    <h2 class="font-serif text-2xl text-brand-navy">Track List</h2>
                    <ol class="mt-4 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                        @foreach ($release['tracks'] as $track)
                            @php $rowPlayback = $trackPlayback($track); @endphp
                            <li
                                data-music-track-row
                                data-music-track-title="{{ $track->title }}"
                                @if ($rowPlayback['src']) data-music-track-src="{{ $rowPlayback['src'] }}" @endif
                                @if ($rowPlayback['complete_url']) data-music-track-complete-url="{{ $rowPlayback['complete_url'] }}" @endif
                                @if ($rowPlayback['guest_limited']) data-music-track-guest-limited="1" @endif
                                @if ($rowPlayback['limit_reached']) data-music-track-limit-reached="1" @endif
                                @if ($loop->first) data-music-track-active="1" @endif
                                class="flex items-center gap-4 py-3 text-sm transition"
                            >
                                @if ($track->audio_media_id)
                                    <button type="button" data-music-track-play aria-label="Play {{ $track->title }}" class="shrink-0 text-brand-navy/50 transition hover:text-brand-gold">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>
                                    </button>
                                @else
                                    <span class="w-5 shrink-0 text-brand-navy/40">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                @endif
                                <a href="{{ route('music.tracks.show', $track) }}" class="flex-1 truncate text-brand-navy transition hover:text-brand-gold">{{ $track->title }}</a>
                                @if ($track->lyrics && $track->lyrics->visibility === 'public')
                                    <a href="{{ route('music.tracks.show', $track) }}" class="shrink-0 text-xs font-semibold tracking-wide text-brand-gold/70 uppercase hover:text-brand-gold">Lyrics</a>
                                @endif
                                <span class="w-12 shrink-0 text-right text-brand-navy/50 tabular-nums">{{ Duration::format($track->duration_seconds) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($related->isNotEmpty())
                <div class="mt-16">
                    <h2 class="font-serif text-2xl text-brand-navy">You may also like</h2>
                    <div class="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
                        @foreach ($related as $item)
                            @php
                                $itemCoverUrl = $item->cover ? Storage::disk($item->cover->disk)->url($item->cover->path) : null;
                                $itemRoute = $item->release_type === 'album'
                                    ? route('music.albums.show', ['album' => $item->slug])
                                    : route('music.singles.show', ['single' => $item->slug]);
                            @endphp
                            <a href="{{ $itemRoute }}" class="group block">
                                <div class="aspect-square overflow-hidden rounded-xl bg-brand-navy/10 shadow ring-1 ring-brand-navy/5">
                                    @if ($itemCoverUrl)
                                        <img src="{{ $itemCoverUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover transition group-hover:scale-105">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center px-2 text-center text-xs text-brand-navy/40">{{ $item->title }}</div>
                                    @endif
                                </div>
                                <span class="mt-3 block text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $item->release_type === 'album' ? 'Album' : 'Single' }}</span>
                                <h3 class="mt-1 truncate font-serif text-base text-brand-navy transition group-hover:text-brand-gold">{{ $item->title }}</h3>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>

    @if ($embedUrl)
        <div data-video-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4">
            <div class="relative w-full max-w-3xl">
                <button type="button" data-video-modal-close aria-label="Close video" class="absolute -top-10 right-0 text-white transition hover:text-brand-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <iframe
                    data-video-modal-iframe
                    data-src="{{ $embedUrl }}"
                    class="aspect-video w-full rounded-lg bg-black"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    frameborder="0"
                ></iframe>
            </div>
        </div>
    @endif
</x-layouts.site>
