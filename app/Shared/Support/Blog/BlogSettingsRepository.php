<?php

namespace App\Shared\Support\Blog;

use App\Shared\Support\Settings\DefaultedSettings;

/**
 * Blog Settings (Admin → Blog → Blog Settings), stored in the `settings`
 * table under group "blog". Every key has a default here, so the blog
 * renders sensibly before an admin has saved anything.
 */
class BlogSettingsRepository extends DefaultedSettings
{
    public const GROUP = 'blog';

    /**
     * key => [default, storage type]
     */
    public const FIELDS = [
        'title' => ['Insights & Articles', 'string'],
        'intro' => ['Practical guides, engineering deep-dives and lessons from real client projects — written to help you make better technology decisions.', 'string'],
        'meta_title' => [null, 'string'],
        'meta_description' => [null, 'string'],
        'per_page' => [9, 'integer'],
        'layout' => ['grid', 'string'],
        'card_style' => ['elevated', 'string'],
        'show_featured' => [true, 'boolean'],
        'show_author' => [true, 'boolean'],
        'show_reading_time' => [true, 'boolean'],
        'show_toc' => [true, 'boolean'],
        'show_share' => [true, 'boolean'],
        'show_related' => [true, 'boolean'],
        'show_newsletter' => [true, 'boolean'],
        'cta_heading' => ['Have a project in mind?', 'string'],
        'cta_text' => ["Tell us what you're building. We'll get back to you with practical next steps — no obligation.", 'string'],
        'cta_label' => ["Let's talk", 'string'],
        'cta_url' => ['/contact', 'string'],
    ];

    public const LAYOUTS = ['grid' => 'Grid (cards in columns)', 'list' => 'List (wide rows with image beside text)'];

    public const CARD_STYLES = ['elevated' => 'Elevated (soft shadow)', 'bordered' => 'Bordered (flat outline)', 'minimal' => 'Minimal (no card frame)'];
}
