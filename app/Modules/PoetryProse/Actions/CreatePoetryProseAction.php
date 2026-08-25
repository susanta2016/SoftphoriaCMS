<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Actions\Concerns\SavesPoetryProseRelations;
use App\Modules\PoetryProse\Actions\Concerns\SnapshotsPoetryProseRevisions;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreatePoetryProseAction
{
    use SavesPoetryProseRelations, SnapshotsPoetryProseRevisions;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): PoetryProse
    {
        return DB::transaction(function () use ($data, $actor): PoetryProse {
            $entry = new PoetryProse;
            $entry->fill(collect($data)->except(['seo', 'categoryIds', 'tagIds'])->all());
            $entry->status ??= PoetryProseStatus::Draft;
            $entry->created_by = $actor->getKey();
            $entry->updated_by = $actor->getKey();
            $entry->save();

            $this->saveSeo($entry, $data['seo'] ?? []);
            $this->syncCategories($entry, $data['categoryIds'] ?? []);
            $this->syncTags($entry, $data['tagIds'] ?? []);
            $this->snapshotRevision($entry, $actor);

            $this->auditLog->record($actor, 'poetry_prose.created', $entry, ['title' => $entry->title]);

            return $entry;
        });
    }
}
