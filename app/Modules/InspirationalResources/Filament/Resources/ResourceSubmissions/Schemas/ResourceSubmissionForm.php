<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Schemas;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "Add Resource" only (create page, gated by
 * config('features.inspirational_resources_admin_create_enabled')) —
 * there is still no edit page. Same fields and limits as the public form's
 * validation in InspirationalResourceSubmissionController::store(), with
 * name/email pre-filled from the admin's own account and Archived left out
 * since adding something straight into the archive is meaningless.
 */
class ResourceSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Shared by')
                    ->required()
                    ->maxLength(255)
                    ->default(fn (): ?string => self::actor()?->displayName())
                    ->helperText('Shown publicly as the byline.'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->default(fn (): ?string => self::actor()?->email),
                TextInput::make('subject')
                    ->maxLength(255),
                TextInput::make('category')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Testimony, Encouragement'),
                Select::make('theme')
                    ->options(array_combine(ResourceSubmission::THEME_OPTIONS, ResourceSubmission::THEME_OPTIONS))
                    ->required()
                    ->native(false),
                Textarea::make('message')
                    ->required()
                    ->maxLength(5000)
                    ->rows(10)
                    ->columnSpanFull(),
                TextInput::make('reference_url')
                    ->label('Reference URL')
                    ->url()
                    ->maxLength(2048),
                Select::make('status')
                    ->options(collect(ResourceSubmissionStatus::options())
                        ->except(ResourceSubmissionStatus::Archived->value)
                        ->all())
                    ->default(ResourceSubmissionStatus::Approved->value)
                    ->required()
                    ->native(false)
                    ->helperText('Approved publishes it on the public Inspirational Resources page immediately.'),
            ]);
    }

    private static function actor(): ?User
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return $actor;
    }
}
