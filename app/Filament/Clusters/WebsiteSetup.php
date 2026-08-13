<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Website Setup (docs/ARCHITECTURE.md §16) — one Core Admin sidebar item.
 * Its Settings page (General/Email tabs) and EmailTemplateResource register
 * themselves under this cluster via their own `$cluster` property; nothing
 * else needs to change here as those are added.
 */
class WebsiteSetup extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;
}
