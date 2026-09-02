<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewResourceSubmission extends ViewRecord
{
    protected static string $resource = ResourceSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ResourceSubmissionResource::viewPublicPageAction(),
            ActionGroup::make([
                ResourceSubmissionResource::markInReviewAction(),
                ResourceSubmissionResource::approveAction(),
                ResourceSubmissionResource::archiveAction(),
            ])
                ->label('Review Actions')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray'),
        ];
    }
}
