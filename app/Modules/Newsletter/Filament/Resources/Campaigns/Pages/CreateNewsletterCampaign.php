<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Pages;

use App\Modules\Newsletter\Filament\Resources\Campaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Filament\Resources\Campaigns\Schemas\NewsletterCampaignForm;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Shared\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Draft only — nothing is sent from here.
 */
class CreateNewsletterCampaign extends CreateRecord
{
    use HasWizard;

    protected static string $resource = NewsletterCampaignResource::class;

    public function getSteps(): array
    {
        return NewsletterCampaignForm::steps();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->create([
                ...Arr::except($data, ['included_groups', 'excluded_groups', 'status']),
                'timezone' => app(NewsletterSettings::class)->timezone(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $campaign->syncGroupSelection($data['included_groups'] ?? [], $data['excluded_groups'] ?? []);

            app(AuditLogService::class)->record(Auth::user(), 'newsletter_campaign.created', $campaign);

            return $campaign;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
