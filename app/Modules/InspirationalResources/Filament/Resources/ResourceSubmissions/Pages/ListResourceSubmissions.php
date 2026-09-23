<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceSubmissions extends ListRecords
{
    protected static string $resource = ResourceSubmissionResource::class;

    /**
     * CreateAction's own authorization checks the model policy, not
     * ResourceSubmissionResource::canCreate() — hence the explicit visible()
     * so the button follows the env flag too.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Resource')
                ->visible(fn (): bool => ResourceSubmissionResource::canCreate()),
        ];
    }
}
