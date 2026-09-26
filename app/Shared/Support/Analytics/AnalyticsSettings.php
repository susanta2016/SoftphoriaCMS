<?php

namespace App\Shared\Support\Analytics;

use App\Shared\Support\Settings\DefaultedSettings;

/**
 * Website Setup → Analytics & Tracking, stored in the `settings` table under
 * group "analytics". See AnalyticsIntegrations for how each value is used.
 */
class AnalyticsSettings extends DefaultedSettings
{
    public const GROUP = 'analytics';

    /**
     * key => [default, storage type]
     */
    public const FIELDS = [
        'enabled' => [true, 'boolean'],
        'exclude_admins' => [true, 'boolean'],
        'ga4_id' => [null, 'string'],
        'gtm_id' => [null, 'string'],
        'clarity_id' => [null, 'string'],
        'meta_pixel_id' => [null, 'string'],
        'linkedin_partner_id' => [null, 'string'],
        'google_site_verification' => [null, 'string'],
        'bing_site_verification' => [null, 'string'],
        'custom_head_code' => [null, 'string'],
        'custom_body_code' => [null, 'string'],
        'custom_code_category' => ['tracking', 'string'],
        'custom_code_description' => [null, 'string'],
    ];
}
