<?php

namespace App\Filament\Support\Media;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Models\Media;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * ADMIN-006 review fix — the stock RichEditor::make() ships with its file
 * attachments unconfigured (no disk/directory, default HasFileAttachments
 * behaviour), so "Attach File" never went through the Media Library: no
 * Media row, no variants, orphaned files outside config('media.categories').
 * self::configure() routes both new uploads and existing-asset selection
 * through the exact same StoreUploadedMediaAction the Media Library and
 * MediaPicker use — see docs/ARCHITECTURE.md §14. Reusable by any future
 * module's RichEditor field, not just Pages.
 */
class RichEditorMediaAttachments
{
    public static function configure(RichEditor $editor, MediaCategory $category = MediaCategory::Image): RichEditor
    {
        $acceptedMimeTypes = [
            ...$category->acceptedMimeTypes(),
            ...MediaCategory::Document->acceptedMimeTypes(),
        ];

        return $editor
            ->fileAttachmentsDisk($category->diskName())
            ->fileAttachmentsVisibility($category->visibility())
            ->fileAttachmentsAcceptedFileTypes($acceptedMimeTypes)
            ->fileAttachmentsMaxSize(max($category->maxSizeKb() ?? 0, MediaCategory::Document->maxSizeKb() ?? 0))
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file): string => self::store($file))
            // A closure, not a built Action instance: this field is defined
            // once as a plain array inside a Repeater's ->schema() (see
            // PageForm::sectionSchema()) and Filament clones that array's
            // objects per repeater item. A pre-built Action instance would
            // be shared by reference across every clone, so every item's
            // RichEditor would register the SAME action object — surfacing
            // as "Multiple schema components found with key
            // [...content_json.body]" the moment two Rich Text sections (or
            // Livewire's own re-render of one) exist at once. Registering a
            // closure makes cacheActions() build a fresh Action bound to
            // the correct component instance on every evaluation.
            ->registerActions([fn (): Action => self::attachFilesAction($category)]);
    }

    private static function store(TemporaryUploadedFile $file): string
    {
        $category = MediaCategory::fromMimeType($file->getMimeType()) ?? MediaCategory::Document;
        $disk = $category->diskName();

        $path = $file->store($category->directory(), $disk);

        if ($category->visibility() === 'public') {
            rescue(fn () => Storage::disk($disk)->setVisibility($path, 'public'), report: false);
        }

        /** @var User $actor */
        $actor = Auth::user();

        app(StoreUploadedMediaAction::class)->handle(
            disk: $disk,
            path: $path,
            uploader: $actor,
            visibility: $category->visibility(),
        );

        return $path;
    }

    /**
     * Replaces the vendor AttachFilesAction (same 'attachFiles' name, so
     * RichEditor::cacheActions() overrides it rather than adding a second
     * button — see Filament\Schemas\Components\Concerns\HasActions) with a
     * version that shows "Select from Media Library" alongside the upload
     * field, rather than behind a reactive toggle: a ->live() field in this
     * modal forces a full Livewire round-trip that re-renders the RichEditor
     * behind the modal, invalidating the Tiptap editorSelection captured
     * when the toolbar button was clicked — breaking runCommands() below
     * with an uncaught JS promise rejection on submit. Showing both fields
     * unconditionally avoids any ->live() field in this modal, matching the
     * vendor action's own (zero-live-field) modal.
     */
    private static function attachFilesAction(MediaCategory $category): Action
    {
        return Action::make('attachFiles')
            ->label(__('filament-forms::components.rich_editor.actions.attach_files.label'))
            ->modalHeading(__('filament-forms::components.rich_editor.actions.attach_files.modal.heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'alt' => $arguments['alt'] ?? null,
            ])
            ->schema(fn (array $arguments, RichEditor $component): array => [
                FileUpload::make('file')
                    ->label(filled($arguments['src'] ?? null)
                        ? __('filament-forms::components.rich_editor.actions.attach_files.modal.form.file.label.existing')
                        : 'Upload a new file')
                    ->acceptedFileTypes($component->getFileAttachmentsAcceptedFileTypes())
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    ->storeFiles(false),
                Placeholder::make('library_divider')
                    ->hiddenLabel()
                    ->content('— or — choose an existing image from the Media Library'),
                ...MediaPicker::libraryBrowserSchema($category, multiple: false, required: false),
                TextInput::make('alt')
                    ->label(filled($arguments['src'] ?? null)
                        ? __('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.existing')
                        : __('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.new'))
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component, LivewireComponent $livewire): void {
                $id = null;
                $src = null;

                if ($data['file'] ?? null) {
                    $id = (string) Str::orderedUuid();

                    data_set($livewire, "componentFileAttachments.{$component->getStatePath()}.{$id}", $data['file']);
                    $src = $component->getUploadedFileAttachmentTemporaryUrl($data['file']);
                } elseif (filled($data['media'] ?? null)) {
                    $media = Media::query()->find($data['media']);

                    if ($media) {
                        $id = $media->path;
                        $src = $component->getFileAttachmentUrl($media->path);
                    }
                }

                if (filled($arguments['src'] ?? null)) {
                    // Fixes an issue where the editor selection is sent as text instead of a node,
                    // which causes the image update to fail when though the image is selected.
                    if ($arguments['editorSelection']['type'] !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor']--;

                        unset($arguments['editorSelection']['head']);
                    }

                    $id ??= $arguments['id'] ?? null;
                    $src ??= $arguments['src'];

                    $component->runCommands(
                        [
                            EditorCommand::make('updateAttributes', arguments: [
                                'image',
                                [
                                    'alt' => $data['alt'] ?? null,
                                    'id' => $id,
                                    'src' => $src,
                                ],
                            ]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                if (blank($id) || blank($src)) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => [
                                'alt' => $data['alt'] ?? null,
                                'id' => $id,
                                'src' => $src,
                            ],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
