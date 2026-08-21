<?php

namespace App\Modules\Commerce\Filament\Resources\Entitlements;

use App\Models\User;
use App\Modules\Commerce\Actions\Entitlement\RevokeEntitlementAction;
use App\Modules\Commerce\Filament\Resources\Entitlements\Pages\ListEntitlements;
use App\Modules\Commerce\Filament\Resources\Entitlements\Pages\ViewEntitlement;
use App\Modules\Commerce\Filament\Resources\Entitlements\Schemas\EntitlementInfolist;
use App\Modules\Commerce\Filament\Resources\Entitlements\Tables\EntitlementsTable;
use App\Modules\Commerce\Models\Entitlement;
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
 * §3/§16: "Entitlements / Purchases" admin visibility. List + View only —
 * entitlements are only ever created by IssueEntitlementForOrderItemAction
 * and revoked by RevokeEntitlementAction, never hand-edited.
 */
class EntitlementResource extends Resource
{
    protected static ?string $model = Entitlement::class;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Entitlements';

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function infolist(Schema $schema): Schema
    {
        return EntitlementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntitlementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntitlements::route('/'),
            'view' => ViewEntitlement::route('/{record}'),
        ];
    }

    public static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke Access')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->schema([
                Textarea::make('reason')->label('Reason (optional)'),
            ])
            ->visible(fn (Entitlement $record): bool => $record->revoked_at === null)
            ->requiresConfirmation()
            ->modalDescription('Revoke this entitlement? The purchaser will immediately lose download access. This cannot be undone from here.')
            ->action(function (Entitlement $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(RevokeEntitlementAction::class)->handle($record, $actor, $data['reason'] ?: null);

                Notification::make()->title('Access revoked')->success()->send();
            });
    }
}
