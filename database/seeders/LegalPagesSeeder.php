<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Publishes the Privacy Policy, Terms of Service and Cookie Policy as
 * Legal-template CMS Pages (editable afterwards in Admin → Pages), from
 * resources/legal/*.html. Business facts come from Website Setup (site
 * name, contact email and address, session cookie settings) at seeding
 * time.
 *
 * Never overwrites an admin's work: a page is written only if it doesn't
 * exist yet or still holds the old placeholder text
 * (PrivacyPolicyPageSeeder). Re-running is safe. Pass --force (via
 * LEGAL_PAGES_FORCE=true) only to deliberately replace edited copies.
 */
class LegalPagesSeeder extends Seeder
{
    /** Sole proprietorship, per the business owner (2026-09-26). */
    private const ENTITY = 'a sole proprietorship';

    private const PLACEHOLDER_MARKER = 'This is placeholder content';

    /**
     * slug => [title, summary, file]
     */
    private const DOCUMENTS = [
        'privacy-policy' => ['Privacy Policy', 'How we collect, use, share and protect your personal data, and the rights you have over it.', 'privacy-policy.html'],
        'terms-of-service' => ['Terms of Service', 'The terms that govern your use of our website and the basis on which we provide our services.', 'terms-of-service.html'],
        'cookie-policy' => ['Cookie Policy', 'The cookies and similar technologies our website uses, and how to manage your choices.', 'cookie-policy.html'],
    ];

    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $force = filter_var(env('LEGAL_PAGES_FORCE', false), FILTER_VALIDATE_BOOL);
        $tokens = $this->tokens();

        foreach (self::DOCUMENTS as $slug => [$title, $summary, $file]) {
            $page = Page::query()->where('slug', $slug)->with('sections')->first();

            if ($page && ! $force && ! $this->isPlaceholder($page)) {
                $this->command?->warn("Skipped /{$slug}: it has been edited in Admin → Pages (set LEGAL_PAGES_FORCE=true to replace it).");

                continue;
            }

            $body = strtr(file_get_contents(resource_path("legal/{$file}")), $tokens);

            $data = [
                'title' => $title,
                'slug' => $slug,
                'template' => PageTemplate::Legal->value,
                'status' => PageStatus::Published->value,
                'summary' => $summary,
                'publish_at' => now(),
                'sections' => [[
                    'section_type' => PageSectionType::RichText->value,
                    'title' => null,
                    'is_enabled' => true,
                    'content_json' => ['body' => $body],
                ]],
                'seo' => [
                    'meta_title' => "{$title} — {$tokens['{{company}}']}",
                    'meta_description' => $summary,
                ],
            ];

            $page
                ? app(UpdatePageAction::class)->handle($page, $data, $actor)
                : app(CreatePageAction::class)->handle($data, $actor);

            $this->command?->info("Published /{$slug}");
        }
    }

    private function isPlaceholder(Page $page): bool
    {
        return $page->sections->contains(fn ($section): bool => str_contains((string) ($section->content_json['body'] ?? ''), self::PLACEHOLDER_MARKER));
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $settings = app(SettingsRepository::class);
        $siteUrl = rtrim($settings->get('general', 'site_url') ?: config('app.url'), '/');
        $address = trim(preg_replace('/\s*\R\s*/', ', ', (string) $settings->get('contact', 'address')) ?? '');

        $e = fn (?string $value): string => e((string) $value);

        return [
            '{{company}}' => $e($settings->get('general', 'site_name') ?: 'Softphoria'),
            '{{entity}}' => self::ENTITY,
            '{{email}}' => $e($settings->get('contact', 'email') ?: 'contact@softphoria.com'),
            '{{address}}' => $e($address ?: 'Monalisa Mansion, Nayabad Avenue, Kolkata, West Bengal, India'),
            '{{site}}' => '<a href="'.$e($siteUrl).'">'.$e(preg_replace('~^https?://~', '', $siteUrl)).'</a>',
            '{{effective_date}}' => now()->format('j F Y'),
            '{{session_cookie}}' => $e(config('session.cookie')),
            '{{session_minutes}}' => (string) (int) config('session.lifetime'),
        ];
    }
}
