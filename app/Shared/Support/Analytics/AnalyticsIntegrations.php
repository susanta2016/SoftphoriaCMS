<?php

namespace App\Shared\Support\Analytics;

use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Auth;

/**
 * The analytics tools an admin can switch on in Website Setup → Analytics &
 * Tracking, and everything the site needs to know about them: the ID
 * format, which cookie-consent category they belong to, the cookies they set
 * and the script that loads them. Currently Google Analytics 4 only — add a
 * tool as another definition (plus its script() case), never as a raw
 * <script> in a layout.
 *
 * Consent first: every tracking script is emitted inside an inert
 * <template data-consent-scripts="{category}"> and only activated by
 * resources/js/app.js after the visitor has accepted that category in the
 * cookie banner — never before, and never if the cookie banner is switched
 * off (then there is no way to consent).
 */
class AnalyticsIntegrations
{
    /**
     * key => definition. `setting` is the AnalyticsSettings field holding its ID.
     *
     * @return array<string, array{label: string, provider: string, category: string, setting: string, pattern: string, example: string, purpose: string, cookies: array<int, array{0: string, 1: string}>}>
     */
    public static function definitions(): array
    {
        return [
            'ga4' => [
                'label' => 'Google Analytics 4',
                'provider' => 'Google LLC',
                'category' => 'tracking',
                'setting' => 'ga4_id',
                'pattern' => '/^G-[A-Z0-9]{4,20}$/',
                'example' => 'G-XXXXXXXXXX',
                'purpose' => 'Measures visits and how the website is used, as aggregated statistics.',
                'cookies' => [['_ga', '2 years'], ['_ga_<container-id>', '2 years']],
            ],
        ];
    }

    public function __construct(
        private readonly AnalyticsSettings $settings,
        private readonly SettingsRepository $siteSettings,
    ) {}

    /**
     * Tools switched on (master switch on and a valid ID saved).
     *
     * @return array<string, array<string, mixed>> key => definition + 'id'
     */
    public function active(): array
    {
        if (! $this->settings->get('enabled')) {
            return [];
        }

        $active = [];

        foreach (self::definitions() as $key => $definition) {
            $id = trim((string) $this->settings->get($definition['setting']));

            if ($id !== '' && preg_match($definition['pattern'], $id)) {
                $active[$key] = [...$definition, 'id' => $id];
            }
        }

        return $active;
    }

    /**
     * Active tools grouped by consent category.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function byCategory(): array
    {
        $grouped = [];

        foreach ($this->active() as $key => $tool) {
            $grouped[$tool['category']][$key] = $tool;
        }

        return $grouped;
    }

    /**
     * Whether tracking scripts may be emitted on this request at all.
     * They still wait for consent in the browser.
     */
    public function shouldEmitScripts(): bool
    {
        if ($this->active() === []) {
            return false;
        }

        // No cookie banner means no way to consent: load nothing.
        if (! $this->siteSettings->get('cookies', 'enabled', config('cookies_policy.enabled', true))) {
            return false;
        }

        if ($this->settings->get('exclude_admins') && ($user = Auth::user()) && $user->hasAdminRole()) {
            return false;
        }

        return true;
    }

    /**
     * The <script> markup for one tool (IDs are validated against each
     * tool's pattern, so they are safe to interpolate).
     */
    public function script(string $key, string $id): string
    {
        $id = e($id);

        return match ($key) {
            'ga4' => "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n"
                ."<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>",
            default => '',
        };
    }

    /**
     * Cookie names/prefixes each consent category's tools set — the browser
     * clears them when a visitor withdraws consent ("_ga_*" = prefix).
     *
     * @return array<string, array<int, string>>
     */
    public function cookiesToClear(): array
    {
        $names = [];

        foreach ($this->active() as $tool) {
            foreach ($tool['cookies'] as [$name]) {
                if (preg_match('/^[A-Za-z0-9_]+/', $name, $m)) {
                    $names[$tool['category']][] = $m[0].(str_contains($name, '<') ? '*' : '');
                }
            }
        }

        return array_map(fn (array $list): array => array_values(array_unique($list)), $names);
    }
}
