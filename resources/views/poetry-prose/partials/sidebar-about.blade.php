<div class="rounded-2xl bg-brand-ivory p-6 ring-1 ring-brand-navy/5">
    <h2 class="font-serif text-lg text-brand-navy">About Poetry / Prose</h2>
    <div class="mt-3 space-y-3 text-sm leading-relaxed text-brand-navy/70">
        @foreach (explode("\n\n", $aboutBody) as $paragraph)
            @if (trim($paragraph) !== '')
                <p>{{ $paragraph }}</p>
            @endif
        @endforeach
    </div>
    <a href="{{ route('inspirational-resources.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-brand-gold/40 px-4 py-2 text-sm font-semibold text-brand-gold transition hover:bg-brand-gold hover:text-white">
        {{ $submitCtaLabel }} <span aria-hidden="true">→</span>
    </a>
</div>
