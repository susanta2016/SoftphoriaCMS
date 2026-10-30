<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every admin reply to a Contact Us submission
 * (App\Actions\Contact\SendContactSubmissionReplyAction) — one row per
 * reply, so a submission's full reply history is kept and the submission
 * row itself is never modified. `recipient_email` is a snapshot of the
 * submission's address at send time. `status` is pending/sent/failed:
 * "sent" means the mail server accepted the message (mail is sent
 * synchronously; there is no inbox-delivery tracking). The unique
 * `idempotency_key` (one per open reply form) is the backstop against a
 * double-click/retry creating a second row or a second email. The sending
 * admin is nulled rather than cascaded so history survives the admin's
 * account being removed; replies go with their submission (spam bulk-delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submission_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submission_replies');
    }
};
