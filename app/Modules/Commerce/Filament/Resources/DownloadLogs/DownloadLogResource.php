<?php

namespace App\Modules\Commerce\Filament\Resources\DownloadLogs;

use App\Modules\Commerce\Filament\Resources\DownloadLogs\Pages\ListDownloadLogs;
use App\Modules\Commerce\Filament\Resources\DownloadLogs\Tables\DownloadLogsTable;
use App\Modules\Commerce\Models\DownloadLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * "Download History" (§6/§16). List-only — an immutable audit trail with no
 * Create/Edit/Delete/View page; every row already reads in full from the
 * list (§16's requested columns fit one table row, no drill-down needed).
 */
class DownloadLogResource extends Resource
{
    protected static ?string $model = DownloadLog::class;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Download History';

    public static function table(Table $table): Table
    {
        return DownloadLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloadLogs::route('/'),
        ];
    }
}
