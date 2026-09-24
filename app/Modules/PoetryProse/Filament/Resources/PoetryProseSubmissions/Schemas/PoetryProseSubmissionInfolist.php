<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseSubmissions\Schemas;

use App\Models\AuditLog;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only, same layout as Inspirational Resources'
 * ResourceSubmissionInfolist. Review history comes from audit_logs, which
 * ChangePoetryProseSubmissionStatusAction writes on every transition.
 */
class PoetryProseSubmissionInfolist
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
                        TextEntry::make('theme')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')->hiddenLabel()->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),

                Section::make('Reference')
                    ->schema([
                        TextEntry::make('reference_url')->label('Website URL')->url(fn (PoetryProseSubmission $record): ?string => $record->reference_url)->openUrlInNewTab(),
                    ])
                    ->visible(fn (PoetryProseSubmission $record): bool => $record->reference_url !== null),

                Section::make('Review Information')
                    ->schema([
                        RepeatableEntry::make('reviewHistory')
                            ->label('')
                            ->state(fn (PoetryProseSubmission $record) => AuditLog::query()
                                ->where('entity_type', 'PoetryProseSubmission')
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
