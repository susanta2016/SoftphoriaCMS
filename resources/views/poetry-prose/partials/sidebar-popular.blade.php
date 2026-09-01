@if ($popular->isNotEmpty())
    <div class="rounded-2xl border border-brand-navy/10 p-6">
        <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Popular Reads</h2>
        <div class="mt-4 space-y-4">
            @foreach ($popular as $item)
                <a href="{{ route('poetry-prose.show', $item) }}" class="group flex items-center gap-3">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-brand-navy/10">
                        @if ($item->featuredImageUrl())
                            <img src="{{ $item->featuredImageUrl() }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-brand-navy transition group-hover:text-brand-gold">{{ $item->title }}</p>
                        <p class="mt-0.5 flex items-center gap-2 text-xs text-brand-navy/50">
                            <span>{{ $item->publish_at?->format('M j, Y') }}</span>
                            <span aria-hidden="true">&bull;</span>
                            <span>{{ $item->readingTimeMinutes() }} min read</span>
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif
