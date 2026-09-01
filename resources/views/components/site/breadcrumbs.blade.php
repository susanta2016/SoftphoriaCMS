@props(['items'])

<nav aria-label="Breadcrumb" class="text-sm">
    <ol class="flex flex-wrap items-center gap-1.5 text-brand-navy/60">
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <span aria-hidden="true">/</span>
                @endif
                @if (! $loop->last && ($item['url'] ?? null))
                    <a href="{{ $item['url'] }}" class="transition hover:text-brand-gold">{{ $item['label'] }}</a>
                @else
                    <span class="text-brand-navy" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
