<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->foreignId('artwork_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->date('publish_date')->nullable();
            $table->unsignedInteger('season')->nullable();
            $table->unsignedInteger('episode_number')->nullable();
            $table->string('embed_url')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('publish_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'deleted_at']);
            $table->index(['status', 'publish_at']);
            $table->index('podcast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_episodes');
    }
};
