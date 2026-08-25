<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Exceptions\ResourceSubmissionAlreadyProcessedException;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The optional action that turns an Approved submission into a Poetry/
 * Prose Draft (client-confirmed final workflow: this is the only outcome
 * of Approved besides Archive). Creates a Draft only — an admin still edits
 * content type/categories/tags/featured image/SEO and moves it through its
 * own Draft → Published lifecycle before it's ever public. Never alters the
 * submission's own review status, and nothing here or afterward (editing,
 * publishing, archiving, or deleting the resulting entry) ever writes back
 * to the original submission beyond this one initial link — the two stay
 * independent records for administrative traceability only.
 */
class CreatePoetryProseFromSubmissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @throws ResourceSubmissionAlreadyProcessedException
     */
    public function handle(ResourceSubmission $submission, User $actor): PoetryProse
    {
        if ($submission->status !== ResourceSubmissionStatus::Approved) {
            throw ResourceSubmissionAlreadyProcessedException::notApproved();
        }

        if ($submission->poetry_prose_id !== null) {
            throw ResourceSubmissionAlreadyProcessedException::alreadyDrafted();
        }

        return DB::transaction(function () use ($submission, $actor): PoetryProse {
            $entry = new PoetryProse;
            $entry->title = $submission->subject ?: $submission->name;
            $entry->slug = $this->uniqueSlug($entry->title);
            $entry->body = $submission->message;
            $entry->content_type = PoetryProseContentType::Essay;
            $entry->status = PoetryProseStatus::Draft;
            $entry->created_by = $actor->getKey();
            $entry->updated_by = $actor->getKey();
            $entry->save();

            $submission->poetry_prose_id = $entry->id;
            $submission->save();

            $this->auditLog->record($actor, 'resource_submission.drafted_as_poetry_prose', $submission, [
                'poetry_prose_id' => $entry->id,
            ]);

            return $entry;
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'poetry-prose-entry';
        $slug = $base;
        $suffix = 1;

        while (PoetryProse::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
