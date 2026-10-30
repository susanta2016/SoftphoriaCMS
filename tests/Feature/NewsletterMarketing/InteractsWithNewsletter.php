<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Enums\UserStatus;
use App\Models\NewsletterSubscriber;
use App\Models\Role;
use App\Models\User;
use App\Modules\Newsletter\Enums\TemplateStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\StarterTemplate;

/**
 * Shared fixtures for the Newsletter Marketing tests. The module is off in
 * phpunit.xml; enable() turns it on per test.
 */
trait InteractsWithNewsletter
{
    protected function enable(): void
    {
        config([
            'newsletter.enabled' => true,
            'newsletter.allowed_sender_domains' => ['example.com'],
            'newsletter.from.address' => 'news@example.com',
            'newsletter.from.name' => 'Example News',
            // No real throttling needed in tests.
            'newsletter.max_rate_per_second' => 1000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value, ...$attributes]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function subscriber(string $email, array $attributes = [], array $groups = []): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => $email,
            'first_name' => 'Sam',
            'last_name' => 'Reader',
            'status' => 'subscribed',
            'consented_at' => now(),
            ...$attributes,
        ]);

        $subscriber->groups()->attach(array_map(fn (NewsletterGroup $group): int => $group->id, $groups));

        return $subscriber;
    }

    protected function group(string $name = 'General Newsletter'): NewsletterGroup
    {
        return NewsletterGroup::query()->create(['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function template(array $attributes = []): NewsletterTemplate
    {
        return NewsletterTemplate::query()->create([
            'name' => 'Monthly Newsletter '.uniqid(),
            'html_content' => StarterTemplate::html(),
            'css_content' => StarterTemplate::css(),
            'status' => TemplateStatus::Active,
            ...$attributes,
        ]);
    }

    /**
     * A draft campaign targeting $included (minus $excluded).
     *
     * @param  array<int, NewsletterGroup>  $included
     * @param  array<int, NewsletterGroup>  $excluded
     * @param  array<string, mixed>  $attributes
     */
    protected function campaign(array $included, array $excluded = [], array $attributes = []): NewsletterCampaign
    {
        $campaign = NewsletterCampaign::query()->create([
            'name' => 'October update',
            'subject' => 'Hello {{ subscriber.first_name }}',
            'preheader' => 'What is new this month',
            'from_name' => 'Example News',
            'from_email' => 'news@example.com',
            'newsletter_template_id' => ($attributes['template'] ?? $this->template())->id,
            'content' => '<p>This month we launched something new.</p><p><a href="https://example.com/launch">Read the launch post</a></p>',
            'cta_text' => 'See what is new',
            'cta_url' => 'https://example.com/new',
            ...collect($attributes)->except('template')->all(),
        ]);

        $campaign->syncGroupSelection(
            array_map(fn (NewsletterGroup $group): int => $group->id, $included),
            array_map(fn (NewsletterGroup $group): int => $group->id, $excluded),
        );

        return $campaign;
    }
}
