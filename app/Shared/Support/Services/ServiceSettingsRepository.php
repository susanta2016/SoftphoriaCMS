<?php

namespace App\Shared\Support\Services;

use App\Shared\Support\Settings\DefaultedSettings;

/**
 * Services Settings (Admin → Services → Services Settings), stored in the
 * `settings` table under group "services": the /services landing page copy
 * and SEO, and the lead-capture call to action shown on every service page.
 */
class ServiceSettingsRepository extends DefaultedSettings
{
    public const GROUP = 'services';

    /**
     * key => [default, storage type]
     */
    public const FIELDS = [
        'title' => ['Technology services built around your business', 'string'],
        'intro' => ['From first idea to long-term support — we design, build, integrate and run the software your business depends on.', 'string'],
        'meta_title' => [null, 'string'],
        'meta_description' => [null, 'string'],
        'cta_heading' => ["Let's talk about your project", 'string'],
        'cta_text' => ['Tell us where you are and where you want to be. We\'ll reply with practical next steps — no obligation.', 'string'],
        'cta_label' => ['Get a free consultation', 'string'],
        'cta_url' => ['/contact', 'string'],
        'show_related_posts' => [true, 'boolean'],
    ];
}
