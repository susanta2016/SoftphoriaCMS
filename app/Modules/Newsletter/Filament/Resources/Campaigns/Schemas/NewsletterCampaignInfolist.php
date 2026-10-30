<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Schemas;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\CampaignReport;
use App\Modules\Newsletter\Support\RecipientResolver;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The campaign page: delivery report once sending started, otherwise the
 * live eligible-recipient estimate, then the campaign's settings.
 */
class NewsletterCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Delivery report')
                    ->visible(fn (NewsletterCampaign $record): bool => $record->recipients_snapshot_at !== null)
                    ->schema([
                        View::make('newsletter-marketing::filament.campaign-metrics')
                            ->viewData(fn (NewsletterCampaign $record): array => [
                                'report' => CampaignReport::for($record),
                                'tracking' => ['opens' => $record->track_opens, 'clicks' => $record->track_clicks],
                            ]),
                    ]),

                Section::make('Overview')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('status_reason')->label('Note')->placeholder('—')->columnSpan(2),
                        TextEntry::make('scheduled_local')
                            ->label('Scheduled for')
                            ->state(fn (NewsletterCampaign $record): ?string => $record->scheduledAtLocal()?->format('M j, Y g:i A T'))
                            ->placeholder('Not scheduled'),
                        TextEntry::make('started_at')->label('Sending started')->dateTime()->placeholder('—'),
                        TextEntry::make('completed_at')->label('Finished')->dateTime()->placeholder('—'),
                        TextEntry::make('eligible_estimate')
                            ->label('Eligible recipients right now')
                            ->visible(fn (NewsletterCampaign $record): bool => in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true))
                            ->state(fn (NewsletterCampaign $record): string => number_format(app(RecipientResolver::class)->countForCampaign($record)))
                            ->helperText('Re-checked for every recipient at send time.'),
                    ]),

                Section::make('Email')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subject'),
                        TextEntry::make('preheader')->placeholder('—'),
                        TextEntry::make('from')
                            ->label('From')
                            ->state(fn (NewsletterCampaign $record): string => "{$record->from_name} <{$record->from_email}>"),
                        TextEntry::make('template.name')->label('Template')->placeholder('Deleted template'),
                        TextEntry::make('cta')
                            ->label('Call to action')
                            ->state(fn (NewsletterCampaign $record): ?string => filled($record->cta_url) ? "{$record->cta_text} → {$record->cta_url}" : null)
                            ->placeholder('None'),
                        TextEntry::make('tracking')
                            ->state(fn (NewsletterCampaign $record): string => implode(', ', array_filter([
                                $record->track_opens ? 'Opens' : null,
                                $record->track_clicks ? 'Clicks' : null,
                            ])) ?: 'Off'),
                    ]),

                Section::make('Recipients')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('included')
                            ->label('Groups')
                            ->badge()
                            ->state(fn (NewsletterCampaign $record): array => $record->groups_snapshot['included'] ?? $record->includedGroups()->pluck('name')->all())
                            ->placeholder('None selected'),
                        TextEntry::make('excluded')
                            ->label('Excluding')
                            ->badge()
                            ->color('gray')
                            ->state(fn (NewsletterCampaign $record): array => $record->groups_snapshot['excluded'] ?? $record->excludedGroups()->pluck('name')->all())
                            ->placeholder('None'),
                    ]),

                Section::make('History')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('creator.name')->label('Created by')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('content_snapshot_at')->label('Content snapshot taken')->dateTime()->placeholder('—'),
                        TextEntry::make('recipients_snapshot_at')->label('Recipient list taken')->dateTime()->placeholder('—'),
                        TextEntry::make('cancelled_at')->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
