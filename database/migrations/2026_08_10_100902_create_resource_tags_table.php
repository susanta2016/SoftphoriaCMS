<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspirational_resource_id')->constrained('inspirational_resources')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inspirational_resource_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_tags');
    }
};
