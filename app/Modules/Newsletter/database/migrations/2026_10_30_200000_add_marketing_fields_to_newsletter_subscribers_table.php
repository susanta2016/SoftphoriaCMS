<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter Marketing: profile fields on the existing newsletter_subscribers
 * table (the footer signup's own table — reused, never duplicated). `name`
 * stays: the public signup still writes it. first_name/last_name are
 * backfilled from it (first word / the rest) so existing subscribers
 * personalize straight away. `source` records where a subscriber came from
 * (footer, admin, import); existing rows predate the module, so they get
 * "footer" — the only way a subscriber could be created before (bar a
 * manual admin add, which can't be told apart now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('source', 50)->nullable()->after('status');
            $table->json('profile_fields')->nullable()->after('source');
        });

        DB::table('newsletter_subscribers')->whereNull('source')->update(['source' => 'footer']);

        DB::table('newsletter_subscribers')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $parts = preg_split('/\s+/', trim((string) $row->name), 2);

                    DB::table('newsletter_subscribers')->where('id', $row->id)->update([
                        'first_name' => mb_substr($parts[0] ?? '', 0, 100) ?: null,
                        'last_name' => mb_substr($parts[1] ?? '', 0, 100) ?: null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'source', 'profile_fields']);
        });
    }
};
