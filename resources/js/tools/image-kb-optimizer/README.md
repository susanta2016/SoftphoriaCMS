# Exact Image KB Optimizer

Browser-only tool that compresses one or more JPG, PNG or WebP images to **at or below** a maximum file size (10 KB … 1 MB, or custom), then verifies the result. Images are never uploaded; ZIP files are built in the browser.

| Path | Role |
| --- | --- |
| `app/Tools/Functionalities/ImageKbOptimizer.php` | Functionality `image-kb-optimizer` (auto-discovered by `ToolRegistry`) |
| `resources/views/tools/functionalities/image-kb-optimizer.blade.php` | Mount point (`data-iko-root`) |
| `resources/js/tools/image-kb-optimizer.js` | Vite entry: styles + `mount()`, events to `shared/track.js` (counts, target, format and error codes only) |
| `database/seeders/ImageKbOptimizerToolSeeder.php` | Landing-page content, SEO and FAQ as a **Draft** (never publishes, never overwrites) |
| `tests/Feature/ImageKbOptimizerToolTest.php` | Registry, seeder, public page/SEO, no upload routes or tables |

## Files

| Path | Role |
| --- | --- |
| `src/target.js` | Presets, custom-target validation, `?target=50kb` parsing, size formatting |
| `src/optimize.js` | The search (pure; takes an `encode()` function) |
| `src/image.js` | Format sniffing, decoding, transparency check, canvas encoder |
| `src/zip.js` | Minimal stored ZIP writer + CRC-32 (pure) |
| `src/app.js` | Interface, `mount(root, { onEvent })` |
| `src/styles.css` | Styles, scoped under `.iko` |

## Rules

- A target is a maximum, and the byte limit uses **1 KB = 1,000 bytes** (stricter than 1,024), so results pass either way a site counts.
- Every size is the **actual** encoded `Blob` size, never an estimate.
- Order: meet the target → keep dimensions → keep quality → shrink only when the lowest quality (`QUALITY.min`, 55% — an internal encoder floor) does not fit, then search back up for the largest size that fits.
- PNG is lossless (dimensions only); if it cannot fit, the UI offers WebP/JPEG and warns before transparency is lost.
- An image already under the target in the same format, with no size limits, is returned untouched.
- Limits: 20 files, 50 MB and ~60 MP per file; work canvases are capped at 4096 × 4096 equivalent (`SAFE_PIXELS`); never below 160 px on the long side.

## Tests

```bash
node --test "resources/js/tools/image-kb-optimizer/tests/*.test.mjs"
```
