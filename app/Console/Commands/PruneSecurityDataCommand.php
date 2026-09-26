<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the Privacy Policy's retention promise for security data
 * (resources/legal/privacy-policy.html §6): IP addresses, browser user
 * agents and IP-derived approximate locations are kept for at most
 * RETENTION_MONTHS, then removed. The records themselves (contact
 * enquiries, comments, reactions) are kept; only these fields are
 * cleared. Scheduled daily in bootstrap/app.php.
 */
class PruneSecurityDataCommand extends Command
{
    protected $signature = 'privacy:prune-security-data';

    protected $description = 'Clear IP addresses, user agents and IP locations older than the retention period';

    public const RETENTION_MONTHS = 12;

    public function handle(): int
    {
        $cutoff = now()->subMonths(self::RETENTION_MONTHS);
        $cleared = [];

        foreach (['contact_requests', 'blog_comments', 'blog_reactions'] as $table) {
            $cleared[$table] = DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->where(fn ($query) => $query->whereNotNull('ip_address')->orWhereNotNull('user_agent'))
                ->update(['ip_address' => null, 'user_agent' => null]);
        }

        $cleared['ip_locations'] = DB::table('ip_locations')
            ->where(fn ($query) => $query->where('looked_up_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('looked_up_at')->where('created_at', '<', $cutoff)))
            ->delete();

        $this->info('Cleared security data older than '.self::RETENTION_MONTHS.' months: '.json_encode($cleared));

        return self::SUCCESS;
    }
}
