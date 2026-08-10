# app/Shared/Rules

Laravel validation rules (`Illuminate\Contracts\Validation\ValidationRule`)
reused by two or more modules.

Empty by design: no cross-module rule exists yet. Module-specific rules
belong inside the owning module (`app/Modules/{Module}/Rules`), not here.

See `docs/ARCHITECTURE.md`.
