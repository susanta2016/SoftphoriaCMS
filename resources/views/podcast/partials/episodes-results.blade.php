@php
    use Illuminate\Support\Str;

    $durationLabel = fn (?int $seconds): ?string => $seconds ? intdiv($seconds, 60).' min' : null;

    $queryWithout = fn (string $key, mixed $value = null): string => request()->fullUrlWithQuery(
        $value === null ? [$key => null] : [$key => $value]
    );
@endphp

<div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm font-semibold text-brand-gold">{{ $episodes->total() }} {{ Str::plural('Episode', $episodes->total()) }}</p>

            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('podcast.episodes.index') }}" data-podcast-episodes-form>
                    @foreach (request()->except(['sort', 'page']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="sort" onchange="this.form.requestSubmit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Newest First</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest First</option>
                    </select>
                </form>

                <div class="flex overflow-hidden rounded-md border border-brand-navy/20">
                    <a href="{{ $queryWithout('view', 'list') }}" aria-label="List view" @class(['flex h-9 w-9 items-center justify-center', 'bg-brand-gold text-white' => $filters['view'] === 'list', 'text-brand-navy/60 hover:text-brand-gold' => $filters['view'] !== 'list'])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a href="{{ $queryWithout('view', 'grid') }}" aria-label="Grid view" @class(['flex h-9 w-9 items-center justify-center border-l border-brand-navy/20', 'bg-brand-gold text-white' => $filters['view'] === 'grid', 'text-brand-navy/60 hover:text-brand-gold' => $filters['view'] !== 'grid'])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </a>
                </div>
            </div>
        </div>

        @if ($episodes->isEmpty())
            <p class="mt-16 text-center text-sm text-brand-navy/60">No episodes match your filters.</p>
        @elseif ($filters['view'] === 'grid')
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                @foreach ($episodes as $episode)
                    @include('podcast.partials.episode-card', ['episode' => $episode, 'durationLabel' => $durationLabel])
                @endforeach
            </div>
        @else
            <ul class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                @foreach ($episodes as $episode)
                    @include('podcast.partials.episode-row', ['episode' => $episode, 'durationLabel' => $durationLabel])
                @endforeach
            </ul>
        @endif

        @if ($episodes->hasPages())
            <div class="mt-10">
                {{ $episodes->onEachSide(1)->links() }}
            </div>
        @endif
    </div>

    <div class="lg:col-span-4">
        <div class="space-y-6 lg:sticky lg:top-28">
            @if ($podcast)
                <div class="rounded-2xl bg-brand-ivory p-6 ring-1 ring-brand-navy/5">
                    <h2 class="font-serif text-lg text-brand-navy">About the Podcast</h2>
                    @if ($podcast->description)
                        <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">{{ str($podcast->description)->stripTags()->limit(240) }}</p>
                    @endif
                    <a href="{{ route('podcast.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-brand-gold/40 px-4 py-2 text-sm font-semibold text-brand-gold transition hover:bg-brand-gold hover:text-white">
                        About the Podcast <span aria-hidden="true">→</span>
                    </a>
                </div>
            @endif

            <div class="rounded-2xl border border-brand-navy/10 p-6">
                <h2 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">Filter Episodes</h2>
                <form method="GET" action="{{ route('podcast.episodes.index') }}" class="mt-4 space-y-4" data-podcast-episodes-form>
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="view" value="{{ $filters['view'] }}">

                    <div>
                        <label for="podcast-search" class="text-xs font-medium text-brand-navy/60">Search Episodes</label>
                        <input id="podcast-search" type="text" name="q" value="{{ $filters['search'] }}" placeholder="Search by title or keyword…" class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                    </div>

                    @if ($topics->isNotEmpty())
                        <div>
                            <label for="podcast-topic" class="text-xs font-medium text-brand-navy/60">Topic</label>
                            <select id="podcast-topic" name="topic" class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                <option value="">All Topics</option>
                                @foreach ($topics as $row)
                                    <option value="{{ $row['category']->slug }}" @selected($filters['topic'] === $row['category']->slug)>{{ $row['category']->name }} ({{ $row['count'] }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="podcast-duration" class="text-xs font-medium text-brand-navy/60">Duration</label>
                        <select id="podcast-duration" name="duration" class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                            <option value="" @selected($filters['duration'] === '')>All Durations</option>
                            <option value="short" @selected($filters['duration'] === 'short')>Under 20 min</option>
                            <option value="medium" @selected($filters['duration'] === 'medium')>20–40 min</option>
                            <option value="long" @selected($filters['duration'] === 'long')>Over 40 min</option>
                        </select>
                    </div>

                    <div>
                        <label for="podcast-release" class="text-xs font-medium text-brand-navy/60">Release Date</label>
                        <select id="podcast-release" name="release" class="mt-1.5 w-full rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                            <option value="" @selected($filters['releaseWindow'] === '')>All Time</option>
                            <option value="30d" @selected($filters['releaseWindow'] === '30d')>Last 30 Days</option>
                            <option value="6m" @selected($filters['releaseWindow'] === '6m')>Last 6 Months</option>
                            <option value="year" @selected($filters['releaseWindow'] === 'year')>This Year</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-brand-gold px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-gold-light">
                        Apply Filters
                    </button>

                    @if ($filters['search'] || $filters['topic'] || $filters['duration'] || $filters['releaseWindow'])
                        <a href="{{ route('podcast.episodes.index') }}" class="block text-center text-sm font-medium text-brand-navy/60 underline hover:text-brand-gold">Clear filters</a>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
