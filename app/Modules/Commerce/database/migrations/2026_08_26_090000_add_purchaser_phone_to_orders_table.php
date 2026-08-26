<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest checkout (P2 of the Music frontend/purchase brief) requires Name,
 * Email, and a Contact number — purchaser_name/purchaser_email already
 * exist; this is the missing phone field. Nullable — a registered user's
 * checkout never collects it (their account is the contact record), only a
 * guest's does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchaser_phone')->nullable()->after('purchaser_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('purchaser_phone');
        });
    }
};
