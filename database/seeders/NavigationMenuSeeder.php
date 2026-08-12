<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Seeds the two Phase-1 menu locations (ADMIN-006 §J) — identified by
 * `slug`, not a separate `location` column. Stage D looks a menu up by
 * this known slug later. Footer uses the same generic menu system as
 * Primary, not a separate mechanism.
 */
class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::query()->firstOrCreate(
            ['slug' => 'primary-navigation'],
            ['name' => 'Primary Navigation', 'is_active' => true],
        );

        Menu::query()->firstOrCreate(
            ['slug' => 'footer-navigation'],
            ['name' => 'Footer Navigation', 'is_active' => true],
        );
    }
}
