<?php

namespace App\Filament\Resources\Media\Pages;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Save is moved into the header, matching EditUser/CreateUser (ARCHITECTURE
 * §13). ->formId('form') is required here for the header button to actually
 * submit the form — see EditUser's docblock for why (Filament's header
 * actions render outside the <form> element by default).
 */
class UploadMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Upload')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * Category-specific disk/directory/size/type rules are looked up live
     * from config('media.categories') as the category selection changes, so
     * validation limits never drift from StoreUploadedMediaAction's own
     * source of truth.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('category')
                    ->label('Category')
                    ->options(MediaCategory::options())
                    ->required()
                    ->live()
                    ->native(false),
                FileUpload::make('file')
                    ->label('File')
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('category')))
                    ->disk(fn (Get $get): string => data_get(config('media.categories'), "{$get('category')}.disk", 'public'))
                    ->directory(fn (Get $get): string => data_get(config('media.categories'), "{$get('category')}.directory", 'media/misc'))
                    ->visibility(fn (Get $get): string => data_get(config('media.categories'), "{$get('category')}.disk") === 'public' ? 'public' : 'private')
                    ->maxSize(fn (Get $get): ?int => data_get(config('media.categories'), "{$get('category')}.max_size"))
                    ->acceptedFileTypes(fn (Get $get): array => data_get(config('media.categories'), "{$get('category')}.accepted_mime_types", []))
                    ->helperText('Select a category first — allowed types and the size limit depend on it.'),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->maxLength(255)
                    ->helperText('Optional. Used for images.'),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        $categoryConfig = config("media.categories.{$data['category']}");

        /** @var Media $media */
        $media = app(StoreUploadedMediaAction::class)->handle(
            disk: $categoryConfig['disk'],
            path: $data['file'],
            uploader: $actor,
            visibility: $categoryConfig['disk'] === 'public' ? 'public' : 'protected',
        );

        if (filled($data['alt_text'] ?? null)) {
            $media->alt_text = $data['alt_text'];
            $media->save();
        }

        return $media;
    }
}
