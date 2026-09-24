<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Tables;

use App\Modules\PoetryProse\Actions\ChangePoetryProseSubmissionStatusAction;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use App\Modules\PoetryProse\Filament\Exports\PoetryProseSubmissionExporter;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\PoetryProseSubmissionResource;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use Filament\Actions\BulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PoetryProseSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->searchable()->placeholder('—'),
                TextColumn::make('category'),
                TextColumn::make('theme')->placeholder('—'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PoetryProseSubmissionStatus::options()),
                SelectFilter::make('category')
                    ->options(array_combine(PoetryProseSubmission::CATEGORY_OPTIONS, PoetryProseSubmission::CATEGORY_OPTIONS)),
                SelectFilter::make('theme')
                    ->options(array_combine(PoetryProseSubmission::THEME_OPTIONS, PoetryProseSubmission::THEME_OPTIONS)),
            ])
            ->searchable()
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exporter(PoetryProseSubmissionExporter::class),
                BulkAction::make('archive')
                    ->label('Archive')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $action = app(ChangePoetryProseSubmissionStatusAction::class);
                        $actor = PoetryProseSubmissionResource::actor();

                        $records->each(fn (PoetryProseSubmission $record) => $action->handle($record, PoetryProseSubmissionStatus::Archived, $actor));

                        Notification::make()->title('Submissions archived')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No submissions yet')
            ->emptyStateDescription('Writing sent through the Poetry/Prose "Submit Your Writing" form will appear here.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
