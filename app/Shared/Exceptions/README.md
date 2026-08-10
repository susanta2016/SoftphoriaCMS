# app/Shared/Exceptions

Exception types meaningfully thrown or caught across module boundaries.

Empty by design: no shared exception exists yet. Module-specific exceptions
belong inside the owning module (`app/Modules/{Module}/Exceptions`). A
platform-wide base exception/error-handling convention, if one is warranted,
is CORE-005's responsibility, not CORE-002's.

See `docs/ARCHITECTURE.md`.
