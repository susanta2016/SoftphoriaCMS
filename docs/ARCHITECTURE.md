# Softphoria Platform Core — Modular Architecture

**Status:** Established by CORE-002, on top of the CORE-001 Laravel baseline.
**Scope:** Architecture and conventions only. No business modules, migrations,
Filament panel, or public UI exist yet — see the completion status in each
section below.

This document is internally authored implementation guidance. It does not
supersede the client-facing specifications in `docs/`; it explains how this
codebase satisfies them. Where anything here appears to conflict with
`Cory_Gold_Master_Development_Specification_v1.0.docx`,
`Softphoria_Platform_Core_Specification_v1.1_Laravel.docx` (Laravel
Architecture Amendment), `Database_Specification_v1.0.docx`, or
`Softphoria_Platform_Implementation_Guide_v1.4_Laravel_Jacob_Approved.md`,
those documents govern.

---

## 1. Core is reusable infrastructure

**Softphoria Platform Core must never contain client-specific business
logic.** Jacob d'IAWARII is the first client of this platform, not the
platform itself. A requirement belongs in Core only if it is genuinely
generic across future Softphoria client projects (see
`Softphoria_Platform_Core_Specification_v1.1_Laravel.docx`); a requirement
that exists only because Jacob's site needs it belongs in a Jacob-specific
module, added in the later JACOB-* stage — never merged into Core merely
because Jacob is the current/only client.

Concretely: nothing under `app/Shared/` may reference Music, Podcast,
Poetry/Prose, Inspirational Resources, Community, or any other
Jacob-specific concept. `app/Shared/` code must read the same whether the
first client were Jacob or any other future Softphoria client.

## 2. Layered request flow

```
HTTP / Filament / Livewire   (presentation — thin)
            ↓
     Services / Actions       (business logic)
            ↓
   Repositories / Eloquent     (persistence)
            ↓
           MariaDB
```

- Controllers, Livewire components, and Filament resources/pages are
  presentation-only: they validate input, call a service or action, and
  render/return a response. They never contain business rules.
- Services and Actions never touch `Illuminate\Http\Request`, never render
  views, and never return HTTP responses — they take and return plain PHP
  values, DTOs, or Eloquent models, so the same business logic is reusable
  from a controller, a Livewire component, a Filament action, an Artisan
  command, or a future API endpoint without duplication.
- Repositories/Eloquent are the only layer that talks to the database.
- No Blade template contains a database query or a business rule.
- No controller contains domain logic.

## 3. Module ownership

| Location | Owns |
|---|---|
| `app/Modules/{Module}/` | Everything specific to that bounded context: controllers, Livewire components, Eloquent models, actions, services, repositories, policies, module-owned Filament resources/pages, events, listeners, jobs, module-specific rules/DTOs/exceptions, module routes, module Blade views. |
| `app/Shared/` | Code with **no** business/domain meaning of its own that is genuinely reused by **two or more** modules — never a place to "pre-stage" logic for a module that doesn't exist yet. |
| `app/Http/` | Global HTTP-layer concerns not owned by any single module: the base `Controller` class, global middleware, a future base `FormRequest`. Module controllers/requests live inside their module, not here. |
| `app/Providers/` | Framework/global bootstrapping only: `AppServiceProvider`, and any future platform-wide provider (e.g. a future Filament `PanelProvider`, a future `EventServiceProvider`). Per-module providers live inside their module and are wired through the Module Registry (§5) — they are never added here by hand. |
| `resources/views/` | Shared/global Blade layouts, reusable components, and framework error pages used across modules. Module-specific views live inside the module and are exposed through a namespaced view path registered by that module's provider. |
| Filament | Platform-wide Filament configuration (the future `PanelProvider`, shared form/table components) is central. Module-owned Resources/Pages/Widgets live inside `app/Modules/{Module}/Filament/` and are registered with the panel by that module's own provider. Not scaffolded yet (ADMIN-001). |
| `database/` | `migrations/`, `factories/`, `seeders/` stay centralized here as the single schema source of truth, per the Database Specification. Modules do not maintain parallel migration directories in Phase 1. |
| Infrastructure/config (`docker/`, `compose.yaml`, root `config/*.php`, `.env*`) | Platform infrastructure — owned by Core, never module- or client-specific. Module-specific settings, only when genuinely required, are added as their own `config/{module}.php` merged by that module's provider (§8) — never hardcoded into shared platform config files. |

### Reserved module namespace

The approved Softphoria module set (from the Implementation Guide and Cory
Gold Master Spec) is:

```
app/Modules/
  Homepage/  About/  Music/  Podcast/  PoetryProse/  InspirationalResources/
  Community/ Users/  Newsletter/ Search/ Media/ SEO/ Analytics/ Settings/ Security/
```

None of these directories exist yet — CORE-002 intentionally does not
scaffold placeholder modules. This list only reserves the names/boundaries
so that when a module is actually implemented, it lands in a predictable
location and does not collide with another module's naming.

### Standard module layout (template for when a module is created)

```
app/Modules/{Module}/
    Actions/
    Services/
    Repositories/
    Models/
    Policies/
    Http/
        Controllers/
        Requests/
    Livewire/
    Filament/
        Resources/
        Pages/
    Events/
    Listeners/
    Jobs/
    Rules/
    DTOs/
    Exceptions/
    Providers/
        {Module}ServiceProvider.php   // extends App\Shared\Support\Modules\ModuleServiceProvider
    routes/
        web.php
    resources/
        views/
```

A module only creates the subdirectories it actually needs — this is a
maximum template, not a mandatory checklist.

## 4. Service / Action convention

- **Service** — a stateless class encapsulating a cohesive set of business
  operations for one bounded context or entity family (e.g. `MusicService`).
  Single responsibility; never a "god service" mixing unrelated concerns.
  Lives in `app/Modules/{Module}/Services`.
- **Action** — a single-purpose, invokable class for one discrete business
  operation (e.g. `PublishAlbumAction`), with one public `handle()` method.
  Easy to unit test and reuse from a controller, Livewire component,
  Filament action, console command, or queued job. Lives in
  `app/Modules/{Module}/Actions`.
- Use an Action for one discrete operation; use a Service when several
  related operations naturally share dependencies/state. Don't build both
  for the same trivial operation.
- Business operations that touch multiple tables run inside a database
  transaction, owned by the Service/Action — never by a controller or a
  repository.
- No cross-module service exists yet, so `app/Shared/Services` is currently
  empty (see its README).

## 5. Module registration mechanism

Implemented in CORE-002, in `app/Shared/Support/Modules/`:

- **`ModuleServiceProvider`** (abstract) — the base every module's own
  provider must extend. It requires one thing: a stable `moduleName()`.
  Everything else about a module's provider is normal Laravel
  `ServiceProvider` `register()`/`boot()` — loading the module's routes,
  views, migrations, or config once that module actually exists.
- **`ModuleRegistry`** — reads a list of provider class names, validates
  each one extends `ModuleServiceProvider`, and registers it with the
  application container via `Application::register()`. Bound as a singleton
  and driven by `AppServiceProvider::register()`, which passes it
  `config('modules.enabled')`.
- **`config/modules.php`** — the single list of enabled module providers.
  Enabling or disabling a module is a one-line change here; it never
  requires touching `AppServiceProvider` or any other bootstrap file.

This is deliberately the entire module system for now: no filesystem
scanning, no manifest file format, no caching layer. That additional
machinery is not justified until real modules exist and demonstrably need
it — adding it now would be exactly the kind of speculative abstraction
CORE-002 is scoped to avoid.

Currently `config('modules.enabled')` is an empty array: the Softphoria
Core ships with zero business modules, by design.

## 6. Repository / Eloquent convention

Per the Repository Pattern mandated by the Core Specification (Ch. 23):

- A repository is responsible **only** for persistence: create, read,
  update, delete, pagination, search, filtering against one Eloquent model
  (or a small closely related set).
- A repository never contains business rules, authorization, validation,
  email/notification sending, or logging — those belong in the
  Service/Action layer that calls the repository.
- Repositories are Eloquent-backed, not a database-agnostic abstraction —
  Eloquent already provides that portability where it is genuinely needed.
- Repositories live inside the owning module
  (`app/Modules/{Module}/Repositories`). There is no shared
  "repository-everything" base class: no repository exists yet because no
  Eloquent models exist yet, and a generic base class with nothing concrete
  to prove it out would itself be a speculative abstraction. The first
  module to need one establishes the concrete pattern other modules follow.
- Simple, non-repeated Eloquent lookups (e.g. a straightforward
  relationship traversal) do not require a repository — repositories exist
  to encapsulate non-trivial or reused queries, not to wrap every model.

## 7. Dependency direction

```
Core / Shared infrastructure
            ↓
         Modules
            ↓
    Presentation adapters (HTTP, Livewire, Filament, console)
```

- A module may depend on `app/Shared/`. `app/Shared/` never depends on a
  module.
- A module never reaches into another module's internals (its Eloquent
  models, repositories, or private services) directly. Cross-module
  interaction happens only through:
  - an explicit public service/contract the owning module exposes, or
  - Laravel events (§8), or
  - another approved boundary (e.g. a shared DTO in `app/Shared/DTOs`).
- No circular module dependencies (Module A depending on Module B while
  Module B depends on Module A).
- This is currently a documented convention, enforced by code review.
  Automated enforcement (e.g. architecture/dependency tests) is a candidate
  for CORE-003 (Code Quality), once static-analysis tooling is chosen —
  CORE-002 does not add a new dependency for this.

## 8. Events, listeners, and jobs

Convention for when modules exist and need them (none are created in
CORE-002 — there is no domain event to model yet):

- **Domain/application events** — named `{Entity}{PastTenseVerb}` (e.g.
  `ArticlePublished`, `UserRegistered`), live in
  `app/Modules/{Module}/Events`. A module fires an event instead of calling
  another module's service directly, so side effects stay loosely coupled.
- **Listeners** — live in `app/Modules/{Module}/Listeners`. A listener in
  one module may subscribe to an event fired by another module (that's the
  approved cross-module boundary from §7); the reverse — a module directly
  invoking another module's internals — is not allowed.
- **Queued jobs** — implement `ShouldQueue`, live in
  `app/Modules/{Module}/Jobs`, and back onto Redis (already configured in
  CORE-001). Used for anything that must not block a request: image
  optimization, email sending, search indexing, sitemap generation, etc.,
  per the Core Specification.

## 9. Configuration convention

- Platform-wide configuration lives in the standard `config/*.php` files
  (as already established by Laravel/CORE-001), plus `config/modules.php`
  added in CORE-002.
- A module-specific config file is only introduced when a module genuinely
  needs one, as `config/{module-slug}.php`, merged by that module's own
  provider via `mergeConfigFrom()`. No config file is pre-created for a
  module that doesn't exist yet.

## 10. Testing convention

- **Architecture tests** verify the conventions CORE-002 introduces (module
  registration, module discovery/validation) — they live in
  `tests/Unit/Architecture/` and do not depend on any business module.
  `ModuleRegistryTest` is the first of these.
- **Business feature tests** (content CRUD, authorization, search, SEO,
  etc.) do not exist yet because no business module exists yet. They will
  live under `tests/Feature/{Module}/` once a module is implemented, per
  the Implementation Guide's testing requirements.
- A throwaway test fixture (`tests/Support/Fixtures/FakeModuleServiceProvider`)
  is used to exercise `ModuleRegistry` without implying any real module —
  it is never referenced by `config/modules.php` and carries no business
  logic.

## 11. What CORE-002 explicitly did not do

Per the CORE-002 task boundaries, none of the following were created:
database tables or migrations, Eloquent domain models, Filament resources
or panels, authentication, RBAC, CMS, Media Library, SEO, Music, Podcast,
Poetry/Prose, Inspirational Resources, Community, Newsletter, Analytics,
Membership, Payments, any Jacob-specific functionality, or any public
frontend/homepage UI. Those belong to DB-*, ADMIN-*, and JACOB-* stages
that follow, per
`docs/Softphoria_Platform_Implementation_Guide_v1.4_Laravel_Jacob_Approved.md`.
