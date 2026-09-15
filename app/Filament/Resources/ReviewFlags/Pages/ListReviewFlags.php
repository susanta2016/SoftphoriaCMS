<?php

namespace App\Filament\Resources\ReviewFlags\Pages;

use App\Filament\Resources\ReviewFlags\ReviewFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewFlags extends ListRecords
{
    protected static string $resource = ReviewFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
