<?php

namespace App\Modules\Commerce\Filament\Resources\Subscriptions;

use App\Modules\Commerce\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Commerce\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Modules\Commerce\Filament\Resources\Subscriptions\Schemas\SubscriptionInfolist;
use App\Modules\Commerce\Filament\Resources\Subscriptions\Tables\SubscriptionsTable;
use App\Modules\Commerce\Models\Subscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * §4/§12 of the approved brief: List + View only — **no Edit page at all**.
 * This is the deliberate answer to "do not allow the Admin UI to manually
 * put the application into a state that contradicts Stripe without a
 * clearly defined administrative override policy": rather than build an
 * override policy nobody has asked for, subscription state simply isn't
 * editable from the admin panel — it's entirely webhook-synced (see
 * app/Modules/Commerce/Actions/Webhook).
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'stripe_subscription_id';

    // Phase 1: no paid membership — UI only, see config/features.php. Also
    // still subject to the broader Commerce menu presentation switch
    // (config/admin_ui.php), unlike Orders/Entitlements/Download History,
    // which stay visible under that flag regardless of this one (Phase 1
    // still sells individual Singles/Albums).
    public static function shouldRegisterNavigation(): bool
    {
        return config('admin_ui.show_commerce_menu') && config('features.member_subscription_enabled');
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubscriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
