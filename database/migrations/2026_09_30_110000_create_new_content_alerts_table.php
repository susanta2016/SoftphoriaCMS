<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per piece of content that has already had its all-member
     * "new content" alert — App\Shared\Services\Notifications\NewContentAlerter.
     * The unique index is the duplicate protection itself.
     *
     * Content that is already live (or was live and is now archived) when
     * this ships is recorded as announced, so editing or re-publishing
     * existing items never emails every member about old content.
     * Scheduled/draft items are left out and alert normally when published.
     */
    public function up(): void
    {
        Schema::create('new_content_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alertable_type');
            $table->unsignedBigInteger('alertable_id');
            $table->string('notification_key');
            $table->timestamp('created_at')->nullable();

            $table->unique(['alertable_type', 'alertable_id']);
        });

        $existing = [
            'albums' => ['App\Modules\Music\Models\Album', 'new_music_published', ['published', 'archived']],
            'singles' => ['App\Modules\Music\Models\Single', 'new_music_published', ['published', 'archived']],
            'podcast_episodes' => ['App\Modules\Podcast\Models\PodcastEpisode', 'new_podcast_episode_published', ['published', 'archived']],
            'resource_submissions' => ['App\Modules\InspirationalResources\Models\ResourceSubmission', 'new_inspirational_resource_published', ['approved', 'archived']],
        ];

        $now = now();

        foreach ($existing as $table => [$type, $key, $statuses]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->whereIn('status', $statuses)->orderBy('id')->chunkById(500, function ($rows) use ($type, $key, $now): void {
                DB::table('new_content_alerts')->insertOrIgnore($rows->map(fn ($row): array => [
                    'alertable_type' => $type,
                    'alertable_id' => $row->id,
                    'notification_key' => $key,
                    'created_at' => $now,
                ])->all());
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('new_content_alerts');
    }
};
