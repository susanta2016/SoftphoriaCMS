<?php

namespace App\Modules\Commerce\Filament\Resources\DownloadLogs\Pages;

use App\Modules\Commerce\Filament\Resources\DownloadLogs\DownloadLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDownloadLogs extends ListRecords
{
    protected static string $resource = DownloadLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
