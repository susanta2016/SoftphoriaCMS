<?php

namespace Tests\Feature\Commerce;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Modules\Commerce\Actions\Cart\AddToCartAction;
use App\Modules\Commerce\Actions\Download\AuthorizeAlbumZipDownloadAction;
use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Commerce\Actions\Order\ClaimGuestPurchasesAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * A guest's purchases move into their account only once that account has
 * proven the purchase email (email verification) — in place, with the
 * original order, paid_at, download history, counters and expiry intact,
 * and without creating anything new.
 */
class ClaimGuestPurchasesTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'single-audio-bytes');
    }

    /**
     * @return array{0: Order, 1: Entitlement, 2: string}
     */
    private function guestPurchase(Album|Single $item, string $email = 'buyer@example.com'): array
    {
        $order = app(CreatePendingOrderAction::class)->handle($item, null, $email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_'.uniqid(), 'evt_'.uniqid());

        return [$order->refresh(), $issued[0]->entitlement, $issued[0]->plainGuestToken];
    }

    private function pendingUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'status' => UserStatus::PendingVerification->value,
            'email_verified_at' => null,
        ]);
    }

    private function verifyViaLink(User $user): void
    {
        $rawToken = 'claim-test-token-'.uniqid();

        $verification = new EmailVerification;
        $verification->user_id = $user->getKey();
        $verification->email = $user->email;
        $verification->token = hash('sha256', $rawToken);
        $verification->expires_at = now()->addHour();
        $verification->save();

        $this->get(route('verification.verify', ['token' => $rawToken]))->assertOk();
    }

    public function test_verifying_the_purchase_email_claims_a_guest_album_purchase_with_everything_preserved(): void
    {
        $album = $this->readyAlbumWithTracks(2);
        [$order, $entitlement, $token] = $this->guestPurchase($album);

        $zip = app(AuthorizeAlbumZipDownloadAction::class);
        @unlink($zip->authorizeForGuest($entitlement, $token)->zipPath);
        @unlink($zip->authorizeForGuest($entitlement, $token)->zipPath);

        $before = $entitlement->refresh();
        $paidAt = $order->paid_at;
        $user = $this->pendingUser('Buyer@Example.com');

        $this->verifyViaLink($user);

        $order->refresh();
        $entitlement->refresh();

        $this->assertSame($user->getKey(), $order->user_id);
        $this->assertTrue($order->paid_at->equalTo($paidAt));
        $this->assertSame($user->getKey(), $entitlement->user_id);
        $this->assertTrue($entitlement->isAlbumPurchase());
        $this->assertSame(5, $entitlement->max_downloads);
        $this->assertTrue($entitlement->expires_at->equalTo($before->expires_at));
        $this->assertSame(2, $entitlement->zip_downloads_used);
        $this->assertSame(3, $entitlement->remainingDownloads());
        $this->assertSame(2, DownloadLog::query()->where('user_id', $user->getKey())->count());

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Entitlement::query()->count());
    }

    public function test_claimed_album_purchase_is_zip_only_in_the_account_with_its_remaining_quota(): void
    {
        $album = $this->readyAlbumWithTracks(1);
        [, $entitlement, $token] = $this->guestPurchase($album);
        $zip = app(AuthorizeAlbumZipDownloadAction::class);

        for ($i = 0; $i < 4; $i++) {
            @unlink($zip->authorizeForGuest($entitlement, $token)->zipPath);
        }

        $user = $this->pendingUser('buyer@example.com');
        $this->verifyViaLink($user);

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertSee($album->title)
            ->assertSee('1 download left')
            ->assertSee(route('account.orders.album-zip', $entitlement), false)
            ->assertDontSee(route('music.tracks.download', $album->tracks()->first()), false);

        $response = $this->actingAs($user)->get(route('account.orders.album-zip', $entitlement))->assertOk();
        @unlink($response->baseResponse->getFile()->getPathname());

        $this->actingAs($user)->get(route('account.orders.album-zip', $entitlement))
            ->assertSessionHas('download_error', 'You\'ve reached the download limit for this album ZIP.');
    }

    public function test_claimed_song_purchases_keep_their_own_remaining_quotas(): void
    {
        $songA = $this->readySingle();
        $songB = $this->readySingle();
        $order = app(CreatePendingOrderAction::class)->handle($songA, null, 'buyer@example.com');
        app(AddToCartAction::class)->handle($order, $songB);
        $issued = collect(app(MarkOrderPaidAction::class)->handle($order, 'pi_ab', 'evt_ab'))
            ->keyBy(fn ($i) => $i->entitlement->single_id);
        $authorize = app(AuthorizeTrackDownloadAction::class);

        for ($i = 0; $i < 3; $i++) {
            $authorize->authorizeForGuest($songA->track, $issued[$songA->id]->entitlement->public_id, $issued[$songA->id]->plainGuestToken);
        }

        $user = $this->pendingUser('buyer@example.com');
        $this->verifyViaLink($user);

        $entA = $issued[$songA->id]->entitlement->refresh();
        $entB = $issued[$songB->id]->entitlement->refresh();
        $this->assertSame([$user->getKey(), $user->getKey()], [$entA->user_id, $entB->user_id]);
        $this->assertSame(2, $entA->remainingDownloads());
        $this->assertSame(5, $entB->remainingDownloads());

        $this->actingAs($user)->get(route('music.tracks.download', $songA->track))->assertOk();
        $this->actingAs($user)->get(route('music.tracks.download', $songA->track))->assertOk();
        $this->actingAs($user)->get(route('music.tracks.download', $songA->track))
            ->assertSessionHas('download_error', 'You\'ve reached the download limit for this track.');
        $this->actingAs($user)->get(route('music.tracks.download', $songB->track))->assertOk();
    }

    public function test_a_guest_single_purchase_is_claimed_too(): void
    {
        $single = $this->readySingle();
        [$order, $entitlement] = $this->guestPurchase($single);
        $user = $this->pendingUser('buyer@example.com');

        $this->verifyViaLink($user);

        $this->assertSame($user->getKey(), $order->refresh()->user_id);
        $this->assertSame($user->getKey(), $entitlement->refresh()->user_id);
        $this->actingAs($user)->get(route('account.orders'))->assertSee('Ready Single');
    }

    public function test_an_unverified_account_claims_nothing(): void
    {
        [$order, $entitlement] = $this->guestPurchase($this->readySingle());
        $user = $this->pendingUser('buyer@example.com');

        $this->assertSame(0, app(ClaimGuestPurchasesAction::class)->handle($user));

        $this->assertNull($order->refresh()->user_id);
        $this->assertNull($entitlement->refresh()->user_id);
    }

    public function test_an_unverified_account_signing_in_claims_nothing(): void
    {
        [$order] = $this->guestPurchase($this->readySingle());
        User::factory()->create([
            'email' => 'buyer@example.com',
            'status' => 'active',
            'email_verified_at' => null,
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login'), ['email' => 'buyer@example.com', 'password' => 'password123']);

        $this->assertNull($order->refresh()->user_id);
    }

    public function test_a_different_email_never_claims_the_purchase(): void
    {
        [$order] = $this->guestPurchase($this->readySingle(), 'buyer@example.com');
        $user = $this->pendingUser('someone-else@example.com');

        $this->verifyViaLink($user);

        $this->assertNull($order->refresh()->user_id);
    }

    public function test_a_verified_member_signing_in_claims_a_guest_checkout_made_with_their_email(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com', 'status' => 'active', 'password' => Hash::make('password123')]);
        [$order] = $this->guestPurchase($this->readySingle(), ' BUYER@example.com ');

        $this->post(route('login'), ['email' => 'buyer@example.com', 'password' => 'password123'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertSame($user->getKey(), $order->refresh()->user_id);
    }

    public function test_claiming_is_idempotent_and_never_duplicates(): void
    {
        $this->guestPurchase($this->readyAlbumWithTracks(1));
        $this->guestPurchase($this->readySingle());
        $user = $this->pendingUser('buyer@example.com');
        $this->verifyViaLink($user);

        $this->assertSame(0, app(ClaimGuestPurchasesAction::class)->handle($user->refresh()));
        $this->assertSame(2, Order::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(2, Entitlement::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(2, Entitlement::query()->count());
    }

    public function test_another_members_orders_are_never_reassigned(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'status' => 'active']);
        $order = app(CreatePendingOrderAction::class)->handle($this->readySingle(), $owner, 'buyer@example.com');
        app(MarkOrderPaidAction::class)->handle($order, 'pi_o', 'evt_o');

        $user = $this->pendingUser('buyer@example.com');
        $this->verifyViaLink($user);

        $this->assertSame($owner->getKey(), $order->refresh()->user_id);
    }

    public function test_the_old_emailed_guest_link_sends_a_claimed_purchaser_to_their_account(): void
    {
        [$order, $entitlement, $token] = $this->guestPurchase($this->readySingle());
        $user = $this->pendingUser('buyer@example.com');
        $this->verifyViaLink($user);

        $this->get(route('downloads.guest.show', $order).'?'.http_build_query(['t' => ["{$entitlement->public_id}.{$token}"]]))
            ->assertRedirect(route('account.orders'));
    }
}
