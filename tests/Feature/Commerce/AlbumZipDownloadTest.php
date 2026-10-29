<?php

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Enums\EntitlementStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Music\Models\Album;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * The full-album ZIP — guest (emailed token + verified email) and
 * registered (own entitlement only) — with its own counter, independent of
 * every per-track counter in both directions.
 */
class AlbumZipDownloadTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array{0: Order, 1: Entitlement, 2: Album}
     */
    private function verifiedGuestAlbumOrder(int $trackCount = 3, string $email = 'guest@example.com'): array
    {
        $album = $this->readyAlbumWithTracks($trackCount);
        $order = app(CreatePendingOrderAction::class)->handle($album, null, $email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_'.uniqid(), 'evt_'.uniqid());
        $entitlement = $issued[0]->entitlement;

        $this->get(route('downloads.guest.show', $order).'?'.http_build_query(['t' => ["{$entitlement->public_id}.{$issued[0]->plainGuestToken}"]]));
        $this->post(route('downloads.guest.verify', $order), ['email' => $email]);

        return [$order->refresh(), $entitlement, $album];
    }

    /**
     * @return array{0: User, 1: Entitlement, 2: Album}
     */
    private function memberAlbumPurchase(int $trackCount = 3): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $album = $this->readyAlbumWithTracks($trackCount);
        $order = app(CreatePendingOrderAction::class)->handle($album, $user, $user->email);
        $entitlement = app(MarkOrderPaidAction::class)->handle($order, 'pi_'.uniqid(), 'evt_'.uniqid())[0]->entitlement;

        return [$user, $entitlement, $album];
    }

    /**
     * @return array<string, string> entry name => contents
     */
    private function zipEntries(TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($path);

        return $entries;
    }

    private function zipDownloadsLogged(): int
    {
        return DownloadLog::query()->whereNotNull('album_id')->where('status', DownloadLogStatus::Succeeded)->count();
    }

    public function test_guest_album_purchaser_can_download_the_complete_album_zip(): void
    {
        [$order, $entitlement, $album] = $this->verifiedGuestAlbumOrder(3);

        $response = $this->get(route('downloads.guest.album-zip', [$order, $entitlement]));

        $response->assertOk();
        $response->assertDownload();
        $entries = $this->zipEntries($response);

        $this->assertCount(3, $entries);
        $this->assertSame("audio-bytes-{$album->getKey()}-1", $entries['01 - Multi Track Album Song 1.mp3']);
        $this->assertSame(1, $this->zipDownloadsLogged());
    }

    public function test_zip_contains_only_the_purchased_albums_tracks(): void
    {
        $this->readyAlbumWithTracks(2, 'Unrelated Album');
        $this->readySingle();
        [$order, $entitlement, $album] = $this->verifiedGuestAlbumOrder(2);

        $entries = $this->zipEntries($this->get(route('downloads.guest.album-zip', [$order, $entitlement])));

        $this->assertSame(['01 - Multi Track Album Song 1.mp3', '02 - Multi Track Album Song 2.mp3'], array_keys($entries));
        foreach ($entries as $contents) {
            $this->assertStringStartsWith("audio-bytes-{$album->getKey()}-", $contents);
        }
    }

    public function test_guest_zip_limit_is_enforced_independently(): void
    {
        [$order, $entitlement] = $this->verifiedGuestAlbumOrder(1);
        $url = route('downloads.guest.album-zip', [$order, $entitlement]);

        $this->assertSame(5, $entitlement->max_downloads);
        $this->assertTrue($entitlement->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));

        for ($i = 1; $i <= 5; $i++) {
            $this->zipEntries($this->get($url)->assertOk());
            $this->assertSame($i, $entitlement->refresh()->zip_downloads_used);
            $this->assertSame(5 - $i, $entitlement->remainingDownloads());
        }

        $this->get($url)
            ->assertRedirect(route('downloads.guest.items', $order))
            ->assertSessionHas('download_error', 'You\'ve reached the download limit for this album ZIP.');
        $this->assertSame(EntitlementStatus::Exhausted, $entitlement->refresh()->status());
    }

    public function test_guest_album_purchase_has_no_individual_track_downloads(): void
    {
        [$order, $entitlement, $album] = $this->verifiedGuestAlbumOrder(2);
        $track = $album->tracks()->orderBy('track_number')->first();

        $this->get(route('downloads.guest.items', $order))
            ->assertOk()
            ->assertSee('Full album (ZIP)')
            ->assertDontSee($track->title)
            ->assertDontSee(route('downloads.guest.track', [$order, $track]), false);

        $this->get(route('downloads.guest.track', [$order, $track]))
            ->assertRedirect(route('downloads.guest.items', $order))
            ->assertSessionHas('download_error');

        $this->assertSame(0, $entitlement->refresh()->downloads_used);
        $this->assertSame(0, $entitlement->zip_downloads_used);
    }

    public function test_member_album_purchase_has_no_individual_track_downloads(): void
    {
        [$user, $entitlement, $album] = $this->memberAlbumPurchase(2);
        $track = $album->tracks()->orderBy('track_number')->first();

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertDontSee(route('music.tracks.download', $track), false);

        $this->actingAs($user)->get(route('music.tracks.download', $track))
            ->assertSessionHas('download_error', 'You don\'t have download access to this track.');

        $this->assertSame(0, $entitlement->refresh()->downloads_used);
    }

    public function test_an_old_album_entitlement_locked_by_track_downloads_can_still_download_its_zip(): void
    {
        // The reported production record: 5 pooled track downloads under the
        // old behavior. That history stays as-is, but no longer blocks the ZIP.
        [$order, $entitlement] = $this->verifiedGuestAlbumOrder(1);
        $entitlement->update(['downloads_used' => 5]);

        $this->zipEntries($this->get(route('downloads.guest.album-zip', [$order, $entitlement]))->assertOk());

        $entitlement->refresh();
        $this->assertSame(5, $entitlement->downloads_used);
        $this->assertSame(1, $entitlement->zip_downloads_used);
        $this->assertSame(4, $entitlement->remainingDownloads());
    }

    public function test_registered_free_member_can_download_the_album_zip_with_member_limits(): void
    {
        [$user, $entitlement] = $this->memberAlbumPurchase(2);
        $url = route('account.orders.album-zip', $entitlement);

        $this->assertSame(10, $entitlement->max_downloads);
        $this->assertTrue($entitlement->expires_at->between(now()->addDays(90)->subMinute(), now()->addDays(90)->addMinute()));

        for ($i = 0; $i < 10; $i++) {
            $this->zipEntries($this->actingAs($user)->get($url)->assertOk());
        }

        $this->actingAs($user)->get($url)
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('download_error');

        $this->assertSame(10, $this->zipDownloadsLogged());
    }

    public function test_member_orders_page_offers_the_zip(): void
    {
        [$user, $entitlement] = $this->memberAlbumPurchase(2);

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertSee('Full album (ZIP)')
            ->assertSee(route('account.orders.album-zip', $entitlement), false);
    }

    public function test_another_member_cannot_download_someone_elses_zip(): void
    {
        [, $entitlement] = $this->memberAlbumPurchase();
        $intruder = User::factory()->create(['status' => 'active']);

        $this->actingAs($intruder)->get(route('account.orders.album-zip', $entitlement))
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('download_error', 'You don\'t have download access to this album.');

        $this->assertSame(0, $this->zipDownloadsLogged());
    }

    public function test_signed_out_visitors_cannot_use_the_member_zip_route(): void
    {
        [, $entitlement] = $this->memberAlbumPurchase();

        $this->get(route('account.orders.album-zip', $entitlement))->assertRedirect(route('login'));
    }

    public function test_unverified_guest_session_cannot_download_the_zip(): void
    {
        $album = $this->readyAlbumWithTracks(1);
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');
        $entitlement = app(MarkOrderPaidAction::class)->handle($order, 'pi_u', 'evt_u')[0]->entitlement;

        $this->get(route('downloads.guest.album-zip', [$order, $entitlement]))
            ->assertRedirect(route('downloads.guest.show', $order));
    }

    public function test_a_verified_guest_cannot_use_another_orders_entitlement(): void
    {
        [$orderA] = $this->verifiedGuestAlbumOrder(1, 'a@example.com');
        $albumB = $this->readyAlbumWithTracks(1, 'Album B');
        $orderB = app(CreatePendingOrderAction::class)->handle($albumB, null, 'b@example.com');
        $entitlementB = app(MarkOrderPaidAction::class)->handle($orderB, 'pi_b', 'evt_b')[0]->entitlement;

        $this->get(route('downloads.guest.album-zip', [$orderA, $entitlementB]))
            ->assertRedirect(route('downloads.guest.items', $orderA))
            ->assertSessionHas('download_error');
    }

    public function test_single_purchases_never_get_an_album_zip(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $single = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        $entitlement = app(MarkOrderPaidAction::class)->handle($order, 'pi_s', 'evt_s')[0]->entitlement;

        $this->actingAs($user)->get(route('account.orders.album-zip', $entitlement))
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('download_error');

        $this->actingAs($user)->get(route('account.orders'))->assertDontSee('Full album (ZIP)');
    }

    public function test_expired_purchases_cannot_download_the_zip(): void
    {
        [$order, $entitlement] = $this->verifiedGuestAlbumOrder(1);
        $entitlement->update(['expires_at' => now()->subMinute()]);

        $this->get(route('downloads.guest.album-zip', [$order, $entitlement]))
            ->assertRedirect(route('downloads.guest.items', $order))
            ->assertSessionHas('download_error');

        [$user, $memberEntitlement] = $this->memberAlbumPurchase(1);
        $memberEntitlement->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->get(route('account.orders.album-zip', $memberEntitlement))
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('download_error');

        $this->assertSame(0, $this->zipDownloadsLogged());
    }

    public function test_guest_items_page_offers_the_zip_with_its_own_count(): void
    {
        [$order] = $this->verifiedGuestAlbumOrder(1);
        $items = $this->get(route('downloads.guest.items', $order));

        $items->assertOk()->assertSee('Full album (ZIP)')->assertSee('5 downloads left');
    }

    public function test_the_emailed_zip_link_lands_on_the_zip_section_after_verification(): void
    {
        $album = $this->readyAlbumWithTracks(1);
        $order = app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_z', 'evt_z');
        $pair = "{$issued[0]->entitlement->public_id}.{$issued[0]->plainGuestToken}";

        $this->get(route('downloads.guest.show', $order).'?'.http_build_query(['t' => [$pair], 'next' => 'album-zip']));

        $this->post(route('downloads.guest.verify', $order), ['email' => 'guest@example.com'])
            ->assertRedirect(route('downloads.guest.items', $order).'#album-zip');
    }
}
