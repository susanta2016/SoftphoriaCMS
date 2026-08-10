# app/Filament

Genuinely platform-wide Filament components that belong to no single module:
the central `Dashboard` landing page, and any future Resource/Page/Widget
that is core infrastructure rather than one module's content.

- `Resources/` — discovered by `AdminPanelProvider` under `App\Filament\Resources`.
- `Pages/` — discovered by `AdminPanelProvider` under `App\Filament\Pages`.
- `Widgets/` — discovered by `AdminPanelProvider` under `App\Filament\Widgets`.

A module's own Resources/Pages/Widgets do **not** belong here — they live in
`app/Modules/{Module}/Filament/` and are picked up automatically by the same
panel via its recursive discovery of `app/Modules`. See `docs/ARCHITECTURE.md`
§3 and the doc comment on `App\Providers\Filament\AdminPanelProvider`.

Do not pre-create empty Resources/Pages/Widgets subdirectories here — add
them only when a genuine core-wide component exists.
