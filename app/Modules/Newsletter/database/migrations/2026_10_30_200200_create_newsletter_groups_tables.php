<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriber groups and their many-to-many membership. Membership is only
 * "who is in which list" — consent and suppression live on the subscriber
 * and in newsletter_suppressions, so adding someone to a group can never
 * make an unsubscribed or suppressed address sendable. Deleting a group
 * removes only its membership rows, never subscribers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_group_subscriber', function (Blueprint $table) {
            $table->foreignId('newsletter_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['newsletter_group_id', 'newsletter_subscriber_id']);
            $table->index('newsletter_subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_group_subscriber');
        Schema::dropIfExists('newsletter_groups');
    }
};
