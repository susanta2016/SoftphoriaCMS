# Softphoria Platform — Claude Code Implementation Guide
## Version 1.4 — Laravel Phase 1 / Jacob d'IAWARII Approved Blueprint

**Status:** Current implementation source for Claude Code  
**Client:** Jacob d'IAWARII / I Am We Are It Is  
**Architecture:** Modular Laravel monolith + Blade + Livewire + Filament  
**Database:** MariaDB  
**Cache / Queue:** Redis  
**Web Server:** Nginx  
**CDN / WAF:** Cloudflare  
**Coding Agent:** Claude Code  

**Addendum (approved 2026-08-13):** ADMIN-007's "Site identity / Global
settings" scope is formalized as **Website Setup** — see the expanded
ADMIN-007 entry in §10 below, and `docs/ARCHITECTURE.md` §16 for the
implementation-level detail (Filament Cluster structure, settings storage,
maintenance mode, Email Settings, and the Email Template registry). This is
a Core Platform requirement, not Jacob-specific.

---

# 1. Authoritative Source Hierarchy

Claude Code MUST treat these sources in this order:

1. **Cory Gold Master Development Specification v1.0 — 10 August 2026**
2. Softphoria Platform Core Specification v1.0
3. Softphoria Database Design Specification
4. Latest approved Jacob navigation and UI/UX designs
5. This implementation guide

The attached **Cory Gold Master Development Specification v1.0** is the current authoritative
client/project blueprint for Phase 1. It explicitly changes the Phase 1 architecture to
Laravel + Blade/Livewire + Filament and excludes Next.js and Express.js. fileciteturn10file0

If two documents conflict, DO NOT silently choose an implementation. Report the conflict,
identify the affected requirement, and wait for clarification unless the newer/authoritative
source clearly resolves it.

---

# 2. Core Development Strategy

The implementation MUST follow this sequence:

```text
STAGE A
Softphoria Platform Core
        ↓
STAGE B
Softphoria Core Admin Panel
        ↓
STAGE C
Jacob Client-Specific Modules + Admin
        ↓
STAGE D
Approved Public Frontend
        ↓
STAGE E
QA / Performance / Security / Deployment
```

## Critical Rule

Do NOT build the public website screen-by-screen while the Core/Admin platform is still
being developed.

The user's required workflow is:

1. Build reusable Softphoria Core.
2. Build and stabilize the complete Softphoria Core Admin Panel.
3. Build Jacob-specific modules and their admin management.
4. Implement the approved homepage.
5. Implement remaining public pages only when their UI/UX designs are approved.
6. Complete QA, performance, security and production deployment.

This order is intentional and MUST NOT be changed without approval.

---

# 3. Phase 1 Architecture — Mandatory

## 3.1 Stack

| Layer | Technology |
|---|---|
| OS | Ubuntu Linux |
| Web | Nginx |
| Application | Laravel / PHP 8.4 |
| Public frontend | Blade + Livewire |
| Admin/CMS | Filament |
| Database | MariaDB |
| Cache / Queues | Redis |
| Search | Laravel Scout + configurable engine |
| CDN/WAF | Cloudflare |
| Repository | GitHub |

## 3.2 Explicitly Forbidden in Phase 1

Claude MUST NOT introduce:

- Next.js
- Express.js
- Node.js as the application backend
- Prisma
- React as the public application framework
- A separate API server merely for theoretical future use
- Paid membership/subscription checkout
- Stripe/PayPal payment processing
- Drag-and-drop page builder
- Native live-streaming infrastructure
- Ecommerce
- Music store
- Merchandise
- Forum
- Messaging
- Groups
- AI recommendations
- Event registration
- Digital publications
- Mobile application
- Online courses
- Custom analytics dashboard

The master blueprint explicitly defines Laravel as the Phase 1 architecture and says
Next.js and Express.js are not part of Phase 1. fileciteturn10file0

---

# 4. Architectural Rules

## 4.1 Modular Laravel Monolith

Use:

```text
app/
  Modules/
    Homepage/
    About/
    Music/
    Podcast/
    PoetryProse/
    InspirationalResources/
    Community/
    Users/
    Newsletter/
    Search/
    Media/
    SEO/
    Analytics/
    Settings/
    Security/

  Shared/
    Services/
    DTOs/
    Support/
    Exceptions/
    Rules/
```

Each module MUST have a clear responsibility and domain boundary.

## 4.2 Business Logic

Business logic MUST NOT live in Blade templates.

Use appropriate Laravel application/domain services, actions, policies, rules and jobs.

Controllers and Livewire components MUST remain thin.

## 4.3 Database Access

Use:

- Eloquent models
- Laravel migrations
- Factories
- Seeders
- Query scopes
- Services/actions where business workflows are involved

Do not introduce Prisma or another ORM.

## 4.4 Future API Readiness

Business workflows must be reusable outside Blade.

Do not build a separate Express API in Phase 1.

If a future API is required, the underlying services/actions should already be reusable.

---

# 5. Approved Public Navigation

The current Phase 1 primary navigation is:

```text
About
Music
Podcast
Poetry/Prose
Inspirational Resources
Contact
```

Header actions:

```text
Search
Log In
Enter Here / Register
```

The public label **Poetry/Prose** is an information-architecture label, not necessarily a
database/table name.

Internally it may contain:

- Poetry
- Prose
- Essays
- Reflections
- Hymns
- Articles
- Collections

Do not create separate unrelated modules merely because public labels differ.

---

# 6. Approved Homepage

The approved homepage reference is:

`Home_page_layout_V4.4.0.png`

It is the visual source of truth for the approved homepage hierarchy, layout, visual
direction, navigation, hero, CTA placement, community surface and footer.

Claude MUST NOT redesign it.

## 6.1 Header

- ALL THE THINGS LIGHT
- I AM. WE ARE. IT IS.
- About
- Music
- Podcast
- Poetry/Prose
- Inspirational Resources
- Contact
- Search
- Log In
- Enter Here →

## 6.2 Hero

Headline:

> Light is our nature.  
> Love is our purpose.

Supporting message:

> Music. Writing. Reflection. Thinking. Community.

> A space to explore ideas, discover music, and connect with what truly matters.

CTAs:

- Explore Music
- Read Writing
- Watch Introduction
- Join Our Community
- Join Now

The **Read Writing** CTA should route to the approved Poetry/Prose editorial destination
unless the client later defines a separate Writing destination.

## 6.3 Community Surface

Approved homepage contains:

- Latest Community Comments
- Join the conversation
- Curated/comment-style cards

IMPORTANT:

The homepage visual does NOT authorize a public forum.

Phase 1 excludes:

- Forum
- Public discussion threads
- Member messaging
- Groups

Until the client confirms the source of the displayed comments, keep this behind a
configurable/curated content service.

## 6.4 Footer

Explore:

- About
- Music
- Podcast
- Poetry/Prose
- Inspirational Resources
- Contact

Community:

- Latest Comments
- Join Our Community

Support:

- Privacy Policy
- Terms of Use
- Cookie Policy
- Contact Us

Newsletter:

- Email subscription

Plus approved social links and copyright.

---

# 7. Stage A — Softphoria Platform Core

The Core must be reusable for future clients.

## CORE-001 — Laravel Project Initialization

Create the Laravel application using PHP 8.4.

Requirements:

- Git repository
- Laravel application
- Environment configuration
- Development/staging/production configuration separation
- `.env.example`
- Composer dependencies
- Application key configuration
- Logging
- Error handling
- Base documentation

Do not create Jacob-specific business modules in this task.

## CORE-002 — Modular Application Structure

Create the documented `app/Modules` and `app/Shared` structure.

Requirements:

- Module registration strategy
- Shared services
- Shared exceptions
- Shared DTO conventions
- Shared rules
- Module ownership conventions

## CORE-003 — Code Quality

Configure:

- PHP formatting
- Static analysis appropriate to the project
- Laravel coding conventions
- Automated checks
- Consistent naming

Do not add unnecessary packages.

## CORE-004 — Configuration

Centralize:

- App configuration
- Database
- Redis
- Mail
- Storage
- Search
- Cloudflare-related settings where applicable
- Analytics identifiers
- Third-party integration settings

Never commit secrets.

## CORE-005 — Logging and Error Handling

Implement:

- Structured application logging where practical
- Production-safe error handling
- Useful local error visibility
- Operational logs
- Correlation/request identifiers where appropriate

Do not expose stack traces or secrets publicly.

---

# 8. Stage A — Database Foundation

## DB-001 — MariaDB Configuration

Configure Laravel for MariaDB.

## DB-002 — Migration Standards

Define:

- Primary key strategy
- Foreign keys
- Indexes
- Timestamps
- Soft deletes where restoration is required
- Naming conventions
- Safe migration practices

## DB-003 — Core Models

Create reusable core models for:

- Users
- Profiles
- Roles/permissions
- Verification/session data
- Settings
- Media
- Navigation
- SEO
- Newsletter
- Audit/operational records
- Homepage configuration
- Search-related records where required

Do not create future ecommerce/payment tables unless required by Phase 1.

## DB-004 — Factories and Seeders

Seed only safe development/reference data.

Never hardcode real production passwords or secrets.

---

# 9. Stage A — Authentication and Registered Users

Phase 1 has **registered users**, not paid Premium Membership.

## AUTH-001

Implement:

- Registration
- Login
- Logout
- Email verification
- Password reset
- Profile management
- Session security
- Rate limiting

## AUTH-002 — Access Model

Use:

```text
Guest
Registered User
```

Do NOT implement:

- Paid Premium
- Subscription
- Membership checkout
- Payment gateway

## AUTH-003 — Protected Content

Registered users may access content designated as protected.

Access rules must be enforced server-side.

---

# 10. Stage A — Core CMS / Filament Admin

After foundation services are stable, build the reusable Softphoria Core Admin Panel.

## ADMIN-001 — Filament Foundation

Implement:

- Filament
- Authentication
- Admin layout
- Navigation
- Role-based permissions
- Common form patterns
- Common table patterns
- Search/filter/pagination
- Notifications
- Confirmation dialogs

## ADMIN-002 — Dashboard

Provide a clean operational dashboard.

Do not build a custom analytics dashboard beyond the Phase 1 requirement.

## ADMIN-003 — Users

Manage:

- Registered users
- Verification status
- Profile
- Account/security status
- Protected-content access

## ADMIN-004 — Roles and Permissions

Implement appropriate role-based admin permissions.

## ADMIN-005 — Media Library

Implement:

- Image/media management
- File validation
- File size validation
- Safe storage
- Metadata
- Media reuse
- Image optimization workflow
- Responsive image variants where appropriate

## ADMIN-006 — Pages / CMS Content

Implement reusable page/content management without a drag-and-drop builder.

## ADMIN-007 — Navigation / Settings

Manage:

- Navigation
- Site identity
- Global settings
- Social links
- Integration settings

### ADMIN-007 Addendum — Website Setup (approved 2026-08-13)

"Site identity / Global settings / Integration settings" above is formalized
as **Website Setup**: one Core Admin sidebar item (a Filament Cluster — see
`docs/ARCHITECTURE.md` §16.1), never split into multiple top-level nav
entries. It contains a tabbed Settings page and the Email Template registry
below. This is a **Core Platform** rule, reusable by any future Softphoria
client — not Jacob-specific functionality.

**Priority 1 — General Settings.** Site Name, Tagline, Site URL, Logo and
Favicon (both via the existing MediaPicker/Media Library — ADMIN-005/§14,
never a separate upload field), a Maintenance Mode toggle, and a Maintenance
Page selector scoped to existing published CMS Pages (ADMIN-006). When
Maintenance Mode is on, public requests must render the selected page
instead of the normal site; the admin area must always remain reachable;
the maintenance page must never be able to recursively re-enter maintenance
mode. No separate maintenance-page content system may be created — reuse
Pages/CMS as-is.

**Priority 1 — Email Settings.** Enable/disable email sending, provider
type, SMTP host/port/encryption/username/password, sender name/email,
reply-to name/email, a test recipient address, and a Send Test Email action.
SMTP passwords must be encrypted at rest and never returned in plaintext.
Test-sending must work against the currently saved configuration and,
where practical, the currently edited-but-unsaved configuration. Reuse
Laravel's own mailer/provider abstraction (`config('mail.mailers.*')`) —
do not build a bespoke provider abstraction or a module-specific SMTP
implementation.

**Priority 2 — Email Templates.** A fixed, seeded registry — administrators
may edit existing templates (subject, HTML body, plain-text fallback,
enabled state) but may not create or delete system templates. Each template
key may have a **User** and/or **Admin** recipient variant, edited via two
tabs when both exist (e.g. "New Registration" → User: Welcome email, Admin:
new-registration alert). Initial templates cover only currently-approved
Phase 1 events (Verify Email, New Registration/Welcome, Password
Reset/Generate New Password, Profile Update, Newsletter
Confirmation/Registration, Contact Form Confirmation, Contact Form Admin
Notification) — module-specific notifications (comments, membership,
payments, Music, Community, etc.) are added only once their owning module
exists and its notification copy is approved; do not build fake
functionality for a module that doesn't exist yet.

**Platform-wide reuse rule:** every future module that sends email or reads
a site-wide setting must reuse the centralized Settings storage, the
centralized mail-provider configuration, the centralized Email Template
registry, the centralized User/Admin recipient model, the centralized
variable-substitution mechanism, and the existing queue/Media
Library/audit-logging infrastructure — never a module-specific settings
table, SMTP config, template table, or notification system. See
`docs/ARCHITECTURE.md` §16 for the concrete class/table design.

## ADMIN-008 — SEO

Manage:

- Meta title
- Meta description
- Canonical
- Open Graph
- Twitter/X card data
- Social image
- Robots directives

## ADMIN-009 — Newsletter

Manage:

- Subscribers
- Consent state
- Subscription state
- Unsubscribe state
- Export where appropriate

## ADMIN-010 — Forms

Provide reusable form/submission infrastructure for Contact and protected submissions.

## ADMIN-011 — Audit / Operational Logs

Phase 1 requires sufficient operational logging for troubleshooting.

Do not build an advanced security-monitoring product.

---

# 11. Stage A — Core Platform Services

## CORE-SVC-001 — Redis

Use Redis for:

- Cache
- Queue support
- Rate limiting
- Transient workloads

## CORE-SVC-002 — Queue

Use Laravel queues for non-critical/background work.

## CORE-SVC-003 — Notifications / Email

Implement reusable notification/email services.

## CORE-SVC-004 — Search

Use Laravel Scout abstraction.

Initial search may use database full-text search where suitable.

Meilisearch may be added only if justified.

## CORE-SVC-005 — Analytics

Support:

- GA4
- Google Search Console verification/configuration
- Defined conversion/click/download events

Do not build a custom analytics dashboard.

---

# 12. Stage A — SEO

SEO is a Phase 1 acceptance requirement.

Implement:

- Dynamic title
- Dynamic description
- Canonical
- Open Graph
- Twitter/X cards
- Robots directives
- Automatic slugs
- Manual slug override
- Fallback metadata
- JSON-LD

Structured data where applicable:

- Organization
- WebSite
- Article
- MusicAlbum
- MusicRecording
- PodcastEpisode
- BreadcrumbList

Generate:

- XML sitemap
- robots.txt

Never publicly index:

- Admin
- Auth/private pages
- Draft content
- Private content
- Duplicate filter URLs where inappropriate

---

# 13. Stage A — Performance Foundation

Performance is engineered from the beginning.

Targets:

| Metric | Target |
|---|---|
| Lighthouse Performance | 85+ representative production pages |
| Accessibility | 90+ |
| SEO | 95+ |
| Best Practices | 90+ where practical |
| Core Web Vitals | Optimize LCP, CLS and INP |

Implement:

- PHP OPcache
- Laravel production caching
- Redis
- Eager loading
- Query optimization
- Database indexes
- Queue non-critical work
- Image optimization
- WebP/AVIF where appropriate
- Responsive images
- `srcset`
- Reserved dimensions
- Lazy loading below the fold
- LCP image prioritization
- CDN suitability
- Minimal JavaScript
- Minimal Livewire requests
- Minimize third-party scripts

---

# 14. Stage A — Accessibility Foundation

Target WCAG 2.2 AA-oriented implementation.

Requirements:

- Semantic HTML
- Keyboard navigation
- Visible focus
- Color contrast
- Alt text
- Accessible forms
- Accessible errors
- Accessible dialogs
- Screen-reader compatible structure
- No mouse-only interaction
- Responsive behavior

---

# 15. Stage B — Softphoria Core Admin Completion Gate

Do NOT start Jacob client modules until the Core Admin Panel passes this gate.

### Required:

- Laravel application works
- MariaDB works
- Redis works
- Authentication works
- Registered users work
- Filament works
- Roles/permissions work
- Media works
- CMS works
- Navigation/settings work
- SEO management works
- Newsletter works
- Forms work
- Search foundation works
- Analytics configuration works
- Queues work
- Security baseline passes
- Core tests pass
- No known critical/high defects
- No unrelated TODO placeholders
- No hardcoded secrets

The result must be a reusable **Softphoria Platform Core Admin Panel**.

---

# 16. Stage C — Jacob Client Modules

Only after Stage A and Stage B are stable.

## JACOB-001 — Homepage Content Administration

Administer:

- Hero/configuration
- Featured selections
- Community invitation
- Newsletter CTA
- Approved homepage content surfaces

Do not hardcode homepage content.

## JACOB-002 — About

Manage:

- Biography
- Vision
- Philosophy
- Images
- Timeline where required

## JACOB-003 — Music

Phase 1 content types:

- Albums
- Singles

Album/single automatically generates its public listening/detail page.

Manage:

- Title
- Slug
- Release date
- Description
- Cover artwork
- Track list
- Lyrics where applicable
- Song stories
- Embedded music videos
- Streaming links

Baseline:

- External embeds/streaming
- No native music store
- No native audio/video hosting requirement

## JACOB-004 — Podcast

Manage:

- Podcast/episode
- Title
- Slug
- Description
- Artwork
- Publish date
- Season/episode where applicable
- Embed URL
- Streaming links
- SEO metadata
- Publication status

Architecture must allow future providers/native media without rewriting the domain.

## JACOB-005 — Poetry / Prose

Support:

- Essays
- Reflections
- Hymns
- Poetry
- Articles
- Collections by theme

Common fields:

- Title
- Slug
- Body
- Category
- Tags
- Featured image
- Publish date
- SEO metadata

Collections reference published items.

## JACOB-006 — Inspirational Resources

Support the approved resource/submission model:

- Ideas
- Observations
- Journals/questions
- Reflections
- Longer-form explorations

Submission categories may include:

- Idea
- Reflection
- Question
- Suggestion
- Observation

Fields:

- Name
- Email
- Subject
- Category
- Message
- Optional related album/song

Admin:

- View
- Search
- Review
- Archive
- Export

## JACOB-007 — Community

Implement only the defined curated model:

- Community invitation
- Updates
- Latest comments/reflections where approved
- Featured content

DO NOT implement:

- Forum
- Groups
- Member messaging
- Public discussion system

## JACOB-008 — Newsletter

Implement the client-facing newsletter subscription workflow using the reusable Core
newsletter service.

## JACOB-009 — Contact

Use the reusable Core form/submission system.

---

# 17. Stage C — Search Integration

Index supported published content:

- Songs
- Albums
- Podcast episodes
- Essays
- Hymns
- Reflections
- Poetry
- Articles
- Inspirational Resources
- Indexed community updates/comments where applicable

Each result should provide:

- Title
- Content type
- Excerpt
- Featured image where applicable
- URL

Never publicly index drafts/private content.

---

# 18. Stage C — Client Admin Completion Gate

Do not start public frontend implementation until:

- Music admin works
- Podcast admin works
- Poetry/Prose admin works
- Inspirational Resources works
- Community works
- About works
- Homepage configuration works
- Newsletter works
- Contact works
- Search indexing works
- SEO metadata works
- Permissions work
- Media works
- Tests pass

---

# 19. Stage D — Public Frontend

Only now implement public UI.

Use:

- Blade
- Livewire selectively
- Reusable Blade components
- Semantic HTML
- Minimal JavaScript
- Responsive layouts
- Accessible interactions

## WEB-001 — Approved Homepage V4.4.0

Implement the approved homepage exactly.

Do not redesign.

Do not substitute generic imagery.

Do not change approved content hierarchy without approval.

## WEB-002 — Header

Implement:

- Logo
- Navigation
- Search
- Log In
- Enter Here

## WEB-003 — Hero

Implement the approved headline, supporting copy and CTA structure.

## WEB-004 — Community Surface

Implement the approved visual surface using the configured/curated backend source.

Do not create a forum.

## WEB-005 — Footer

Implement approved Explore, Community, Support and Newsletter areas.

---

# 20. Remaining Public Pages

Build only when their corresponding UI/UX design is approved.

Expected Phase 1 public destinations:

- About
- Music
- Podcast
- Poetry/Prose
- Inspirational Resources
- Contact
- Login
- Registration
- User account/profile where required
- Protected-content experiences where required
- Newsletter interactions

The public implementation must consume existing backend/domain services.

Do not create duplicate business logic in Blade or Livewire components.

---

# 21. Registered User Access Matrix

Phase 1 has NO paid Premium Membership.

| Capability | Guest | Registered |
|---|---:|---:|
| Public pages | Yes | Yes |
| Music | Yes | Yes |
| Podcast | Yes | Yes |
| Protected Poetry/Prose | No | Yes |
| Protected Inspirational Resources | No | Yes |
| Newsletter | Yes | Yes |
| Exclusive content | No | Yes |
| Downloads where enabled | No | Yes |
| Protected submissions | No | Yes |

Server-side authorization is mandatory.

---

# 22. Media and Downloads

Use a consistent media abstraction for:

- Images
- Documents
- External embeds

Validate:

- File type
- File size
- Destination/storage
- Authorization

Registered-only downloads must use protected authorization and protected storage where
appropriate.

Native live-streaming infrastructure is excluded.

---

# 23. Security Requirements

Mandatory:

- HTTPS/SSL
- Secure password hashing
- Email verification
- CSRF protection
- Input validation
- Output escaping
- Role-based admin permissions
- Login/brute-force protection
- Spam protection
- Secure file validation
- Protected downloads
- Secure sessions/cookies
- Dependency updates
- Scheduled backups
- Recovery procedures
- Basic server hardening

Never:

- Commit secrets
- Store plaintext passwords
- Store unnecessary payment data
- Trust client-provided authorization state
- expose private content through public search

---

# 24. Testing and QA

## Automated

Test:

- Authentication
- Authorization
- Content CRUD
- Search
- SEO metadata
- Policies
- Newsletter
- Homepage/navigation
- Protected content
- Forms

## Manual

Test:

- Desktop
- Tablet
- Mobile
- Chrome
- Firefox
- Safari
- Edge where available
- Guest vs registered
- Search
- Forms/spam
- Broken links
- SEO source
- Structured data
- Keyboard accessibility
- Screen-reader spot checks
- Lighthouse

## Release Gate

Must have:

- No critical/high defects
- Core journeys pass
- SEO reviewed
- Sitemap works
- robots works
- Analytics verified
- Backups verified
- Security reviewed
- Performance reviewed

---

# 25. Deployment

Baseline:

```text
Ubuntu
Nginx
PHP 8.4
Laravel
MariaDB
Redis
Cloudflare
HTTPS
```

Starting compute:

```text
2 vCPU
4 GB RAM
```

Scale according to measured traffic.

## Deployment flow

```text
Feature branch
    ↓
Automated tests/static checks
    ↓
Pull request/review
    ↓
Protected branch
    ↓
Staging smoke tests where available
    ↓
Production deployment
    ↓
Safe migrations
    ↓
Cache rebuild/clear
    ↓
Post-deploy smoke test
```

## Backups

- Scheduled database backups
- Retention policy
- Off-server copy where practical
- Periodic restore test
- Documented recovery procedure

---

# 26. Git and Claude Code Protocol

## Branching

```text
main

feature/core-auth
feature/core-cms
feature/core-media
feature/core-search
feature/jacob-music
feature/jacob-podcast
feature/jacob-poetry-prose
feature/jacob-resources
feature/jacob-community
feature/homepage
bugfix/<description>
```

## Every Claude Task

Claude MUST:

1. Read the relevant specification.
2. Inspect the existing repository before creating files.
3. Identify affected modules/files.
4. Implement one bounded task.
5. Reuse existing services before creating new ones.
6. Follow the modular Laravel architecture.
7. Run relevant tests/checks.
8. Review security, SEO and performance impact.
9. Summarize changes.
10. Identify the next logical task.

## Claude MUST NOT

- Change unrelated modules.
- Invent Phase 1 features.
- Redesign approved UI.
- Introduce Next.js.
- Introduce Express.js.
- Introduce Prisma.
- Add paid membership.
- Add Stripe/PayPal checkout.
- Add ecommerce.
- Add forum/messaging/groups.
- Add drag-and-drop page builder.
- Commit secrets.
- Skip authorization tests.
- Put database queries in Blade.
- Duplicate business rules.
- Create a second backend stack.

---

# 27. Standard Claude Prompt Contract

Every implementation prompt in the separate Claude Prompt Book should contain:

## Prompt ID

Example:

`CORE-001`

## Title

Short implementation name.

## Goal

One clear implementation objective.

## References

Only relevant specification sections.

## Prerequisites

Completed prompts required before execution.

## Scope

What Claude MUST implement.

## Out of Scope

What Claude MUST NOT implement.

## Files / Modules

Expected files/modules to create or modify.

## Database

Only when relevant.

## Routes / APIs

Only when relevant.

## Validation

Required validation.

## Authorization

Required policies/permissions.

## UI

Only when relevant.

## Tests

Required automated/manual verification.

## Acceptance Criteria

Measurable completion conditions.

## Definition of Done

The implementation is not complete until all required checks pass.

## Final Claude Response

Claude MUST report:

- Summary of work completed
- Files created
- Files modified
- Migrations created
- Routes/endpoints added
- Tests added/run
- Manual verification steps
- Known issues
- Suggested next prompt

---

# 28. Recommended Prompt Sequence

## Stage A — Softphoria Core

```text
CORE-001 Laravel initialization
CORE-002 Modular structure
CORE-003 Environment/configuration
CORE-004 Code quality/static checks
CORE-005 Logging/error handling
DB-001 MariaDB configuration
DB-002 Migration standards
DB-003 Core models
DB-004 Factories/seeders
AUTH-001 Registration
AUTH-002 Login/logout
AUTH-003 Email verification
AUTH-004 Password reset
AUTH-005 Profile/session security
CMS-001 Filament foundation
CMS-002 Roles/permissions
CMS-003 Users
CMS-004 Media
CMS-005 Pages
CMS-006 Navigation/settings
CMS-007 Forms
CMS-008 Newsletter
SEO-001 SEO foundation
SEARCH-001 Scout/search foundation
SVC-001 Redis
SVC-002 Queues
SVC-003 Notifications/email
ANALYTICS-001 GA4/Search Console
SEC-001 Security baseline
```

## Stage B — Softphoria Core Admin

```text
ADMIN-001 Admin shell
ADMIN-002 Dashboard
ADMIN-003 Users
ADMIN-004 Roles/permissions
ADMIN-005 Media Library
ADMIN-006 Pages
ADMIN-007 Navigation
ADMIN-008 Site Settings
ADMIN-009 SEO
ADMIN-010 Forms
ADMIN-011 Newsletter
ADMIN-012 Operational logs
ADMIN-013 Final core admin QA
```

## Stage C — Jacob Modules

```text
JACOB-001 Homepage content configuration
JACOB-002 About
JACOB-003 Music
JACOB-004 Podcast
JACOB-005 Poetry/Prose
JACOB-006 Inspirational Resources
JACOB-007 Community
JACOB-008 Newsletter integration
JACOB-009 Contact
JACOB-010 Search integration
JACOB-011 SEO integration
JACOB-012 Jacob Admin QA
```

## Stage D — Public Frontend

```text
WEB-001 Design tokens/components
WEB-002 Header
WEB-003 Footer
WEB-004 Approved Homepage V4.4.0
WEB-005 About
WEB-006 Music
WEB-007 Podcast
WEB-008 Poetry/Prose
WEB-009 Inspirational Resources
WEB-010 Contact
WEB-011 Login/Registration
WEB-012 Protected content
WEB-013 Responsive/accessibility
WEB-014 Performance optimization
```

## Stage E — Final QA / Deployment

```text
QA-001 Automated regression
QA-002 Manual cross-browser
QA-003 Accessibility
QA-004 SEO
QA-005 Structured data
QA-006 Performance/Core Web Vitals
QA-007 Security
QA-008 Backup/restore
DEPLOY-001 Staging
DEPLOY-002 Production
DEPLOY-003 Post-deployment verification
```

---

# 29. Phase 1 Scope Protection

Claude must treat the following as future scope:

- Music store/ecommerce checkout
- Merchandise
- Community forum
- Messaging
- Groups
- AI recommendations
- Event registration
- Digital publications
- Mobile application
- Online courses
- Custom analytics dashboard
- Native live-streaming infrastructure
- Drag-and-drop page builder
- Automatic no-code arbitrary future departments
- Paid Premium Membership
- Subscription checkout
- Payment gateways

Future-ready architecture means clean domain boundaries and reusable services.
It does NOT mean implementing future functionality now.

---

# 30. Final Completion Criteria

Phase 1 is complete only when:

- Approved navigation is implemented.
- Approved homepage V4.4.0 is implemented responsively.
- CMS manages all defined content types.
- Guest/registered permissions work.
- No paid membership workflow exists.
- Music is manageable/publishable.
- Podcast is manageable/publishable.
- Poetry/Prose is manageable/publishable.
- Inspirational Resources work.
- Defined Community interactions work.
- Global search works.
- Dynamic metadata works.
- Structured data works.
- Sitemap works.
- robots.txt works.
- Canonical URLs work.
- GA4/Search Console are configurable.
- Defined events are tracked.
- Security baseline is complete.
- Backups are configured.
- Accessibility is tested.
- Representative pages meet agreed Lighthouse/performance targets.
- Production deployment is complete.
- DNS/SSL/Cloudflare are configured.
- Handover documentation is complete.

---

# 31. Final Rule for Claude Code

The goal is NOT to create a generic Laravel website quickly.

The goal is to create:

> **A reusable Softphoria Platform Core + a Jacob-specific Phase 1 implementation, using a modular Laravel monolith, followed by the approved public website.**

Every implementation decision must preserve:

- Reusability
- Modularity
- SEO
- Performance
- Accessibility
- Security
- Maintainability
- Future API readiness

At the same time, Claude must strictly protect Phase 1 scope and must not implement
future functionality simply because the architecture is designed to accommodate it.
