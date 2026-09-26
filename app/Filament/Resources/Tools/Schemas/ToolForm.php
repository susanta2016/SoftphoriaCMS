<?php

namespace App\Filament\Resources\Tools\Schemas;

use App\Enums\MediaCategory;
use App\Enums\ToolStatus;
use App\Filament\Resources\Tools\ToolResource;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Service;
use App\Models\Tool;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Pages\GalleryItemIcons;
use App\Tools\ToolRegistry;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The tool editor: landing-page content, SEO, FAQ, related content and CTA
 * in tabs, with the publication panel (Preview / Publish / Unpublish) in the
 * sidebar. The functionality itself is only *selected* here, from the
 * functionalities deployed in the codebase — never edited.
 */
class ToolForm
{
    public static function configure(Schema $schema): Schema
    {
        $syncCanonical = fn (?string $slug, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', 'tools/'.($slug ?? ''));
        $registry = app(ToolRegistry::class);

        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Tabs::make('Tool')
                    ->persistTabInQueryString()
                    ->columnSpan(['lg' => 2])
                    ->tabs([
                        Tab::make('General')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Tool name')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get) use ($syncCanonical): void {
                                        if ($operation !== 'create') {
                                            return;
                                        }

                                        $slug = Str::slug($state ?? '');
                                        $set('slug', $slug);
                                        $syncCanonical($slug, $set, $get);

                                        if (blank($get('heading'))) {
                                            $set('heading', $state);
                                        }
                                        if (blank($get('seo.meta_title')) && filled($state)) {
                                            $siteName = app(SettingsRepository::class)->get('general', 'site_name') ?: config('app.name');
                                            $set('seo.meta_title', "{$state} | {$siteName}");
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(120)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->unique(table: Tool::class, column: 'slug', ignoreRecord: true)
                                    ->prefix('/tools/')
                                    ->helperText(fn (?Tool $record): ?string => $record?->published_at
                                        ? 'This tool has been public: changing the slug 301-redirects the old URL to the new one.'
                                        : 'Lowercase words separated by hyphens.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => $syncCanonical($state, $set, $get)),
                                Select::make('tool_category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name', fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText('Required to publish.'),
                                Select::make('icon')
                                    ->options(GalleryItemIcons::groupedOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Shown on tool cards and the page header.'),
                                Textarea::make('short_description')
                                    ->rows(2)
                                    ->maxLength(300)
                                    ->helperText('One or two sentences for tool cards on /tools. Also the default meta description.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                        if (blank($get('seo.meta_description')) && filled($state)) {
                                            $set('seo.meta_description', $state);
                                        }
                                    })
                                    ->columnSpanFull(),
                                Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Shown in the Featured section of /tools.'),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ]),

                        Tab::make('Functionality')
                            ->schema([
                                Select::make('functionality')
                                    ->label('Tool functionality')
                                    ->options(fn (): array => $registry->options())
                                    ->native(false)
                                    ->live()
                                    ->placeholder('Select the deployed functionality')
                                    ->helperText('The working tool is application code, deployed through Git. New functionalities appear here automatically once deployed.'),
                                TextEntry::make('functionality_details')
                                    ->hiddenLabel()
                                    ->state(function (Get $get) use ($registry): string {
                                        $module = $registry->find($get('functionality'));

                                        if ($module === null) {
                                            return filled($get('functionality'))
                                                ? "The functionality \"{$get('functionality')}\" is not available in this deployment — this tool cannot be published."
                                                : 'No functionality selected yet.';
                                        }

                                        return "{$module->description()} Identifier: {$module->key()} · Version {$module->version()}";
                                    })
                                    ->color(fn (Get $get): string => filled($get('functionality')) && ! $registry->has($get('functionality')) ? 'danger' : 'gray'),
                            ]),

                        Tab::make('Content')
                            ->schema([
                                TextInput::make('heading')
                                    ->label('Page heading (H1)')
                                    ->maxLength(160)
                                    ->helperText('Required to publish. Usually the tool name, phrased the way people search for it.'),
                                Textarea::make('introduction')
                                    ->rows(3)
                                    ->maxLength(600)
                                    ->helperText('A short paragraph shown above the tool. Keep it brief — the tool should be visible straight away.'),
                                RichEditorMediaAttachments::configure(RichEditor::make('how_it_works')->label('How it works'))
                                    ->helperText('Shown below the tool: how to use it and how results are calculated.'),
                                RichEditorMediaAttachments::configure(RichEditor::make('use_cases')->label('Use cases')),
                                RichEditorMediaAttachments::configure(RichEditor::make('additional_content')->label('Additional content'))
                                    ->helperText('Any further explanation. Use Heading 2 for sections.'),
                                RichEditorMediaAttachments::configure(RichEditor::make('important_notes')->label('Important notes'))
                                    ->helperText('Caveats and limitations, shown in a highlighted box.'),
                            ]),

                        Tab::make('FAQ')
                            ->schema([
                                Repeater::make('faqs')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->schema([
                                        TextInput::make('question')->required()->maxLength(200)->columnSpanFull(),
                                        Textarea::make('answer')->required()->rows(3)->maxLength(2000)->columnSpanFull(),
                                        Toggle::make('is_visible')->label('Visible on the page')->default(true),
                                    ])
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                    ->addActionLabel('Add FAQ'),
                            ]),

                        Tab::make('SEO')
                            ->columns(2)
                            ->schema([
                                SeoFields::metaTitle()->helperText('Required to publish. Filled from the tool name — edit it to match search intent.'),
                                SeoFields::metaDescription()->helperText('Required to publish. Filled from the short description.')->columnSpanFull(),
                                Select::make('seo.robots')
                                    ->label('Search engine indexing')
                                    ->options(['' => 'Index (default)', 'noindex, follow' => 'Noindex — hide from search results'])
                                    ->native(false),
                                ...SeoFields::canonicalUrlFields(
                                    'seo.canonical_url',
                                    'seo.canonical_url_is_auto',
                                    fn (Get $get): string => 'tools/'.($get('slug') ?? ''),
                                ),
                                TextInput::make('seo.og_title')->label('Social share title')->maxLength(255),
                                Textarea::make('seo.og_description')->label('Social share description')->rows(2)->maxLength(500)->columnSpanFull(),
                                MediaPicker::make('og_image_media_id', 'Social share image', MediaCategory::Image),
                            ]),

                        Tab::make('Related')
                            ->schema([
                                Select::make('related_tool_ids')
                                    ->label('Related tools')
                                    ->multiple()
                                    ->options(fn (?Tool $record): array => Tool::query()
                                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                        ->ordered()
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->helperText('In the order chosen. Only published tools are shown on the page; with none chosen, other tools from the same category are suggested.'),
                                Select::make('service_id')
                                    ->label('Related service')
                                    ->options(fn (): array => Service::query()->ordered()->pluck('title', 'id')->all())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Linked from the tool page, so visitors can get professional help with the same topic.'),
                            ]),

                        Tab::make('Call to action')
                            ->schema([
                                TextInput::make('cta_heading')
                                    ->label('Heading')
                                    ->maxLength(120)
                                    ->placeholder('Need help with your website?')
                                    ->helperText('Leave empty to use the default call to action from Tools Settings.'),
                                Textarea::make('cta_text')->label('Text')->rows(2)->maxLength(300),
                                Grid::make(2)->schema([
                                    TextInput::make('cta_label')->label('Button label')->maxLength(40)->placeholder('Discuss your project'),
                                    TextInput::make('cta_url')->label('Button URL')->maxLength(255)
                                        ->placeholder('/contact')
                                        ->rule('regex:/^(https?:\/\/|\/|#)/i')
                                        ->validationMessages(['regex' => 'Use a full URL (https://…) or a path starting with /.']),
                                ]),
                            ]),
                    ]),

                Group::make([
                    Section::make('Publish')
                        ->schema([
                            TextEntry::make('status')
                                ->state(fn (?Tool $record): ToolStatus => $record?->status ?? ToolStatus::Draft)
                                ->badge(),
                            TextEntry::make('published_at')
                                ->label('First published')
                                ->state(fn (?Tool $record) => $record?->published_at)
                                ->dateTime()
                                ->placeholder('Never')
                                ->visible(fn (?Tool $record): bool => $record !== null),
                            TextEntry::make('public_url')
                                ->label('Public URL')
                                ->state(fn (?Tool $record): ?string => $record?->isLive() ? $record->url() : null)
                                ->url(fn (?Tool $record): ?string => $record?->isLive() ? $record->url() : null, shouldOpenInNewTab: true)
                                ->placeholder('Not public')
                                ->visible(fn (?Tool $record): bool => $record !== null),
                            Actions::make([
                                ToolResource::previewAction(),
                                ToolResource::publishAction(),
                                ToolResource::unpublishAction(),
                            ])->visible(fn (?Tool $record): bool => $record !== null),
                            TextEntry::make('save_first')
                                ->hiddenLabel()
                                ->state('Save the tool as a draft first — then preview it and publish.')
                                ->visible(fn (?Tool $record): bool => $record === null),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
