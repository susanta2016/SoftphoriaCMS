<li class="flex gap-5 py-6">
    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-xl bg-brand-navy/10 sm:h-32 sm:w-32">
        <img src="{{ $submission->submitterAvatarUrl() }}" alt="" class="h-full w-full object-cover">
    </div>

    <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            {{ $submission->category }} &bull; {{ $submission->created_at->format('M j, Y') }}
        </p>
        <a href="{{ route('inspirational-resources.show', $submission) }}" class="mt-1 block font-serif text-xl text-brand-navy transition hover:text-brand-gold">
            {{ $submission->publicTitle() }}
        </a>
        <p class="mt-2 line-clamp-2 text-sm text-brand-navy/65">{{ $submission->excerpt(150) }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-brand-navy/50">
            <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $submission->readingTimeMinutes() }} min read
            </span>
            @if ($submission->category)
                <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><path d="M20.6 12.6 12 21.2 2.8 12 11.4 3.4H20.6Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5"/></svg>
                    {{ $submission->category }}
                </span>
            @endif
            <a href="{{ route('inspirational-resources.show', $submission) }}" class="ml-auto inline-flex items-center gap-1.5 font-semibold text-brand-gold transition hover:text-brand-navy">
                Read More <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</li>
