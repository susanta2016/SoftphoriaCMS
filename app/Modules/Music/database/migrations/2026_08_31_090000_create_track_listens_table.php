<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only record of one completed (fully played, via the <audio>
 * element's native `ended` event) whole-track listen by an authenticated
 * user — written only by App\Http\Controllers\Music\TrackListenController,
 * and the sole input to TrackStreamController's daily-quota check
 * (features.registered_user_whole_song_listens_per_day). Deliberately
 * separate from user_downloads/DownloadLog — this is a listening limit, not
 * a download limit, and must never be conflated with one. No updated_at:
 * a row is never mutated once written, same convention as DownloadLog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_listens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_listens');
    }
};
