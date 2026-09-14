<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NewsletterSubscriber::scopeSendable() — the query every future
 * newsletter-sending feature must use. There is no bulk-send feature built
 * yet (the Newsletter module currently only collects subscribers), so this
 * is a regression test on the scope itself rather than on a sending job.
 */
class NewsletterSendableScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_subscribed_subscribers_are_sendable(): void
    {
        NewsletterSubscriber::query()->create(['email' => 'pending@example.com', 'status' => 'pending']);
        NewsletterSubscriber::query()->create(['email' => 'subscribed@example.com', 'status' => 'subscribed']);
        NewsletterSubscriber::query()->create(['email' => 'unsubscribed@example.com', 'status' => 'unsubscribed']);
        NewsletterSubscriber::query()->create(['email' => 'bounced@example.com', 'status' => 'bounced']);
        NewsletterSubscriber::query()->create(['email' => 'complained@example.com', 'status' => 'complained']);

        $sendable = NewsletterSubscriber::query()->sendable()->pluck('email')->all();

        $this->assertSame(['subscribed@example.com'], $sendable);
    }
}
