<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\ModuleKey;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Sections are a plain array field (not a Filament relationship-repeater)
 * so CreatePageAction/UpdatePageAction — not Filament's automatic
 * relationship save — own reconciling page_sections, writing the revision
 * snapshot, and detecting a slug change. Same reasoning as MenuForm's
 * items Repeater.
 */
class PageForm
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
                                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get): void {
                                            if ($operation === 'create') {
                                                $slug = Str::slug($state ?? '');
                                                $set('slug', $slug);
                                                SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', $slug);
                                            }
                                        })
                                        ->columnSpanFull(),
                                    TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: Page::class, column: 'slug', ignoreRecord: true)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => SeoFields::syncCanonicalUrlIfAuto($set, $get, 'seo.canonical_url', 'seo.canonical_url_is_auto', $state ?? ''))
                                        ->helperText('Auto-filled from the title — override it if needed. Changing it on an existing page creates a redirect from the old slug.'),
                                    Select::make('template')
                                        ->options(PageTemplate::options())
                                        ->default(PageTemplate::Standard->value)
                                        ->required(),
                                    Textarea::make('summary')
                                        ->rows(2)
                                        ->maxLength(65535)
                                        ->columnSpanFull(),
                                    MediaPicker::make('featured_image_id', 'Featured Image')
                                        ->columnSpanFull(),
                                    Select::make('author_id')
                                        ->label('Author')
                                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable()
                                        ->preload(),
                                ]),

                            Section::make('Sections')
                                ->description('Structured content blocks — not a freeform drag-and-drop builder. Choose from the approved block types below.')
                                ->schema([
                                    Repeater::make('sections')
                                        ->hiddenLabel()
                                        ->reorderable()
                                        ->collapsible()
                                        ->defaultItems(0)
                                        ->itemLabel(fn (array $state): ?string => $state['title']
                                            ?: (isset($state['section_type']) ? PageSectionType::from($state['section_type'])->getLabel() : null))
                                        ->addActionLabel('Add section')
                                        ->schema(self::sectionSchema())
                                        ->columns(2),
                                ]),

                            Section::make('SEO')
                                ->description('Independent per-page metadata (title, description, canonical, Open Graph, Twitter card, structured data).')
                                ->collapsed()
                                ->schema([
                                    SeoFields::metaTitle(),
                                    ...SeoFields::canonicalUrlFields(
                                        'seo.canonical_url',
                                        'seo.canonical_url_is_auto',
                                        fn (Get $get): string => (string) ($get('slug') ?? ''),
                                    ),
                                    SeoFields::metaDescription()->columnSpanFull(),
                                    TextInput::make('seo.robots')->label('Robots')->maxLength(255)->placeholder('index, follow'),
                                    TextInput::make('seo.og_title')->label('Open Graph title')->maxLength(255),
                                    MediaPicker::make('seo.og_image_media_id', 'Open Graph image'),
                                    Textarea::make('seo.og_description')->label('Open Graph description')->rows(2)->maxLength(500)->columnSpanFull(),
                                    TextInput::make('seo.twitter_title')->label('Twitter title')->maxLength(255),
                                    MediaPicker::make('seo.twitter_image_media_id', 'Twitter image'),
                                    Textarea::make('seo.twitter_description')->label('Twitter description')->rows(2)->maxLength(500)->columnSpanFull(),
                                ])
                                ->columns(2),
                        ])->columnSpan(['default' => 12, 'lg' => 7]),

                        Group::make([
                            Section::make('Status')
                                ->schema([
                                    Placeholder::make('status_display')
                                        ->label('Current status')
                                        ->content(fn (?Page $record): string => $record ? $record->status->getLabel() : PageStatus::Draft->getLabel()),
                                    Select::make('status')
                                        ->options(PageStatus::options())
                                        ->default(PageStatus::Draft->value)
                                        ->required()
                                        ->live()
                                        ->hidden(fn (?string $operation): bool => $operation === 'edit')
                                        ->helperText('After creation, status is changed from the actions below.'),
                                    DateTimePicker::make('publish_at')
                                        ->label('Publish at')
                                        ->native(false)
                                        ->helperText('Required to schedule a page — a scheduled command publishes it automatically once this passes.')
                                        ->visible(fn (?string $operation, Get $get): bool => $operation !== 'edit' && $get('status') === PageStatus::Scheduled->value),

                                    Actions::make([PageResource::publishAction()->outlined()])->key('publishActions')->fullWidth()
                                        ->visible(fn (?string $operation): bool => $operation === 'edit'),
                                    Actions::make([PageResource::unpublishAction()->outlined()])->key('unpublishActions')->fullWidth()
                                        ->visible(fn (?string $operation): bool => $operation === 'edit'),
                                    Actions::make([PageResource::archiveAction()->outlined()])->key('archiveActions')->fullWidth()
                                        ->visible(fn (?string $operation): bool => $operation === 'edit'),
                                    Actions::make([PageResource::scheduleAction()->outlined()])->key('scheduleActions')->fullWidth()
                                        ->visible(fn (?string $operation): bool => $operation === 'edit'),
                                ]),

                            Section::make('Navigation')
                                ->schema([
                                    Actions::make([PageResource::addToNavigationAction()->outlined()])->key('addToNavigationActions')->fullWidth(),
                                ])
                                ->visible(fn (?string $operation): bool => $operation === 'edit'),

                            Section::make('Preview')
                                ->description('Opens the structured content in a new browser tab — this page stays open.')
                                ->schema([
                                    Actions::make([PageResource::previewAction()])->key('previewActions')->fullWidth(),
                                ])
                                ->visible(fn (?string $operation): bool => $operation === 'edit'),

                            Section::make('Revisions')
                                ->collapsed()
                                ->schema([
                                    Placeholder::make('revisions_display')
                                        ->hiddenLabel()
                                        ->content(fn (?Page $record): HtmlString => self::renderRevisions($record)),
                                    Actions::make([PageResource::restoreRevisionAction()->outlined()])->key('restoreRevisionActions')->fullWidth(),
                                ])
                                ->visible(fn (?string $operation): bool => $operation === 'edit'),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function sectionSchema(): array
    {
        return [
            Select::make('section_type')
                ->label('Type')
                ->options(PageSectionType::options())
                ->required()
                ->live(),
            TextInput::make('title')
                ->label('Admin label')
                ->maxLength(255)
                ->helperText('For your own reference in this list — not necessarily rendered.'),
            Toggle::make('is_enabled')
                ->default(true)
                ->columnSpanFull(),

            TextInput::make('content_json.heading')->label('Heading')
                ->visible(fn (Get $get): bool => in_array($get('section_type'), [PageSectionType::Hero->value, PageSectionType::Cta->value], true)),
            Textarea::make('content_json.subheading')->label('Subheading')->rows(2)
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Hero->value),
            MediaPicker::make('content_json.media_id', 'Image')
                ->visible(fn (Get $get): bool => in_array($get('section_type'), [PageSectionType::Hero->value, PageSectionType::ImageText->value], true)),
            TextInput::make('content_json.cta_label')->label('Button label')
                ->visible(fn (Get $get): bool => in_array($get('section_type'), [PageSectionType::Hero->value, PageSectionType::Cta->value], true)),
            TextInput::make('content_json.cta_url')->label('Button URL')->maxLength(255)
                ->helperText('An absolute URL, a relative path (e.g. /music), or # while the destination isn\'t built yet.')
                ->visible(fn (Get $get): bool => in_array($get('section_type'), [PageSectionType::Hero->value, PageSectionType::Cta->value], true)),

            TextInput::make('content_json.secondary_cta_label')->label('Secondary button label')
                ->helperText('Optional — a second, outlined button shown next to the primary one.')
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Hero->value),
            TextInput::make('content_json.secondary_cta_url')->label('Secondary button URL')->maxLength(255)
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Hero->value),
            TextInput::make('content_json.tertiary_label')->label('Additional link label')
                ->helperText('Optional — a plain link shown below the buttons (e.g. "Watch Introduction").')
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Hero->value),
            TextInput::make('content_json.tertiary_url')->label('Additional link URL')->maxLength(255)
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Hero->value),

            RichEditorMediaAttachments::configure(RichEditor::make('content_json.body')->label('Content')->columnSpanFull())
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::RichText->value),

            // A distinct key from RichText's content_json.body above, even
            // though both are only ever visible one-at-a-time — Filament
            // renders every conditionally-visible field into the DOM
            // (toggling CSS visibility client-side), so sharing a key here
            // means two DOM nodes both claim schema-component key
            // "content_json.body". That's invisible to a plain form save,
            // but RichEditor's own file-attachment JS looks up its schema
            // component by that same key and throws "Multiple schema
            // components found" the moment two DOM nodes match — which is
            // why "Attach File" never worked at all (ADMIN-006 review fix).
            Textarea::make('content_json.text')->label('Text')->rows(4)->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::ImageText->value),

            Repeater::make('content_json.items')
                ->label('FAQ items')
                ->schema([
                    TextInput::make('question')->required(),
                    Textarea::make('answer')->required()->rows(2),
                ])
                ->defaultItems(0)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Faq->value),

            Textarea::make('content_json.quote')->label('Quote')->rows(3)->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Quote->value),
            TextInput::make('content_json.attribution')->label('Attribution')
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Quote->value),

            MediaPicker::make('content_json.media_ids', 'Images', multiple: true)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::Gallery->value),

            Select::make('content_json.module_key')
                ->label('Module')
                ->options(ModuleKey::options())
                ->helperText('Shows placeholder content until this module is implemented.')
                ->visible(fn (Get $get): bool => $get('section_type') === PageSectionType::FeaturedContent->value),

            Placeholder::make('inert_notice')
                ->hiddenLabel()
                ->content('No configuration needed — this block has no functionality yet until the corresponding system exists.')
                ->visible(fn (Get $get): bool => in_array($get('section_type'), [
                    PageSectionType::NewsletterSignup->value,
                    PageSectionType::ContactForm->value,
                ], true)),
        ];
    }

    private static function renderRevisions(?Page $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<p style="color:#6b7280;margin:0">Save the page to see revision history.</p>');
        }

        $revisions = $record->revisions()->latest('version')->limit(10)->get();

        if ($revisions->isEmpty()) {
            return new HtmlString('<p style="color:#6b7280;margin:0">No revisions yet.</p>');
        }

        $html = '<div style="display:flex;flex-direction:column;gap:0.5rem">';

        foreach ($revisions as $revision) {
            /** @var PageRevision $revision */
            $html .= sprintf(
                '<div style="display:flex;justify-content:space-between;align-items:center"><span>v%d — %s</span></div>',
                $revision->version,
                e($revision->created_at?->toDayDateTimeString() ?? ''),
            );
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
