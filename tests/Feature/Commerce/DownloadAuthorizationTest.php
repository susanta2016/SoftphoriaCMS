<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §13/§22 of the approved brief — the full server-side download
 * authorization chain, and its audit trail. No HTTP route exists yet
 * (§11: no public download endpoint in this task), so this exercises the
 * Action directly — exactly what a future controller will call.
 */
class DownloadAuthorizationTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_registered_user_with_entitlement_can_download(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForUser($single->track, $user, '127.0.0.1', 'PHPUnit');

        $this->assertTrue($result->authorized);
        $this->assertNotNull($result->media);
        $this->assertSame(1, DownloadLog::query()->where('status', DownloadLogStatus::Succeeded)->count());
    }

    public function test_user_without_entitlement_is_denied_and_logged(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForUser($single->track, $user);

        $this->assertFalse($result->authorized);
        $this->assertSame('not_entitled', $result->denialReason);
        $this->assertSame(1, DownloadLog::query()->where('status', DownloadLogStatus::Denied)->count());
    }

    public function test_guest_with_a_correct_token_can_download(): void
    {
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $issued[0]->entitlement;
        $token = $issued[0]->plainGuestToken;

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForGuest($single->track, $entitlement->public_id, $token);

        $this->assertTrue($result->authorized);
    }

    public function test_guest_with_a_wrong_token_is_denied(): void
    {
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $entitlement = $issued[0]->entitlement;

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForGuest($single->track, $entitlement->public_id, 'totally-wrong-token');

        $this->assertFalse($result->authorized);
        $this->assertSame('not_entitled', $result->denialReason);
    }

    public function test_one_guests_token_cannot_be_used_against_another_guests_entitlement(): void
    {
        $singleA = $this->readySingle();
        $singleB = $this->readySingle();

        $orderA = app(CreatePendingOrderAction::class)->handle($singleA, null, 'guest-a@example.com');
        $issuedA = app(MarkOrderPaidAction::class)->handle($orderA, 'pi_a', 'evt_a');

        $orderB = app(CreatePendingOrderAction::class)->handle($singleB, null, 'guest-b@example.com');
        $issuedB = app(MarkOrderPaidAction::class)->handle($orderB, 'pi_b', 'evt_b');

        // Guest B's token against Guest A's entitlement public_id.
        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForGuest(
            $singleA->track,
            $issuedA[0]->entitlement->public_id,
            $issuedB[0]->plainGuestToken,
        );

        $this->assertFalse($result->authorized);
    }

    public function test_download_counter_is_enforced_and_cannot_be_bypassed(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $issued[0]->entitlement->update(['max_downloads' => 1, 'downloads_used' => 0]);

        $action = app(AuthorizeTrackDownloadAction::class);

        $first = $action->authorizeForUser($single->track, $user);
        $second = $action->authorizeForUser($single->track, $user);

        $this->assertTrue($first->authorized);
        $this->assertFalse($second->authorized);
        $this->assertSame('limit_reached', $second->denialReason);
        $this->assertSame(1, $issued[0]->entitlement->refresh()->downloads_used);
    }

    public function test_expired_entitlement_denies_download(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $order = app(CreatePendingOrderAction::class)->handle($single, $user, $user->email);
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $issued[0]->entitlement->update(['expires_at' => now()->subDay()]);

        $result = app(AuthorizeTrackDownloadAction::class)->authorizeForUser($single->track, $user);

        $this->assertFalse($result->authorized);
    }
}
