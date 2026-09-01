@php
    $activeCategory = isset($filters) ? ($filters['category'] ?? null) : null;
@endphp

<div class="rounded-2xl border border-brand-navy/10 p-6">
    <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Categories</h2>
    <ul class="mt-4 space-y-1">
        <li>
            <a
                href="{{ route('poetry-prose.index') }}"
                @class([
                    'flex items-center justify-between rounded-md px-3 py-2 text-sm transition',
                    'bg-brand-gold/10 font-semibold text-brand-gold' => ! $activeCategory,
                    'text-brand-navy hover:text-brand-gold' => (bool) $activeCategory,
                ])
            >
                All Categories
                <span class="text-xs text-brand-navy/50">{{ $totalPublished }}</span>
            </a>
        </li>
        @foreach ($categories as $row)
            <li>
                <a
                    href="{{ route('poetry-prose.index', ['category' => $row['category']->slug]) }}"
                    @class([
                        'flex items-center justify-between rounded-md px-3 py-2 text-sm transition',
                        'bg-brand-gold/10 font-semibold text-brand-gold' => $activeCategory === $row['category']->slug,
                        'text-brand-navy hover:text-brand-gold' => $activeCategory !== $row['category']->slug,
                    ])
                >
                    {{ $row['category']->name }}
                    <span class="text-xs text-brand-navy/50">{{ $row['count'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
