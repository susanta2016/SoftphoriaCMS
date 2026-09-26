<?php

namespace App\Shared\Support\Analytics;

use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Auth;

/**
 * The analytics / marketing tools an admin can switch on in Website Setup →
 * Analytics & Tracking, and everything the site needs to know about them:
 * the ID format, which cookie-consent category they belong to, the cookies
 * they set (for the Cookie Policy), who provides them (for the Privacy
 * Policy) and the script that loads them.
 *
 * Consent first: every tracking script is emitted inside an inert
 * <template data-consent-scripts="{category}"> and only activated by
 * resources/js/app.js after the visitor has accepted that category in the
 * cookie banner — never before, and never if the cookie banner is switched
 * off (then there is no way to consent). Search-engine verification meta
 * tags set no cookies, so they are always output.
 */
class AnalyticsIntegrations
{
    public const CATEGORY_LABELS = [
        'functionality' => 'Functionality',
        'tracking' => 'Tracking (analytics)',
        'targeting' => 'Targeting and advertising',
    ];

    /**
     * key => definition. `setting` is the AnalyticsSettings field holding its ID.
     *
     * @return array<string, array{label: string, provider: string, category: string, setting: string, pattern: string, example: string, purpose: string, cookies: array<int, array{0: string, 1: string}>, privacy_url: string}>
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
                'purpose' => 'Measures visits and how the Website is used, as aggregated statistics (IP addresses are anonymised).',
                'cookies' => [['_ga', '2 years'], ['_ga_<container-id>', '2 years']],
                'privacy_url' => 'https://policies.google.com/privacy',
            ],
            'gtm' => [
                'label' => 'Google Tag Manager',
                'provider' => 'Google LLC',
                'category' => 'tracking',
                'setting' => 'gtm_id',
                'pattern' => '/^GTM-[A-Z0-9]{4,12}$/',
                'example' => 'GTM-XXXXXXX',
                'purpose' => 'Loads the measurement tags we configure in one place.',
                'cookies' => [['Set by the tags configured in the container', 'Varies']],
                'privacy_url' => 'https://policies.google.com/privacy',
            ],
            'clarity' => [
                'label' => 'Microsoft Clarity',
                'provider' => 'Microsoft Corporation',
                'category' => 'tracking',
                'setting' => 'clarity_id',
                'pattern' => '/^[a-z0-9]{6,20}$/i',
                'example' => 'abcd1234ef',
                'purpose' => 'Heatmaps and anonymised session replays to improve usability. Form fields are masked.',
                'cookies' => [['_clck', '1 year'], ['_clsk', '1 day'], ['CLID', '1 year'], ['MUID', '1 year']],
                'privacy_url' => 'https://privacy.microsoft.com/privacystatement',
            ],
            'meta_pixel' => [
                'label' => 'Meta Pixel',
                'provider' => 'Meta Platforms, Inc.',
                'category' => 'targeting',
                'setting' => 'meta_pixel_id',
                'pattern' => '/^\d{10,20}$/',
                'example' => '1234567890123456',
                'purpose' => 'Measures the performance of our Facebook and Instagram ads and builds advertising audiences.',
                'cookies' => [['_fbp', '90 days'], ['fr', '90 days']],
                'privacy_url' => 'https://www.facebook.com/privacy/policy/',
            ],
            'linkedin' => [
                'label' => 'LinkedIn Insight Tag',
                'provider' => 'LinkedIn Corporation',
                'category' => 'targeting',
                'setting' => 'linkedin_partner_id',
                'pattern' => '/^\d{4,12}$/',
                'example' => '1234567',
                'purpose' => 'Measures the performance of our LinkedIn ads and provides aggregated audience insights.',
                'cookies' => [['li_sugr', '90 days'], ['bcookie', '1 year'], ['lidc', '1 day'], ['UserMatchHistory', '30 days'], ['AnalyticsSyncHistory', '30 days']],
                'privacy_url' => 'https://www.linkedin.com/legal/privacy-policy',
            ],
        ];
    }

    public function __construct(
        private readonly AnalyticsSettings $settings,
        private readonly SettingsRepository $siteSettings,
    ) {}

    /**
     * Tools switched on (master switch on and a valid ID saved), plus the
     * admin's custom code as a pseudo-tool when present.
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

        if (filled($this->settings->get('custom_head_code')) || filled($this->settings->get('custom_body_code'))) {
            $active['custom'] = [
                'label' => 'Additional tracking code',
                'provider' => 'See description',
                'category' => array_key_exists((string) $this->settings->get('custom_code_category'), self::CATEGORY_LABELS)
                    ? $this->settings->get('custom_code_category')
                    : 'tracking',
                'purpose' => $this->settings->get('custom_code_description') ?: 'Additional analytics or marketing code configured by the site administrator.',
                'cookies' => [['Set by the provider', 'Varies']],
                'privacy_url' => null,
                'id' => null,
            ];
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

    public function usesOptionalCookies(): bool
    {
        return $this->active() !== [];
    }

    /**
     * Whether tracking scripts may be emitted on this request at all.
     * They still wait for consent in the browser.
     */
    public function shouldEmitScripts(): bool
    {
        if (! $this->usesOptionalCookies()) {
            return false;
        }

        // No cookie banner means no way to consent: load nothing.
        if (! $this->siteSettings->get('cookies', 'enabled', true)) {
            return false;
        }

        if ($this->settings->get('exclude_admins') && ($user = Auth::user()) && $user->canAccessPanel(filament()->getPanel('admin'))) {
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
                ."<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}',{anonymize_ip:true});</script>",
            'gtm' => "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>",
            'clarity' => "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$id}');</script>",
            'meta_pixel' => "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>",
            'linkedin' => "<script>window._linkedin_partner_id='{$id}';window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(window._linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName('script')[0];var b=document.createElement('script');b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li/lms-analytics/insight.min.js';s.parentNode.insertBefore(b,s);})(window.lintrk);</script>",
            default => '',
        };
    }

    /**
     * Cookie names/prefixes each consent category's tools set — the browser
     * clears them when a visitor withdraws consent.
     *
     * @return array<string, array<int, string>>
     */
    public function cookiesToClear(): array
    {
        $names = [];

        foreach ($this->active() as $tool) {
            foreach ($tool['cookies'] as [$name]) {
                if (preg_match('/^[A-Za-z0-9_]+/', $name, $m) && ! str_contains($name, ' ')) {
                    $names[$tool['category']][] = $m[0].(str_contains($name, '<') ? '*' : '');
                }
            }
        }

        return array_map(fn (array $list): array => array_values(array_unique($list)), $names);
    }

    public function settings(): AnalyticsSettings
    {
        return $this->settings;
    }
}
