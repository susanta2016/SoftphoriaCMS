<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-template "Forward Email" switch: when on, a copy of the template's
 * user-facing email is BCC'd to Website Setup → Settings → Email →
 * Forward Email. Only meaningful on `user` rows — admin notifications are
 * never forwarded (see TemplatedMailer::renderAsMailable()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->boolean('forward_enabled')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('forward_enabled');
        });
    }
};
