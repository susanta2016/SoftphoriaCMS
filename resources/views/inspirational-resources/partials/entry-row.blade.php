<li class="py-6">
    <p class="text-xs font-semibold tracking-wide text-brand-gold uppercase">
        {{ $submission->category }} &bull; {{ $submission->created_at->format('M j, Y') }}
    </p>
    <a href="{{ route('inspirational-resources.show', $submission) }}" class="mt-1 block font-serif text-xl text-brand-navy transition hover:text-brand-gold">
        {{ $submission->publicTitle() }}
    </a>
    <p class="mt-2 line-clamp-2 text-sm text-brand-navy/65">{{ $submission->excerpt(180) }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-brand-navy/50">
        <span>Shared by {{ $submission->name }}</span>
        <a href="{{ route('inspirational-resources.show', $submission) }}" class="ml-auto inline-flex items-center gap-1.5 font-semibold text-brand-gold transition hover:text-brand-navy">
            Read More <span aria-hidden="true">→</span>
        </a>
    </div>
</li>
