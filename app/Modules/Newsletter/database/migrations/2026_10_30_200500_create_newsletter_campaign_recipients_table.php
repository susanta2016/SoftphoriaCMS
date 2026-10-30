<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (campaign, address) — the recipient snapshot taken when the
 * campaign starts sending, and that recipient's delivery state. The unique
 * (campaign, email) index is what guarantees one email per address even
 * when a subscriber is in several selected groups. `token` is the random,
 * unguessable key in that recipient's unsubscribe/open/click links and SES
 * message tag; it never exposes the subscriber id or address.
 *
 * Status lifecycle (CampaignRecipientStatus): pending -> sending -> sent ->
 * delivered/bounced/complained, or skipped (no longer eligible at send
 * time), failed, cancelled. The subscriber is nulled on delete so campaign
 * history and reports survive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('status_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->string('message_id')->nullable()->unique();
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            // Explicit names: the generated ones exceed MySQL's 64-char limit.
            $table->unique(['newsletter_campaign_id', 'email'], 'nl_recipients_campaign_email_unique');
            $table->index(['newsletter_campaign_id', 'status'], 'nl_recipients_campaign_status_index');
            $table->index('newsletter_subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_recipients');
    }
};
