<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Role\UpdateRoleAction;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
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
     * "permissions" is a virtual field (the role_permissions pivot, not a
     * column) — filled manually the same way UserForm's "role_id" is.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = $this->record->permissions()->pluck('permissions.id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdateRoleAction::class)->handle($record, $data, $actor);
    }
}
