<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Models\User;
use App\Modules\InspirationalResources\Actions\CreateAdminResourceSubmissionAction;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
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

        // "Other" stores the admin's own category, as the public form does.
        if (($data['category'] ?? null) === ResourceSubmission::OTHER_CATEGORY) {
            $data['category'] = trim((string) $data['category_other']);
        }
        unset($data['category_other']);

        return app(CreateAdminResourceSubmissionAction::class)->handle($data, $actor);
    }

    protected function getRedirectUrl(): string
    {
        return ResourceSubmissionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
