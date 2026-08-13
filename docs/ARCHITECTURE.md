# Softphoria Platform Core — Modular Architecture

**Status:** Established by CORE-002, on top of the CORE-001 Laravel baseline.
The Filament admin panel foundation was added by ADMIN-001 (see §3 below and
`app/Providers/Filament/AdminPanelProvider.php`).
**Scope:** Architecture and conventions only. No business modules or public
UI exist yet — see the completion status in each section below.

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
| Filament | Platform-wide Filament configuration is central: the single panel is `App\Providers\Filament\AdminPanelProvider` (`/admin`), scaffolded by ADMIN-001. It discovers components from `app/Filament/{Resources,Pages,Widgets}` (genuinely core-wide assets) and, recursively, from `app/Modules/**/Filament/{Resources,Pages,Widgets}` — so a module registers its own Resources/Pages/Widgets simply by placing them at that conventional path, without editing `AdminPanelProvider`. No Resources/Pages/Widgets exist yet for any module or for Core; ADMIN-001 is foundation only (panel, auth gate, navigation/discovery wiring) — see `docs/Softphoria_Platform_Implementation_Guide_v1.4_Laravel_Jacob_Approved.md` §10 for the ADMIN-002+ scope that builds on it. |
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

## 12. Filament Admin Panel Foundation (ADMIN-001)

**Status:** Panel shell established. The Users resource (ADMIN-003) is the
first admin-manageable content built on it, and its UI conventions — see
§13 — are the reference every later Resource should follow.

- **Panel:** `App\Providers\Filament\AdminPanelProvider`, id `admin`, path
  `/admin`, registered in `bootstrap/providers.php`. Login is Filament's
  built-in auth page against the existing `web` guard/`users` table — this
  is a separate login screen from any future public-facing auth flow
  (AUTH-*), not a second authentication system.
- **Authorization integration point:** `App\Models\User::canAccessPanel()`
  implements `Filament\Models\Contracts\FilamentUser`, gating on
  `status === 'active'` plus an existing `roles` relationship having the
  reserved slug `admin`. This is deliberately minimal — it reuses the
  DB-002/003 `roles`/`user_roles` tables as-is rather than introducing a new
  concept, and does not add any UI to manage roles. Full role/permission
  administration is ADMIN-004.
- **Component discovery (module registration point):** the panel calls
  `discoverResources()`/`discoverPages()`/`discoverWidgets()` against both
  `app/Filament/*` (core-wide, non-module Filament assets — see
  `app/Filament/README.md`) and, recursively, `app/Modules/*` (every
  module's own `Filament/{Resources,Pages,Widgets}`, per §3 module
  ownership). A future module registers with the admin panel purely by
  placing its Resource/Page/Widget class at the conventional path —
  `AdminPanelProvider` never needs to change.
- **Notifications:** `->databaseNotifications()` is enabled, backed by the
  existing `notifications` table (DB-002) and the `Notifiable` trait already
  on `User` — no new table, no new package.
- **Navigation shell** (docs/Reference UI/Admin/Admin navigation UI.docx):
  `->sidebarCollapsibleOnDesktop()` and `->globalSearch()` are native
  Filament panel config. The topbar's "View Site" link and "System Tools"
  dropdown (cache management, Optimize Application, Clear All Sessions) are
  custom, added via a `TOPBAR_START` render hook
  (`resources/views/filament/admin/topbar/start.blade.php`) rendering
  `App\Filament\Livewire\SystemToolsMenu` — a plain Livewire component that
  opts into `Filament\Actions\Concerns\InteractsWithActions` the same way
  `Filament\Pages\BasePage` does, purely so each tool gets a real
  confirmation modal instead of `window.confirm()`. Two non-obvious
  requirements for any future component built this way: the root Blade view
  must have exactly one root element (Livewire throws
  `MultipleRootElementsDetectedException` otherwise) and must include
  `<x-filament-actions::modals />` inside that root, or mounted actions
  execute with no visible modal. Available to any admin — no super-admin
  tier exists (see the authorization bullet above).
- **Form/table/search/pagination/confirmation conventions**: `TextInput`/
  `Select`/etc. with `->required()`/`->maxLength()` validation matching the
  migration's constraints; `Table` columns with `->searchable()` and
  `->sortable()` on user-facing lookup fields; standard Filament pagination
  (`10/25/50/all`, default `25`); every destructive/state-changing `Action`
  must call `->requiresConfirmation()`. Beyond these Filament defaults, the
  concrete list/form/action shape a Resource should follow is §13's — Users
  (ADMIN-003) is the first Resource built and is the reference
  implementation, not a one-off.

## 13. Resource UI conventions, established by Users (ADMIN-003)

Users (`app/Filament/Resources/Users/`) was the first Resource built on the
ADMIN-001 panel foundation. Per §6's philosophy ("the first module to need
one establishes the concrete pattern other modules follow"), every
subsequent Resource — ADMIN-*, JACOB-*, or any future module's own
`Filament/Resources` — should follow the same shape unless it has a
specific, documented reason not to.

- **List page**: a `StatsOverviewWidget` scoped to the resource
  (`{Resource}/Widgets/{Resource}StatsWidget`, wired via the List page's
  `getHeaderWidgets()`) showing the 2–4 counts an admin actually needs at a
  glance — not a general analytics dashboard (§11's Phase 1 exclusion still
  applies). The header `CreateAction` is relabeled to a specific verb
  (`"Add User"`, not the generic "New user") with a `Heroicon::OutlinedPlus`
  icon. Row actions are grouped into one `ActionGroup` (ellipsis-vertical
  icon) instead of a row of separate buttons, and the built-in view action
  is relabeled `"View Details"`.
- **Create/Edit forms**: build the schema as
  `$schema->columns(1)->components([Grid::make(['default' => 1, 'lg' => 12])->schema([...])])`.
  The explicit `->columns(1)` on the root schema is required —
  `EditRecord`/`CreateRecord::defaultForm()` silently applies its own
  `columns(2)` to the root schema otherwise, which halves the width
  available to a custom `Grid` (this shipped broken in ADMIN-003 before
  being caught — don't repeat it). Lay the `Grid` out as three independent
  `Group`s (`columnSpan` roughly `3 / 5 / 4` of 12): a narrow left column
  for record-scoped media (avatar/thumbnail upload) and, on Edit only, a
  card of record-scoped quick actions; a wide middle column for the primary
  editable fields; a right column for status/relationship fields (e.g. a
  role or category assignment). Override `getMaxContentWidth()` to
  `Width::Full` on the Create/Edit page classes so the wide grid isn't
  capped by Filament's default page width.
- **Header actions, not a bottom bar**: override `getFormActions()` to
  return `[]` and move Save/Cancel (and View, on Edit) into
  `getHeaderActions()` via `$this->getSaveFormAction()`,
  `$this->getCreateFormAction()`, `$this->getCancelFormAction()`, each given
  a specific label (`"Save changes"`, `"Add {Resource}"`) rather than
  Filament's generic defaults.
- **Record-scoped quick actions embedded in the form**: use
  `Filament\Schemas\Components\Actions::make([$action])->key('someKey')->fullWidth()`
  for buttons that act on the record itself (send an email, regenerate
  something, force a state change) instead of putting them in the page
  header. Give each its own `->key()` — that's the only way Filament's
  `callFormComponentAction()`/`assertFormComponentAction*` test helpers can
  address it; a plain `assertActionHidden()` fails to resolve an action
  whose whole containing component is hidden. Keep the button-building logic
  as static methods on the Resource class (e.g. `UserResource::blockAction()`)
  so List, Edit, and View pages reuse the exact same definition instead of
  duplicating it.
- **Self-protection + audit trail on every mutating action**: any action an
  admin could use to lock themselves out (change their own status, role,
  password, or sessions) must both hide itself for the acting admin's own
  record (`->visible(fn ($record) => ! $record->is(Auth::user()))`) and
  enforce the same guard at the domain layer — throw from the Action class
  itself (see `App\Exceptions\Users\CannotModifySelfException`), not just
  the Filament layer, so it can't be bypassed by calling the Action
  directly. Every state-changing action calls
  `->requiresConfirmation()->modalDescription(...)` and writes an entry via
  `App\Shared\Services\AuditLogService` (§4 already requires the write;
  this is which service to write through).
- **No hard deletes**: on any table with a `status` column, "deleting" a
  record from a Resource is a status transition to a deleted-equivalent
  value, never `DeleteAction`/`forceDelete()`.

No shared base Resource/Table/Form class exists yet. Once a second Resource
is built against this pattern, revisit extracting one for the parts that
turn out identical (the `columns(1)` + full-width `Grid` scaffold, in
particular) — don't guess at the shape before then.

## 14. Media selection convention, established by the ADMIN-006 review fix

Every Admin field that references a Media Library asset — a featured image,
an Open Graph/Twitter image, a section image, a gallery, a rich-text inline
attachment — **must** be built with `App\Filament\Support\Media\MediaPicker`
(for a form field) or `App\Filament\Support\Media\RichEditorMediaAttachments`
(for a `RichEditor`'s file attachments). This is a platform-wide rule, not
an ADMIN-006-specific one: **no Admin module may implement its own image/file
upload, storage, or selection mechanism.** There is exactly one place media
gets uploaded and stored — ADMIN-005's `App\Actions\Media\StoreUploadedMediaAction`
plus `config('media.categories')` — and every module-level picker calls
through to it rather than reimplementing validation, disk/directory
resolution, or variant generation.

- **`MediaPicker::make(string $name, string $label, MediaCategory $category = MediaCategory::Image, bool $multiple = false)`**
  gives a field two explicit actions: **Upload New Media** (a `FileUpload`
  scoped to the category's `config('media.categories')` disk/directory/
  size/mime rules, calling `StoreUploadedMediaAction` — creates exactly one
  `Media` row, dispatches `GenerateImageVariantsJob` when it qualifies, same
  as the Media Library's own `UploadMedia` page) and **Select from Media
  Library**, which opens the shared grid browser below — never a plain
  searchable `Select`/text field as the primary way to find an asset.
  Returns a `Fieldset` wrapping a `Hidden` state field, a thumbnail
  `Placeholder` preview, and a keyed `Actions` row (`{name}__actions`) —
  give every module's own extra actions their own `->key()` too, per §13,
  so tests can address them via `callFormComponentAction()`.
- **`MediaPicker::libraryBrowserSchema(MediaCategory $category, bool $multiple, string $fieldName = 'media', bool|Closure $required = true)`**
  is the actual Media Library browser — a visual grid, not a searchable
  field. It renders every matching `Media` row **immediately** on open (no
  search required first), with an optional live-filtered search box above
  it and a "Load more" action below it (`MediaPicker::PER_PAGE` per page,
  so an admin visiting a library with thousands of assets never loads them
  unbounded). Selection is a `Filament\Forms\Components\ViewField` that
  **is** the state holder — clicking a card in
  `resources/views/filament/forms/components/media-library-grid.blade.php`
  calls Livewire's native `$set('{{ $getStatePath() }}', …)` magic action
  directly against the field's own path, so no separate hidden field or
  nested Livewire component is needed just to capture a click. Category
  filtering happens in `MediaPicker::query()` (a `whereIn('mime_type', …)`
  against `MediaCategory::acceptedMimeTypes()`) — at the query level, never
  by hiding cards with CSS/JS — so a Document-only field's browser can never
  even fetch an image row, and vice versa. This schema fragment is embedded
  as-is everywhere the admin needs to pick existing media: both
  `MediaPicker::selectAction()`'s own modal and
  `RichEditorMediaAttachments`' attach modal share this exact array — there
  is one Media Library browser in the whole admin, not a per-consumer
  reimplementation of "grid of media cards."
- Action *names* are sanitized (`.` → `_`) before being passed to
  `Action::make()`, even though the field's own state path keeps the real
  dotted `$name` (e.g. `seo.og_image_media_id`, `content_json.media_id`) —
  Filament's action resolution parses `.` as an action-nesting separator, so
  a dotted action name breaks `getAction()`/`callFormComponentAction()`
  lookups. Any new action name built from a dotted field name must go
  through the same sanitization (see `MediaPicker::actionKey()`).
- **`RichEditorMediaAttachments::configure(RichEditor $editor, MediaCategory $category = MediaCategory::Image)`**
  must wrap every `RichEditor::make()` a module adds. A bare `RichEditor::make()`
  ships with its file attachments unconfigured (no disk/directory — falls
  back to Filament's own default, which never touches the Media Library at
  all — this was the original ADMIN-006 "Attach File is not working" bug).
  `configure()` sets `fileAttachmentsDisk`/`Visibility`/`AcceptedFileTypes`/
  `MaxSize` from `config('media.categories')` and overrides
  `saveUploadedFileAttachmentUsing()` to call `StoreUploadedMediaAction`
  (one `Media` row per upload, same variant pipeline), plus replaces the
  vendor `attachFiles` action (same action name, so `registerActions()`
  overrides it rather than adding a second button — see
  `Filament\Schemas\Components\Concerns\HasActions::cacheActions()`) with a
  version that embeds `MediaPicker::libraryBrowserSchema()` — the identical
  grid browser, not a separate rich-text-specific selection UX — alongside
  the upload field. Both fields are optional there (`required: false`) since
  either one satisfies the attachment; neither field in that modal is
  `->live()`, because a live round-trip re-renders the `RichEditor` behind
  the modal and can invalidate the Tiptap `editorSelection` captured when
  the toolbar button was clicked.
- No module may create a second Media table, a second upload Action/Service,
  or a second variant-generation pipeline. If a module's picker needs
  something `MediaPicker`/`RichEditorMediaAttachments` doesn't support yet,
  extend those classes — don't build a parallel one next to them.

## 15. SEO metadata convention, established by the ADMIN-006 review fix

Every Admin content type that has a `seo()` `MorphOne` to `SeoMetadata`
(Database Specification §18.6 — `seo_metadata` is a single shared
polymorphic table, never a per-module SEO table) must build its SEO fields
with `App\Filament\Support\Seo\SeoFields`, not ad hoc `TextInput`/`Textarea`
calls:

- **`SeoFields::metaTitle()`** / **`SeoFields::metaDescription()`** enforce
  the platform-wide limits — `SeoFields::META_TITLE_MAX` (60) and
  `SeoFields::META_DESCRIPTION_MAX` (160) — both in the UI (native
  `maxlength` plus a live "n/limit" `hint()`, updating via `->live()` as the
  admin types) and server-side (Filament's `->maxLength()` adds the matching
  `max:` validation rule to the same schema the UI uses, so there is no
  second validation path to keep in sync).
- **`SeoFields::canonicalUrlFields(string $name, string $isAutoName, Closure $autoPathUsing)`**
  gives a content type an automatically-generated, overridable canonical
  URL: default value is `config('app.url')` (never a hardcoded domain) plus
  the content's own path, via `SeoFields::autoCanonicalUrl()`. A sibling
  `Hidden` field (`dehydrated(false)` — UI-only, never written to
  `seo_metadata`) tracks whether the value is still automatic; typing
  directly into the canonical URL field flips it to manual and a keyed
  `Actions` row (`{name}_reset_actions`, same `.`→`_` action-name
  sanitization as §14) offers "Use automatic URL" to flip back. The owning
  module is responsible for calling `SeoFields::syncCanonicalUrlIfAuto()`
  from its own slug/title field's `afterStateUpdated()` (see `PageForm`) —
  once manually overridden, a slug/title change must never silently
  overwrite the admin's custom value — and for computing
  `SeoFields::isCanonicalUrlAuto()` in its Edit page's
  `mutateFormDataBeforeFill()` so a previously-saved custom canonical URL is
  still shown as manual after a reload.
- Do not add a second SEO table or a per-module meta-title/description
  column — every content type with SEO needs uses the one `seo_metadata`
  table and these shared field builders.

## 16. Website Setup (Core Admin Settings), approved 2026-08-13

**Status:** Authoritative requirement, documented ahead of implementation per
the client-approved instruction of 2026-08-13. This is a **Core Platform**
convention (§1) — Website Setup, centralized Email Settings, the Email
Template registry, and the notification-sending mechanism it establishes are
generic across any future Softphoria client, not Jacob-specific, and every
later module that needs to send an email or read a site-wide setting reuses
what this section defines rather than building its own.

### 16.1 One sidebar item, built as a Filament Cluster

"Website Setup" is **one** Core Admin sidebar entry
(`app/Filament/Clusters/WebsiteSetup.php`, a `Filament\Clusters\Cluster`) —
never three separate top-level nav items for General/Email/Templates. It
contains:

- **`Settings` page** (`app/Filament/Clusters/WebsiteSetup/Pages/Settings.php`)
  — a single Filament Page whose schema is one
  `Filament\Schemas\Components\Tabs` with two tabs, **General** and
  **Email**, per the approved "existing tab convention" (Filament's native
  `Tabs` component is the established mechanism for this — the first use of
  `Tabs` in this codebase; every later multi-section settings-style screen
  should follow the same shape rather than inventing another layout).
- **`EmailTemplateResource`** (`app/Filament/Clusters/WebsiteSetup/Resources/EmailTemplates/`)
  — the fixed template registry (§16.4), nested in the same cluster so it
  still reads as part of the one "Website Setup" sidebar entry, not a
  separate resource floating elsewhere in the nav.

This is the first Cluster in the app — `docs/ARCHITECTURE.md` §13's Resource
conventions (Grid/Group layout, header actions not a bottom bar,
self-protection + audit trail on mutating actions) still apply everywhere
they're relevant inside it.

### 16.2 Settings storage — reuses the existing `settings` table, no new migration

General and Email Settings are **key/value rows in the existing `settings`
table** (`group`, `key`, `value`, `type` — DB-002/003, already migrated,
already exactly the shape this needs). No new table or column is introduced
for either tab. Concretely:

- `group = 'general'`: `site_name`, `tagline`, `site_url`, `logo_media_id`,
  `favicon_media_id`, `maintenance_mode` (`type = 'boolean'`),
  `maintenance_page_id`.
- `group = 'email'`: `enabled` (`type = 'boolean'`), `provider` (currently
  only `smtp` is admin-configurable — see §16.3), `smtp_host`, `smtp_port`,
  `smtp_encryption`, `smtp_username`, `smtp_password` (`type = 'encrypted'`
  — see below), `from_name`, `from_email`, `reply_to_name`,
  `reply_to_email`, `test_recipient_email`.
- **`App\Shared\Services\Settings\SettingsRepository`** (new, platform-wide,
  `app/Shared/Services/Settings/`) is the single read/write entry point —
  `get(string $group, string $key, mixed $default = null)` /
  `set(string $group, string $key, mixed $value)` / `all(string $group)` —
  so no module ever queries the `settings` table directly. It is
  `type`-aware: `type = 'boolean'` round-trips through PHP bool casting,
  `type = 'encrypted'` round-trips through Laravel's `Crypt` facade
  (`Crypt::encryptString()` on write, `Crypt::decryptString()` on read) so
  `smtp_password` is never stored or returned in plaintext — this satisfies
  the "encrypted at rest, never returned in plaintext" requirement without a
  dedicated encrypted column, reusing the `type` discriminator the `Setting`
  model already has.
- Logo and Favicon fields are built with **`MediaPicker::make('logo_media_id', 'Logo', MediaCategory::Image)`** /
  the equivalent for favicon — per §14, no other upload mechanism is
  permitted here. `SettingsRepository` stores/reads the selected `media.id`
  the same way `Page::featured_image_id` already does.

### 16.3 Maintenance Mode

- **Fields:** `maintenance_mode` (bool) and `maintenance_page_id` — a
  `Select`/`MediaPicker`-style existing-record picker scoped to
  `Page::query()->where('status', PageStatus::Published)` (see §16.5 for why
  it must be published). **No separate maintenance-page content system is
  created** — the selected record is an ordinary `pages` row, edited through
  the existing Pages Resource like any other page.
- **Enforcement:** `App\Http\Middleware\CheckMaintenanceMode` (new), applied
  globally to the `web` middleware group in `bootstrap/app.php`. It exits
  immediately (`return $next($request)`) when:
  - the request path is `admin` or `admin/*` (the entire Filament panel —
    login included, so an admin can always turn maintenance mode back off),
  - the request path matches Livewire's own AJAX/asset endpoint prefix —
    **not** a hardcoded `livewire/*`. Livewire 4 derives a per-installation
    prefix from `APP_KEY` (`Livewire\Mechanisms\HandleRequests\EndpointResolver::prefix()`,
    e.g. `/livewire-f1db4272`) specifically so the endpoint can't be
    guessed — a hardcoded path silently misses it. (Caught in browser
    verification: enabling Maintenance Mode intercepted the admin's own
    Save request and rendered the maintenance page into the middle of the
    Settings form. `EndpointResolver::prefix()` is called at request time
    so the exclusion is correct regardless of what it resolves to.)
  - or `maintenance_mode` is off. This check is wrapped in a
    `try`/`catch (QueryException)` that fails open (treats the request as
    "maintenance mode off") — every request passes through this middleware,
    including on a freshly deployed, not-yet-migrated environment where the
    `settings` table doesn't exist yet, and that must never turn into a hard
    500 on every page.

  Otherwise it renders the selected page directly through the **existing**
  `App\Shared\Support\Pages\PageContentRenderer` (the same renderer
  `PreviewPageController` already uses — ADMIN-006 review fix) and returns
  it with HTTP **503** (standard practice for site-wide maintenance, and
  consistent with the Implementation Guide §12's "never publicly index...
  draft content" — a 503 tells crawlers this is temporary and not the
  page's real status).
- **No recursion is possible by construction:** the middleware renders the
  page's content directly: it does not re-dispatch the request through
  routing/middleware a second time, so the selected maintenance page can
  never trigger its own maintenance check again regardless of its own
  content.
- **Known scope boundary:** Stage D (public frontend) does not exist yet —
  today the only non-admin route is `/` (the framework's default `welcome`
  view). This middleware is written to guard the `web` group generically
  (by path exclusion, not by an allowlist of specific public routes) so it
  needs no changes once Stage D adds real public pages/routes — but until
  then, enabling maintenance mode only visibly affects `/`. This is not a
  gap to "fix" now — building out Stage D routes early would violate the
  Implementation Guide §2 Critical Rule.

### 16.4 Email Settings — provider abstraction

- **No new mail provider abstraction is built.** "Provider type" is
  Laravel's own existing `config('mail.mailers.*')` transport abstraction —
  Phase 1 exposes exactly one admin-configurable provider, `smtp`, matching
  the fields the client approved (host/port/encryption/username/password).
  A future provider (Postmark, SES, etc.) is added as another
  `config('mail.mailers.*')` entry Laravel already supports, never a
  module- or provider-specific bespoke implementation.
- **Runtime application:** `App\Shared\Services\Settings\MailSettingsApplier`
  (new) reads `SettingsRepository`'s `email` group and calls
  `config(['mail.mailers.smtp' => [...], 'mail.default' => ..., 'mail.from' => [...]])`
  — host/port/encryption-scheme/username/password/from only, since those
  are Laravel's real global mail config keys. Reply-To has no equivalent
  global config key in Laravel; it is applied per-message
  (`->replyTo($email, $name)`) by whichever code is about to send
  (`TemplatedMailer`, the Send Test Email action), reading it directly from
  `SettingsRepository`. `MailSettingsApplier::apply()` is called
  **immediately before mail is sent** — from inside `TemplatedMailer::send()`
  and the Send Test Email action — rather than unconditionally on every
  request, so a page load that sends no mail costs no extra `settings`
  lookup. No queued-job/worker-restart synchronization problem exists
  because a queue worker re-boots the framework (and re-reads `settings`)
  on every job per Laravel's normal queue lifecycle.
- **"Enable/Disable Email Sending":** when disabled, `MailSettingsApplier`
  forces `mail.default` to Laravel's built-in `log` driver regardless of the
  stored provider config — mail is composed and logged, never actually
  delivered, without every call site needing its own enabled/disabled
  branch.
- **Send Test Email** is a Filament `Action` on the Email tab. It reads the
  values directly from the **live form state** (`Get $get`, not the
  database) to build a one-off mailer config and send through it — so
  testing works against an edited-but-unsaved configuration as well as a
  previously-saved one, per the approved requirement. If a field is blank in
  the live form, it falls back to the saved `SettingsRepository` value for
  that key (e.g. testing after only changing the port shouldn't require
  retyping the host).

### 16.5 Email Templates — fixed, seeded registry

- **New table `email_templates`** (new migration — the only genuinely new
  migration in this feature; a structured, independently-editable,
  seed-then-lock registry does not fit the generic `settings` key/value
  shape): `notification_key` (string), `recipient_type` (`user`|`admin`),
  `is_enabled` (bool, default true), `subject` (string), `html_body`
  (longtext), `text_body` (longtext, nullable), `available_variables`
  (json — informational, not admin-editable, documents which `{{token}}`
  placeholders this key supports), audit columns (`created_by`/`updated_by`
  per §3's audit-column convention), unique on
  (`notification_key`, `recipient_type`).
- **Fixed registry, not admin-CRUD:** `EmailTemplateResource` has
  `canCreate() => false` and no delete action — administrators edit
  `subject`/`html_body`/`text_body`/`is_enabled` on existing rows only. New
  rows are added exclusively by extending the seeder (§16.6) when a new
  module's notification requirements are approved, never through the admin
  UI. This directly satisfies "Administrators may edit templates but may not
  arbitrarily create or delete system templates."
- **Edit UI:** opening a template (grouped/listed by `notification_key`)
  shows a `Tabs` component with **User** and **Admin** tabs (same native
  Filament `Tabs` convention as §16.1), each editing that key's `user`/
  `admin` row where one exists for that key — a key with only one recipient
  variant (e.g. `email_verification` is user-only) simply has no tab for the
  variant that doesn't apply, rather than showing an empty/disabled one.
- **Variable substitution is plain token replacement, never Blade/PHP
  execution.** `App\Shared\Services\Notifications\TemplatedMailer` (new,
  the single centralized sending entry point every module must call) resolves
  `{{token}}` occurrences in `subject`/`html_body`/`text_body` via a literal
  `str_replace()` against the caller-supplied variable array — admin-edited
  template content is never passed to `Blade::render()` or `eval()`-adjacent
  mechanisms, which would let an admin-editable field execute arbitrary
  server-side code. This is a deliberate security boundary, not an
  oversight.
- **`TemplatedMailer::send(string $notificationKey, EmailRecipientType $recipientType, string $to, array $variables = []): void`**
  looks up the enabled row for `(notificationKey, recipientType)`, skips
  silently if disabled or missing (a template being off must never throw),
  substitutes variables, and sends via `App\Shared\Mail\TemplatedNotificationMail`
  — a small real `Mailable`, not a raw `Mail::send($view, ...)` array call.
  This matters beyond style: Laravel's `Mail::fake()` only records sends
  that are an actual `Mailable` instance (`MailFake::sendMail()` silently
  no-ops otherwise), so routing through one real Mailable class is what
  keeps every Email Template send — and the `ResetPassword::toMailUsing()`
  integration below, which must return a Mailable anyway — observable in
  tests via `Mail::assertSent(TemplatedNotificationMail::class, ...)`.
  `TemplatedMailer::renderAsMailable()` builds the same `TemplatedNotificationMail`
  for integration points that must return a Mailable rather than have
  `send()` deliver it directly. This is the **one** notification-sending
  path every current and future module must call — see §16.7.
- **`ResetPassword::toMailUsing()` integration** (`AppServiceProvider::boot()`)
  — the first real (non-inert) consumer, replacing Laravel's own built-in
  markdown content for the `password_reset`/`user` template, already
  triggered today by `SendUserPasswordResetLinkAction`/`GenerateNewPasswordAction`
  via `Password::broker()`. Falls back to Laravel's default `MailMessage`
  content if the template is disabled or missing — unlike a "welcome"
  email, password-reset delivery is functionally required for account
  recovery, so "disabled" must not silently break the reset flow. The
  `{{reset_url}}` variable degrades to `config('app.url')` when
  `Route::has('password.reset')` is false, since that route doesn't exist
  until AUTH-001 ships — guarded rather than fabricated ahead of that
  stage.

### 16.6 Initial seeded registry

`database/seeders/EmailTemplateSeeder.php` (new, called from
`DatabaseSeeder`) seeds exactly these keys — chosen because they map to
functionality that exists or is explicitly approved for Phase 1, per the
Implementation Guide's AUTH-001/ADMIN-003 scope; nothing here is invented
ahead of its owning feature:

| `notification_key` | Recipients | Approved event |
|---|---|---|
| `email_verification` | user | Verify Email |
| `user_registered` | user + admin | New Registration / Welcome |
| `password_reset` | user | Password Reset / Generate New Password |
| `profile_updated` | user | Profile Update |
| `newsletter_subscribed` | user | Newsletter Confirmation/Registration |
| `contact_form_submitted` | user + admin | Contact Form Confirmation / Contact Form Admin Notification |

`password_reset` is the one key with a real sender today: it replaces the
default `Illuminate\Auth\Notifications\ResetPassword` mail content already
triggered by `SendUserPasswordResetLinkAction`/`GenerateNewPasswordAction`
(via `Password::broker()`), by customizing that notification's
`toMailUsing()` to call `TemplatedMailer` instead of Laravel's own built-in
markdown template — the first real (non-inert) consumer of this system.
`email_verification`, `user_registered`, and `newsletter_subscribed` have no
trigger point yet (AUTH-001/registration and the public newsletter
subscribe flow are not built) — their rows exist and are editable now, per
the approved requirement to seed them ahead of time, but sending them is
inert until the owning feature ships; **do not fabricate a fake trigger** to
exercise them early. `contact_form_submitted` is seeded but likewise has no
public Contact form yet (ADMIN-010/JACOB-009) to call it.

### 16.7 Platform-wide rule

Every future module that needs to email a user or an admin, or read a
site-wide setting, reuses:

- `SettingsRepository` for any `settings`-table value (never a
  module-specific settings table or hardcoded config),
- `MailSettingsApplier`'s effect (automatic — no module calls this
  directly, it applies once per request),
- `TemplatedMailer::send()` for anything sent by email (never
  `Mail::raw()`/a bespoke `Mailable` with inline hardcoded copy, never a
  module-specific template table),
- `MediaPicker`/`RichEditorMediaAttachments` (§14) for any upload/selection
  the settings or template screens need,
- `AuditLogService` for settings/template changes, matching §13's existing
  "every mutating action" convention.

A module that needs a new *kind* of notification adds a seeded
`notification_key` (and, if genuinely module-specific, an `available_variables`
entry) to `EmailTemplateSeeder` when that module ships and its notification
copy is approved — it never creates a parallel table, a parallel sending
path, or a module-specific SMTP config.
