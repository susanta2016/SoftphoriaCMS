<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: what a purchaser (guest or registered) is allowed to
 * download, granted from exactly one paid order_item (1:1 — order_item_id is
 * unique). album_id/single_id are a denormalized copy of the order_item's
 * value: the hot-path "can this track be downloaded" check
 * (ResolveTrackAccessAction) never needs a join through order_items for it.
 * access_token_hash only holds a SHA-256 hash — the plaintext guest token is
 * generated once by IssueEntitlementForOrderItemAction and handed to its
 * caller; it is never persisted anywhere. Pro Member (subscription) access is
 * deliberately NOT represented here — see App\Modules\Commerce\Models\Subscription's
 * docblock for why. See App\Modules\Commerce\Models\Entitlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchaser_email')->index();
            $table->foreignId('album_id')->nullable()->constrained('albums')->restrictOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->restrictOnDelete();
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('downloads_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoked_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
