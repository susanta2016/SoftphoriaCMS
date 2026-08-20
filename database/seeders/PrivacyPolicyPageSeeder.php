<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a placeholder "privacy-policy" CMS Page so the Cookies Policy
 * popup's "More information" tab (docs/Cookies Policy popup.docx) always
 * has a real Privacy Policy link to point to, through the same
 * CreatePageAction/UpdatePageAction the Pages admin UI uses — see
 * HomePageSeeder for the identical idempotent pattern. The seeded body is
 * placeholder copy; an admin is expected to replace it with real
 * legal-reviewed content via Pages before launch.
 */
class PrivacyPolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $data = [
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'template' => PageTemplate::Legal->value,
            'status' => PageStatus::Published->value,
            'summary' => 'How we collect, use, and protect your information, including the cookies we use on this website.',
            'publish_at' => now(),
            'sections' => [
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => 'Privacy Policy',
                    'is_enabled' => true,
                    'content_json' => [
                        'body' => '<p><em>This is placeholder content — replace it with your reviewed Privacy Policy text via Pages.</em></p>'
                            .'<h2>Overview</h2>'
                            .'<p>This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website.</p>'
                            .'<h2>Cookies</h2>'
                            .'<p>We use cookies and similar tracking technologies to operate our website, remember your preferences, analyze traffic, and personalize content. You can manage your cookie preferences at any time using the Cookie Settings link in the site footer.</p>'
                            .'<h2>Contact Us</h2>'
                            .'<p>If you have any questions about this Privacy Policy, please contact us.</p>',
                    ],
                ],
            ],
            'seo' => [
                'meta_title' => 'Privacy Policy',
                'meta_description' => 'How we collect, use, and protect your information, including the cookies we use on this website.',
            ],
        ];

        $page = Page::query()->where('slug', 'privacy-policy')->first();

        if ($page) {
            app(UpdatePageAction::class)->handle($page, $data, $actor);
        } else {
            app(CreatePageAction::class)->handle($data, $actor);
        }
    }
}
