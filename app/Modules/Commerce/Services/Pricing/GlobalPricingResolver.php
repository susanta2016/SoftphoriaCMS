<?php

namespace App\Modules\Commerce\Services\Pricing;

use App\Shared\Services\Settings\SettingsRepository;

/**
 * The ONLY place Commerce reads a price from — Website Setup's Global
 * Pricing (App\Filament\Pages\GlobalPricing) remains the single source of
 * truth, unmodified. No price field is added to Album/Single/anything else;
 * every order/order_item/subscription snapshots whatever this resolver
 * returns *at the moment of purchase* and never re-reads it afterward (see
 * OrderItem::unit_price / Subscription::price_at_subscription).
 */
class GlobalPricingResolver
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function perSongPrice(): string
    {
        return (string) $this->settings->get('pricing', 'music_per_song_price', '0.99');
    }

    public function fullAlbumPrice(): string
    {
        return (string) $this->settings->get('pricing', 'full_album_price', '9.99');
    }

    public function proMemberMonthlyPrice(): string
    {
        return (string) $this->settings->get('pricing', 'pro_member_monthly_price', '7.99');
    }
}
