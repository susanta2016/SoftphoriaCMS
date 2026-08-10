<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->cascadeOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('album_id');
            $table->index('single_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_categories');
    }
};
