@php
    use App\Modules\Search\Services\SearchService;

    $typeIcons = [
        'Music' => '🎵',
        'Light Posts' => '📖',
        'Inspirational Resource' => '✨',
        'Community' => '💬',
        'Podcast' => '🎧',
    ];
@endphp

@if ($query === '')
    <p class="text-sm text-brand-navy/60">Type at least {{ SearchService::MIN_LENGTH }} characters above to search.</p>
@elseif (mb_strlen($query) < SearchService::MIN_LENGTH)
    <p class="text-sm text-brand-navy/60">Type at least {{ SearchService::MIN_LENGTH }} characters to search.</p>
@else
    <p class="text-sm font-semibold text-brand-gold">
        {{ $results->total() }} {{ Str::plural('result', $results->total()) }}
    </p>

    @if ($results->isEmpty())
        <p class="mt-10 text-center text-sm text-brand-navy/60">No results match &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <ul class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
            @foreach ($results as $result)
                <li>
                    <a href="{{ $result->url }}" class="group flex items-center gap-4 py-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-brand-ivory text-2xl" aria-hidden="true">
                            @if ($result->image)
                                <img src="{{ $result->image }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ $typeIcons[$result->type] ?? '•' }}
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $result->type }}</span>
                            <span class="mt-0.5 block truncate font-serif text-lg text-brand-navy transition group-hover:text-brand-gold">{{ $result->title }}</span>
                            @if ($result->excerpt !== '')
                                <span class="mt-0.5 block truncate text-sm text-brand-navy/60">{{ $result->excerpt }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($results->hasPages())
            <div class="mt-10">
                {{ $results->onEachSide(1)->links() }}
            </div>
        @endif
    @endif
@endif
