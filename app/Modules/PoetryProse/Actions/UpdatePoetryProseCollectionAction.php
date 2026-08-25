<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdatePoetryProseCollectionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PoetryProseCollection $collection, array $data, User $actor): PoetryProseCollection
    {
        return DB::transaction(function () use ($collection, $data, $actor): PoetryProseCollection {
            $collection->fill($data);
            $collection->updated_by = $actor->getKey();
            $collection->save();

            $this->auditLog->record($actor, 'poetry_prose_collection.updated', $collection, ['title' => $collection->title]);

            return $collection;
        });
    }
}
