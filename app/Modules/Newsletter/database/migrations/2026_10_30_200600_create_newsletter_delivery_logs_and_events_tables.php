<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * newsletter_delivery_logs: every send attempt and what the provider said
 * (or the error) — the audit trail behind a recipient's status.
 *
 * newsletter_events: what happened after the send (delivery, bounce,
 * complaint, open, click, unsubscribe), from the SES/SNS webhook or the
 * module's own tracking links. `dedupe_key` is unique, so a webhook
 * redelivered by SNS, or the same event published twice, is stored and
 * counted once. No raw provider payload is kept — only what reports need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_delivery_logs', function (Blueprint $table) {
            $table->id();
            // Explicit FK name: the generated one exceeds MySQL's 64-char limit.
            $table->foreignId('newsletter_campaign_recipient_id');
            $table->foreign('newsletter_campaign_recipient_id', 'nl_delivery_logs_recipient_foreign')
                ->references('id')->on('newsletter_campaign_recipients')->cascadeOnDelete();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->string('status', 20);
            $table->string('mailer', 50)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['newsletter_campaign_id', 'status']);
        });

        Schema::create('newsletter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_campaign_recipient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('source', 20);
            $table->string('dedupe_key', 64)->unique();
            $table->string('detail', 2048)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['newsletter_campaign_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_events');
        Schema::dropIfExists('newsletter_delivery_logs');
    }
};
