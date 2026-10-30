<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Pages;

use App\Models\User;
use App\Modules\Newsletter\Actions\Subscribers\ManageSubscriptionAction;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Subscribers\NewsletterSubscriberResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateNewsletterSubscriber extends CreateRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    /**
     * Through ManageSubscriptionAction, so the consent attestation, the
     * duplicate check and the suppression list apply here exactly as they
     * do everywhere else. Groups are saved by the form's relationship
     * field afterwards.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $admin */
        $admin = Auth::user();

        try {
            return app(ManageSubscriptionAction::class)->create(
                $admin,
                (string) $data['email'],
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                [],
                (bool) ($data['consent_confirmed'] ?? false),
                filled($data['source'] ?? null) ? (string) $data['source'] : 'admin',
                $data['profile_fields'] ?? null,
            );
        } catch (NewsletterException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
