<?php

namespace App\Modules\InspirationalResources\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A review-queue status, never a publication status — approving a
 * submission does not make it public. There is no separate "publish this
 * submission" step and no public InspirationalResource editorial model
 * (client-confirmed, final); the only editorial conversion is
 * CreatePoetryProseFromSubmissionAction, available once Approved.
 * `Submitted`'s stored value is literally `'new'` to match
 * resource_submissions.status's existing default (2026_08_10_100903) — no
 * migration change needed to introduce this enum.
 */
enum ResourceSubmissionStatus: string implements HasColor, HasLabel
{
    case Submitted = 'new';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Archived => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
