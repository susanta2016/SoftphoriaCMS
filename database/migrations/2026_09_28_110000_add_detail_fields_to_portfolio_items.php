<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Portfolio detail pages (/portfolio/{slug}): extends the existing
 * portfolio_items table rather than adding a parallel project model.
 *
 * - slug: the public URL segment; existing rows get one from their title.
 * - challenge / solution / outcome: optional rich-text detail sections —
 *   each is only shown when an admin has entered approved content.
 * - gallery_media_ids: optional Media Library images (JSON id list).
 * - portfolio_item_service: the Services (Admin → Services) a project
 *   involved, picked by an admin — nothing is assigned automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->string('slug', 160)->nullable()->after('title');
            $table->longText('challenge')->nullable()->after('summary');
            $table->longText('solution')->nullable()->after('challenge');
            $table->longText('outcome')->nullable()->after('solution');
            $table->json('gallery_media_ids')->nullable()->after('cover_media_id');
        });

        $taken = [];

        foreach (DB::table('portfolio_items')->orderBy('id')->get(['id', 'title']) as $item) {
            $base = Str::slug($item->title) ?: 'project';
            $slug = $base;

            for ($i = 2; in_array($slug, $taken, true); $i++) {
                $slug = "{$base}-{$i}";
            }

            $taken[] = $slug;
            DB::table('portfolio_items')->where('id', $item->id)->update(['slug' => $slug]);
        }

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->string('slug', 160)->nullable(false)->change();
            $table->unique('slug');
        });

        Schema::create('portfolio_item_service', function (Blueprint $table) {
            $table->foreignId('portfolio_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['portfolio_item_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_item_service');

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'challenge', 'solution', 'outcome', 'gallery_media_ids']);
        });
    }
};
