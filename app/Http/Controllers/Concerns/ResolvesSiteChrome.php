<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;

/**
 * Every AUTH-001→005 controller needs the same site-wide header/footer
 * chrome (site name/tagline/logo) that HomeController/ContactController
 * already resolve inline — extracted here because this stage introduces 7
 * new controllers needing the identical lookup, past the threshold this
 * codebase's own convention (see e.g. RegisterUserAction's admin-resolution
 * reuse) treats as worth a shared helper rather than 7 copies of it.
 */
trait ResolvesSiteChrome
{
    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    protected function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
