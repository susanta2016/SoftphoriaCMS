<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Actions\Permission\CreatePermissionAction;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Add Permission')->formId('form'),
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

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreatePermissionAction::class)->handle($data, $actor);
    }
}
