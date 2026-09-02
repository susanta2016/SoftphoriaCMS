@php
    use Illuminate\Support\Str;

    $queryWithout = fn (string $key, mixed $value = null): string => request()->fullUrlWithQuery(
        $value === null ? [$key => null] : [$key => $value]
    );
@endphp

<form method="GET" action="{{ route('inspirational-resources.index') }}" class="flex flex-wrap items-center gap-3" data-inspirational-resources-filters-form>
    <input type="hidden" name="view" value="{{ $filters['view'] }}">

    <div class="relative min-w-[220px] flex-1">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-brand-navy/40"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
        <label for="inspirational-resources-search" class="sr-only">Search stories</label>
        <input
            id="inspirational-resources-search"
            type="text"
            name="q"
            value="{{ $filters['search'] }}"
            placeholder="Search stories…"
            class="w-full rounded-md border border-brand-navy/20 py-2 pr-3 pl-9 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
        >
    </div>

    @if ($categories->isNotEmpty())
        <select name="category" onchange="this.form.requestSubmit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
            <option value="">All Categories</option>
            @foreach ($categories as $row)
                <option value="{{ $row['category'] }}" @selected($filters['category'] === $row['category'])>{{ $row['category'] }}</option>
            @endforeach
        </select>
    @endif

    <select name="sort" onchange="this.form.requestSubmit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
        <option value="newest" @selected($filters['sort'] === 'newest')>Newest First</option>
        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest First</option>
    </select>

    <button type="submit" class="sr-only">Search</button>

    <div class="flex overflow-hidden rounded-md border border-brand-navy/20">
        <a href="{{ $queryWithout('view', 'list') }}" aria-label="List view" @class(['flex h-9 w-9 items-center justify-center', 'bg-brand-gold text-white' => $filters['view'] === 'list', 'text-brand-navy/60 hover:text-brand-gold' => $filters['view'] !== 'list'])>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <a href="{{ $queryWithout('view', 'grid') }}" aria-label="Grid view" @class(['flex h-9 w-9 items-center justify-center border-l border-brand-navy/20', 'bg-brand-gold text-white' => $filters['view'] === 'grid', 'text-brand-navy/60 hover:text-brand-gold' => $filters['view'] !== 'grid'])>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </a>
    </div>

    @if ($filters['search'] || $filters['category'])
        <a href="{{ route('inspirational-resources.index') }}" class="text-sm font-medium text-brand-navy/60 underline hover:text-brand-gold">Clear filters</a>
    @endif
</form>

<div class="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <p class="text-sm font-semibold text-brand-gold">{{ $submissions->total() }} {{ Str::plural('Story', $submissions->total()) }}</p>

        @if ($submissions->isEmpty())
            <p class="mt-16 text-center text-sm text-brand-navy/60">
                @if ($filters['search'] || $filters['category'])
                    No stories match your filters.
                @else
                    No stories to show yet — check back soon.
                @endif
            </p>
        @elseif ($filters['view'] === 'grid')
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                @foreach ($submissions as $submission)
                    @include('inspirational-resources.partials.entry-card', ['submission' => $submission])
                @endforeach
            </div>
        @else
            <ul class="mt-4 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                @foreach ($submissions as $submission)
                    @include('inspirational-resources.partials.entry-row', ['submission' => $submission])
                @endforeach
            </ul>
        @endif

        @if ($submissions->hasPages())
            <div class="mt-10">
                {{ $submissions->onEachSide(1)->links() }}
            </div>
        @endif
    </div>

    <div class="lg:col-span-4">
        <div class="space-y-6 lg:sticky lg:top-28">
            @include('inspirational-resources.partials.sidebar-about')
            @include('inspirational-resources.partials.sidebar-categories')
            @include('inspirational-resources.partials.sidebar-recent')
        </div>
    </div>
</div>
