<?php

namespace App\Modules\Newsletter\Models;

use App\Models\User;
use App\Modules\Newsletter\Enums\SuppressionReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One address on the global suppression list. Written only through
 * App\Modules\Newsletter\Support\SuppressionList.
 */
#[Fillable(['email', 'reason', 'detail', 'suppressed_by_user_id'])]
class NewsletterSuppression extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => SuppressionReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suppressed_by_user_id');
    }
}
