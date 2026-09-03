<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Role\CreateRoleAction;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Add Role')->formId('form'),
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

        return app(CreateRoleAction::class)->handle($data, $actor);
    }
}
