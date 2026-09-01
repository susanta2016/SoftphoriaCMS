<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A submitted Review/Rating's moderation state (reviews.status). Mirrors
 * PodcastEpisodeStatus's shape (a genuine public-visibility "published"
 * end-state, not just an internal review queue like ResourceSubmission's
 * new/in_review/approved/archived) since only Approved reviews are ever
 * shown publicly. See config('reviews.reviews_ratings_admin_approval') for
 * whether a new submission starts Pending or Approved.
 */
enum ReviewStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
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
