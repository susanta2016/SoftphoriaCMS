<a href="{{ route('inspirational-resources.show', $submission) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white p-5 shadow-xl ring-1 ring-brand-navy/5 transition hover:ring-brand-gold/40">
    <span class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
        {{ $submission->category }} &bull; {{ $submission->created_at->format('M j, Y') }}
    </span>
    <h3 class="mt-2 font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $submission->publicTitle() }}</h3>
    <p class="mt-2 line-clamp-3 text-sm text-brand-navy/65">{{ $submission->excerpt(140) }}</p>
    <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-brand-navy/50">
        <span>Shared by {{ $submission->name }}</span>
        <span class="inline-flex items-center gap-1.5 font-semibold text-brand-gold">
            Read More <span aria-hidden="true">→</span>
        </span>
    </div>
</a>
