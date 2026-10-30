<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Schemas\NewsletterCampaignForm;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Shared\Services\AuditLogService;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Drafts only (NewsletterCampaignResource::canEdit()); the save re-checks
 * the status under a row lock, so a campaign scheduled in another tab
 * meanwhile can't be edited after its snapshot was taken.
 */
class EditNewsletterCampaign extends EditRecord
{
    use HasWizard;

    protected static string $resource = NewsletterCampaignResource::class;

    public function getSteps(): array
    {
        return NewsletterCampaignForm::steps();
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var NewsletterCampaign $campaign */
        $campaign = $this->getRecord();

        return [
            ...$data,
            'included_groups' => $campaign->includedGroups()->pluck('newsletter_groups.id')->map(fn ($id): string => (string) $id)->all(),
            'excluded_groups' => $campaign->excludedGroups()->pluck('newsletter_groups.id')->map(fn ($id): string => (string) $id)->all(),
        ];
    }

    /**
     * @param  NewsletterCampaign  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($campaign->status !== CampaignStatus::Draft) {
                Notification::make()->title('Only draft campaigns can be edited. Unschedule it first, or duplicate it.')->danger()->send();
                $this->halt();
            }

            $campaign->fill([
                ...Arr::except($data, ['included_groups', 'excluded_groups', 'status']),
                'updated_by' => Auth::id(),
            ])->save();

            $campaign->syncGroupSelection($data['included_groups'] ?? [], $data['excluded_groups'] ?? []);

            app(AuditLogService::class)->record(Auth::user(), 'newsletter_campaign.updated', $campaign);

            return $campaign;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
