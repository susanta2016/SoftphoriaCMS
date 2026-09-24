<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Pages;

use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\PoetryProseSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPoetryProseSubmissions extends ListRecords
{
    protected static string $resource = PoetryProseSubmissionResource::class;
}
