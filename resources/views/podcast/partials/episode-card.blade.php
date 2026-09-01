@php
    $thumbnailUrl = $episode->thumbnailUrl();
@endphp

<a href="{{ route('podcast.episodes.show', $episode) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
    <div class="relative aspect-video overflow-hidden bg-brand-navy/10">
        @if ($thumbnailUrl)
            <img src="{{ $thumbnailUrl }}" alt="{{ $episode->title }}" class="h-full w-full object-cover">
        @endif
        <span class="absolute inset-0 flex items-center justify-center bg-brand-navy/10 transition group-hover:bg-brand-navy/30">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-brand-gold shadow-lg transition group-hover:scale-105">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z"/></svg>
            </span>
        </span>
    </div>
    <div class="flex flex-1 flex-col p-5">
        <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            @if ($episode->episode_number)Episode {{ $episode->episode_number }} &bull; @endif{{ $episode->publish_date?->format('M j, Y') }}
        </span>
        <h3 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $episode->title }}</h3>
        @if ($episode->description)
            <p class="mt-2 text-sm text-brand-navy/65">{{ str($episode->description)->stripTags()->limit(110) }}</p>
        @endif
        <div class="mt-auto flex items-center gap-4 pt-4 text-xs text-brand-navy/50">
            @if ($durationLabel($episode->duration_seconds))
                <span>{{ $durationLabel($episode->duration_seconds) }}</span>
            @endif
            @if ($episode->categories->isNotEmpty())
                <span>{{ $episode->categories->first()->name }}</span>
            @endif
        </div>
    </div>
</a>
