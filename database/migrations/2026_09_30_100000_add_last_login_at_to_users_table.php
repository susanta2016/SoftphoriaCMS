<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal tracking only — written by App\Actions\Auth\RecordLastLoginAction
     * on every successful member or admin sign-in, and deliberately never
     * shown in the admin panel or the member account area (hidden on the
     * User model too). Null = hasn't signed in since this column shipped.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
