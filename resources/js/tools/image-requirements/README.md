# Image Requirements Checker

Browser-only checker that answers "Is this image ready to publish, and where can I use it?": it reads a JPG, PNG or WebP image locally, checks it against platform placement profiles (or the user's own requirements), and explains every result with crop and safe-zone previews. The image is never uploaded or stored.

| Path | Role |
| --- | --- |
| `app/Tools/Functionalities/ImageRequirements.php` | Functionality `image-requirements` (auto-discovered by `ToolRegistry`) |
| `resources/views/tools/functionalities/image-requirements.blade.php` | Mount point (`data-irc-root`) |
| `resources/js/tools/image-requirements.js` | Vite entry: styles + `mount()`, events to `shared/track.js` |
| `database/seeders/ImageRequirementsToolSeeder.php` | Landing-page content, SEO and FAQ as a **Draft** (never publishes, never overwrites) |
| `tests/Feature/ImageRequirementsToolTest.php` | Registry, seeder, preview, public page/SEO, no upload routes or image tables |

## Files

| Path | Role |
| --- | --- |
| `data/profiles.js` | **All platform requirements** (the only place they live), with levels and sources |
| `src/analyze.js` | Format by content; PNG/JPEG/WebP headers: size, alpha, animation, ICC/sRGB, CMYK, DPI, EXIF (orientation, camera, date, GPS present) |
| `src/check.js` | Engine: per-placement checks and explanations, best-uses groups, readiness score, custom profiles |
| `src/geometry.js` | Shape names/parsing, crop rectangles |
| `src/app.js` | Interface, `mount(root, { onEvent })` |
| `src/styles.css` | Styles, scoped under `.irc` |

## Requirement levels

Every rule in `data/profiles.js` is `hard` (a documented platform limit: Fail), `recommended` (documented or widely published guidance: Warning) or `advisory` (context: Info). A value not confirmed on an official page is never `hard`; the profile test enforces that hard rules have official, linked sources. Profiles were reviewed on 2026-10-08 (`REVIEWED`). To update: change the profile, its sources and `REVIEWED`, bump `PROFILES_VERSION`, and keep the seeder's "Where the requirements come from" copy consistent.

## Tests

```bash
node --test "resources/js/tools/image-requirements/tests/*.test.mjs"
```

Covers the header reader with byte-level PNG/JPEG/WebP fixtures, crop maths, every profile, the five spec scenarios (A–E) and the profile data rules.
