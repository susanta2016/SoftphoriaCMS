<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Tables;

use App\Models\User;
use App\Modules\InspirationalResources\Actions\ArchiveResourceSubmissionAction;
use App\Modules\InspirationalResources\Actions\DeleteResourceSubmissionAction;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Filament\Exports\ResourceSubmissionExporter;
use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
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
 * No edit action (Delete removes permanently) — submissions come from the public form, or from the
 * env-gated admin "Add Resource" page (see ResourceSubmissionResource::canCreate()).
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
                TextColumn::make('theme')->placeholder('—'),
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
                SelectFilter::make('theme')
                    ->options(array_combine(ResourceSubmission::THEME_OPTIONS, ResourceSubmission::THEME_OPTIONS)),
            ])
            ->searchable()
            ->recordActions([
                ViewAction::make(),
                ResourceSubmissionResource::deleteAction(),
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
                BulkAction::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete selected resources')
                    ->modalDescription('This permanently deletes the selected resources, including from the public Inspirational Resources page. This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->action(function (Collection $records): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        $action = app(DeleteResourceSubmissionAction::class);

                        $records->each(fn (ResourceSubmission $record) => $action->handle($record, $actor));

                        Notification::make()->title('Resources deleted')->success()->send();
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
