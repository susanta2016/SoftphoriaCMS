<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Blog module: posts, categories (one primary category per post),
 * tags (many-to-many), member comments + reports, and emoji reactions.
 * Per-module taxonomy tables, following the music_/podcast_/poetry_prose_
 * categories/tags precedent — the older generic `categories`/`tags`
 * tables are left untouched. SEO lives in the shared polymorphic
 * seo_metadata table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['blog_post_id', 'blog_tag_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['blog_post_id', 'status', 'created_at']);
        });

        Schema::create('blog_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            $table->string('details', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['blog_comment_id', 'user_id']);
        });

        Schema::create('blog_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20);
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->unique(['blog_post_id', 'user_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_reactions');
        Schema::dropIfExists('blog_comment_reports');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
