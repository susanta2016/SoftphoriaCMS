// Rule catalogue: every finding has a stable id, a severity, a category, a
// plain explanation and a suggested action, and names the fix (if any)
// that can repair it. Severities: an error means the file is malformed or
// its timing cannot work as written; a warning is a readability or quality
// concern that players accept.

export const DEFAULT_SETTINGS = Object.freeze({
    maxCharsPerLine: 42,
    maxLines: 2,
    maxCps: 20,
    minDuration: 0.8,
    maxDuration: 7,
    minGap: 0,
});

export const MAX_CUES = 10000;

// Built from code points: U+FEFF and U+2028/2029 must never appear
// literally in source files (U+2028 ends a line inside a regex literal).
export const BOM = String.fromCodePoint(0xfeff);

/** Control characters for a regex character class: C0 (not tab/LF/CR), DEL, C1, line/paragraph separators, BOM. */
export const CONTROL_RANGES = [[0x00, 0x08], [0x0b, 0x0c], [0x0e, 0x1f], [0x7f, 0x9f], [0x2028, 0x2029], [0xfeff, 0xfeff]]
    .map(([from, to]) => `${String.fromCodePoint(from)}-${String.fromCodePoint(to)}`)
    .join('');

export const CATEGORIES = {
    structure: 'Structure',
    timestamp: 'Timestamp',
    timing: 'Timing',
    readability: 'Readability',
    encoding: 'Encoding',
};

// [severity, category, explanation, suggestion, fix]
export const RULES = {
    'file-empty': ['error', 'structure', 'The file is empty.', 'Add subtitle cues, or choose a different file.', null],
    'no-cues': ['error', 'structure', 'No subtitle cues were found.', 'Each cue needs a timing line such as 00:00:01,000 --> 00:00:03,000. Check that this is an SRT or WebVTT file.', null],
    'too-many-cues': ['error', 'structure', 'The file has more than 10,000 cues, which is above this checker\'s limit.', 'Split the file into smaller parts and check each one.', null],
    'vtt-missing-header': ['error', 'structure', 'A WebVTT file must start with the line WEBVTT.', 'Add WEBVTT as the first line, followed by a blank line.', 'add-header'],
    'vtt-header-invalid': ['error', 'structure', 'The first line starts with WEBVTT but is not a valid header.', 'The header is WEBVTT, optionally followed by a space or tab and a title.', null],
    'missing-blank-line': ['error', 'structure', 'This cue starts without a blank line before it, so players may read it as part of the previous cue.', 'Put one blank line between cues.', 'separators'],
    'srt-missing-index': ['error', 'structure', 'This SRT cue has no sequence number above its timing line.', 'Number the cues 1, 2, 3 … in order.', 'renumber'],
    'srt-index-sequence': ['warning', 'structure', 'The sequence number does not follow the previous cue.', 'Renumber the cues 1, 2, 3 … in order.', 'renumber'],
    'srt-index-duplicate': ['warning', 'structure', 'This sequence number is already used by an earlier cue.', 'Renumber the cues 1, 2, 3 … in order.', 'renumber'],
    'stray-text': ['error', 'structure', 'These lines are not part of any cue: there is no timing line for them, or a blank line splits a cue in two.', 'Remove the blank line inside the cue, or add the missing number and timing line.', null],
    'cue-empty': ['error', 'structure', 'This cue has no text.', 'Add the subtitle text, or remove the cue.', 'remove-empty'],
    'timing-malformed': ['error', 'structure', 'This timing line could not be read.', 'Write it as start --> end, with a space on both sides of the arrow.', null],
    'srt-timing-settings': ['warning', 'structure', 'There is extra text after the end time. SRT has no cue settings, so many players ignore or reject it.', 'Remove the text after the end time unless your player needs it.', null],
    'vtt-cue-setting': ['warning', 'structure', 'A cue setting is not valid WebVTT, so players will ignore it.', 'Use vertical, line, position, size, align or region with a valid value, or remove it.', null],
    'vtt-note-arrow': ['error', 'structure', 'A NOTE block cannot contain -->.', 'Remove the arrow from the comment, or add a blank line if a cue follows the note.', null],
    'vtt-block-after-cue': ['warning', 'structure', 'STYLE and REGION blocks must come before the first cue; players ignore them here.', 'Move the block above the first cue.', null],
    'vtt-duplicate-id': ['warning', 'structure', 'This cue identifier is already used by an earlier cue.', 'Give every cue a unique identifier, or remove the identifiers.', null],
    'whitespace-separator': ['warning', 'structure', 'A blank line between cues contains spaces or tabs.', 'Use completely empty lines between cues.', 'separators'],
    'mixed-line-endings': ['warning', 'structure', 'The file mixes Windows (CRLF) and Unix (LF) line endings.', 'Save the file with one kind of line ending.', 'line-endings'],

    'timing-arrow-spacing': ['error', 'timestamp', 'The --> arrow needs a space on each side.', 'Write the timing line as 00:00:01,000 --> 00:00:03,000.', 'timestamps'],
    'timestamp-invalid': ['error', 'timestamp', 'This timestamp is not valid.', 'Use HH:MM:SS,mmm for SRT or HH:MM:SS.mmm for WebVTT, with minutes and seconds from 00 to 59.', null],
    'timestamp-precision': ['error', 'timestamp', 'The milliseconds must have exactly three digits.', 'Write milliseconds with three digits, for example 01,500 rather than 01,5.', null],
    'timestamp-negative': ['error', 'timestamp', 'This timestamp is negative.', 'Timestamps start at 00:00:00. Correct the time in your subtitle editor; it is never clipped automatically.', null],
    'timestamp-separator': ['error', 'timestamp', 'The milliseconds separator is wrong for this format.', 'SRT uses a comma (00:00:01,000) and WebVTT a full stop (00:00:01.000).', 'timestamps'],
    'timestamp-hours': ['error', 'timestamp', 'The hours are missing or have only one digit.', 'Write hours with two digits, for example 00:00:01,000.', 'timestamps'],

    'end-before-start': ['error', 'timing', 'The cue ends before it starts.', 'Correct the start or end time so the end is later.', null],
    'zero-duration': ['error', 'timing', 'The cue starts and ends at the same time, so it is never shown.', 'Give the cue a duration, or remove it.', null],
    'out-of-order': ['error', 'timing', 'This cue starts before the previous cue.', 'Order the cues by start time.', 'sort'],
    'overlap': ['warning', 'timing', 'This cue starts before the previous cue ends, so both are on screen at once.', 'If the overlap is not intended, end the previous cue when this one starts.', 'trim-overlaps'],
    'short-duration': ['warning', 'timing', 'The cue is on screen for less than the minimum duration.', 'Show it for longer, or merge it with a neighbouring cue.', null],
    'long-duration': ['warning', 'timing', 'The cue is on screen for longer than the maximum duration.', 'Split it into shorter cues, or end it earlier.', null],
    'small-gap': ['warning', 'timing', 'The gap after the previous cue is shorter than the minimum gap.', 'Move the start time later, or end the previous cue earlier.', null],

    'line-too-long': ['warning', 'readability', 'A line is longer than the maximum characters per line.', 'Break the text into shorter lines.', 'rewrap'],
    'too-many-lines': ['warning', 'readability', 'The cue has more lines than the maximum.', 'Shorten the text or split it into two cues.', 'rewrap'],
    'reading-speed': ['warning', 'readability', 'The reading speed is above the maximum characters per second.', 'Shorten the text or show the cue for longer.', null],
    'text-whitespace': ['warning', 'readability', 'Text lines start or end with spaces or tabs.', 'Remove the extra spaces.', 'trim-whitespace'],
    'duplicate-cue': ['warning', 'readability', 'This cue repeats the text of the previous cue.', 'Merge the two cues into one, unless the repeat is intended.', 'merge-duplicates'],
    'control-characters': ['warning', 'readability', 'The text contains invisible control characters.', 'Remove them; they can show as boxes or break some players.', 'remove-control'],

    'encoding-windows-1252': ['warning', 'encoding', 'The file was read as Windows-1252 because it is not valid UTF-8.', 'Check that accented and special characters look right. The corrected file is saved as UTF-8.', null],
};

// [label, explanation, needs confirmation]
export const FIXES = {
    'add-header': ['Add the WEBVTT header', 'Adds WEBVTT and a blank line at the top.', false],
    separators: ['Fix blank lines between cues', 'Puts exactly one empty line between cues and blocks.', false],
    renumber: ['Renumber SRT cues', 'Numbers the cues 1, 2, 3 … in order.', false],
    timestamps: ['Fix timestamp format', 'Rewrites timestamps with the correct separator and two-digit hours. The times themselves do not change.', false],
    'line-endings': ['Use one kind of line ending', 'Saves every line with the file\'s most common line ending.', false],
    'trim-whitespace': ['Trim spaces around text lines', 'Removes spaces and tabs at the start and end of subtitle lines.', false],
    'remove-empty': ['Remove empty cues', 'Deletes cues that have no text.', true],
    sort: ['Sort cues by start time', 'Changes the order of cues.', true],
    'trim-overlaps': ['Trim overlapping cues', 'Ends each overlapped cue when the next one starts. This changes timing.', true],
    'merge-duplicates': ['Merge repeated adjacent cues', 'Keeps one cue for each repeat and extends it to cover both. This changes timing.', true],
    rewrap: ['Rewrap long lines', 'Re-breaks the text of affected cues to fit the line length. This changes the text layout.', true],
    'remove-control': ['Remove control characters', 'Deletes invisible control characters from the text.', true],
};

/** Builds a finding from a rule id plus where it happened. */
export function finding(rule, at = {}) {
    const [severity, category, message, suggestion, fix] = RULES[rule];
    return { rule, severity, category, message, suggestion, fix, cue: null, line: null, start: null, end: null, detail: null, ...at };
}
