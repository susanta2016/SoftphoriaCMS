# app/Shared/DTOs

Immutable data transfer objects reused by two or more modules (for example a
future generic `Pagination` or `Address` value object).

Empty by design: no cross-module DTO exists yet. Module-specific DTOs belong
inside the owning module (`app/Modules/{Module}/DTOs`), not here.

Convention for when a DTO is introduced: `final readonly class`, no Eloquent
or `Illuminate\Http\Request` dependency, named `{Noun}Data`. See
`docs/ARCHITECTURE.md`.
