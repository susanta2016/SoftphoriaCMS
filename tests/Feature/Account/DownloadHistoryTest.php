<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * /account/downloads — the registered member's own download history,
 * covering both Purchase and Pro Membership grants. Distinct from
 * OrderHistoryTest's /account/orders: a Pro subscriber with no purchases has
 * nothing there but still downloads tracks, which is the gap this page
 * closes. Reads DownloadLog only; the actual download endpoint is already
 * fully covered by TrackDownloadControllerTest.
 */
class DownloadHistoryTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.downloads'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_with_no_downloads_sees_an_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.downloads'));

        $response->assertOk();
        $response->assertSee("haven't downloaded any tracks", false);
    }

    public function test_a_pro_members_membership_download_appears_in_their_history(): void
    {
        $user = User::factory()->create();
        $single = $this->readySingle();

        DownloadLog::query()->create([
            'user_id' => $user->getKey(),
            'access_type' => DownloadAccessType::Membership,
            'track_id' => $single->track->getKey(),
            'media_id' => $single->track->audio_media_id,
            'status' => DownloadLogStatus::Succeeded,
        ]);

        $response = $this->actingAs($user)->get(route('account.downloads'));

        $response->assertOk();
        $response->assertSee($single->track->title);
        $response->assertSee('Pro Membership');
        $response->assertSee('1 download');
    }

    public function test_a_denied_download_attempt_is_not_counted(): void
    {
        $user = User::factory()->create();
        $single = $this->readySingle();

        DownloadLog::query()->create([
            'user_id' => $user->getKey(),
            'access_type' => null,
            'track_id' => $single->track->getKey(),
            'status' => DownloadLogStatus::Denied,
            'denial_reason' => 'not_entitled',
        ]);

        $response = $this->actingAs($user)->get(route('account.downloads'));

        $response->assertOk();
        $response->assertSee("haven't downloaded any tracks", false);
    }

    public function test_a_user_never_sees_another_users_downloads(): void
    {
        $userA = User::factory()->create();
        $single = $this->readySingle();

        DownloadLog::query()->create([
            'user_id' => $userA->getKey(),
            'access_type' => DownloadAccessType::Membership,
            'track_id' => $single->track->getKey(),
            'media_id' => $single->track->audio_media_id,
            'status' => DownloadLogStatus::Succeeded,
        ]);

        $userB = User::factory()->create();

        $response = $this->actingAs($userB)->get(route('account.downloads'));

        $response->assertOk();
        $response->assertDontSee($single->track->title);
    }

    public function test_the_downloads_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.downloads'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_downloads_page_is_absent_from_the_sitemap(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee(route('account.downloads'), false);
    }
}
