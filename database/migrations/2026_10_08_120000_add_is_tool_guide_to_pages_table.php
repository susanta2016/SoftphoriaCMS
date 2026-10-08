<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A CMS Page flagged as a tool guide is served at /tools/{slug} (when no
 * live Tool has that slug) instead of /{slug} — guides that support an
 * interactive tool, e.g. the Social Video Safe Zone Checker's platform
 * guides. See ToolController::show().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_tool_guide')->default(false)->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('is_tool_guide');
        });
    }
};
