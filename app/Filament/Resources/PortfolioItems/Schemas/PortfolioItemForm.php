<?php

namespace App\Filament\Resources\PortfolioItems\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Shared\Support\Pages\GalleryItemIcons;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortfolioItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Project')
                    ->columnSpan(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('B2B E-Commerce Platform'),

                        TextInput::make('category')
                            ->maxLength(80)
                            ->placeholder('E-Commerce')
                            ->helperText('Optional. Shown as a small label above the title, e.g. "E-Commerce" or "Cloud".'),

                        Textarea::make('summary')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('A sentence or two for the project card.'),

                        TagsInput::make('technologies')
                            ->placeholder('Add a technology and press Enter')
                            ->helperText('Optional. Shown as small tags on the card, e.g. Laravel, AWS.')
                            ->nestedRecursiveRules(['max:40']),

                        TextInput::make('link_url')
                            ->label('Link URL')
                            ->maxLength(255)
                            ->helperText('Optional. A case study page, the live site, or a path like /case-studies/acme. Leave blank for no link.')
                            ->rule('regex:/^(https?:\/\/|\/)/i')
                            ->validationMessages(['regex' => 'Use a full URL (https://…) or a path starting with /.']),
                    ]),

                Section::make('Visibility')
                    ->columnSpan(1)
                    ->components([
                        Toggle::make('is_featured')
                            ->label('Featured on homepage')
                            ->helperText("Shows this project in the homepage's Featured Portfolio section.")
                            ->default(false),

                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText('Unpublished items are hidden everywhere on the site.')
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first. You can also drag rows in the list.'),

                        MediaPicker::make('cover_media_id', 'Cover image', MediaCategory::Image),

                        Select::make('icon')
                            ->label("Icon (used when there's no cover image)")
                            ->options(GalleryItemIcons::groupedOptions())
                            ->searchable()
                            ->native(false),
                    ]),
            ]);
    }
}
