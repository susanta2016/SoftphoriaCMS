<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('album_id')->nullable()->constrained('albums')->cascadeOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('track_number')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'deleted_at']);
            $table->index('album_id');
            $table->index('single_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
