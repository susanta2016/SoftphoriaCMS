<?php

namespace App\Modules\Music\Filament\Resources\Singles\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Music\Enums\MusicLinkProvider;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Single;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * See Album's AlbumForm — identical shape (Single mirrors Album's
 * release-level fields exactly per Master Scope Specification §8.1). The
 * song itself (lyrics/song story/credits/details) is added separately under
 * Music > Tracks once this single is saved, same as an album's tracks.
 */
class SingleForm
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
                                        ->unique(table: Single::class, column: 'slug', ignoreRecord: true)
                                        ->helperText('Auto-filled from the title — override it if needed.'),
                                    DatePicker::make('release_date')
                                        ->label('Release Date')
                                        ->native(false),
                                    MediaPicker::make('cover_media_id', 'Cover Artwork', MediaCategory::Image)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('About This Single')
                                ->schema([
                                    RichEditorMediaAttachments::configure(
                                        RichEditor::make('description')->hiddenLabel()->columnSpanFull()
                                    ),
                                ]),

                            Section::make('Song')
                                ->description('Add this single\'s song — lyrics, song story, credits — under Music > Tracks once it\'s saved.')
                                ->schema([]),

                            Section::make('Streaming Links')
                                ->description('Where listeners can play this single.')
                                ->schema([
                                    Repeater::make('links')
                                        ->hiddenLabel()
                                        ->reorderable()
                                        ->defaultItems(0)
                                        ->addActionLabel('Add streaming link')
                                        ->itemLabel(fn (array $state): ?string => isset($state['provider'])
                                            ? MusicLinkProvider::from($state['provider'])->getLabel()
                                            : null)
                                        ->schema([
                                            Select::make('provider')
                                                ->options(MusicLinkProvider::options())
                                                ->required(),
                                            TextInput::make('url')
                                                ->url()
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                ]),

                            Section::make('SEO')
                                ->description('Independent per-single metadata (title, description, canonical, Open Graph, Twitter card, structured data).')
                                ->collapsed()
                                ->schema([
                                    SeoFields::metaTitle(),
                                    ...SeoFields::canonicalUrlFields(
                                        'seo.canonical_url',
                                        'seo.canonical_url_is_auto',
                                        fn (Get $get): string => 'music/'.(string) ($get('slug') ?? ''),
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
                                        ->options(ReleaseStatus::options())
                                        ->default(ReleaseStatus::Draft->value)
                                        ->required()
                                        ->live(),
                                    DateTimePicker::make('publish_at')
                                        ->label('Publish At')
                                        ->native(false)
                                        ->helperText('Required to schedule a release — a scheduled command publishes it automatically once this passes.')
                                        ->visible(fn (Get $get): bool => $get('status') === ReleaseStatus::Scheduled->value)
                                        ->required(fn (Get $get): bool => $get('status') === ReleaseStatus::Scheduled->value),
                                    Toggle::make('is_featured')
                                        ->label('Featured Release')
                                        ->helperText('Shown as the Featured Release on the Music page.'),
                                ]),

                            self::readinessSection(),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),
                    ]),
            ]);
    }

    /**
     * ADMIN-008 §17/§8: read-only, computed live from
     * CheckSingleReadinessAction and GlobalPricingResolver — never a stored
     * field, and never a price editable here (Global Pricing stays the only
     * pricing source). Hidden on Create — a Single has no Track yet, so
     * readiness is meaningless until the record and its Track both exist.
     */
    private static function readinessSection(): Section
    {
        return Section::make('Purchase Readiness')
            ->visible(fn (?Single $record): bool => $record !== null)
            ->schema([
                Placeholder::make('purchase_readiness')
                    ->hiddenLabel()
                    ->content(function (?Single $record): HtmlString {
                        if ($record === null) {
                            return new HtmlString('');
                        }

                        $result = app(CheckSingleReadinessAction::class)->handle($record);
                        $price = app(GlobalPricingResolver::class)->perSongPrice();

                        $lines = $result->ready
                            ? ['✓ Ready for purchase']
                            : array_merge(['✗ Not ready for purchase'], array_map(fn ($issue) => "✗ {$issue}", $result->issues));

                        $lines[] = 'Global Single Price: $'.$price;

                        return new HtmlString(implode('<br>', array_map('e', $lines)));
                    }),
            ]);
    }
}
