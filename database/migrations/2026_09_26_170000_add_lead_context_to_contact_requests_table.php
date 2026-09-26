<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead context for every contact submission (see LeadContext): the page it
 * was sent from, which form (contact page / page section / side widget /
 * CTA popup), which call-to-action opened it, and where the visitor
 * originally came from. Shown in Admin → Contact Requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->string('page_url', 2048)->nullable()->after('message');
            $table->string('page_title')->nullable()->after('page_url');
            $table->string('source', 30)->nullable()->after('page_title');
            $table->string('cta_label', 120)->nullable()->after('source');
            $table->string('referrer', 2048)->nullable()->after('cta_label');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['page_url', 'page_title', 'source', 'cta_label', 'referrer']);
        });
    }
};
