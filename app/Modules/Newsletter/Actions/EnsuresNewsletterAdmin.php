<?php

namespace App\Modules\Newsletter\Actions;

use App\Models\User;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Filament\Facades\Filament;

/**
 * The server-side checks every admin-initiated Newsletter Marketing action
 * makes itself, independent of what the Filament UI shows: the actor can
 * use the admin panel (active admin) and, unless $requiresModule is false,
 * the module is switched on. Subscriber management passes false — the
 * subscriber list was live before the module existed and stays available
 * with NEWSLETTER_MARKETING_ENABLED off.
 */
trait EnsuresNewsletterAdmin
{
    /**
     * @throws NewsletterException
     */
    protected function ensureAdmin(?User $admin, bool $requiresModule = true): void
    {
        if ($requiresModule) {
            NewsletterFeature::ensureEnabled();
        }

        if ($admin === null || ! $admin->canAccessPanel(Filament::getPanel('admin'))) {
            throw NewsletterException::invalid('Only administrators can manage the newsletter.');
        }
    }
}
