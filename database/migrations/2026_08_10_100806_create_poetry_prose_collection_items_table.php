<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poetry_prose_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poetry_prose_collection_id')
                ->constrained('poetry_prose_collections', indexName: 'pp_collection_items_collection_id_foreign')
                ->cascadeOnDelete();
            $table->foreignId('poetry_prose_id')->constrained('poetry_prose')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['poetry_prose_collection_id', 'poetry_prose_id'], 'pp_collection_items_collection_id_pp_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poetry_prose_collection_items');
    }
};
