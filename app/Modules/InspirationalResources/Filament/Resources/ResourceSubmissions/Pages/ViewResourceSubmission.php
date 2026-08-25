<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewResourceSubmission extends ViewRecord
{
    protected static string $resource = ResourceSubmissionResource::class;

    /**
     * "Create Poetry/Prose Draft" is deliberately its own standalone,
     * prominent button — not grouped with the review-queue actions — so an
     * admin never confuses "move this through the review queue" with
     * "start a brand-new editorial draft from it" (client requirement:
     * visually and logically separated).
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ResourceSubmissionResource::markInReviewAction(),
                ResourceSubmissionResource::approveAction(),
                ResourceSubmissionResource::archiveAction(),
            ])
                ->label('Review Actions')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray'),
            ResourceSubmissionResource::createPoetryProseAction(),
        ];
    }
}
