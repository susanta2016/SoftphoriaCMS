<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable campaign email templates (full HTML documents + CSS). Separate
 * from the transactional email_templates table on purpose: those are
 * one-row-per-notification-key bodies inside the shared branded layout;
 * these are whole marketing layouts, many per site, chosen per campaign.
 * A campaign never reads its template again after it is scheduled — it
 * sends from its own rendered snapshot — so editing or archiving a
 * template can't change a campaign already scheduled or sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('subject', 200)->nullable();
            $table->string('preheader', 255)->nullable();
            $table->longText('html_content');
            $table->text('css_content')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_templates');
    }
};
