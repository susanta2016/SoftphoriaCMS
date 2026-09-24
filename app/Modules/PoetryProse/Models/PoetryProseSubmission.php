<?php

namespace App\Modules\PoetryProse\Models;

use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of writing sent through the Poetry/Prose (Light Posts) "Submit
 * Your Writing" form (2026-09-24). A private review-queue record only —
 * never public, never searchable, never in the sitemap. Fields mirror
 * Inspirational Resources' ResourceSubmission, but this is its own table:
 * the two modules are client-confirmed never to relate.
 */
#[Fillable([
    'user_id', 'name', 'email', 'subject', 'category', 'theme', 'message',
    'reference_url', 'status',
])]
class PoetryProseSubmission extends Model
{
    /**
     * @var list<string>
     */
    public const CATEGORY_OPTIONS = ['Testimony', 'Original Essay', 'Original Poetry', 'Reflection'];

    /**
     * @var list<string>
     */
    public const THEME_OPTIONS = ['Inspiration', 'Love', 'Faith', 'Forgiveness', 'Giving', 'Gratitude'];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => PoetryProseSubmissionStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
