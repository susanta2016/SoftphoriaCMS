<a href="{{ route('inspirational-resources.show', $submission) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
    <div class="aspect-[4/3] overflow-hidden bg-brand-navy/10">
        <img src="{{ $submission->submitterAvatarUrl() }}" alt="" class="h-full w-full object-cover">
    </div>
    <div class="flex flex-1 flex-col p-5">
        <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
            {{ $submission->category }} &bull; {{ $submission->created_at->format('M j, Y') }}
        </span>
        <h3 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $submission->publicTitle() }}</h3>
        <p class="mt-2 line-clamp-2 text-sm text-brand-navy/65">{{ $submission->excerpt(110) }}</p>
        <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-brand-navy/50">
            <span>{{ $submission->readingTimeMinutes() }} min read</span>
            <span class="inline-flex items-center gap-1.5 font-semibold text-brand-gold">
                Read More <span aria-hidden="true">→</span>
            </span>
        </div>
    </div>
</a>
