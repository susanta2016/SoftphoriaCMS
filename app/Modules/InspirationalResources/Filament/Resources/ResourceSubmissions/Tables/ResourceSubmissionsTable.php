<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Tables;

use App\Models\User;
use App\Modules\InspirationalResources\Actions\ArchiveResourceSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Exports\ResourceSubmissionExporter;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Filament\Actions\BulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * List-only (mirrors OrdersTable) — submissions are created exclusively by
 * CreateResourceSubmissionAction from the public form, never hand-created
 * in the admin panel.
 */
class ResourceSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->searchable()->placeholder('—'),
                TextColumn::make('category')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ResourceSubmissionStatus::options()),
                SelectFilter::make('category')
                    ->options(fn (): array => ResourceSubmission::query()
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->searchable()
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(ResourceSubmissionExporter::class),
                BulkAction::make('archive')
                    ->label('Archive')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        $action = app(ArchiveResourceSubmissionAction::class);

                        $records->each(fn (ResourceSubmission $record) => $action->handle($record, $actor));

                        Notification::make()->title('Submissions archived')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No submissions yet')
            ->emptyStateDescription('New Inspirational Resources submissions from the public form will appear here.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
