<?php

namespace App\Modules\PoetryProse\Filament\Exports;

use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Same CSV-formula-injection guard as Inspirational Resources'
 * ResourceSubmissionExporter — name/subject/message are untrusted public
 * input.
 */
class PoetryProseSubmissionExporter extends Exporter
{
    protected static ?string $model = PoetryProseSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('email'),
            ExportColumn::make('subject')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('category'),
            ExportColumn::make('theme'),
            ExportColumn::make('message')->formatStateUsing(fn (?string $state): string => self::sanitize($state)),
            ExportColumn::make('status')->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('reference_url')->label('Reference URL'),
            ExportColumn::make('created_at')->label('Submitted At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Your Poetry/Prose submissions export has completed with {$count} ".str('row')->plural($export->successful_rows).' exported.';
    }

    private static function sanitize(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
