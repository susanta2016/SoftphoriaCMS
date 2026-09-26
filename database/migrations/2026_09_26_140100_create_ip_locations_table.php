<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached IP geolocation (one row per IP address), filled by
 * App\Shared\Services\Geo\IpGeolocator after a member comments or reacts
 * on the blog, and shown to admins next to those records. Admin-only
 * data — never rendered on the public site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_locations', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('country_code', 2)->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('postal', 20)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('network')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('provider', 30)->nullable();
            $table->timestamp('looked_up_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_locations');
    }
};
