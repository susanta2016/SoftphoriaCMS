<?php

use App\Models\Media;
use App\Modules\Music\Support\AudioDurationDetector;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public Podcast frontend (hero, episode list rows, filters) needs each
 * episode's playtime, which nothing on podcast_episodes stores today. Mirrors
 * Track's own duration_seconds column exactly: nullable, never an
 * admin-editable form field, always recomputed from the real uploaded audio
 * file via the existing App\Modules\Music\Support\AudioDurationDetector (read
 * cross-module, not duplicated) — see
 * SavesPodcastEpisodeRelations::detectAndSetDuration().
 *
 * Backfills every existing episode that already has an uploaded audio file
 * right here, rather than leaving them all null until an admin happens to
 * re-save each one — real sample episodes already exist in this database and
 * the frontend's duration display/filter would otherwise silently omit all
 * of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('audio_media_id');
        });

        $detector = app(AudioDurationDetector::class);

        PodcastEpisode::query()
            ->whereNotNull('audio_media_id')
            ->each(function (PodcastEpisode $episode) use ($detector): void {
                $media = Media::find($episode->audio_media_id);
                $seconds = $media !== null ? $detector->detect($media) : null;

                if ($seconds !== null) {
                    $episode->newQuery()->whereKey($episode->getKey())->update(['duration_seconds' => $seconds]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
