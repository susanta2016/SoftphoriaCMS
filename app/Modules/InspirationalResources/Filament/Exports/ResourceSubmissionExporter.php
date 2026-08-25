<?php

namespace App\Modules\InspirationalResources\Filament\Exports;

use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * name/subject/message are all untrusted public-form input — every
 * free-text column strips a leading formula-trigger character
 * (=, +, -, @) per Exporter's own CSV-formula-injection warning, since
 * this data has never passed through any other sanitization step.
 */
class ResourceSubmissionExporter extends Exporter
{
    protected static ?string $model = ResourceSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('email'),
            ExportColumn::make('subject')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('category'),
            ExportColumn::make('message')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('status')->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('related_album_id')->label('Related Album ID'),
            ExportColumn::make('related_track_id')->label('Related Track ID'),
            ExportColumn::make('created_at')->label('Submitted At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Your Inspirational Resource submissions export has completed with {$count} ".str('row')->plural($export->successful_rows).' exported.';
    }

    private static function sanitize(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
