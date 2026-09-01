@php
    $imageUrl = $entry->featuredImageUrl();
@endphp

<a href="{{ route('poetry-prose.show', $entry) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
    <div class="aspect-[4/3] overflow-hidden bg-brand-navy/10">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $entry->title }}" class="h-full w-full object-cover">
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            {{ $entry->content_type->getLabel() }} &bull; {{ $entry->publish_at?->format('M j, Y') }}
        </span>
        <h3 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $entry->title }}</h3>
        <p class="mt-2 line-clamp-2 text-sm text-brand-navy/65">{{ $entry->excerpt(110) }}</p>
        <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-brand-navy/50">
            <span>{{ $entry->readingTimeMinutes() }} min read</span>
            <span class="inline-flex items-center gap-1.5 font-semibold text-brand-gold">
                Read More <span aria-hidden="true">→</span>
            </span>
        </div>
    </div>
</a>
