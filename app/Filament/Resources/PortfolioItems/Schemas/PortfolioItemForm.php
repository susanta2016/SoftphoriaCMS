<?php

namespace App\Filament\Resources\PortfolioItems\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Seo\SeoFields;
use App\Models\PortfolioItem;
use App\Shared\Support\Pages\GalleryItemIcons;
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

/**
 * A portfolio project: card fields, the /portfolio/{slug} detail page's
 * optional sections, gallery, services, visibility and SEO. Detail text is
 * only ever what an admin enters here — empty sections stay hidden.
 */
class PortfolioItemForm
{
    private const DETAIL_TOOLBAR = [['bold', 'italic', 'link'], ['h3', 'bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']];

    public static function configure(Schema $schema): Schema
    {
        $syncCanonical = fn (?string $slug, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', 'portfolio/'.($slug ?? ''));

        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make([
                    Section::make('Project')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('B2B E-Commerce Platform')
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
                                ->maxLength(160)
                                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                ->unique(table: PortfolioItem::class, column: 'slug', ignoreRecord: true)
                                ->prefix('/portfolio/')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $syncCanonical($state, $set, $get)),

                            TextInput::make('category')
                                ->maxLength(80)
                                ->placeholder('E-Commerce')
                                ->helperText('Optional. Shown as a small label above the title, e.g. "E-Commerce" or "Fintech".'),

                            TextInput::make('link_url')
                                ->label('Project URL')
                                ->maxLength(255)
                                ->helperText('Optional. Adds a "Visit project" button — only use a URL approved for public display.')
                                ->rule('regex:/^(https?:\/\/|\/)/i')
                                ->validationMessages(['regex' => 'Use a full URL (https://…) or a path starting with /.']),

                            Textarea::make('summary')
                                ->label('Short description')
                                ->rows(3)
                                ->maxLength(500)
                                ->helperText('Shown on the project card and at the top of the project page, and used as its search description.')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Project details')
                        ->description('Optional sections on the project page. Only enter information approved for public display — a section left empty is not shown at all.')
                        ->collapsible()
                        ->schema([
                            RichEditor::make('challenge')->toolbarButtons(self::DETAIL_TOOLBAR),
                            RichEditor::make('solution')->toolbarButtons(self::DETAIL_TOOLBAR),
                            RichEditor::make('outcome')->toolbarButtons(self::DETAIL_TOOLBAR),
                        ]),

                    Section::make('Gallery')
                        ->description('Optional images shown on the project page. Use real, approved project images only — give each an alt text in the Media Library.')
                        ->collapsible()
                        ->schema([
                            MediaPicker::make('gallery_media_ids', 'Gallery images', MediaCategory::Image, multiple: true),
                        ]),

                    Section::make('SEO')
                        ->description('Search and social previews. Optional — defaults come from the title, short description and featured image.')
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
                                fn (Get $get): string => 'portfolio/'.($get('slug') ?? ''),
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
                                ->helperText('Unpublished projects are hidden everywhere on the site, including their project page.')
                                ->default(true),

                            Toggle::make('is_featured')
                                ->label('Featured on homepage')
                                ->helperText("Shows this project in the homepage's Featured Portfolio section.")
                                ->default(false),

                            TextInput::make('sort_order')
                                ->label('Sort order')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->helperText('Lower numbers appear first. You can also drag rows in the list.'),
                        ]),

                    Section::make('Featured image')
                        ->schema([
                            MediaPicker::make('cover_media_id', 'Featured image', MediaCategory::Image),

                            Select::make('icon')
                                ->label("Project graphic icon (used when there's no featured image)")
                                ->options(GalleryItemIcons::groupedOptions())
                                ->searchable()
                                ->native(false),
                        ]),

                    Section::make('Services & technologies')
                        ->schema([
                            Select::make('services')
                                ->relationship('services', 'title')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('The Softphoria services this project involved.'),

                            TagsInput::make('technologies')
                                ->placeholder('Add a technology and press Enter')
                                ->helperText('One per tag, with its proper name — e.g. Node.js, Angular, MySQL, AWS.')
                                ->nestedRecursiveRules(['max:40']),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
