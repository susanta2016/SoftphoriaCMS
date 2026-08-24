<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Category;
use App\Models\Tag;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastLinkProvider;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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

/**
 * Streaming links are a plain array field (not a Filament relationship
 * repeater) so Create/UpdatePodcastEpisodeAction — not Filament's automatic
 * relationship save — own reconciling podcast_links, same reasoning as
 * PageForm's sections Repeater.
 *
 * Client-confirmed 2026-08-24: an Episode has exactly one streaming link in
 * the admin UI — a flat provider/URL pair (state paths `links.0.provider`/
 * `links.0.url`), not a Repeater, so there is no "Add streaming link"
 * option. The podcast_links table/`links()` HasMany stay multi-row (shared
 * shape with Music's streaming links, and no reason to redesign the schema
 * for a UI-only rule). See EditPodcastEpisode's mutateFormDataBeforeFill()
 * for how a legacy episode with more than one stored link is represented
 * here (its first link only) and
 * SavesPodcastEpisodeRelations::syncPrimaryLink() for why saving never
 * deletes any additional legacy link this form can't show — only removing
 * Video/the multi-add UI was asked for, not destroying pre-existing data.
 *
 * Video is permanently removed from this form (client-confirmed
 * 2026-08-24, not merely presentation-mode) — unlike Track, it is not
 * gated behind config('admin_ui.show_video_fields'). video_media_id and
 * the video() relation stay on the model/DB untouched.
 */
class PodcastEpisodeForm
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
                                        ->unique(table: PodcastEpisode::class, column: 'slug', ignoreRecord: true)
                                        ->helperText('Auto-filled from the title — override it if needed.'),
                                    TextInput::make('embed_url')
                                        ->label('Embed URL')
                                        ->url()
                                        ->maxLength(255)
                                        ->helperText('An external streaming source for this episode (Spotify, Apple Podcasts, etc.) — never a download source.'),
                                    MediaPicker::make('audio_media_id', 'Audio File', MediaCategory::Audio)
                                        ->columnSpanFull(),
                                    TextInput::make('season')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('Leave blank if this episode isn\'t part of a season.'),
                                    TextInput::make('episode_number')
                                        ->label('Episode Number')
                                        ->numeric()
                                        ->minValue(1),
                                    MediaPicker::make('artwork_media_id', 'Artwork', MediaCategory::Image)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('About This Episode')
                                ->schema([
                                    RichEditorMediaAttachments::configure(
                                        RichEditor::make('description')->hiddenLabel()->columnSpanFull()
                                    ),
                                ]),

                            Section::make('Topics')
                                ->description('Powers the list page\'s Topic filter and the episode\'s Key Themes.')
                                ->columns(2)
                                ->schema([
                                    Select::make('categoryIds')
                                        ->label('Categories')
                                        ->options(fn (): array => Category::query()->where('type', 'podcast')->orderBy('name')->pluck('name', 'id')->all())
                                        ->multiple()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')->required()->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                                            TextInput::make('slug')->required()->maxLength(255),
                                        ])
                                        ->createOptionUsing(fn (array $data): int => Category::query()->create(['type' => 'podcast', ...$data])->getKey())
                                        ->dehydrated(),
                                    Select::make('tagIds')
                                        ->label('Key Themes (Tags)')
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

                            Section::make('Streaming Link')
                                ->description('Where listeners can play this episode.')
                                ->columns(2)
                                ->schema([
                                    Select::make('links.0.provider')
                                        ->label('Provider')
                                        ->options(PodcastLinkProvider::options()),
                                    TextInput::make('links.0.url')
                                        ->label('URL')
                                        ->url()
                                        ->maxLength(255),
                                ]),

                            Section::make('SEO')
                                ->description('Independent per-episode metadata (title, description, canonical, Open Graph, Twitter card, structured data).')
                                ->collapsed()
                                ->schema([
                                    SeoFields::metaTitle(),
                                    ...SeoFields::canonicalUrlFields(
                                        'seo.canonical_url',
                                        'seo.canonical_url_is_auto',
                                        fn (Get $get): string => 'podcast/'.(string) ($get('slug') ?? ''),
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
                            Section::make('Publication')
                                ->schema([
                                    Select::make('podcast_id')
                                        ->label('Podcast')
                                        ->options(fn (): array => Podcast::query()->orderBy('title')->pluck('title', 'id')->all())
                                        ->searchable()
                                        ->required()
                                        ->helperText('Create a Podcast show first under Podcast > Shows.'),
                                    Select::make('status')
                                        ->options(PodcastEpisodeStatus::options())
                                        ->default(PodcastEpisodeStatus::Draft->value)
                                        ->required()
                                        ->live(),
                                    DatePicker::make('publish_date')
                                        ->label('Publish Date')
                                        ->native(false)
                                        ->helperText('The date shown to visitors (e.g. "May 20, 2024").'),
                                    DateTimePicker::make('publish_at')
                                        ->label('Publish At')
                                        ->native(false)
                                        ->helperText('Required to schedule an episode — a scheduled command publishes it automatically once this passes.')
                                        ->visible(fn (Get $get): bool => $get('status') === PodcastEpisodeStatus::Scheduled->value)
                                        ->required(fn (Get $get): bool => $get('status') === PodcastEpisodeStatus::Scheduled->value),
                                ]),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),
                    ]),
            ]);
    }
}
