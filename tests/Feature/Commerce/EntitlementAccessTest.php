<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Entitlement\ResolveTrackAccessAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §3/§9/§22 of the approved brief — the access-control core: an Album
 * entitlement resolves every eligible Album Track, a Single entitlement
 * resolves only its own Track, neither ever grants a track outside what was
 * actually paid for, and an active Subscription grants the whole catalogue
 * while an inactive one grants nothing.
 */
class EntitlementAccessTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_album_purchase_grants_every_track_under_that_album(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();

        $secondTrack = Track::query()->create([
            'album_id' => $album->getKey(),
            'title' => 'Second Track',
            'slug' => 'second-track',
            'track_number' => 2,
            'status' => TrackStatus::Published,
            'audio_media_id' => $this->audioMedia()->getKey(),
        ]);

        $order = app(CreatePendingOrderAction::class)->handle($album, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $resolver = app(ResolveTrackAccessAction::class);

        $this->assertNotNull($resolver->forUser($album->tracks->first(), $user));
        $this->assertNotNull($resolver->forUser($secondTrack, $user));
    }

    public function test_a_track_from_a_different_album_cannot_be_downloaded_with_that_entitlement(): void
    {
        $user = $this->admin();
        $purchasedAlbum = $this->readyAlbum();
        $otherAlbum = $this->readyAlbum();

        $order = app(CreatePendingOrderAction::class)->handle($purchasedAlbum, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $grant = app(ResolveTrackAccessAction::class)->forUser($otherAlbum->tracks->first(), $user);

        $this->assertNull($grant);
    }

    public function test_single_purchase_grants_only_its_own_track(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();
        $otherSingle = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $resolver = app(ResolveTrackAccessAction::class);

        $this->assertNotNull($resolver->forUser($single->track, $user));
        $this->assertNull($resolver->forUser($otherSingle->track, $user));
    }

    public function test_revoked_entitlement_denies_access(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $issued[0]->entitlement;
        $entitlement->revoked_at = now();
        $entitlement->save();

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);

        $this->assertNull($grant);
    }

    public function test_expired_entitlement_denies_access(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $issued[0]->entitlement->update(['expires_at' => now()->subDay()]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);

        $this->assertNull($grant);
    }

    public function test_one_user_cannot_use_another_users_entitlement(): void
    {
        $owner = $this->admin();
        $stranger = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $owner, $owner->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $stranger);

        $this->assertNull($grant);
    }

    public function test_active_subscription_grants_any_published_track(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);

        $this->assertNotNull($grant);
        $this->assertSame(DownloadAccessType::Membership, $grant->type);
        $this->assertNull($grant->entitlement);
    }

    public function test_canceled_subscription_grants_nothing(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Canceled,
            'ended_at' => now()->subDay(),
        ]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);

        $this->assertNull($grant);
    }

    public function test_subscription_past_its_current_period_end_grants_nothing_even_if_status_lags(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->subDay(),
        ]);

        $grant = app(ResolveTrackAccessAction::class)->forUser($single->track, $user);

        $this->assertNull($grant);
    }
}
