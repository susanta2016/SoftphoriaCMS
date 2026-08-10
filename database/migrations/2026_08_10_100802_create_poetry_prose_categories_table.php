<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poetry_prose_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poetry_prose_id')->constrained('poetry_prose')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poetry_prose_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poetry_prose_categories');
    }
};
