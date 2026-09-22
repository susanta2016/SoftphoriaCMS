<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable + unique: existing users have none until they set one on
     * Account > Profile (App\Http\Controllers\Account\ProfileController).
     * New registrations require it (App\Http\Controllers\
     * RegistrationController). The `users` table's connection default
     * collation is utf8mb4_unicode_ci (config/database.php) — case-
     * insensitive, so this unique index alone already stops "Jacob" and
     * "jacob" both being taken, matching App\Shared\Support\Users\
     * UsernameRules' own validation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 20)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
