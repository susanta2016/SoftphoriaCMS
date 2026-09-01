<?php

namespace Tests\Feature\Commerce;

use App\Models\EmailTemplate;
use App\Modules\Commerce\Actions\Entitlement\ResolveTrackAccessAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Exceptions\InvalidEntitlementPurchasableException;
use App\Modules\Commerce\Exceptions\InvalidOrderItemPurchasableException;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * The pricing/purchase-scope correction: an Album-owned Track is now
 * individually purchasable, always at GlobalPricingResolver::perSongPrice()
 * (the exact same global rate a Single already uses) — never its parent
 * Album's price, and a Track purchase never grants the rest of the Album.
 * The Album's own whole-album purchase (CheckAlbumReadinessAction,
 * EntitlementAccessTest::test_album_purchase_grants_every_track_under_that_album)
 * is completely unaffected by any of this.
 */
class TrackPurchaseTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    private function payViaWebhook(string $orderPublicId, string $eventId, string $sessionId): void
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $sessionId,
                'mode' => 'payment',
                'client_reference_id' => $orderPublicId,
                'payment_intent' => 'pi_'.$sessionId,
            ]],
        ]);

        $this->call('POST', '/commerce/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => 'valid'], $payload);
    }

    private function secondTrackIn(Album $album): Track
    {
        return Track::query()->create([
            'album_id' => $album->getKey(),
            'title' => 'Second Track',
            'slug' => 'second-track-'.uniqid(),
            'track_number' => 2,
            'status' => TrackStatus::Published,
            'audio_media_id' => $this->audioMedia()->getKey(),
        ]);
    }

    // -- Pricing distinction ------------------------------------------------

    public function test_the_track_page_shows_the_global_per_song_price_never_the_album_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'full_album_price', '12.00');
        app(SettingsRepository::class)->set('pricing', 'music_per_song_price', '1.99');
        $album = $this->readyAlbum();
        $track = $album->tracks->first();

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertOk();
        $response->assertSee('1.99');
        $response->assertDontSee('12.00');
    }

    public function test_the_album_page_shows_the_full_album_price_never_the_per_song_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'full_album_price', '12.00');
        app(SettingsRepository::class)->set('pricing', 'music_per_song_price', '1.99');
        $album = $this->readyAlbum();

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('12.00');
        $response->assertDontSee('1.99');
    }

    public function test_the_order_snapshots_the_per_song_price_for_a_track_purchase_regardless_of_album_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'full_album_price', '12.00');
        app(SettingsRepository::class)->set('pricing', 'music_per_song_price', '1.99');
        $album = $this->readyAlbum();
        $track = $album->tracks->first();

        $order = app(CreatePendingOrderAction::class)->handle($track, null, 'guest@example.com');

        $this->assertSame('1.99', (string) $order->total);
        $this->assertSame('1.99', (string) $order->items->first()->unit_price);
    }

    // -- Registered user ------------------------------------------------

    public function test_a_registered_user_can_purchase_an_individual_album_owned_track(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();
        $track = $album->tracks->first();

        $order = app(CreatePendingOrderAction::class)->handle($track, $user, $user->email);

        $this->assertSame($track->getKey(), $order->items->first()->track_id);
        $this->assertNull($order->items->first()->album_id);
        $this->assertNull($order->items->first()->single_id);

        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $grant = app(ResolveTrackAccessAction::class)->forUser($track, $user);
        $this->assertNotNull($grant);
    }

    public function test_purchasing_one_album_track_does_not_grant_a_sibling_track_in_the_same_album(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();
        $purchasedTrack = $album->tracks->first();
        $otherTrack = $this->secondTrackIn($album);

        $order = app(CreatePendingOrderAction::class)->handle($purchasedTrack, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $resolver = app(ResolveTrackAccessAction::class);

        $this->assertNotNull($resolver->forUser($purchasedTrack, $user));
        $this->assertNull($resolver->forUser($otherTrack, $user));
    }

    public function test_a_registered_user_can_download_only_the_purchased_track_via_the_existing_download_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $user = $this->admin();
        $album = $this->readyAlbum();
        $purchasedTrack = $album->tracks->first();
        $otherTrack = $this->secondTrackIn($album);

        $order = app(CreatePendingOrderAction::class)->handle($purchasedTrack, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $ownDownload = $this->actingAs($user)->get(route('music.tracks.download', $purchasedTrack));
        $ownDownload->assertOk();
        $ownDownload->assertHeader('content-disposition');

        $otherDownload = $this->actingAs($user)->from(route('music.tracks.show', $otherTrack))
            ->get(route('music.tracks.download', $otherTrack));
        $otherDownload->assertRedirect(route('music.tracks.show', $otherTrack));
        $otherDownload->assertSessionHas('download_error');
    }

    // -- Guest buyer ------------------------------------------------

    public function test_a_guest_can_purchase_an_individual_album_owned_track_and_receives_a_scoped_notification(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        // The default seeded guest_download_access body is generic
        // boilerplate with no {{order_items}} placeholder (see
        // EmailTemplateSeeder::defaultHtmlBody()) — exercise the same
        // admin-configured-body path OrderConfirmationEmailTest uses, rather
        // than asserting against placeholder copy nobody wrote.
        EmailTemplate::query()
            ->where('notification_key', 'guest_download_access')
            ->update(['html_body' => '<p>Your purchase: {{order_items}}</p>']);

        // Deliberately non-overlapping titles (unlike readyAlbum()'s default
        // "Ready Album"/"Ready Album Track", where the track title contains
        // the album title as a substring) — the email must contain the
        // track's own title and must not contain the album's, and that
        // distinction has to be checkable by a plain substring assertion.
        $album = $this->readyAlbum();
        $album->update(['title' => 'The Light Within']);
        $track = $album->tracks->first();
        $track->update(['title' => 'Song of Light']);
        $order = app(CreatePendingOrderAction::class)->handle($track, null, 'guest@example.com');

        $this->assertSame($track->getKey(), $order->items->first()->track_id);
        $this->assertNull($order->items->first()->album_id);
        $this->assertSame($track->title, $order->items->first()->item_title);

        $this->payViaWebhook($order->public_id, 'evt_track_guest_1', 'cs_track_guest_1');

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($track, $album): bool {
            $rendered = $mail->render();

            return $mail->hasTo('guest@example.com')
                && str_contains($rendered, $track->title)
                && ! str_contains($rendered, $album->title);
        });
    }

    public function test_a_guests_downloads_page_exposes_only_the_purchased_track_not_the_whole_album(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');
        $this->app->bind(StripeGatewayContract::class, FakeStripeGateway::class);

        $album = $this->readyAlbum();
        $purchasedTrack = $album->tracks->first();
        $otherTrack = $this->secondTrackIn($album);
        $order = app(CreatePendingOrderAction::class)->handle($purchasedTrack, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_track_guest_2');

        $pairs = collect($issued)->map(fn ($i) => "{$i->entitlement->public_id}.{$i->plainGuestToken}")->all();
        $accessUrl = route('downloads.guest.show', $order).'?'.http_build_query(['t' => $pairs]);
        $this->get($accessUrl);
        $this->post(route('downloads.guest.verify', $order->refresh()), ['email' => 'guest@example.com']);

        $itemsPage = $this->get(route('downloads.guest.items', $order));
        $itemsPage->assertOk();
        $itemsPage->assertSee($purchasedTrack->title);
        $itemsPage->assertDontSee($otherTrack->title);

        $download = $this->get(route('downloads.guest.track', [$order, $purchasedTrack]));
        $download->assertOk();
        $download->assertHeader('content-disposition');

        $deniedDownload = $this->get(route('downloads.guest.track', [$order, $otherTrack]));
        $deniedDownload->assertRedirect(route('downloads.guest.items', $order));
        $deniedDownload->assertSessionHas('download_error');
    }

    // -- Album purchase regression ------------------------------------------------

    public function test_album_purchase_still_grants_every_track_under_the_album(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();
        $secondTrack = $this->secondTrackIn($album);

        $order = app(CreatePendingOrderAction::class)->handle($album, $user, $user->email);
        $this->assertNull($order->items->first()->track_id);

        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $resolver = app(ResolveTrackAccessAction::class);
        $this->assertNotNull($resolver->forUser($album->tracks->first(), $user));
        $this->assertNotNull($resolver->forUser($secondTrack, $user));
    }

    // -- Additional regression ------------------------------------------------

    public function test_a_track_with_an_album_id_never_becomes_an_album_purchase(): void
    {
        $album = $this->readyAlbum();
        $track = $album->tracks->first();
        $this->assertNotNull($track->album_id);

        $order = app(CreatePendingOrderAction::class)->handle($track, null, 'guest@example.com');
        $orderItem = $order->items->first();

        $this->assertSame($track->getKey(), $orderItem->track_id);
        $this->assertNull($orderItem->album_id);
        $this->assertSame('track', $orderItem->itemType());

        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $order->items->first()->refresh()->entitlement;

        $this->assertSame($track->getKey(), $entitlement->track_id);
        $this->assertNull($entitlement->album_id);
    }

    public function test_global_pricing_resolver_remains_the_single_source_of_truth_for_a_track_price(): void
    {
        app(SettingsRepository::class)->set('pricing', 'music_per_song_price', '2.49');
        $album = $this->readyAlbum();
        $track = $album->tracks->first();

        $order = app(CreatePendingOrderAction::class)->handle($track, null, 'guest@example.com');

        $this->assertSame('2.49', (string) $order->total);
        $this->assertSame((string) app(GlobalPricingResolver::class)->perSongPrice(), (string) $order->total);
    }

    // -- Model-level "exactly one of three" guard ------------------------------------------------

    public function test_an_order_item_cannot_reference_both_an_album_and_a_track(): void
    {
        $album = $this->readyAlbum();
        $track = $album->tracks->first();
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');

        $this->expectException(InvalidOrderItemPurchasableException::class);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'album_id' => $album->getKey(),
            'track_id' => $track->getKey(),
            'item_title' => 'Invalid dual-reference item',
            'quantity' => 1,
            'unit_price' => 1,
            'currency' => 'usd',
            'subtotal' => 1,
            'total' => 1,
        ]);
    }

    public function test_an_order_item_cannot_reference_none_of_album_single_or_track(): void
    {
        $album = $this->readyAlbum();
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');

        $this->expectException(InvalidOrderItemPurchasableException::class);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'item_title' => 'Invalid empty-reference item',
            'quantity' => 1,
            'unit_price' => 1,
            'currency' => 'usd',
            'subtotal' => 1,
            'total' => 1,
        ]);
    }

    public function test_an_entitlement_cannot_reference_both_an_album_and_a_track(): void
    {
        $album = $this->readyAlbum();
        $track = $album->tracks->first();
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');
        $orderItem = $order->items->first();

        $this->expectException(InvalidEntitlementPurchasableException::class);

        Entitlement::query()->create([
            'order_item_id' => $orderItem->getKey(),
            'purchaser_email' => 'guest@example.com',
            'album_id' => $album->getKey(),
            'track_id' => $track->getKey(),
            'downloads_used' => 0,
        ]);
    }

    // -- No cross-contamination between purchase scopes ------------------------------------------------

    public function test_a_track_id_entitlement_never_satisfies_coverstrack_for_a_sibling_album_track(): void
    {
        $album = $this->readyAlbum();
        $purchasedTrack = $album->tracks->first();
        $otherTrack = $this->secondTrackIn($album);
        $order = app(CreatePendingOrderAction::class)->handle($purchasedTrack, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $issued[0]->entitlement;

        $this->assertTrue($entitlement->coversTrack($purchasedTrack));
        $this->assertFalse($entitlement->coversTrack($otherTrack));
        // tracks() must resolve to exactly the one purchased track, never
        // every track under the album it happens to belong to.
        $this->assertSame([$purchasedTrack->getKey()], $entitlement->tracks()->pluck('id')->all());
    }

    public function test_the_track_purchase_form_never_posts_the_album_purchase_type(): void
    {
        $album = $this->readyAlbum();
        $track = $album->tracks->first();

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertOk();
        // The purchase form's hidden "type" field must be "track", never
        // "album" — the only value CartController would need to treat this
        // as an Album purchase.
        $response->assertSee('name="type" value="track"', false);
        $response->assertDontSee('name="type" value="album"', false);
    }

    public function test_track_download_controller_never_resolves_the_parent_album_from_a_track_entitlement(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $user = $this->admin();
        $album = $this->readyAlbum();
        $purchasedTrack = $album->tracks->first();
        $otherTrack = $this->secondTrackIn($album);
        $order = app(CreatePendingOrderAction::class)->handle($purchasedTrack, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        // The download endpoint is asked for the SIBLING track directly —
        // if TrackDownloadController (or the access resolver behind it) ever
        // resolved "this track's album" from the entitlement instead of
        // checking the specific requested track, this would incorrectly
        // succeed.
        $response = $this->actingAs($user)->from(route('music.tracks.show', $otherTrack))
            ->get(route('music.tracks.download', $otherTrack));

        $response->assertRedirect(route('music.tracks.show', $otherTrack));
        $response->assertSessionHas('download_error');
    }
}
