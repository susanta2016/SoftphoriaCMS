<?php

namespace App\Filament\Livewire;

use App\Actions\System\RevokeAllSessionsAction;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasRenderHookScopes;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Topbar "System Tools" dropdown
 * (docs/Reference UI/Admin/Admin navigation UI.docx). A plain Livewire
 * component — not a Filament Page — that opts into the same Actions
 * machinery Pages use (see Filament\Pages\BasePage) so each tool gets a
 * real confirmation modal instead of a native browser confirm(), and is
 * mounted into the panel via AdminPanelProvider's TOPBAR_START render hook.
 *
 * Available to any admin (same gate as the rest of the panel — no
 * super-admin tier exists yet, see ARCHITECTURE.md §12).
 */
class SystemToolsMenu extends Component implements HasActions, HasRenderHookScopes, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function render(): View
    {
        return view('filament.livewire.system-tools-menu');
    }

    public function systemToolsAction(): ActionGroup
    {
        return ActionGroup::make([
            $this->clearAllCachesAction(),
            $this->clearAppCacheAction(),
            $this->clearRouteCacheAction(),
            $this->clearConfigCacheAction(),
            $this->clearViewCacheAction(),
            $this->optimizeApplicationAction(),
            $this->clearAllSessionsAction(),
        ])
            ->label('System Tools')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->link()
            ->color('gray');
    }

    public function clearAllCachesAction(): Action
    {
        return Action::make('clearAllCaches')
            ->label('Clear All Caches')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Clear the application, route, config, and view caches?')
            ->action(fn () => $this->runArtisan('optimize:clear', 'system.caches_cleared', 'All caches cleared'));
    }

    public function clearAppCacheAction(): Action
    {
        return Action::make('clearAppCache')
            ->label('Clear App Cache')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn () => $this->runArtisan('cache:clear', 'system.cache_cleared', 'Application cache cleared'));
    }

    public function clearRouteCacheAction(): Action
    {
        return Action::make('clearRouteCache')
            ->label('Clear Route Cache')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn () => $this->runArtisan('route:clear', 'system.route_cache_cleared', 'Route cache cleared'));
    }

    public function clearConfigCacheAction(): Action
    {
        return Action::make('clearConfigCache')
            ->label('Clear Config Cache')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn () => $this->runArtisan('config:clear', 'system.config_cache_cleared', 'Config cache cleared'));
    }

    public function clearViewCacheAction(): Action
    {
        return Action::make('clearViewCache')
            ->label('Clear View Cache')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->action(fn () => $this->runArtisan('view:clear', 'system.view_cache_cleared', 'View cache cleared'));
    }

    public function optimizeApplicationAction(): Action
    {
        return Action::make('optimizeApplication')
            ->label('Optimize Application')
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Cache config, routes, views, and events for production performance?')
            ->action(fn () => $this->runArtisan('optimize', 'system.optimized', 'Application optimized'));
    }

    public function clearAllSessionsAction(): Action
    {
        return Action::make('clearAllSessions')
            ->label('Clear All Sessions')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Sign every other active session out, platform-wide? Your own session is kept — everyone else (admins and users) will need to log back in.')
            ->action(function (): void {
                $revoked = app(RevokeAllSessionsAction::class)->handle(session()->getId());

                /** @var User $actor */
                $actor = Auth::user();

                app(AuditLogService::class)->record($actor, 'system.sessions_cleared', $actor, [
                    'revoked' => $revoked,
                ]);

                Notification::make()
                    ->title("Cleared {$revoked} other session(s)")
                    ->success()
                    ->send();
            });
    }

    private function runArtisan(string $command, string $auditAction, string $successMessage): void
    {
        // Resolve the actor *before* calling Artisan — commands like
        // "optimize"/"config:cache" re-cache the config mid-request, which
        // has been observed to reset auth state for the remainder of the
        // request if Auth::user() is read afterward.
        /** @var User $actor */
        $actor = Auth::user();

        Artisan::call($command);

        app(AuditLogService::class)->record($actor, $auditAction, $actor);

        Notification::make()
            ->title($successMessage)
            ->success()
            ->send();
    }
}
