<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-touch acquisition attribution, written once at registration by
     * App\Shared\Support\Marketing\UtmAttribution (see the registration
     * Actions). All nullable: existing users and visitors who arrived
     * without UTM parameters keep NULL here.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->after('last_login_at')->index();
            $table->string('utm_medium', 100)->nullable()->after('utm_source')->index();
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium')->index();
            $table->string('utm_content', 100)->nullable()->after('utm_campaign');
            $table->string('utm_term', 100)->nullable()->after('utm_content');
            $table->text('utm_landing_url')->nullable()->after('utm_term');
            $table->timestamp('utm_captured_at')->nullable()->after('utm_landing_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['utm_source']);
            $table->dropIndex(['utm_medium']);
            $table->dropIndex(['utm_campaign']);
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_landing_url', 'utm_captured_at']);
        });
    }
};
