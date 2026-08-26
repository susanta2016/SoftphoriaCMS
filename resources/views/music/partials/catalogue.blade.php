@php
    $coverUrl = fn ($media) => $media ? \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) : null;
@endphp

<section class="mx-auto max-w-7xl px-4 pt-16 pb-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="text-xs font-semibold tracking-widest text-brand-gold uppercase">The Catalogue</span>
            <h2 class="mt-2 font-serif text-2xl text-brand-navy sm:text-3xl">Albums &amp; Singles</h2>
        </div>

        <div class="inline-flex rounded-full border border-brand-navy/15 bg-white p-1">
            @foreach (['' => 'All', 'album' => 'Albums', 'single' => 'Singles'] as $value => $label)
                <a
                    href="{{ route('music.index', array_filter(['type' => $value ?: null, 'q' => $filters['q'] ?: null, 'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null])) }}"
                    @class([
                        'rounded-full px-4 py-1.5 text-sm font-medium transition',
                        'bg-brand-navy text-white' => ($filters['type'] ?? '') === $value,
                        'text-brand-navy hover:text-brand-gold' => ($filters['type'] ?? '') !== $value,
                    ])
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('music.index') }}" data-catalogue-form class="mt-6 flex flex-wrap items-center gap-3">
        @if ($filters['type'])
            <input type="hidden" name="type" value="{{ $filters['type'] }}">
        @endif

        <label for="music-search" class="sr-only">Search music</label>
        <input
            id="music-search"
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Search music…"
            class="w-full max-w-xs rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
        >

        <select name="sort" onchange="this.form.requestSubmit()" class="rounded-md border border-brand-navy/20 px-3 py-2 text-sm text-brand-navy focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
            <option value="newest" @selected($filters['sort'] === 'newest')>Newest First</option>
            <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest First</option>
        </select>

        <button type="submit" class="rounded-md bg-brand-gold px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-gold-light">Search</button>

        @if ($filters['q'])
            <a href="{{ route('music.index', array_filter(['type' => $filters['type']])) }}" class="text-sm font-medium text-brand-navy/60 underline hover:text-brand-gold">Clear search</a>
        @endif
    </form>
</section>

<section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
    @if ($releases->isEmpty())
        <p class="py-16 text-center text-sm text-brand-navy/60">No releases to show yet.</p>
    @else
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($releases as $release)
                @php
                    $releaseRoute = $release->release_type === 'album'
                        ? route('music.albums.show', ['album' => $release->slug])
                        : route('music.singles.show', ['single' => $release->slug]);
                @endphp
                <a href="{{ $releaseRoute }}" class="group block">
                    <div class="aspect-square overflow-hidden rounded-xl bg-brand-navy/10 shadow ring-1 ring-brand-navy/5">
                        @if ($release->cover)
                            <img src="{{ $coverUrl($release->cover) }}" alt="{{ $release->title }}" class="h-full w-full object-cover transition group-hover:scale-105">
                        @else
                            <div class="flex h-full w-full items-center justify-center px-2 text-center text-xs text-brand-navy/40">{{ $release->title }}</div>
                        @endif
                    </div>
                    <span class="mt-3 block text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $release->release_type === 'album' ? 'Album' : 'Single' }}</span>
                    <h3 class="mt-1 truncate font-serif text-base text-brand-navy transition group-hover:text-brand-gold">{{ $release->title }}</h3>
                    <p class="mt-0.5 text-xs text-brand-navy/50">
                        @if ($release->release_type === 'album')
                            {{ $release->track_count }} {{ Str::plural('Track', $release->track_count) }} &bull; {{ $release->release_date?->format('Y') }}
                        @else
                            Single &bull; {{ $release->release_date?->format('Y') }}
                        @endif
                    </p>
                </a>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $releases->onEachSide(1)->links() }}
        </div>
    @endif
</section>
