@if ($recent->isNotEmpty())
    <div class="rounded-2xl border border-brand-navy/10 p-6">
        <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Recent Stories</h2>
        <div class="mt-4 space-y-4">
            @foreach ($recent as $item)
                <a href="{{ route('inspirational-resources.show', $item) }}" class="group block">
                    <p class="truncate text-sm font-medium text-brand-navy transition group-hover:text-brand-gold">{{ $item->publicTitle() }}</p>
                    <p class="mt-0.5 flex items-center gap-2 text-xs text-brand-navy/50">
                        <span>{{ $item->created_at->format('M j, Y') }}</span>
                        <span aria-hidden="true">&bull;</span>
                        <span>{{ $item->category }}</span>
                    </p>
                </a>
            @endforeach
        </div>
    </div>
@endif
