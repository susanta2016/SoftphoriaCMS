# Social Video Safe Zone Checker (P1a)

Browser-only checker that overlays the measured Instagram Reels and YouTube Shorts UI zones (TikTok provisional) on a vertical video or image, flags key elements hidden under them, scores each platform and exports the result. Spec: the approved P1 implementation specification (Claude Doc, 2026-10-07).

**Status: P1a, standalone.** It is deliberately *not* a Softphoria Tool yet: there is no `app/Tools/Functionalities` class, no Blade view, no database change, and no `resources/js/tools/social-video-safe-zone.js` entry file. Vite only builds files directly in `resources/js/tools/`, so nothing in this folder reaches the CMS build. Registering it (P1b) needs separate approval.

## Run it

```bash
# demo page (no Laravel, no Vite), then open http://127.0.0.1:5178/demo/
node resources/js/tools/social-video-safe-zone/scripts/serve.mjs

# unit + contract tests (node:test, no dependencies)
node --test "resources/js/tools/social-video-safe-zone/tests/*.test.mjs"
```

The demo page sets `connect-src 'none'` in its Content Security Policy, so the browser blocks any network request from the page: files cannot leave the device.

## Data

`data/safezones.v1.json` (and its importable twin `safezones.v1.js`) is generated from the FINAL P0 data and never edited by hand:

```bash
node resources/js/tools/social-video-safe-zone/scripts/generate-dataset.mjs           # regenerate
node resources/js/tools/social-video-safe-zone/scripts/generate-dataset.mjs --check   # verify it is up to date
```

The P0 workspace (`C:/Users/susan/Documents/P0-measurements`, or `--p0 <dir>` / `P0_DIR`) is read only. Rows follow the P0 completion report rule (usable, playing, inside the picture; edges as P0 recorded them, rounded to the nearest 10 px). Every zone records its provenance and the P0 rows it came from. `tests/dataset.test.mjs` rebuilds the dataset and fails on any difference.

## Detector check on real frames

```bash
node resources/js/tools/social-video-safe-zone/scripts/eval-detector-p0.mjs
```

Runs the detector on the 9 P0 calibration reference frames (decoded with the P0 workspace's own `sharp`; nothing is added to this repo) and reports whether the code panel is found. Report-only; the gated bar is the synthetic set in the tests.

## Files

| Path | Role |
| --- | --- |
| `src/app.js` | UI controller, `mount(rootElement, { onEvent })` |
| `src/dataset.js` | Resolves zones for platform, caption length, CC, sound line, phone view, aspect |
| `src/scoring.js` | Coverage, risk, score, verdict, issues (pure) |
| `src/fixes.js` | Smallest move / shrink that clears every hard zone (pure) |
| `src/detector-core.js` | Heuristic text/logo detector (pure, deterministic) |
| `src/detector.worker.js` | Web Worker wrapper for the detector |
| `src/frames.js` | Local decode, frame sampling (fast muted playback, seek fallback), tracking |
| `src/tracker.js` | Links detections across frames into elements (pure) |
| `src/intake.js`, `src/aspect.js`, `src/geometry.js`, `src/constants.js` | File sniffing, aspect classes, rect maths, tunable values |
| `src/render.js`, `src/export.js`, `src/report.js`, `src/sample.js` | Canvas drawing, exports, report JSON / summary, synthetic sample |
| `src/styles.css` | Styles, scoped under `.svsz` |
| `tests/` | `node:test` suites; `tests/helpers/` synthetic frames and detector evaluation |

## Analytics

The checker never sends anything itself. `mount()` takes an optional `onEvent(name, params)` callback; P1b will connect it to `resources/js/tools/shared/track.js`. Events: `tool_started`, `safezone_file_loaded` (kind, aspect, duration bucket), `tool_completed` (platforms, verdicts, score buckets), `safezone_export` (type), `safezone_error` (code). No file names or image data.
