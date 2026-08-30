<?php

namespace Tests\Feature\Music;

use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * The single/track listening page's "Download" link for an active Pro
 * Member (replacing the old static "Included with your Pro Membership"
 * text) and the HTTP endpoint it points at — see
 * App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction, whose
 * own docblock names this "a future download controller" that has now been
 * built. Purchase-based download authorization is already fully covered by
 * Tests\Feature\Commerce\DownloadAuthorizationTest; this file only exercises
 * the membership grant and the controller/route/view wiring around it.
 */
class TrackDownloadControllerTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    private function proMember(): User
    {
        $user = $this->admin();
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
        ]);

        return $user;
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $single = $this->readySingle();

        $response = $this->get(route('music.tracks.download', $single->track));

        $response->assertRedirect(route('login'));
    }

    public function test_an_active_pro_member_can_download_the_track(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $user = $this->proMember();
        $single = $this->readySingle();

        $response = $this->actingAs($user)->get(route('music.tracks.download', $single->track));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $log = DownloadLog::query()->where('status', DownloadLogStatus::Succeeded)->first();
        $this->assertNotNull($log);
        $this->assertSame(DownloadAccessType::Membership, $log->access_type);
        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertSame($single->track->getKey(), $log->track_id);
    }

    public function test_a_user_without_entitlement_or_membership_is_redirected_back_with_an_error(): void
    {
        $user = $this->admin();
        $single = $this->readySingle();

        $response = $this->actingAs($user)->from(route('music.singles.show', $single))
            ->get(route('music.tracks.download', $single->track));

        $response->assertRedirect(route('music.singles.show', $single));
        $response->assertSessionHas('download_error');
        $this->assertSame(1, DownloadLog::query()->where('status', DownloadLogStatus::Denied)->count());
    }

    public function test_the_single_page_shows_a_download_link_instead_of_the_included_badge_for_a_pro_member(): void
    {
        $user = $this->proMember();
        $single = $this->readySingle();

        $response = $this->actingAs($user)->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('Download');
        $response->assertSee(route('music.tracks.download', $single->track), false);
        $response->assertDontSee('Included with your Pro Membership');
        $response->assertDontSee(route('cart.add'), false);
    }

    public function test_the_download_button_renders_after_the_play_now_button(): void
    {
        $user = $this->proMember();
        $single = $this->readySingle();

        $response = $this->actingAs($user)->get(route('music.singles.show', $single));

        $content = $response->getContent();
        $playPosition = strpos($content, 'Play Now');
        $downloadPosition = strpos($content, 'Download');

        $this->assertNotFalse($playPosition);
        $this->assertNotFalse($downloadPosition);
        $this->assertLessThan($downloadPosition, $playPosition);
    }

    public function test_the_album_page_shows_a_download_all_button_instead_of_the_included_badge_for_a_pro_member(): void
    {
        $user = $this->proMember();
        $album = $this->readyAlbum();

        $response = $this->actingAs($user)->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('data-music-download-all', false);
        $response->assertSee(route('music.tracks.download', $album->tracks->first()), false);
        $response->assertDontSee('Included with your Pro Membership');
    }

    public function test_an_active_pro_member_can_download_an_album_track_via_the_same_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        $user = $this->proMember();
        $album = $this->readyAlbum();

        $response = $this->actingAs($user)->get(route('music.tracks.download', $album->tracks->first()));

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $log = DownloadLog::query()->where('status', DownloadLogStatus::Succeeded)->first();
        $this->assertNotNull($log);
        $this->assertSame(DownloadAccessType::Membership, $log->access_type);
        $this->assertSame($album->tracks->first()->getKey(), $log->track_id);
    }

    public function test_a_non_subscriber_sees_a_buy_button_not_the_download_all_button(): void
    {
        $user = $this->admin();
        $album = $this->readyAlbum();

        $response = $this->actingAs($user)->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('Buy —', false);
        $response->assertDontSee('data-music-download-all', false);
        $response->assertDontSee('Included with your Pro Membership');
    }
}
