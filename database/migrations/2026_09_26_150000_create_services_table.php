<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Services module (Admin → Services), public at /services and
 * /services/{slug}. highlights/technologies/faqs are small ordered lists
 * edited as repeaters, so they live as JSON on the row. SEO lives in the
 * shared polymorphic seo_metadata table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon', 60)->nullable();
            $table->string('tagline')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('highlights')->nullable();
            $table->json('technologies')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_featured')->default(true);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
