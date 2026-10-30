<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A campaign's lifecycle. Every status change goes through
 * NewsletterCampaign::transitionTo(), which refuses anything not listed in
 * allowedTransitions() — e.g. a Sent campaign can never be re-sent, a
 * Cancelled one never resumed. Sent/Partially Sent/Failed/Cancelled are
 * final: to send similar content again, duplicate the campaign.
 */
enum CampaignStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case PartiallySent = 'partially_sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Queued, self::Cancelled],
            // Unschedule returns to Draft for editing.
            self::Scheduled => [self::Draft, self::Queued, self::Cancelled, self::Failed],
            self::Queued => [self::Sending, self::Cancelled, self::Failed],
            self::Sending => [self::Sent, self::PartiallySent, self::Failed, self::Cancelled],
            self::Sent, self::PartiallySent, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Statuses a campaign is "in flight" in — the group-delete guard and
     * the dispatcher's stall check look at these.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Scheduled, self::Queued, self::Sending];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::PartiallySent => 'Partially Sent',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::Scheduled => 'info',
            self::Queued, self::Sending, self::PartiallySent => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
