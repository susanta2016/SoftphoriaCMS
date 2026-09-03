<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-010 — extends the existing DB-002/003 contact_requests table
 * (never replaced/redesigned) with what the read-only audit found missing:
 * abuse-investigation forensics, soft-deletes (ARCHITECTURE.md §13 — no
 * hard deletes on any table with a status column), and an index the admin
 * listing's default sort needs. `status` itself is unchanged — it already
 * exists as a string column; ContactRequestStatus only gives it a typed
 * surface, the same way PageStatus/UserStatus do for their own columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('message');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->softDeletes();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
