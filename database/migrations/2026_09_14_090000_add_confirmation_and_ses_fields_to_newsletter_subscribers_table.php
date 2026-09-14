<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            // Double opt-in — only the hash is ever stored (see
            // App\Actions\Newsletter\SubscribeToNewsletterAction). Indexed so
            // the confirmation lookup is a plain equality query.
            $table->string('confirmation_token_hash', 64)->nullable()->unique()->after('status');
            $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token_hash');
            $table->timestamp('confirmed_at')->nullable()->after('unsubscribed_at');

            // SES/SNS bounce+complaint feedback loop (App\Http\Controllers\
            // Newsletter\SesWebhookController) — last relevant event only,
            // not a full event log.
            $table->timestamp('ses_event_at')->nullable()->after('confirmed_at');
            $table->string('ses_event_type', 20)->nullable()->after('ses_event_at');
            $table->string('ses_event_reason', 255)->nullable()->after('ses_event_type');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_token_hash',
                'confirmation_expires_at',
                'confirmed_at',
                'ses_event_at',
                'ses_event_type',
                'ses_event_reason',
            ]);
        });
    }
};
