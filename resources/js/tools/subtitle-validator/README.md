# Subtitle Validator

Tool `subtitle-validator` (`App\Tools\Functionalities\SubtitleValidator`), page `/tools/subtitle-validator`. Content, SEO and FAQ: `database/seeders/SubtitleValidatorToolSeeder.php` (create-if-missing draft; edit the live page in Admin → Tools).

## Files

| File | Role |
|---|---|
| `src/decode.js` | Bytes → text. UTF-8 (± BOM), UTF-16 with BOM. Invalid UTF-8 is reported with its line, never replaced; Windows-1252 only when the visitor chooses it. |
| `src/timestamp.js` | Strict timestamp parsing with a diagnosis code and `fixable` flag; formatting. |
| `src/parse.js` | SRT / WebVTT → one document model, with source line numbers and structural findings. Lenient: a timing line always starts a cue; stray lines are kept. |
| `src/validate.js` | Rules → findings (deduplicated per rule and place), stats, score, status. |
| `src/rules.js` | Rule catalogue (stable ids, severity, category, wording, fix), fixes, default limits. |
| `src/repair.js` | Corrected copy. Always applied: header, blank lines, SRT numbering, timestamp form (same times), line endings. Only when ticked: trim spaces, remove empty cues, remove control characters, sort, merge repeats, trim overlaps, rewrap. Unreadable content is written back verbatim. |
| `src/report.js` | CSV / text reports (no subtitle text, formula-safe CSV). |
| `src/check.js` | `check(text, options)` entry point; the two samples. |
| `../subtitle-validator.js` | Interface (DOM only; subtitle text is set with `textContent`). |

## Product limits

- 5 MiB per file (checked before reading) and 10,000 cues. Measured in Node: 10,000 cues with 20,000 findings validate in ~110 ms and repair in ~220 ms; 5 MiB of long lines validate in ~120 ms. The interface renders findings 200 at a time, so no worker is needed.
- Default rules (configurable, not platform rules): 42 characters per line, 2 lines per cue, 20 CPS, 0.8–7 s per cue, minimum gap off.

## Score

100 − 10 × errors − 3 × warnings, clamped to 0–100. Findings are counted once per rule and place. A product scoring model only; it says nothing about transcription accuracy, accessibility compliance or platform acceptance.

## Privacy

Everything runs in the browser: no upload route, no storage table, no `fetch` / storage APIs in this code (asserted by `tests/Feature/SubtitleValidatorToolTest.php`). Analytics (consent-gated, `shared/track.js`) send only `tool_started {input}`, `tool_completed {format, status}`, `tool_error {error_code}`, `tool_download {download_type, format}` and `tool_copy {copied}` — never file names, text or timings.

## Tests

```
node --test "resources/js/tools/subtitle-validator/tests/*.test.mjs"
php artisan test tests/Feature/SubtitleValidatorToolTest.php
```

Fixtures in `tests/fixtures` are synthetic. Never commit real client subtitles.

## Not supported (Phase 1)

ASS/SSA, checking against audio, transcription/translation, timeline editing, platform-specific profiles.
