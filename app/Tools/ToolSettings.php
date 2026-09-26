<?php

namespace App\Tools;

use App\Shared\Support\Settings\DefaultedSettings;

/**
 * Tools Settings (Admin → Tools → Tools Settings), stored in the `settings`
 * table under group "tools": the /tools hub copy and SEO, and the default
 * call to action for tools that don't set their own.
 */
class ToolSettings extends DefaultedSettings
{
    public const GROUP = 'tools';

    /**
     * key => [default, storage type]
     */
    public const FIELDS = [
        'title' => ['Free tools for websites and marketing', 'string'],
        'intro' => ['Practical calculators, converters and checkers from the Softphoria team — free to use, no sign-up needed.', 'string'],
        'meta_title' => [null, 'string'],
        'meta_description' => [null, 'string'],
        'cta_heading' => ['Need help with your website?', 'string'],
        'cta_text' => ['Our team builds, improves and supports websites and software for businesses in India and worldwide. Tell us what you need.', 'string'],
        'cta_label' => ['Discuss your project', 'string'],
        'cta_url' => ['/contact', 'string'],
    ];
}
