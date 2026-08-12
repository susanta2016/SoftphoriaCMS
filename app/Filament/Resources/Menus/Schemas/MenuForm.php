<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Enums\MenuItemDestinationType;
use App\Enums\ModuleKey;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Menu authoring is one page: name/slug/active, plus every item, in a
 * single Repeater. Deliberately NOT a Repeater `->relationship()` — items
 * are reconciled in EditMenu/CreateMenu via CreateMenuItemAction/
 * UpdateMenuItemAction so the destination-exclusivity validation and audit
 * log always run, the same way PageForm's sections go through
 * UpdatePageAction rather than Filament's automatic relationship save
 * (ADMIN-006 Navigation).
 *
 * "Parent" only offers already-saved sibling items (a fresh item added in
 * the same edit session can't yet be selected as another new item's
 * parent — save once, then nest it on the next edit). This keeps the tree
 * structurally correct (parent_id) without a fragile nested-repeater-in-
 * repeater mechanism the Filament version here doesn't need for the
 * currently-approved flat navigation list.
 */
class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Menu')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                                if ($operation === 'create') {
                                    $set('slug', str($state ?? '')->slug()->toString());
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: Menu::class, column: 'slug', ignoreRecord: true)
                            ->helperText('Stage D looks up a menu by this slug (e.g. primary-navigation, footer-navigation).'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Add menu item')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Select::make('destination_type')
                                    ->label('Destination type')
                                    ->options(MenuItemDestinationType::options())
                                    ->default(MenuItemDestinationType::Group->value)
                                    ->required()
                                    ->live()
                                    ->columnSpan(2),

                                Select::make('page_id')
                                    ->label('Page')
                                    ->options(fn (): array => Page::published()->orderBy('title')->pluck('title', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Page->value)
                                    ->visible(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Page->value)
                                    ->helperText('Only published pages are selectable.')
                                    ->columnSpan(2),

                                Select::make('route_key')
                                    ->label('Module')
                                    ->options(ModuleKey::options())
                                    ->required(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Module->value)
                                    ->visible(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Module->value)
                                    ->helperText('Becomes a working link automatically once this module is implemented.')
                                    ->columnSpan(2),

                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Url->value)
                                    ->visible(fn (Get $get): bool => $get('destination_type') === MenuItemDestinationType::Url->value)
                                    ->columnSpan(2),

                                Select::make('target')
                                    ->label('Open in')
                                    ->options(['_self' => 'Same window', '_blank' => 'New window'])
                                    ->default('_self')
                                    ->visible(fn (Get $get): bool => $get('destination_type') !== MenuItemDestinationType::Group->value)
                                    ->columnSpan(1),

                                Select::make('parent_id')
                                    ->label('Parent')
                                    ->options(fn (?Menu $record): array => $record
                                        ? MenuItem::query()
                                            ->where('menu_id', $record->id)
                                            ->orderBy('label')
                                            ->pluck('label', 'id')
                                            ->all()
                                        : [])
                                    ->searchable()
                                    ->helperText('A newly-added item must be saved once before it can be chosen as another item\'s parent. An item cannot be its own parent.')
                                    ->columnSpan(1),

                                Toggle::make('is_enabled')
                                    ->label('Enabled')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(1),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
