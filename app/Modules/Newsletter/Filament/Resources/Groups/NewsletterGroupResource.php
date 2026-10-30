<?php

namespace App\Modules\Newsletter\Filament\Resources\Groups;

use App\Modules\Newsletter\Filament\Concerns\GatedByNewsletterFeature;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\CreateNewsletterGroup;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\EditNewsletterGroup;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\ListNewsletterGroups;
use App\Modules\Newsletter\Filament\Resources\Groups\RelationManagers\SubscribersRelationManager;
use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Shared\Services\AuditLogService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Newsletter Marketing > Subscriber Groups. A group is only a list:
 * deleting one removes its memberships, never subscribers, and is refused
 * while a scheduled/queued/sending campaign still targets it.
 */
class NewsletterGroupResource extends Resource
{
    use GatedByNewsletterFeature;

    protected static ?string $model = NewsletterGroup::class;

    protected static string|UnitEnum|null $navigationGroup = 'Newsletter Marketing';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'newsletter/groups';

    protected static ?string $navigationLabel = 'Subscriber Groups';

    protected static ?string $modelLabel = 'subscriber group';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->rows(3)
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'subscribers',
                'subscribers as active_subscribers_count' => fn (Builder $query) => $query->sendable(),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('subscribers_count')->label('Members')->sortable(),
                TextColumn::make('active_subscribers_count')->label('Sendable')->sortable()
                    ->tooltip('Active and not suppressed — who a campaign to this group would reach.'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(DeleteAction::make()),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No groups yet')
            ->emptyStateDescription('Create groups such as "General Newsletter" or "Product Updates", then add subscribers to them.');
    }

    /**
     * Shared by the table row and the Edit page header.
     */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription('Subscribers in this group are kept — only their membership of this group is removed.')
            ->before(function (NewsletterGroup $record, DeleteAction $action): void {
                if ($record->isInUseByActiveCampaign()) {
                    Notification::make()
                        ->title('This group is used by a scheduled or sending campaign.')
                        ->body('Cancel or unschedule that campaign first.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }
            })
            ->after(fn (NewsletterGroup $record) => app(AuditLogService::class)->record(Auth::user(), 'newsletter_group.deleted', $record, ['name' => $record->name]));
    }

    public static function getRelations(): array
    {
        return [
            SubscribersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterGroups::route('/'),
            'create' => CreateNewsletterGroup::route('/create'),
            'edit' => EditNewsletterGroup::route('/{record}/edit'),
        ];
    }
}
