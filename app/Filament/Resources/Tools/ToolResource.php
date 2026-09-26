<?php

namespace App\Filament\Resources\Tools;

use App\Enums\ToolStatus;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Filament\Resources\Tools\Schemas\ToolForm;
use App\Models\Tool;
use App\Models\User;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Tools → Tools: landing-page content, SEO and publication for tools whose
 * functionality ships as application code (App\Tools\ToolRegistry).
 * Draft → Preview → Publish; Unpublish takes a tool down without deleting
 * it. Publishing only changes the row — see ToolPublisher.
 */
class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Tools';

    protected static ?string $slug = 'tools';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ToolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $registry = app(ToolRegistry::class);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tool $record): ?string => $record->short_description ? Str::limit($record->short_description, 80) : null),
                TextColumn::make('slug')
                    ->prefix('/tools/')
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('functionality')
                    ->label('Functionality')
                    ->formatStateUsing(fn (?string $state): string => $registry->find($state)?->name() ?? "Missing: {$state}")
                    ->description(fn (Tool $record): ?string => ($module = $registry->find($record->functionality)) ? 'v'.$module->version() : null)
                    ->color(fn (?string $state): ?string => $registry->has($state) ? null : 'danger')
                    ->placeholder('Not selected'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_featured')
                    ->label('Featured')
                    ->afterStateUpdated(fn (Tool $record) => $record->forceFill(['updated_by' => Auth::id()])->saveQuietly()),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ToolStatus::class),
                SelectFilter::make('tool_category_id')->label('Category')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                self::previewAction(),
                ActionGroup::make([
                    self::publishAction(),
                    self::unpublishAction(),
                    self::duplicateAction(),
                    self::deleteAction(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No tools yet')
            ->emptyStateDescription('Add a tool, choose one of the deployed functionalities, write its landing page, preview it and publish.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit' => EditTool::route('/{record}/edit'),
        ];
    }

    /**
     * The real tool page, private and noindex. From the editor it saves the
     * form first, so the preview always shows what was just typed.
     */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (Tool $record, $livewire): ?string => $livewire instanceof EditTool ? null : $record->previewUrl(), shouldOpenInNewTab: true)
            ->action(function (Tool $record, EditTool $livewire): void {
                $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $livewire->js('window.open('.json_encode($record->previewUrl()).', "_blank")');
            });
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->visible(fn (Tool $record): bool => $record->status !== ToolStatus::Published)
            ->requiresConfirmation()
            ->modalHeading(fn (Tool $record): string => "Publish {$record->name}?")
            ->modalDescription(fn (Tool $record): string => 'It becomes public at /tools/'.$record->slug.', and appears on the Tools hub and in the sitemap. Publishing does not change or deploy any code.')
            ->modalSubmitActionLabel('Publish')
            ->action(function (Tool $record, $livewire): void {
                if ($livewire instanceof EditTool) {
                    $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $record->refresh();
                }

                $publisher = app(ToolPublisher::class);
                $missing = $publisher->publish($record, self::actor());

                if ($missing !== []) {
                    Notification::make()
                        ->title('Cannot publish this tool.')
                        ->body(new HtmlString('Please complete:<ul class="mt-1 list-disc ps-5">'.collect($missing)->map(fn (string $item): string => '<li>'.e($item).'</li>')->implode('').'</ul>'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Tool published')
                    ->body($record->url())
                    ->success()
                    ->send();

                if (($recommended = $publisher->missingRecommendations($record)) !== []) {
                    Notification::make()
                        ->title('Published — consider adding')
                        ->body(implode(', ', $recommended))
                        ->warning()
                        ->send();
                }

                if ($livewire instanceof EditTool) {
                    $livewire->refreshFormData(['status', 'published_at']);
                }
            });
    }

    public static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->visible(fn (Tool $record): bool => $record->status === ToolStatus::Published)
            ->requiresConfirmation()
            ->modalDescription('The tool disappears from the site, the Tools hub, related tools and the sitemap straight away. Nothing is deleted — you can publish it again at any time.')
            ->modalSubmitActionLabel('Unpublish')
            ->action(function (Tool $record, $livewire): void {
                app(ToolPublisher::class)->unpublish($record, self::actor());

                Notification::make()->title('Tool unpublished')->success()->send();

                if ($livewire instanceof EditTool) {
                    $livewire->refreshFormData(['status']);
                }
            });
    }

    /**
     * Copies content, SEO, FAQ, CTA and related content into a new Draft;
     * the name, slug and functionality are confirmed first.
     */
    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Duplicate tool')
            ->modalDescription('Creates a new draft with the same content, SEO, FAQ, call to action and related content.')
            ->fillForm(fn (Tool $record): array => [
                'name' => "{$record->name} (copy)",
                'slug' => "{$record->slug}-copy",
                'functionality' => $record->functionality,
            ])
            ->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(table: Tool::class, column: 'slug')
                    ->prefix('/tools/'),
                Select::make('functionality')
                    ->label('Tool functionality')
                    ->options(fn (): array => app(ToolRegistry::class)->options())
                    ->required()
                    ->native(false),
            ])
            ->action(function (Tool $record, array $data, $livewire): void {
                $copy = app(ToolPublisher::class)->duplicate($record, $data['name'], $data['slug'], $data['functionality'], self::actor());

                Notification::make()->title('Draft copy created')->success()->send();

                $livewire->redirect(self::getUrl('edit', ['record' => $copy]));
            });
    }

    /**
     * Only once a tool isn't public — a published tool is unpublished first.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Tool $record): bool => $record->status !== ToolStatus::Published)
            ->requiresConfirmation()
            ->modalDescription('Permanently deletes this tool page, its FAQ and SEO settings. The tool functionality code is not affected.');
    }

    private static function actor(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }
}
