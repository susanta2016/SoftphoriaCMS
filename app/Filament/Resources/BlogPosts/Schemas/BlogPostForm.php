<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Enums\BlogPostStatus;
use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BlogPostForm
{
    /** Slugs that would collide with other /blog/* routes. */
    public const RESERVED_SLUGS = ['feed', 'category', 'tag'];

    public static function configure(Schema $schema): Schema
    {
        $syncCanonical = fn (?string $slug, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', 'blog/'.($slug ?? ''));

        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make([
                    Section::make('Content')
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(255)
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
                                ->maxLength(255)
                                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                ->notIn(self::RESERVED_SLUGS)
                                ->unique(table: BlogPost::class, column: 'slug', ignoreRecord: true)
                                ->prefix('/blog/')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $syncCanonical($state, $set, $get))
                                ->helperText('Lowercase letters, numbers and dashes. Keep it short and keyword-rich — changing it later breaks existing links.'),
                            Textarea::make('excerpt')
                                ->rows(3)
                                ->maxLength(300)
                                ->helperText('1–2 sentences shown on cards and used as the search-result description when no meta description is set.'),
                            RichEditorMediaAttachments::configure(RichEditor::make('body')->label('Article'))
                                ->required()
                                ->helperText('Use Heading 2 / Heading 3 for sections — they build the table of contents automatically.'),
                        ]),

                    Section::make('SEO')
                        ->description('Search and social previews. Everything here is optional — sensible defaults come from the title, excerpt and cover image.')
                        ->collapsible()
                        ->collapsed()
                        ->columns(2)
                        ->schema([
                            SeoFields::metaTitle(),
                            SeoFields::metaDescription()->columnSpanFull(),
                            SeoFields::metaKeywords(),
                            Select::make('seo.robots')
                                ->label('Search engine indexing')
                                ->options([
                                    '' => 'Index (default)',
                                    'noindex, follow' => 'Noindex — hide from search results',
                                ])
                                ->native(false)
                                ->helperText('Noindex posts are also left out of the sitemap.'),
                            ...SeoFields::canonicalUrlFields(
                                'seo.canonical_url',
                                'seo.canonical_url_is_auto',
                                fn (Get $get): string => 'blog/'.($get('slug') ?? ''),
                            ),
                            TextInput::make('seo.og_title')->label('Social share title')->maxLength(255),
                            Textarea::make('seo.og_description')->label('Social share description')->rows(2)->maxLength(500)->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Publishing')
                        ->schema([
                            Select::make('status')
                                ->options(BlogPostStatus::class)
                                ->default(BlogPostStatus::Draft->value)
                                ->required()
                                ->native(false)
                                ->live(),
                            DateTimePicker::make('published_at')
                                ->label('Publish date')
                                ->seconds(false)
                                ->default(now())
                                ->required(fn (Get $get): bool => self::isPublished($get('status')))
                                ->helperText('A future date schedules the post — it goes live automatically at that time.'),
                            Select::make('author_id')
                                ->label('Author')
                                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->default(fn (): ?int => Auth::id())
                                ->searchable(),
                            Toggle::make('is_featured')
                                ->label('Featured post')
                                ->helperText('Highlighted at the top of the blog page.'),
                            Toggle::make('allow_comments')
                                ->label('Allow comments')
                                ->default(true),
                        ]),

                    Section::make('Organize')
                        ->schema([
                            Select::make('blog_category_id')
                                ->label('Category')
                                ->relationship('category', 'name', fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required()->maxLength(120)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                    TextInput::make('slug')->required()->maxLength(120)
                                        ->unique(table: BlogCategory::class, column: 'slug'),
                                ]),
                            Select::make('tags')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required()->maxLength(60)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                    TextInput::make('slug')->required()->maxLength(60)
                                        ->unique(table: BlogTag::class, column: 'slug'),
                                ]),
                            MediaPicker::make('cover_media_id', 'Cover image', MediaCategory::Image),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    private static function isPublished(mixed $status): bool
    {
        return ($status instanceof BlogPostStatus ? $status : BlogPostStatus::tryFrom((string) $status)) === BlogPostStatus::Published;
    }
}
