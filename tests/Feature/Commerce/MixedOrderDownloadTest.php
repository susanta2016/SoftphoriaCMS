<?php

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Actions\Cart\AddToCartAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * Download mode is decided per purchased line item, never per order: in
 * one order holding two individually purchased songs (a Single and an
 * Album-owned Track) plus a complete Album, each song gets its own link and
 * counter, the Album gets exactly one ZIP option with its own counter, and
 * no item ever consumes another's allowance — for guests (5) and
 * registered free members (10) alike.
 */
class MixedOrderDownloadTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    private Single $songA;

    private Track $songB;

    private Album $album;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'single-audio-bytes');

        $this->songA = $this->readySingle();
        $this->songB = $this->readyAlbumWithTracks(2, 'Source Album')->tracks()->orderBy('track_number')->first();
        $this->album = $this->readyAlbumWithTracks(3, 'Complete Album');
    }

    /**
     * @return array{0: Order, 1: Entitlement, 2: Entitlement, 3: Entitlement} order, songA, songB, album
     */
    private function placeMixedOrder(?User $user, string $email): array
    {
        $order = app(CreatePendingOrderAction::class)->handle($this->songA, $user, $email);
        app(AddToCartAction::class)->handle($order, $this->songB);
        app(AddToCartAction::class)->handle($order, $this->album);
        $issued = collect(app(MarkOrderPaidAction::class)->handle($order, 'pi_'.uniqid(), 'evt_'.uniqid()));

        if ($user === null) {
            $pairs = $issued->map(fn ($i) => "{$i->entitlement->public_id}.{$i->plainGuestToken}")->all();
            $this->get(route('downloads.guest.show', $order).'?'.http_build_query(['t' => $pairs]));
            $this->post(route('downloads.guest.verify', $order), ['email' => $email]);
        }

        $entitlements = $issued->map->entitlement;

        return [
            $order->refresh(),
            $entitlements->firstWhere('single_id', $this->songA->id),
            $entitlements->firstWhere('track_id', $this->songB->id),
            $entitlements->firstWhere('album_id', $this->album->id),
        ];
    }

    private function discardZip(TestResponse $response): void
    {
        @unlink($response->baseResponse->getFile()->getPathname());
    }

    public function test_a_mixed_order_issues_one_entitlement_per_line_item(): void
    {
        [$order, $songA, $songB, $album] = $this->placeMixedOrder(null, 'guest@example.com');

        $this->assertCount(3, $order->items);
        $this->assertSame(3, Entitlement::query()->count());
        $this->assertNotNull($songA);
        $this->assertNotNull($songB);
        $this->assertTrue($album->isAlbumPurchase());
        $this->assertFalse($songA->isAlbumPurchase());
        $this->assertFalse($songB->isAlbumPurchase());
    }

    public function test_guest_items_page_shows_song_links_and_one_zip_and_no_album_track_links(): void
    {
        [$order, , , $album] = $this->placeMixedOrder(null, 'guest@example.com');
        $albumTrack = $this->album->tracks()->first();

        $this->get(route('downloads.guest.items', $order))
            ->assertOk()
            ->assertSee(route('downloads.guest.track', [$order, $this->songA->track]), false)
            ->assertSee(route('downloads.guest.track', [$order, $this->songB]), false)
            ->assertSee(route('downloads.guest.album-zip', [$order, $album]), false)
            ->assertDontSee(route('downloads.guest.track', [$order, $albumTrack]), false)
            ->assertDontSee($albumTrack->title);
    }

    public function test_guest_song_counters_and_zip_counter_are_fully_independent(): void
    {
        [$order, $songA, $songB, $album] = $this->placeMixedOrder(null, 'guest@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->get(route('downloads.guest.track', [$order, $this->songA->track]))->assertOk();
        }

        $this->get(route('downloads.guest.track', [$order, $this->songA->track]))
            ->assertSessionHas('download_error', 'You\'ve reached the download limit for this track.');

        $this->assertSame(5, $songB->refresh()->remainingDownloads());
        $this->assertSame(5, $album->refresh()->remainingDownloads());

        $this->discardZip($this->get(route('downloads.guest.album-zip', [$order, $album]))->assertOk());
        $this->get(route('downloads.guest.track', [$order, $this->songB]))->assertOk();

        $this->assertSame(0, $songA->refresh()->remainingDownloads());
        $this->assertSame(4, $songB->refresh()->remainingDownloads());
        $this->assertSame(4, $album->refresh()->remainingDownloads());
    }

    public function test_guest_cannot_download_album_tracks_individually_or_a_zip_for_a_song(): void
    {
        [$order, , $songB, $album] = $this->placeMixedOrder(null, 'guest@example.com');

        $this->get(route('downloads.guest.track', [$order, $this->album->tracks()->first()]))
            ->assertSessionHas('download_error', 'You don\'t have download access to this track.');

        // An individually purchased song from another album never unlocks
        // that album's ZIP — or the rest of its tracks.
        $this->get(route('downloads.guest.album-zip', [$order, $songB]))
            ->assertSessionHas('download_error');
        $this->get(route('downloads.guest.track', [$order, $this->songB->album->tracks()->orderBy('track_number')->get()[1]]))
            ->assertSessionHas('download_error');

        $this->assertSame(0, $album->refresh()->zip_downloads_used);
    }

    public function test_member_mixed_order_has_independent_counters_with_member_limits(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [, $songA, $songB, $album] = $this->placeMixedOrder($user, $user->email);

        $this->assertSame([10, 10, 10], [$songA->max_downloads, $songB->max_downloads, $album->max_downloads]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->get(route('music.tracks.download', $this->songA->track))->assertOk();
        }

        $this->actingAs($user)->get(route('music.tracks.download', $this->songA->track))
            ->assertSessionHas('download_error', 'You\'ve reached the download limit for this track.');

        $this->actingAs($user)->get(route('music.tracks.download', $this->songB))->assertOk();
        $this->discardZip($this->actingAs($user)->get(route('account.orders.album-zip', $album))->assertOk());

        $this->assertSame(9, $songB->refresh()->remainingDownloads());
        $this->assertSame(9, $album->refresh()->remainingDownloads());

        $this->actingAs($user)->get(route('music.tracks.download', $this->album->tracks()->first()))
            ->assertSessionHas('download_error', 'You don\'t have download access to this track.');
    }

    public function test_member_orders_page_shows_song_links_and_one_zip_and_no_album_track_links(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [, , , $album] = $this->placeMixedOrder($user, $user->email);

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertSee(route('music.tracks.download', $this->songA->track), false)
            ->assertSee(route('music.tracks.download', $this->songB), false)
            ->assertSee(route('account.orders.album-zip', $album), false)
            ->assertDontSee(route('music.tracks.download', $this->album->tracks()->first()), false);
    }

    public function test_three_individual_songs_in_one_order_each_get_their_own_allowance(): void
    {
        $songC = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($this->songA, null, 'guest@example.com');
        app(AddToCartAction::class)->handle($order, $this->songB);
        app(AddToCartAction::class)->handle($order, $songC);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_three', 'evt_three');

        $this->assertCount(3, $issued);
        $this->assertSame([5, 5, 5], collect($issued)->map(fn ($i) => $i->entitlement->max_downloads)->all());
        $this->assertCount(3, collect($issued)->map(fn ($i) => $i->entitlement->public_id)->unique());
    }
}
