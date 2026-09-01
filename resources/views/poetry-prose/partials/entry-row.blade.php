@php
    $imageUrl = $entry->featuredImageUrl();
@endphp

<li class="flex gap-5 py-6">
    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-xl bg-brand-navy/10 sm:h-32 sm:w-32">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $entry->title }}" class="h-full w-full object-cover">
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            {{ $entry->content_type->getLabel() }} &bull; {{ $entry->publish_at?->format('M j, Y') }}
        </p>
        <a href="{{ route('poetry-prose.show', $entry) }}" class="mt-1 block font-serif text-xl text-brand-navy transition hover:text-brand-gold">
            {{ $entry->title }}
        </a>
        <p class="mt-2 line-clamp-2 text-sm text-brand-navy/65">{{ $entry->excerpt(150) }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-brand-navy/50">
            <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $entry->readingTimeMinutes() }} min read
            </span>
            @if ($entry->categories->isNotEmpty())
                <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/></svg>
                    {{ $entry->categories->first()->name }}
                </span>
            @endif
            <a href="{{ route('poetry-prose.show', $entry) }}" class="ml-auto inline-flex items-center gap-1.5 font-semibold text-brand-gold transition hover:text-brand-navy">
                Read More <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</li>
