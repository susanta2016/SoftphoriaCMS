<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a Menu's items against submitted Repeater data, called by
 * CreateMenuAction/UpdateMenuAction. Every add/change goes through
 * CreateMenuItemAction/UpdateMenuItemAction (not Filament's automatic
 * relationship-repeater save) so destination validation and audit logging
 * always run — the Navigation equivalent of Page's SyncsPageSections.
 */
class SyncMenuItemsAction
{
    public function __construct(
        private readonly CreateMenuItemAction $createMenuItem,
        private readonly UpdateMenuItemAction $updateMenuItem,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(Menu $menu, array $items, User $actor): void
    {
        DB::transaction(function () use ($menu, $items, $actor): void {
            $keepIds = [];

            foreach (array_values($items) as $index => $data) {
                $id = $data['id'] ?? null;
                $data['sort_order'] = $index;

                $existing = $id ? $menu->items()->whereKey($id)->first() : null;

                $item = $existing
                    ? $this->updateMenuItem->handle($existing, $data, $actor)
                    : $this->createMenuItem->handle($menu, $data, $actor);

                $keepIds[] = $item->id;
            }

            $menu->items()->whereNotIn('id', $keepIds)->delete();
        });
    }
}
