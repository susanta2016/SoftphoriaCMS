<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Schemas;

use App\Models\AuditLog;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only — everything Admin needs to see about one submission. The
 * mutations available from here (Review/Approve/Archive/Create
 * Poetry-Prose) are all header actions on ViewResourceSubmission, never a
 * form on this record.
 */
class ResourceSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Submitter'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('user.name')->label('Account')->placeholder('Guest'),
                        TextEntry::make('subject')->placeholder('—'),
                        TextEntry::make('category'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')->hiddenLabel()->columnSpanFull(),
                    ]),

                Section::make('Related item')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('relatedAlbum.title')->label('Album')->placeholder('—'),
                        TextEntry::make('relatedTrack.title')->label('Track')->placeholder('—'),
                    ])
                    ->visible(fn (ResourceSubmission $record): bool => $record->related_album_id !== null || $record->related_track_id !== null),

                Section::make('Editorial outcome')
                    ->schema([
                        TextEntry::make('poetryProse.title')
                            ->label('Drafted as Poetry/Prose')
                            ->placeholder('Not drafted yet'),
                    ])
                    ->visible(fn (ResourceSubmission $record): bool => $record->status === ResourceSubmissionStatus::Approved || $record->poetry_prose_id !== null),

                // Reviewer/timestamp info reuses the existing platform-wide
                // audit_logs table (AuditLogService) rather than adding new
                // reviewed_by/reviewed_at columns — every status transition
                // this resource's own actions perform already writes one.
                Section::make('Review Information')
                    ->schema([
                        RepeatableEntry::make('reviewHistory')
                            ->label('')
                            ->state(fn (ResourceSubmission $record) => AuditLog::query()
                                ->where('entity_type', 'ResourceSubmission')
                                ->where('entity_id', $record->id)
                                ->with('user')
                                ->orderByDesc('created_at')
                                ->get())
                            ->schema([
                                TextEntry::make('action')->label('Action'),
                                TextEntry::make('user.name')->label('By')->placeholder('System'),
                                TextEntry::make('created_at')->label('When')->dateTime(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
