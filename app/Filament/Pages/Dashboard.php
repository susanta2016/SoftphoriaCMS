<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemStatusWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Overrides Filament's default dashboard purely to drop widgets that aren't
 * Softphoria admin content: SystemStatusWidget (Queued/Failed Jobs), per
 * explicit UI feedback. AccountWidget/FilamentInfoWidget ("Welcome" and
 * "filament" cards) are dropped by simply not registering them on the panel
 * (see AdminPanelProvider) rather than filtered here, since nothing else
 * would render them. SystemStatusWidget itself is left intact — still
 * auto-discovered and tested — in case a future dedicated ops page wants it.
 */
class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return array_filter(
            parent::getWidgets(),
            fn ($widget): bool => $widget !== SystemStatusWidget::class,
        );
    }
}
