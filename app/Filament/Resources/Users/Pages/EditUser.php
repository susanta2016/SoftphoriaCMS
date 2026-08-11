<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\UpdateUserAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * No DeleteAction here: ADMIN-003 does not permit hard-deleting users
 * (see ADMIN-003 §2 decision). "Deletion" is a status transition, handled
 * by UserResource::changeStatusAction()/deleteUserAction(). Status/password/
 * session quick actions live in the form's "Account Status" sidebar card
 * (UserForm) rather than the header, matching the reference UI. Save/Cancel
 * are moved into the header (instead of Filament's default bottom bar) to
 * match the reference UI's "View | Save changes | Cancel" row.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            $this->getSaveFormAction()->label('Save changes'),
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['profile_bio'] = $this->record->profile?->bio;
        $data['profile_phone_number'] = $this->record->profile?->phone_number;
        $data['profile_address'] = $this->record->profile?->address;
        $data['profile_zip_code'] = $this->record->profile?->zip_code;
        $data['avatar'] = $this->record->profile?->avatar?->path;
        $data['role_id'] = $this->record->roles()->orderBy('roles.id')->value('roles.id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdateUserAction::class)->handle($record, $data, $actor);
    }
}
