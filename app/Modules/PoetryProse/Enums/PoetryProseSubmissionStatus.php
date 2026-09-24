<?php

namespace App\Modules\PoetryProse\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A review-queue status for PoetryProseSubmission — never a publication
 * status. Approving a submission publishes nothing; an admin who wants to
 * feature it writes a Poetry/Prose entry themselves. Same states (and the
 * same stored 'new' value for Submitted) as Inspirational Resources'
 * ResourceSubmissionStatus, kept as its own enum since the two modules
 * don't relate.
 */
enum PoetryProseSubmissionStatus: string implements HasColor, HasLabel
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
