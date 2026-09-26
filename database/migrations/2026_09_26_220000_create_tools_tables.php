<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Tools module (Admin → Tools), public at /tools and /tools/{slug}.
 *
 * A tool row is only the landing-page content, SEO and publication state.
 * Its working functionality is application code (app/Tools/Functionalities),
 * referenced by its registry key in `functionality` — never stored or
 * uploaded here. SEO lives in the shared polymorphic seo_metadata table.
 *
 * tool_redirects keeps the old slugs of published tools, so renaming a live
 * tool 301s its old URL to the new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('tool_category_id')->nullable()->constrained('tool_categories')->nullOnDelete();
            $table->string('functionality', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('short_description', 300)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('heading')->nullable();
            $table->text('introduction')->nullable();
            $table->longText('how_it_works')->nullable();
            $table->longText('use_cases')->nullable();
            $table->longText('additional_content')->nullable();
            $table->longText('important_notes')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('cta_heading')->nullable();
            $table->string('cta_text', 300)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('tool_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tool_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('related_tool_id')->constrained('tools')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['tool_id', 'related_tool_id']);
        });

        Schema::create('tool_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('tool_categories')->insert(collect([
            ['Website & Business', 'website-business', 'Plan budgets, estimate costs and make better decisions about your website.'],
            ['WordPress', 'wordpress', 'Costs, maintenance and practical helpers for WordPress sites.'],
            ['SEO', 'seo', 'Check and improve how your pages appear in search results.'],
            ['Marketing', 'marketing', 'Campaign tracking and everyday marketing helpers.'],
            ['Web Development', 'web-development', 'Converters and utilities for designers and developers.'],
            ['Image & Media', 'image-media', 'Prepare images and media for the web.'],
        ])->map(fn (array $row, int $i): array => [
            'name' => $row[0],
            'slug' => $row[1],
            'description' => $row[2],
            'sort_order' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_redirects');
        Schema::dropIfExists('tool_related');
        Schema::dropIfExists('tool_faqs');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('tool_categories');
    }
};
