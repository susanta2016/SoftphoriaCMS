<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\ContactRequests\Widgets\ContactRequestStatsWidget;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction — contact requests are created exclusively by
 * SubmitContactRequestAction from the public form, never hand-built in
 * the admin panel.
 */
class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContactRequestStatsWidget::class,
        ];
    }
}
