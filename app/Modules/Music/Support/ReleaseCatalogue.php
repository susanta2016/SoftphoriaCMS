<?php

namespace App\Modules\Music\Support;

use App\Models\Media;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The public Album + Single release catalogue — one UNION ALL of published
 * Albums and published Singles as flat rows (release_type, public_id, title,
 * slug, cover_media_id, release_date, is_featured, track_count).
 *
 * Extracted unchanged from MusicController::paginatedCatalogue() so the
 * Music landing page's catalogue and the Inclusive CMS page's Music section
 * share the exact same published-release filter, row shape and ordering
 * rather than two copies drifting apart. Only Albums/Singles — never
 * individual Tracks — are catalogue entries.
 */
class ReleaseCatalogue
{
    /**
     * Published releases, newest release_date first unless $sort is
     * 'oldest' (ties broken by id, newest first — same as the catalogue
     * always has been).
     *
     * @param  'album'|'single'|null  $type
     */
    public function query(?string $type = null, string $search = '', string $sort = 'newest'): Builder
    {
        $albumsQuery = Album::query()->published()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->select([
                'id', DB::raw("'album' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date', 'is_featured',
                DB::raw("(select count(*) from tracks where tracks.album_id = albums.id and tracks.status = 'published') as track_count"),
            ]);

        $singlesQuery = Single::query()->published()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->select([
                'id', DB::raw("'single' as release_type"), 'public_id', 'title', 'slug',
                'cover_media_id', 'release_date', 'is_featured',
                DB::raw('1 as track_count'),
            ]);

        $union = match ($type) {
            'album' => $albumsQuery->toBase(),
            'single' => $singlesQuery->toBase(),
            default => $albumsQuery->toBase()->unionAll($singlesQuery->toBase()),
        };

        return DB::query()->fromSub($union, 'releases')
            ->orderBy('release_date', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderByDesc('id');
    }

    /**
     * The newest $limit published releases, covers attached.
     *
     * @return Collection<int, object>
     */
    public function latest(int $limit): Collection
    {
        return $this->attachCovers($this->query()->limit($limit)->get());
    }

    /**
     * Attaches each row's cover Media (one query for the whole set) and
     * casts release_date to Carbon — raw UNION rows are plain stdClass.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function attachCovers(Collection $rows): Collection
    {
        $coverIds = $rows->pluck('cover_media_id')->filter()->unique();
        $covers = Media::query()->whereIn('id', $coverIds)->get()->keyBy('id');

        return $rows->map(function (object $row) use ($covers) {
            $row->cover = $covers->get($row->cover_media_id);
            $row->release_date = $row->release_date ? Carbon::parse($row->release_date) : null;

            return $row;
        });
    }
}
