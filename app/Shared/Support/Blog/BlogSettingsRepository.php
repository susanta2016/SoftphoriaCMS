<?php

namespace App\Shared\Support\Blog;

use App\Shared\Services\Settings\SettingsRepository;

/**
 * Blog Settings (Admin → Blog → Blog Settings), stored in the `settings`
 * table under group "blog". Every key has a default here, so the blog
 * renders sensibly before an admin has saved anything.
 */
class BlogSettingsRepository
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

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(string $key): mixed
    {
        $stored = $this->cache ??= $this->settings->all(self::GROUP);

        return $stored[$key] ?? self::FIELDS[$key][0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return collect(self::FIELDS)->keys()->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (self::FIELDS as $key => [, $type]) {
            if (array_key_exists($key, $values)) {
                $value = $values[$key];
                $this->settings->set(self::GROUP, $key, $type === 'boolean' ? (bool) $value : $value, $type);
            }
        }

        $this->cache = null;
    }
}
