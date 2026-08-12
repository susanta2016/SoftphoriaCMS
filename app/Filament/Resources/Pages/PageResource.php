<?php

namespace App\Filament\Resources\Pages;

use App\Actions\Menu\CreateMenuItemAction;
use App\Actions\Page\ArchivePageAction;
use App\Actions\Page\DeletePageAction;
use App\Actions\Page\PublishPageAction;
use App\Actions\Page\RestorePageRevisionAction;
use App\Actions\Page\SchedulePageAction;
use App\Actions\Page\UnpublishPageAction;
use App\Enums\MenuItemDestinationType;
use App\Enums\PageStatus;
use App\Exceptions\Menu\InvalidMenuItemDestinationException;
use App\Exceptions\Page\PageInUseByNavigationException;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Filament\Resources\Pages\Tables\PagesTable;
use App\Models\Menu;
use App\Models\Page;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * ADMIN-006 Pages/CMS — generic structured page management, independent
 * from the specialized Music/Podcast/Poetry-Prose/Inspirational-Resources/
 * Community modules (Database Specification §18 opening line; those are
 * never `pages` rows, see PageTemplate). No public rendering here — that
 * is Stage D. See Schemas\PageForm for the "Add to Navigation" convenience
 * action, which is the only connection to ADMIN-006 Navigation and creates
 * an ordinary menu_items row through Navigation's own CreateMenuItemAction
 * rather than a parallel mechanism.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Page $record): bool => $record->status !== PageStatus::Published)
            ->requiresConfirmation()
            ->modalDescription(fn (Page $record): string => "Publish \"{$record->title}\"?")
            ->action(function (Page $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(PublishPageAction::class)->handle($record, $actor);

                Notification::make()->title('Page published')->success()->send();
            });
    }

    public static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Page $record): bool => $record->status === PageStatus::Published)
            ->requiresConfirmation()
            ->modalDescription(fn (Page $record): string => "Unpublish \"{$record->title}\"? It reverts to Draft.")
            ->action(function (Page $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(UnpublishPageAction::class)->handle($record, $actor);

                Notification::make()->title('Page unpublished')->success()->send();
            });
    }

    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('danger')
            ->visible(fn (Page $record): bool => $record->status !== PageStatus::Archived)
            ->requiresConfirmation()
            ->modalDescription(fn (Page $record): string => "Archive \"{$record->title}\"?")
            ->action(function (Page $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(ArchivePageAction::class)->handle($record, $actor);

                Notification::make()->title('Page archived')->success()->send();
            });
    }

    public static function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('Schedule')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->schema([
                DateTimePicker::make('publish_at')
                    ->label('Publish at')
                    ->native(false)
                    ->required()
                    ->after('now'),
            ])
            ->requiresConfirmation()
            ->modalDescription(fn (Page $record): string => "Schedule \"{$record->title}\"? A background task publishes it automatically once the date arrives.")
            ->action(function (Page $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(SchedulePageAction::class)->handle($record, Carbon::parse($data['publish_at']), $actor);

                Notification::make()->title('Page scheduled')->success()->send();
            });
    }

    /**
     * A convenience shortcut into Navigation's own CreateMenuItemAction —
     * never a parallel mechanism. Defaults the menu select to the active
     * Primary Navigation when it is the only suitable menu, per the
     * approved decision, but lets the admin pick another.
     */
    public static function addToNavigationAction(): Action
    {
        return Action::make('addToNavigation')
            ->label('Add to Navigation')
            ->icon(Heroicon::OutlinedBars3)
            ->color('gray')
            ->visible(fn (Page $record): bool => $record->status === PageStatus::Published)
            ->schema([
                Select::make('menu_id')
                    ->label('Menu')
                    ->options(fn (): array => Menu::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Menu::query()->where('is_active', true)->count() === 1
                        ? Menu::query()->where('is_active', true)->value('id')
                        : null)
                    ->required()
                    ->searchable(),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->default(fn (Page $record): string => $record->title),
            ])
            ->action(function (Page $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                $menu = Menu::query()->findOrFail($data['menu_id']);

                try {
                    app(CreateMenuItemAction::class)->handle($menu, [
                        'label' => $data['label'],
                        'destination_type' => MenuItemDestinationType::Page->value,
                        'page_id' => $record->id,
                        'is_enabled' => true,
                    ], $actor);

                    Notification::make()
                        ->title('Added to navigation')
                        ->body("Added to \"{$menu->name}\". Reorder or nest it from the Navigation area.")
                        ->success()
                        ->send();
                } catch (InvalidMenuItemDestinationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Pages soft-delete (real deleted_at, unlike Users' status-based
     * "delete") — but going through Filament's stock DeleteAction would
     * skip DeletePageAction's guard against removing a page still
     * referenced by an enabled navigation item, and the audit log entry.
     */
    public static function deletePageAction(): Action
    {
        return Action::make('deletePage')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Page $record): string => "Delete \"{$record->title}\"?")
            ->action(function (Page $record) {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeletePageAction::class)->handle($record, $actor);

                    Notification::make()->title('Page deleted')->success()->send();

                    return redirect(PageResource::getUrl('index'));
                } catch (PageInUseByNavigationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return null;
                }
            });
    }

    public static function restoreRevisionAction(): Action
    {
        return Action::make('restoreRevision')
            ->label('Restore a Revision')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->schema([
                Select::make('revision_id')
                    ->label('Version')
                    ->options(fn (Page $record): array => $record->revisions()
                        ->latest('version')
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn ($revision) => [$revision->id => "v{$revision->version} — {$revision->created_at?->toDayDateTimeString()}"])
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Restore this version? The current content is saved as a new revision first, so nothing is lost.')
            ->action(function (Page $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                $revision = $record->revisions()->findOrFail($data['revision_id']);

                app(RestorePageRevisionAction::class)->handle($record, $revision, $actor);

                Notification::make()->title('Revision restored')->success()->send();
            });
    }
}
