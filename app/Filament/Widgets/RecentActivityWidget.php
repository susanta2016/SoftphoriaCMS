<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Surfaces the existing audit_logs table (DB-002) on the dashboard. No new
 * table or writer is introduced here — this only reads whatever audit
 * entries other stages record.
 */
class RecentActivityWidget extends TableWidget
{
    protected static ?int $sort = 30;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Recent Administrative Activity')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->formatStateUsing(fn (?string $state): string => $state ?? 'System'),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('entity_type')
                    ->label('Entity'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No administrative activity yet')
            ->emptyStateDescription('Actions performed in the admin panel will appear here.');
    }

    protected function getTableQuery(): Builder
    {
        return AuditLog::query()->with('user')->latest()->limit(10);
    }
}
