@php
    $thumbnailUrl = $episode->thumbnailUrl();
@endphp

<li class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center">
    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-brand-navy/10">
        @if ($thumbnailUrl)
            <img src="{{ $thumbnailUrl }}" alt="" class="h-full w-full object-cover">
        @endif
    </div>

    <a href="{{ route('podcast.episodes.show', $episode) }}" aria-label="Open {{ $episode->title }}" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-brand-gold/40 text-brand-gold transition hover:bg-brand-gold hover:text-white">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
    </a>

    <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold text-brand-gold uppercase">
            @if ($episode->episode_number)Episode {{ $episode->episode_number }} &bull; @endif{{ $episode->publish_date?->format('M j, Y') }}
        </p>
        <a href="{{ route('podcast.episodes.show', $episode) }}" class="mt-1 block font-serif text-lg text-brand-navy transition hover:text-brand-gold">{{ $episode->title }}</a>
        @if ($episode->description)
            <p class="mt-1 max-w-xl text-sm text-brand-navy/65">{{ str($episode->description)->stripTags()->limit(140) }}</p>
        @endif
        @if ($episode->tags->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($episode->tags->take(3) as $tag)
                    <span class="rounded-full border border-brand-navy/15 px-2.5 py-0.5 text-xs text-brand-navy/60">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-4 text-sm text-brand-navy/50 sm:flex-col sm:items-end sm:gap-1.5">
        @if ($durationLabel($episode->duration_seconds))
            <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $durationLabel($episode->duration_seconds) }}
            </span>
        @endif
        @if ($episode->publish_date)
            <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4" stroke-linecap="round"/></svg>
                {{ $episode->publish_date->format('M j, Y') }}
            </span>
        @endif
    </div>
</li>
