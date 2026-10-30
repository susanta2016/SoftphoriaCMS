<?php

namespace App\Modules\Newsletter\Support;

use App\Modules\Newsletter\Exceptions\NewsletterException;

/**
 * The single reader of config('newsletter.enabled') — the
 * NEWSLETTER_MARKETING_ENABLED environment variable, the module's only
 * on/off switch (no database setting can override it). Checked by the
 * admin middleware, every Filament resource/page's canAccess(), the
 * dispatcher command, each queued job and NewsletterDeliveryService, so
 * hiding the navigation is never the protection.
 */
final class NewsletterFeature
{
    public static function enabled(): bool
    {
        return (bool) config('newsletter.enabled');
    }

    /**
     * @throws NewsletterException
     */
    public static function ensureEnabled(): void
    {
        if (! self::enabled()) {
            throw NewsletterException::disabled();
        }
    }
}
