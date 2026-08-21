<?php

namespace App\Modules\Music\Filament\Resources\Tracks\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Category;
use App\Models\Tag;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Lyrics/song story/credits/categoryIds/tagIds/seo are all separate tables,
 * not tracks columns, so — same reasoning as Podcast's PodcastEpisodeForm —
 * Create/UpdateTrackAction (not Filament's automatic relationship save) own
 * reconciling them. "release" is a virtual field only; see
 * SavesTrackRelations::resolveRelease().
 */
class TrackForm
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
                                        ->unique(table: Track::class, column: 'slug', ignoreRecord: true)
                                        ->helperText('Auto-filled from the title — override it if needed.'),
                                    TextInput::make('duration_seconds')
                                        ->label('Length (seconds)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->live()
                                        ->hint(fn ($state): string => gmdate('i:s', (int) ($state ?: 0))),
                                    TextInput::make('written_by')
                                        ->label('Written By')
                                        ->maxLength(255),
                                    TextInput::make('produced_by')
                                        ->label('Produced By')
                                        ->maxLength(255),
                                    TextInput::make('isrc')
                                        ->label('ISRC')
                                        ->maxLength(255),
                                    TextInput::make('video_embed_url')
                                        ->label('Embedded Video URL')
                                        ->url()
                                        ->maxLength(255)
                                        ->helperText('An external video source (YouTube/Vimeo) — never a download source.')
                                        ->columnSpanFull(),
                                    MediaPicker::make('audio_media_id', 'Audio File', MediaCategory::Audio)
                                        ->columnSpanFull(),
                                    MediaPicker::make('video_media_id', 'Video File', MediaCategory::Video)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('About This Song')
                                ->description('A short, general description for the listening page — distinct from the Song Story below.')
                                ->schema([
                                    RichEditorMediaAttachments::configure(
                                        RichEditor::make('description')->hiddenLabel()->columnSpanFull()
                                    ),
                                ]),

                            Section::make('Lyrics')
                                ->columns(2)
                                ->schema([
                                    Select::make('lyrics.visibility')
                                        ->label('Visibility')
                                        ->options(['public' => 'Public', 'private' => 'Private'])
                                        ->default('public')
                                        ->required(),
                                    Textarea::make('lyrics.content')
                                        ->hiddenLabel()
                                        ->rows(10)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Song Story')
                                ->schema([
                                    Textarea::make('song_story.content')
                                        ->hiddenLabel()
                                        ->rows(6)
                                        ->columnSpanFull(),
                                    MediaPicker::make('song_story.media_id', 'Accompanying Image', MediaCategory::Image)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Credits')
                                ->schema([
                                    Repeater::make('credits')
                                        ->hiddenLabel()
                                        ->reorderable()
                                        ->defaultItems(0)
                                        ->addActionLabel('Add credit')
                                        ->itemLabel(fn (array $state): ?string => $state['role'] ?? null)
                                        ->schema([
                                            TextInput::make('role')
                                                ->required()
                                                ->maxLength(255)
                                                ->placeholder('Vocals, Lyrics, Composition'),
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                ]),

                            Section::make('Genre & Tags')
                                ->columns(2)
                                ->schema([
                                    Select::make('categoryIds')
                                        ->label('Genres')
                                        ->options(fn (): array => Category::query()->where('type', 'music')->orderBy('name')->pluck('name', 'id')->all())
                                        ->multiple()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->required()->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                            TextInput::make('slug')->required()->maxLength(255),
                                        ])
                                        ->createOptionUsing(fn (array $data): int => Category::query()->create(['type' => 'music', ...$data])->getKey())
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
                                ]),

                            Section::make('SEO')
                                ->description('Independent per-song metadata (title, description, canonical, Open Graph, Twitter card, structured data).')
                                ->collapsed()
                                ->schema([
                                    SeoFields::metaTitle(),
                                    ...SeoFields::canonicalUrlFields(
                                        'seo.canonical_url',
                                        'seo.canonical_url_is_auto',
                                        fn (Get $get): string => 'music/tracks/'.(string) ($get('slug') ?? ''),
                                    ),
                                    SeoFields::metaDescription()->columnSpanFull(),
                                    SeoFields::metaKeywords(),
                                    TextInput::make('seo.robots')->label('Robots')->maxLength(255)->placeholder('index, follow'),
                                    TextInput::make('seo.og_title')->label('Open Graph title')->maxLength(255),
                                    MediaPicker::make('seo.og_image_media_id', 'Open Graph image'),
                                    TextInput::make('seo.twitter_title')->label('Twitter title')->maxLength(255),
                                    MediaPicker::make('seo.twitter_image_media_id', 'Twitter image'),
                                ])
                                ->columns(2),
                        ])->columnSpan(['default' => 12, 'lg' => 7]),

                        Group::make([
                            Section::make('Release')
                                ->schema([
                                    Select::make('release')
                                        ->label('Album / Single')
                                        ->options(fn (): array => self::releaseOptions())
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->helperText('Create the Album or Single first under Music > Albums / Music > Singles.'),
                                    TextInput::make('track_number')
                                        ->label('Track Number')
                                        ->numeric()
                                        ->minValue(1)
                                        ->visible(fn (Get $get): bool => str_starts_with((string) $get('release'), 'album:'))
                                        ->unique(
                                            table: Track::class,
                                            column: 'track_number',
                                            ignoreRecord: true,
                                            modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                                                $release = (string) $get('release');

                                                if (! str_starts_with($release, 'album:')) {
                                                    return $rule->where('id', 0);
                                                }

                                                return $rule->where('album_id', (int) substr($release, strlen('album:')));
                                            },
                                        )
                                        ->helperText('Position within the album\'s tracklist. Must be unique within the album. Hidden for singles.'),
                                    Select::make('status')
                                        ->options(TrackStatus::options())
                                        ->default(TrackStatus::Draft->value)
                                        ->required(),
                                ]),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),
                    ]),
            ]);
    }

    /**
     * Grouped Select options shaped "album:{id}"/"single:{id}" — the only
     * way this schema represents "belongs to exactly one of two parent
     * tables" without introducing true polymorphism into a schema that
     * elsewhere (music_categories, music_tags, music_streaming_links) has
     * consistently used a dual nullable FK instead.
     *
     * @return array<string, array<string, string>>
     */
    public static function releaseOptions(): array
    {
        return [
            'Albums' => Album::query()->orderBy('title')->get()
                ->mapWithKeys(fn (Album $album): array => ["album:{$album->id}" => $album->title])
                ->all(),
            'Singles' => Single::query()->orderBy('title')->get()
                ->mapWithKeys(fn (Single $single): array => ["single:{$single->id}" => $single->title])
                ->all(),
        ];
    }
}
