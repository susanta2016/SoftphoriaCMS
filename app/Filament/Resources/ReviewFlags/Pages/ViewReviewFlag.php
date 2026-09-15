<?php

namespace App\Filament\Resources\ReviewFlags\Pages;

use App\Filament\Resources\ReviewFlags\ReviewFlagResource;
use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReviewFlag extends ViewRecord
{
    protected static string $resource = ReviewFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReviewFlagResource::dismissAction(),
            // Reuses ReviewResource's own Approve/Reject actions rather than
            // duplicating them — a reported comment is still an ordinary
            // Review underneath, and "Reject" here is exactly the same
            // "hide this comment from the public site" action the sibling
            // Light Posts & Comments queue already exposes.
            ReviewResource::approveAction(),
            ReviewResource::rejectAction(),
        ];
    }
}
