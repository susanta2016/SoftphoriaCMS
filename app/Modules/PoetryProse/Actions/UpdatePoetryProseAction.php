<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Actions\Concerns\SavesPoetryProseRelations;
use App\Modules\PoetryProse\Actions\Concerns\SnapshotsPoetryProseRevisions;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdatePoetryProseAction
{
    use SavesPoetryProseRelations, SnapshotsPoetryProseRevisions;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PoetryProse $entry, array $data, User $actor): PoetryProse
    {
        return DB::transaction(function () use ($entry, $data, $actor): PoetryProse {
            $entry->fill(collect($data)->except(['seo', 'categoryIds', 'tagIds'])->all());
            $entry->updated_by = $actor->getKey();
            $entry->save();

            $this->saveSeo($entry, $data['seo'] ?? []);
            $this->syncCategories($entry, $data['categoryIds'] ?? []);
            $this->syncTags($entry, $data['tagIds'] ?? []);
            $this->snapshotRevision($entry, $actor);

            $this->auditLog->record($actor, 'poetry_prose.updated', $entry, ['title' => $entry->title]);

            return $entry;
        });
    }
}
