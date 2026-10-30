<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns and their include/exclude group selections.
 *
 * `scheduled_at` is always UTC; `timezone` is only how it is entered and
 * displayed. The rendered_* columns are the content snapshot taken when the
 * campaign is scheduled or sent (SnapshotCampaignContent) — sends only ever
 * use them, never the live template. `groups_snapshot` keeps the selected
 * group names for history, since a group can later be renamed or deleted
 * (its newsletter_campaign_group rows then go with it). The template is
 * nulled, not cascaded, on delete for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('subject', 200);
            $table->string('preheader', 255)->nullable();
            $table->string('from_name', 100);
            $table->string('from_email');
            $table->foreignId('newsletter_template_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content')->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->timestamp('scheduled_at')->nullable()->index();

            $table->longText('rendered_html')->nullable();
            $table->longText('rendered_text')->nullable();
            $table->json('tracked_links')->nullable();
            $table->boolean('track_opens')->default(false);
            $table->boolean('track_clicks')->default(false);
            $table->json('groups_snapshot')->nullable();
            $table->timestamp('content_snapshot_at')->nullable();
            $table->timestamp('recipients_snapshot_at')->nullable();

            $table->unsignedInteger('recipients_total')->default(0);
            // The one live chain of SendCampaignBatchJob for this campaign;
            // a job carrying any other id exits (SendCampaignBatchJob).
            $table->string('send_chain', 36)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_progress_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('newsletter_campaign_group', function (Blueprint $table) {
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_group_id')->constrained()->cascadeOnDelete();
            $table->boolean('excluded')->default(false);

            $table->primary(['newsletter_campaign_id', 'newsletter_group_id']);
            $table->index('newsletter_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_group');
        Schema::dropIfExists('newsletter_campaigns');
    }
};
