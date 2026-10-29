<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where each public Inspirational Resources submission came from,
 * so spam that still gets past the honeypot / time trap / throttle can be
 * recognised by source in the admin panel — same columns as
 * contact_submissions. Both nullable: existing rows and admin-created
 * submissions have no recorded source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('message')->index();
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('resource_submissions', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
