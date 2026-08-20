<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\GlobalPricing;
use App\Models\Role;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup's Global Pricing — the site-wide Music (per-song, full
 * album) and Pro Member prices, stored as `pricing` group rows via
 * SettingsRepository, same pattern as Settings/Cookies Policy.
 */
class GlobalPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_global_pricing(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/website-setup/global-pricing');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_global_pricing_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->assertSuccessful();
    }

    public function test_default_prices_match_the_approved_amounts(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->assertSet('data.music_per_song_price', '0.99')
            ->assertSet('data.full_album_price', '9.99')
            ->assertSet('data.pro_member_monthly_price', '7.99');
    }

    public function test_admin_can_update_global_pricing(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->fillForm([
                'music_per_song_price' => '1.29',
                'full_album_price' => '12.99',
                'pro_member_monthly_price' => '9.99',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertSame('1.29', $settings->get('pricing', 'music_per_song_price'));
        $this->assertSame('12.99', $settings->get('pricing', 'full_album_price'));
        $this->assertSame('9.99', $settings->get('pricing', 'pro_member_monthly_price'));
    }

    public function test_prices_must_be_numeric_and_non_negative(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GlobalPricing::class)
            ->fillForm(['music_per_song_price' => -1])
            ->call('save')
            ->assertHasFormErrors(['music_per_song_price']);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
