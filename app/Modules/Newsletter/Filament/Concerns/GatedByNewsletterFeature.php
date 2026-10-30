<?php

namespace App\Modules\Newsletter\Filament\Concerns;

use App\Modules\Newsletter\Support\NewsletterFeature;

/**
 * For every Newsletter Marketing Resource and Page: inaccessible and out of
 * the navigation while NEWSLETTER_MARKETING_ENABLED is off, on top of the
 * usual panel access. EnsureNewsletterAdminEnabled turns the direct URLs
 * into 404s.
 */
trait GatedByNewsletterFeature
{
    public static function canAccess(): bool
    {
        return NewsletterFeature::enabled() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return NewsletterFeature::enabled() && parent::shouldRegisterNavigation();
    }
}
