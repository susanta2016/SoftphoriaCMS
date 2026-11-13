{{--
    One Album/Single release card — a flat row from
    App\Modules\Music\Support\ReleaseCatalogue (release_type, slug, title,
    cover, release_date, track_count). Shared by the Music catalogue grid
    (music/partials/catalogue.blade.php) and the Inclusive page's Music
    section (pages/inclusive.blade.php). Pass showFeaturedBadge => true to
    mark a release the admin flagged Featured in the Music admin.
--}}
@php
    $releaseRoute = $release->release_type === 'album'
        ? route('music.albums.show', ['album' => $release->slug])
        : route('music.singles.show', ['single' => $release->slug]);
    $releaseCoverUrl = $release->cover ? \Illuminate\Support\Facades\Storage::disk($release->cover->disk)->url($release->cover->path) : null;
@endphp
<a href="{{ $releaseRoute }}" class="group block">
    <div class="relative aspect-square overflow-hidden rounded-xl bg-brand-navy/10 shadow ring-1 ring-brand-navy/5">
        @if ($releaseCoverUrl)
            <img src="{{ $releaseCoverUrl }}" alt="{{ $release->title }}" class="h-full w-full object-cover transition group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center px-2 text-center text-xs text-brand-navy/40">{{ $release->title }}</div>
        @endif
        @if (($showFeaturedBadge ?? false) && $release->is_featured)
            <span class="absolute top-3 left-3 rounded-full bg-brand-gold px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide text-white uppercase shadow">Featured</span>
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
