<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PoetryProseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        Group::make([
                            Section::make('Details')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('title')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                                            if ($operation === 'create') {
                                                $set('slug', Str::slug($state ?? ''));
                                            }
                                        })
                                        ->columnSpanFull(),
                                    TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: PoetryProse::class, column: 'slug', ignoreRecord: true)
                                        ->helperText('Auto-filled from the title — override it if needed.'),
                                    Select::make('content_type')
                                        ->label('Content Type')
                                        ->options(PoetryProseContentType::options())
                                        ->required()
                                        ->native(false),
                                    Select::make('author_id')
                                        ->label('Author')
                                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable()
                                        ->helperText('The credited byline, if different from whichever admin edits this record.'),
                                    MediaPicker::make('featured_image_id', 'Featured Image', MediaCategory::Image)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Body')
                                ->schema([
                                    RichEditorMediaAttachments::configure(
                                        RichEditor::make('body')->hiddenLabel()->required()->columnSpanFull()
                                    ),
                                ]),

                            Section::make('Classification')
                                ->description('Powers the public list page\'s filters.')
                                ->columns(3)
                                ->schema([
                                    Select::make('categoryIds')
                                        ->label('Categories')
                                        ->options(fn (): array => Category::query()->where('type', 'poetry_prose')->orderBy('name')->pluck('name', 'id')->all())
                                        ->multiple()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->required()->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                            TextInput::make('slug')->required()->maxLength(255),
                                        ])
                                        ->createOptionUsing(fn (array $data): int => Category::query()->create(['type' => 'poetry_prose', ...$data])->getKey())
                                        ->dehydrated(),
                                    Select::make('tagIds')
                                        ->label('Tags')
                                        ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                                        ->multiple()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->required()->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                            TextInput::make('slug')->required()->maxLength(255),
                                        ])
                                        ->createOptionUsing(fn (array $data): int => Tag::query()->create($data)->getKey())
                                        ->dehydrated(),
                                    Select::make('collection_id')
                                        ->label('Collection')
                                        ->options(fn (): array => PoetryProseCollection::query()->orderBy('title')->pluck('title', 'id')->all())
                                        ->searchable()
                                        ->native(false)
                                        ->helperText('One thematic collection per entry.'),
                                ]),

                            Section::make('SEO')
                                ->description('Independent per-entry metadata (title, description, canonical, Open Graph, Twitter card, structured data).')
                                ->collapsed()
                                ->schema([
                                    SeoFields::metaTitle(),
                                    ...SeoFields::canonicalUrlFields(
                                        'seo.canonical_url',
                                        'seo.canonical_url_is_auto',
                                        fn (Get $get): string => 'poetry-prose/'.(string) ($get('slug') ?? ''),
                                    ),
                                    SeoFields::metaDescription()->columnSpanFull(),
                                    SeoFields::metaKeywords(),
                                    SeoFields::indexing(),
                                    TextInput::make('seo.og_title')->label('Open Graph title')->maxLength(255),
                                    MediaPicker::make('seo.og_image_media_id', 'Open Graph image'),
                                    TextInput::make('seo.twitter_title')->label('Twitter title')->maxLength(255),
                                    MediaPicker::make('seo.twitter_image_media_id', 'Twitter image'),
                                ])
                                ->columns(2),
                        ])->columnSpan(['default' => 12, 'lg' => 7]),

                        Group::make([
                            Section::make('Publication')
                                ->schema([
                                    Select::make('status')
                                        ->options(PoetryProseStatus::options())
                                        ->default(PoetryProseStatus::Draft->value)
                                        ->required()
                                        ->native(false),
                                    DatePicker::make('publish_at')
                                        ->label('Publish Date')
                                        ->native(false)
                                        ->helperText('The publication/display/sorting date shown to visitors. Not automated — status alone controls visibility.'),
                                ]),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),
                    ]),
            ]);
    }
}
