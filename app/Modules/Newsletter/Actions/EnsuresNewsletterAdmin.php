<?php

namespace App\Modules\Newsletter\Actions;

use App\Models\User;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Support\NewsletterFeature;
use Filament\Facades\Filament;

/**
 * The server-side checks every admin-initiated Newsletter Marketing action
 * makes itself, independent of what the Filament UI shows: the module is
 * on, and the actor can use the admin panel (active admin).
 */
trait EnsuresNewsletterAdmin
{
    /**
     * @throws NewsletterException
     */
    protected function ensureAdmin(?User $admin): void
    {
        NewsletterFeature::ensureEnabled();

        if ($admin === null || ! $admin->canAccessPanel(Filament::getPanel('admin'))) {
            throw NewsletterException::invalid('Only administrators can manage newsletter campaigns.');
        }
    }
}
