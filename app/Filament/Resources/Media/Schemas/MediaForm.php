<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Enums\MediaCategory;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Support\Media\MediaPreview;
use App\Models\Media;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

/**
 * View + Edit combined on one page (per docs/Reference UI/Admin/Media
 * Edit-details.docx: "Details and edit option in same page, don't need
 * them in separate pages") — this schema is EditMedia's whole form; there
 * is no separate ViewMedia page/infolist anymore. Read-only details use
 * Filament\Forms\Components\Placeholder rather than Infolist Entries —
 * Placeholder is already the established pattern for read-only display
 * inside an editable form in this codebase (see UserForm's Account Status
 * card) and needs no mixing of the Forms/Infolists component families.
 *
 * Replacing the file is still the dedicated Replace File action
 * (MediaResource::replaceFileAction()), not a plain field — see that
 * method's docblock. Same for regenerating variants
 * (MediaResource::regenerateVariantsAction()).
 */
class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 12])
            ->components([
                Group::make([
                    Section::make('Preview')
                        ->schema([
                            Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(fn (Media $record): HtmlString => self::renderPreview($record)),
                        ]),

                    Section::make('Responsive Variants')
                        ->schema([
                            Placeholder::make('variants_status')
                                ->label('Status')
                                ->content(fn (Media $record): HtmlString => self::renderVariantsStatus($record)),
                            Placeholder::make('variants_display')
                                ->hiddenLabel()
                                ->content(fn (Media $record): HtmlString => self::renderVariants($record)),
                            Actions::make([MediaResource::regenerateVariantsAction()])
                                ->key('regenerateVariantsActions')
                                ->fullWidth(),
                        ])
                        ->visible(fn (Media $record): bool => $record->category() === MediaCategory::Image)
                        // GenerateImageVariantsJob runs on a queue worker, not
                        // this request, so while it's in flight (neither
                        // generated nor failed yet) nothing else tells this
                        // page to update once it finishes — see
                        // EditMedia::refreshVariantsStatus(). Livewire drops
                        // the poll itself once the re-rendered section no
                        // longer carries this attribute.
                        ->extraAttributes(
                            fn (Media $record): array => self::isProcessingVariants($record)
                                ? ['wire:poll.3s' => 'refreshVariantsStatus']
                                : [],
                        ),
                ])->columnSpan(['default' => 12, 'lg' => 6]),

                Group::make([
                    Section::make('Details')
                        ->columns(2)
                        ->schema([
                            Placeholder::make('original_filename_display')
                                ->label('Filename')
                                ->content(fn (Media $record): string => $record->original_filename),
                            Placeholder::make('mime_type_display')
                                ->label('MIME type')
                                ->content(fn (Media $record): string => $record->mime_type),
                            Placeholder::make('size_display')
                                ->label('Size')
                                ->content(fn (Media $record): string => Number::fileSize($record->size)),
                            Placeholder::make('visibility_display')
                                ->label('Visibility')
                                ->content(fn (Media $record): string => $record->visibility),
                            Placeholder::make('uploader_display')
                                ->label('Uploaded by')
                                ->content(fn (Media $record): string => $record->uploader?->name ?? '—'),
                            Placeholder::make('created_at_display')
                                ->label('Uploaded')
                                ->content(fn (Media $record): string => $record->created_at?->toDayDateTimeString() ?? '—'),
                        ]),

                    Section::make('Edit')
                        ->schema([
                            TextInput::make('alt_text')
                                ->label('Alt text')
                                ->maxLength(255)
                                ->helperText('Used for images.'),
                            Actions::make([MediaResource::replaceFileAction()])
                                ->key('replaceFileActions')
                                ->fullWidth(),
                        ]),
                ])->columnSpan(['default' => 12, 'lg' => 6]),
            ]);
    }

    private static function renderPreview(Media $record): HtmlString
    {
        return match ($record->category()) {
            MediaCategory::Image => new HtmlString(sprintf(
                '<img src="%s" alt="%s" style="max-height:300px;max-width:100%%;border-radius:0.5rem" />',
                e(Storage::disk($record->disk)->url($record->path)),
                e($record->alt_text ?? ''),
            )),
            MediaCategory::Audio => MediaPreview::audioPlayer($record),
            MediaCategory::Video => MediaPreview::videoPlayer($record),
            default => new HtmlString('No preview available for this file type.'),
        };
    }

    /**
     * Three states an admin can actually distinguish, instead of the prior
     * binary "Generated timestamp or 'Not yet processed'": Processing
     * (dispatched or not yet dispatched, no result either way — the
     * ordinary state for a just-uploaded image), Generated, or Failed
     * (GenerateImageVariantsJob::failed() ran — see that method).
     */
    private static function renderVariantsStatus(Media $record): HtmlString
    {
        return new HtmlString(match (true) {
            $record->variants_failed_at !== null => sprintf(
                '<span style="color:#dc2626;font-weight:600">Failed</span> — last attempt %s',
                e($record->variants_failed_at->toDayDateTimeString()),
            ),
            $record->variants_generated_at !== null => sprintf(
                '<span style="color:#16a34a;font-weight:600">Generated</span> — %s',
                e($record->variants_generated_at->toDayDateTimeString()),
            ),
            default => '<span style="color:#d97706;font-weight:600">Processing</span> — variants have not been generated yet.',
        });
    }

    /**
     * Single source of truth for "the queue job hasn't resolved yet" — used
     * both by the status text above (as the match's default/fallthrough
     * case) and by the wire:poll gate below, so the two can't drift apart.
     */
    private static function isProcessingVariants(Media $record): bool
    {
        return $record->variants_generated_at === null && $record->variants_failed_at === null;
    }

    /**
     * Grouped by (type, width) — currently thumbnail/300 + responsive/640 +
     * responsive/1280 per config('media.variants'), but derived from the
     * actual rows rather than hardcoded so this doesn't silently lie if
     * that config ever changes. No select/use-this-variant controls here
     * by design — variants are consumed automatically by future CMS/
     * frontend responsive-image logic, not picked by an admin.
     */
    private static function renderVariants(Media $record): HtmlString
    {
        if ($record->variants->isEmpty()) {
            return new HtmlString('<p style="color:#6b7280;margin:0">No variants.</p>');
        }

        $groups = $record->variants
            ->groupBy(fn ($variant): string => "{$variant->type}:{$variant->width}")
            ->sortBy(fn ($group) => $group->first()->type === 'thumbnail' ? -1 : $group->first()->width);

        $html = '';

        foreach ($groups as $group) {
            $first = $group->first();
            $label = $first->type === 'thumbnail'
                ? "Thumbnail ({$first->width}\u{00d7}{$first->height})"
                : "Responsive Images ({$first->width}px)";

            $html .= '<div style="margin-bottom:1rem"><p style="font-weight:600;margin:0 0 0.5rem">'.e($label).'</p>';
            $html .= '<div style="display:flex;gap:0.75rem;flex-wrap:wrap">';

            foreach ($group->sortBy('format') as $variant) {
                $url = Storage::disk($variant->disk)->url($variant->path);

                $html .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener" style="display:block;width:120px;text-decoration:none;color:inherit">'
                    .'<img src="%s" alt="" style="width:120px;height:90px;object-fit:cover;border-radius:0.375rem;border:1px solid #e5e7eb" />'
                    .'<div style="font-size:0.75rem;margin-top:0.25rem;line-height:1.3">'
                    .'<div style="font-weight:600;text-transform:uppercase">%s</div>'
                    .'<div>%d&times;%d</div>'
                    .'<div>%s</div>'
                    .'</div>'
                    .'</a>',
                    e($url),
                    e($url),
                    e($variant->format),
                    $variant->width,
                    $variant->height,
                    e(Number::fileSize($variant->size)),
                );
            }

            $html .= '</div></div>';
        }

        return new HtmlString($html);
    }
}
