<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-008: the purchased line (Single or Album), with a full historical
 * snapshot. album_id/single_id are dual-nullable FKs — the same "exactly one
 * of two parents" shape Track already uses for album_id/single_id — rather
 * than a morph column, matching the existing codebase convention (see
 * Track's own docblock) of avoiding true polymorphism where the two possible
 * parents are already known, fixed types. item_title/unit_price/currency/
 * subtotal/total are all snapshotted at creation and never re-read from
 * Album/Single or Global Pricing afterward — an order must never change
 * because a title was edited or Global Pricing was updated later. See
 * App\Modules\Commerce\Models\OrderItem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('album_id')->nullable()->constrained('albums')->restrictOnDelete();
            $table->foreignId('single_id')->nullable()->constrained('singles')->restrictOnDelete();
            $table->string('item_title');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->char('currency', 3);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->unique(['order_id', 'album_id']);
            $table->unique(['order_id', 'single_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
