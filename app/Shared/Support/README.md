# app/Shared/Support

Framework-agnostic utilities and platform infrastructure that are genuinely
used by more than one module (or by the platform itself), and that carry no
business/domain meaning of their own.

Currently contains:

- `Modules/` — the Softphoria module registration mechanism
  (`ModuleServiceProvider`, `ModuleRegistry`). See `docs/ARCHITECTURE.md`.

Do not add speculative helpers here. A class only belongs in `Support` once
at least one real, current need for it exists.
