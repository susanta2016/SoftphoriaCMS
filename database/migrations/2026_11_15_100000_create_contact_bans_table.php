<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Senders banned from the public Contact Us form (Contact → Banned Senders,
 * or the "Ban senders" bulk action on Contact → Submissions). One row per
 * banned value: an IP address, an email address or a name, stored
 * normalised (see ContactBan::normalize()) so matching is a plain lookup.
 * source_submission_id is informational only (no FK) — the submission it
 * came from is usually deleted straight after banning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_bans', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('value', 255);
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('source_submission_id')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_bans');
    }
};
