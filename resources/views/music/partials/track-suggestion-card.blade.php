{{--
    A Music Track suggestion card — used only by the Podcast episode page's
    "You May Also Like — Music Tracks" section (App\Http\Controllers\Podcast\
    PodcastController::trackSuggestionsFor()). No standalone "Track card"
    exists elsewhere in the app (tracks normally appear as list rows), so
    this mirrors podcast.partials.episode-card's exact visual structure/
    classes rather than inventing a new design. Links to the track's own
    existing detail page (music.tracks.show — redirects to the parent Single
    when single-owned) rather than embedding a second inline player.
--}}
@php
    $trackRelease = $track->release();
    $trackCoverUrl = $trackRelease?->cover ? \Illuminate\Support\Facades\Storage::disk($trackRelease->cover->disk)->url($trackRelease->cover->path) : null;
@endphp

<a href="{{ route('music.tracks.show', $track) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
    <div class="relative aspect-video overflow-hidden bg-brand-navy/10">
        @if ($trackCoverUrl)
            <img src="{{ $trackCoverUrl }}" alt="{{ $track->title }}" class="h-full w-full object-cover">
        @endif
        <span class="absolute inset-0 flex items-center justify-center bg-brand-navy/10 transition group-hover:bg-brand-navy/30">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-brand-gold shadow-lg transition group-hover:scale-105">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>
            </span>
        </span>
    </div>
    <div class="flex flex-1 flex-col p-5">
        <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            {{ $trackRelease?->title ?? 'Music' }}
        </span>
        <h3 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $track->title }}</h3>
        @if ($track->duration_seconds)
            <div class="mt-auto flex items-center gap-4 pt-4 text-xs text-brand-navy/50">
                <span>{{ \App\Modules\Music\Support\Duration::format($track->duration_seconds) }}</span>
            </div>
        @endif
    </div>
</a>
