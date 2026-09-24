<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages;

use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\PoetryProseSubmissionResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPoetryProseSubmission extends ViewRecord
{
    protected static string $resource = PoetryProseSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                PoetryProseSubmissionResource::markInReviewAction(),
                PoetryProseSubmissionResource::approveAction(),
                PoetryProseSubmissionResource::archiveAction(),
            ])
                ->label('Review Actions')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray'),
        ];
    }
}
