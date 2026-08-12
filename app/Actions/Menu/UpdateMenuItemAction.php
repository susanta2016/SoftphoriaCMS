<?php

namespace App\Actions\Menu;

use App\Actions\Menu\Concerns\ValidatesMenuItemDestination;
use App\Models\MenuItem;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use InvalidArgumentException;

class UpdateMenuItemAction
{
    use ValidatesMenuItemDestination;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MenuItem $item, array $data, User $actor): MenuItem
    {
        $this->validateDestination($data);

        if (isset($data['parent_id']) && (int) $data['parent_id'] === $item->id) {
            throw new InvalidArgumentException('A menu item cannot be its own parent.');
        }

        $item->fill($data);
        $item->updated_by = $actor->getKey();
        $item->save();

        $this->auditLog->record($actor, 'menu_item.updated', $item, ['label' => $item->label]);

        return $item;
    }
}
