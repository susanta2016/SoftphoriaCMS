# app/Shared/Services

Application services reused by two or more modules (or genuinely core-wide,
with no owning module of their own): `AuditLogService`, and the `Settings/`
namespace (`SettingsRepository`, `MailSettingsApplier`) added by Website
Setup (`docs/ARCHITECTURE.md` §16). Module-specific services belong inside
the owning module (`app/Modules/{Module}/Services`), not here — do not
pre-place them in Shared just because a future module "might" need
something similar.

See `docs/ARCHITECTURE.md` for the full service/action convention.
