// One entry point for the interface and the tests: text in, result out.

import { detectFormat, parse } from './parse.js';
import { DEFAULT_SETTINGS, finding } from './rules.js';
import { validate } from './validate.js';

/**
 * @param {string} text
 * @param {{format?: 'auto'|'srt'|'vtt', fileName?: string, settings?: object, encoding?: string}} options
 */
export function check(text, { format = 'auto', fileName = '', settings = DEFAULT_SETTINGS, encoding = 'UTF-8' } = {}) {
    const detected = detectFormat(text, fileName);
    const chosen = format === 'auto' ? detected.format ?? 'srt' : format;
    const { doc, findings } = parse(text, chosen);

    if (encoding === 'Windows-1252') findings.unshift(finding('encoding-windows-1252'));

    return { format: chosen, detected, doc, result: validate(doc, findings, settings) };
}

export const SAMPLES = {
    valid: `1
00:00:01,000 --> 00:00:03,500
Welcome to the channel.

2
00:00:04,000 --> 00:00:07,000
Today we're checking subtitle files
before they go online.

3
00:00:07,500 --> 00:00:10,000
Let's get started.
`,
    broken: `1
00:00:01,000 --> 00:00:03,500
Welcome to the channel.

2
00:00:03,000 --> 00:00:04.200
This cue overlaps the one before it and is far too long to read comfortably.

4
00:00:06,000 --> 00:00:05,000
The end time comes before the start.

5
00:00:08,000 --> 00:00:09,500

6
00:00:10,000 --> 00:00:12,000
Same words again.

7
00:00:12,000 --> 00:00:14,000
Same words again.
`,
};
