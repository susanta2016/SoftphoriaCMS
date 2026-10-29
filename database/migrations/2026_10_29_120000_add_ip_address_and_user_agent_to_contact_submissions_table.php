<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where each Contact Us submission came from, so spam that still
 * gets past the honeypot / time trap / throttle can be recognised by source
 * in the admin panel. Both nullable: existing rows have no recorded source,
 * and a request can legitimately arrive without a User-Agent. 45 chars fits
 * a full IPv6 address; the user agent is capped at 512 (stored truncated).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('message')->index();
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
