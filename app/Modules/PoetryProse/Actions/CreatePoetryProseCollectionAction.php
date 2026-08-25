<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreatePoetryProseCollectionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): PoetryProseCollection
    {
        return DB::transaction(function () use ($data, $actor): PoetryProseCollection {
            $collection = new PoetryProseCollection;
            $collection->fill($data);
            $collection->status ??= PoetryProseStatus::Draft;
            $collection->created_by = $actor->getKey();
            $collection->updated_by = $actor->getKey();
            $collection->save();

            $this->auditLog->record($actor, 'poetry_prose_collection.created', $collection, ['title' => $collection->title]);

            return $collection;
        });
    }
}
