<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction;
use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Commerce\Actions\Order\MarkOrderPaidAction;
use App\Modules\Commerce\Filament\Resources\DownloadLogs\Pages\ListDownloadLogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

class DownloadLogResourceTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_non_admin_cannot_access_download_history(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/download-logs');

        $response->assertForbidden();
    }

    public function test_admin_can_see_download_history_without_the_raw_token_ever_appearing(): void
    {
        $admin = $this->admin();
        $single = $this->readySingle();

        // Guest purchase — the only case that actually generates a token —
        // so the "never leaked" assertion below is meaningful.
        $order = app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com');
        $issued = app(MarkOrderPaidAction::class)->handle($order, 'pi_1', 'evt_1');
        $token = $issued[0]->plainGuestToken;

        app(AuthorizeTrackDownloadAction::class)->authorizeForGuest($single->track, $issued[0]->entitlement->public_id, $token);

        $response = Livewire::actingAs($admin)->test(ListDownloadLogs::class);

        $response->assertOk();
        $response->assertDontSeeText($token);
        $response->assertDontSeeText($issued[0]->entitlement->refresh()->access_token_hash);
    }
}
