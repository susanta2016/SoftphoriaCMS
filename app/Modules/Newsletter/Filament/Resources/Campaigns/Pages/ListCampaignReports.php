<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Newsletter Marketing > Campaign Reports: every campaign that has started
 * sending, with its headline numbers counted in the query (withCount), so
 * the list stays one query per page. Open the campaign for the full report
 * and per-recipient detail.
 */
class ListCampaignReports extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected static ?string $title = 'Campaign Reports';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereNotNull('recipients_snapshot_at')
                ->withCount([
                    'recipients as submitted_count' => fn (Builder $query) => $query->whereIn('status', CampaignRecipientStatus::submittedValues()),
                    'recipients as delivered_count' => fn (Builder $query) => $query->whereNotNull('delivered_at'),
                    'recipients as failed_count' => fn (Builder $query) => $query->where('status', CampaignRecipientStatus::Failed->value),
                    'recipients as bounced_count' => fn (Builder $query) => $query->whereNotNull('bounced_at'),
                    'recipients as opened_count' => fn (Builder $query) => $query->whereNotNull('opened_at'),
                    'recipients as clicked_count' => fn (Builder $query) => $query->whereNotNull('clicked_at'),
                    'recipients as unsubscribed_count' => fn (Builder $query) => $query->whereNotNull('unsubscribed_at'),
                ]))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (NewsletterCampaign $record): string => $record->subject),
                TextColumn::make('status')->badge(),
                TextColumn::make('started_at')->label('Sent')->dateTime('M j, Y')->sortable(),
                TextColumn::make('recipients_total')->label('Recipients')->numeric(),
                TextColumn::make('submitted_count')->label('Submitted')->numeric(),
                TextColumn::make('delivered_count')->label('Delivered')->numeric(),
                TextColumn::make('failed_count')->label('Failed')->numeric(),
                TextColumn::make('bounced_count')->label('Bounced')->numeric()->toggleable(),
                TextColumn::make('opened_count')
                    ->label('Opens*')
                    ->tooltip('Unique opens, approximate.')
                    ->state(fn (NewsletterCampaign $record): string => $record->track_opens ? self::withRate($record->opened_count, $record->submitted_count) : 'Not tracked'),
                TextColumn::make('clicked_count')
                    ->label('Clicks')
                    ->state(fn (NewsletterCampaign $record): string => $record->track_clicks ? self::withRate($record->clicked_count, $record->submitted_count) : 'Not tracked'),
                TextColumn::make('unsubscribed_count')->label('Unsubscribes')->numeric()->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Report'),
            ])
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('No reports yet')
            ->emptyStateDescription('Reports appear here once a campaign starts sending.');
    }

    private static function withRate(int $count, int $of): string
    {
        return $of > 0 ? number_format($count).' ('.round($count / $of * 100, 1).'%)' : number_format($count);
    }

    public function getSubheading(): ?string
    {
        return '"Submitted" means the mail provider accepted the email; "Delivered" needs provider delivery events (SES). '
            .CampaignStatus::Sending->getLabel().' campaigns update as they go.';
    }
}
