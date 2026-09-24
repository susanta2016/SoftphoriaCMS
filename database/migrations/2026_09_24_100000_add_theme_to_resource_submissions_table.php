<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The submit form's Theme dropdown (ResourceSubmission::THEME_OPTIONS).
 * Nullable because submissions made before this column existed have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->string('theme')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
