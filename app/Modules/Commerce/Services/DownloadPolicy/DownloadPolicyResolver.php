<?php

namespace App\Modules\Commerce\Services\DownloadPolicy;

use App\Modules\Commerce\Support\DownloadPolicy;
use App\Shared\Services\Settings\SettingsRepository;

/**
 * §7/§14 of the approved brief: guest and registered-free-member download
 * limits/expiry must be configurable, not hardcoded, and must reuse the
 * existing Settings architecture (App\Shared\Services\Settings\SettingsRepository) —
 * no new settings mechanism. Backed by Website Setup → "Download Access"
 * (App\Modules\Commerce\Filament\Pages\DownloadAccessSettings), a new
 * settings *group* (`downloads`), never touching the `pricing` group Global
 * Pricing owns. Defaults below are placeholders — admin-editable from day
 * one, not an approved business number (see the final report's open
 * decisions). Pro Member access is never governed by this: it's checked live
 * against Subscription::isActive(), always unlimited while active.
 */
class DownloadPolicyResolver
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function forGuest(): DownloadPolicy
    {
        return new DownloadPolicy(
            maxDownloads: (int) $this->settings->get('downloads', 'guest_max_downloads', 5),
            expiryDays: (int) $this->settings->get('downloads', 'guest_expiry_days', 30),
        );
    }

    public function forRegisteredMember(): DownloadPolicy
    {
        return new DownloadPolicy(
            maxDownloads: (int) $this->settings->get('downloads', 'member_max_downloads', 10),
            expiryDays: (int) $this->settings->get('downloads', 'member_expiry_days', 90),
        );
    }
}
