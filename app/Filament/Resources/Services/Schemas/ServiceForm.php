<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Service;
use App\Shared\Support\Pages\GalleryItemIcons;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $syncCanonical = fn (?string $slug, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', 'services/'.($slug ?? ''));

        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make([
                    Section::make('Service')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(120)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get) use ($syncCanonical): void {
                                    if ($operation === 'create') {
                                        $slug = Str::slug($state ?? '');
                                        $set('slug', $slug);
                                        $syncCanonical($slug, $set, $get);
                                    }
                                }),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(120)
                                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                ->unique(table: Service::class, column: 'slug', ignoreRecord: true)
                                ->prefix('/services/')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $syncCanonical($state, $set, $get)),
                            TextInput::make('tagline')
                                ->maxLength(160)
                                ->placeholder('Fast, accessible websites that turn visitors into customers.')
                                ->helperText('One line under the page heading.')
                                ->columnSpanFull(),
                            Textarea::make('summary')
                                ->required()
                                ->rows(2)
                                ->maxLength(300)
                                ->helperText('Shown on service cards and used as the search description when no meta description is set.')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Overview')
                        ->description('The main text of the service page. Use Heading 2 for sections.')
                        ->schema([
                            RichEditorMediaAttachments::configure(RichEditor::make('body')->hiddenLabel()),
                        ]),

                    Section::make("What's included")
                        ->description('Key deliverables shown as a grid of cards.')
                        ->collapsible()
                        ->schema([
                            Repeater::make('highlights')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('title')->required()->maxLength(80),
                                    Textarea::make('description')->rows(2)->maxLength(250),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->maxItems(12)
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                ->addActionLabel('Add item'),
                        ]),

                    Section::make('FAQs')
                        ->description('Answer common buyer questions. Shown as an accordion and marked up as FAQ structured data for search engines.')
                        ->collapsible()
                        ->schema([
                            Repeater::make('faqs')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('question')->required()->maxLength(200),
                                    Textarea::make('answer')->required()->rows(3)->maxLength(1000),
                                ])
                                ->defaultItems(0)
                                ->maxItems(15)
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                ->addActionLabel('Add question'),
                        ]),

                    Section::make('SEO')
                        ->description('Search and social previews. Optional — defaults come from the title, summary and cover image.')
                        ->collapsible()
                        ->collapsed()
                        ->columns(2)
                        ->schema([
                            SeoFields::metaTitle(),
                            SeoFields::metaDescription()->columnSpanFull(),
                            SeoFields::metaKeywords(),
                            Select::make('seo.robots')
                                ->label('Search engine indexing')
                                ->options(['' => 'Index (default)', 'noindex, follow' => 'Noindex — hide from search results'])
                                ->native(false),
                            ...SeoFields::canonicalUrlFields(
                                'seo.canonical_url',
                                'seo.canonical_url_is_auto',
                                fn (Get $get): string => 'services/'.($get('slug') ?? ''),
                            ),
                            TextInput::make('seo.og_title')->label('Social share title')->maxLength(255),
                            Textarea::make('seo.og_description')->label('Social share description')->rows(2)->maxLength(500)->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Visibility')
                        ->schema([
                            Toggle::make('is_published')
                                ->label('Published')
                                ->helperText('Unpublished services are hidden everywhere on the site.')
                                ->default(true),
                            Toggle::make('is_featured')
                                ->label('Show on homepage')
                                ->helperText("Listed in the homepage's Services section.")
                                ->default(true),
                            TextInput::make('sort_order')
                                ->label('Sort order')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->helperText('Lower numbers appear first. You can also drag rows in the list.'),
                        ]),

                    Section::make('Visuals')
                        ->schema([
                            Select::make('icon')
                                ->options(GalleryItemIcons::groupedOptions())
                                ->searchable()
                                ->native(false)
                                ->helperText('Shown on service cards and the page header.'),
                            MediaPicker::make('cover_media_id', 'Cover image', MediaCategory::Image),
                        ]),

                    Section::make('Technologies')
                        ->schema([
                            TagsInput::make('technologies')
                                ->hiddenLabel()
                                ->placeholder('Add and press Enter')
                                ->helperText('Shown as a tech stack list. Blog posts tagged with the same names appear as related reading.')
                                ->nestedRecursiveRules(['max:40']),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
