# app/Shared/Services

Application services reused by two or more modules, with no owning module of
their own.

Empty by design: no cross-module service exists yet because no modules have
been implemented. Module-specific services belong inside the owning module
(`app/Modules/{Module}/Services`), not here — do not pre-place them in Shared
just because a future module "might" need something similar.

See `docs/ARCHITECTURE.md` for the full service/action convention.
