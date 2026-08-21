<?php

namespace App\Modules\Commerce\Filament\Resources\Orders;

use App\Models\User;
use App\Modules\Commerce\Actions\Entitlement\RevokeEntitlementAction;
use App\Modules\Commerce\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Commerce\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Commerce\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Modules\Commerce\Filament\Resources\Orders\Tables\OrdersTable;
use App\Modules\Commerce\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * §1 of the approved brief: list-only + view. No Create/Edit — orders are
 * created exclusively by CreatePendingOrderAction/MarkOrderPaidAction, never
 * hand-built in the admin panel.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    /**
     * §3/§16: "if an entitlement can be revoked, design that safely." Only
     * ever revokes — never deletes the Entitlement or the Order — and only
     * appears once there's something to revoke. Mirrors
     * UserResource::changeStatusAction()'s confirmation-modal shape.
     */
    public static function revokeAccessAction(): Action
    {
        return Action::make('revokeAccess')
            ->label('Revoke Access')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->schema([
                Textarea::make('reason')->label('Reason (optional)'),
            ])
            ->visible(fn (Order $record): bool => $record->items->first()?->entitlement !== null && $record->items->first()?->entitlement?->revoked_at === null)
            ->requiresConfirmation()
            ->modalDescription('Revoke this purchaser\'s access to download this item? This cannot be undone from here.')
            ->action(function (Order $record, array $data): void {
                $entitlement = $record->items->first()?->entitlement;

                if ($entitlement === null) {
                    return;
                }

                /** @var User $actor */
                $actor = Auth::user();

                app(RevokeEntitlementAction::class)->handle($entitlement, $actor, $data['reason'] ?: null);

                Notification::make()->title('Access revoked')->success()->send();
            });
    }
}
