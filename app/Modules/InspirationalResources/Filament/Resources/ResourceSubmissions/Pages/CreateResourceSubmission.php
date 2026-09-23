<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Models\User;
use App\Modules\InspirationalResources\Actions\CreateAdminResourceSubmissionAction;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateResourceSubmission extends CreateRecord
{
    protected static string $resource = ResourceSubmissionResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Add Resource';
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreateAdminResourceSubmissionAction::class)->handle($data, $actor);
    }

    protected function getRedirectUrl(): string
    {
        return ResourceSubmissionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
