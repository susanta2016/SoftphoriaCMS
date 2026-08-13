<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website Setup's fixed, seeded Email Template registry (docs/ARCHITECTURE.md
 * §16.5). One row per (notification_key, recipient_type) pair — e.g.
 * ('user_registered', 'user') and ('user_registered', 'admin') are two
 * separate rows for the same event. Rows are seeded by
 * EmailTemplateSeeder; administrators may edit existing rows but the admin
 * UI never creates or deletes one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key');
            $table->string('recipient_type');
            $table->boolean('is_enabled')->default(true);
            $table->string('subject');
            $table->longText('html_body');
            $table->longText('text_body')->nullable();
            $table->json('available_variables')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['notification_key', 'recipient_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
