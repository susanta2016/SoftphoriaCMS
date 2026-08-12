<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-006 Navigation — adds a typed destination to menu_items on top of
 * the existing generic url/route_name columns (kept untouched for
 * compatibility). destination_type discriminates between an admin-created
 * Page (page_id), a specialized/module route (route_key — a closed,
 * enum-backed identifier, never a free-typed route name), a raw external
 * URL (the pre-existing url column), or a non-clickable parent/group item
 * (none of the target columns set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('destination_type')->default('group')->after('menu_id');
            $table->foreignId('page_id')->nullable()->after('destination_type')
                ->constrained('pages')->restrictOnDelete();
            $table->string('route_key')->nullable()->after('page_id');
            $table->string('target')->default('_self')->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
            $table->dropColumn(['destination_type', 'route_key', 'target']);
        });
    }
};
