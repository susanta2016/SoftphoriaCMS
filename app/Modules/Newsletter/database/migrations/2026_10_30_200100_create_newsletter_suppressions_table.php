<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The global suppression list — keyed by normalized email, not subscriber,
 * so it holds even for an address with no subscriber row (an SES bounce for
 * someone since deleted) and survives a subscriber being deleted and
 * re-added. NewsletterSubscriber::scopeSendable() excludes every address
 * here. Only an "unsubscribed" row is lifted automatically, by the
 * subscriber re-confirming through double opt-in; every other reason is
 * only lifted by an explicit, audited admin action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason', 30)->index();
            $table->string('detail')->nullable();
            $table->foreignId('suppressed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_suppressions');
    }
};
