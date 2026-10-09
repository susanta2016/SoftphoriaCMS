<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\SubtitleValidator;
use Illuminate\Database\Seeder;

/**
 * The Subtitle Validator's landing page (Admin → Tools) as a DRAFT:
 * content, SEO, FAQ, related tools and CTA for /tools/subtitle-validator.
 * It is never published here — review it, then publish from the admin.
 *
 * Wording rules: describe only what resources/js/tools/subtitle-validator
 * implements; limits are configurable general-purpose defaults, never
 * platform rules; no claim of platform acceptance, audio checking or ASS/SSA
 * support; privacy claims match the code (browser-only, analytics only with
 * consent and without file names or text). Related tools: only ones that
 * exist.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class SubtitleValidatorToolSeeder extends Seeder
{
    public const SLUG = 'subtitle-validator';

    public const RELATED = ['social-video-safe-zone-checker', 'image-requirements-checker'];

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Subtitle Validator already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Subtitle Validator',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'image-media')->value('id'),
            'functionality' => SubtitleValidator::KEY,
            'icon' => 'document',
            'short_description' => 'Check SRT and WebVTT subtitle files for timestamp, timing, overlap and reading-speed problems, then download a corrected copy.',
            'heading' => 'Free Subtitle Validator: Check SRT & VTT Files Online',
            'introduction' => 'Check your SRT and WebVTT subtitle files for formatting errors, invalid timestamps, overlapping captions and readability problems. Review detailed findings, fix common issues and download a corrected file with our free online subtitle validator.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Add your file.</strong> Choose or drop an .srt or .vtt file, or paste the subtitle text. It stays in your browser.</li>
<li><strong>Check the format.</strong> SRT or WebVTT is detected automatically; choose it yourself if the detection is unsure.</li>
<li><strong>Adjust the rules.</strong> Change line length, lines per cue, reading speed and durations in Rule settings to match your style guide.</li>
<li><strong>Review the findings.</strong> Filter errors and warnings, and open any finding to see that cue in your file.</li>
<li><strong>Fix and download.</strong> Preview a corrected copy, tick any optional fixes, and download it once it has been checked again.</li>
<li><strong>Keep the report.</strong> Copy it, or download it as CSV or plain text.</li>
</ol>
HTML,
            'additional_content' => <<<'HTML'
<h2>What Does the Subtitle Validator Check?</h2>
<p>It reads SRT and WebVTT files the way a player does and reports anything that could stop a cue from showing, show it at the wrong time, or make it hard to read. Errors mean the file is malformed or its timing cannot work as written; warnings are readability and quality concerns that players still accept.</p>
<h3>File structure</h3>
<p>For SRT, the sequence number, timing line and text of every cue, and the blank line between cues. For WebVTT, the WEBVTT header, optional cue identifiers, cue settings, and NOTE, STYLE and REGION blocks. Lines that belong to no cue, empty cues and missing blank lines are reported with their line number.</p>
<h3>Timestamps</h3>
<p>SRT timestamps are written HH:MM:SS,mmm with a comma; WebVTT uses a full stop and may leave out the hours. The checker reports the wrong separator, missing or one-digit hours, milliseconds without exactly three digits, minutes or seconds above 59, negative times and a missing space around the <code>--&gt;</code> arrow.</p>
<h3>Timing and overlaps</h3>
<p>Cues that end before they start, have no duration or are out of order are errors. Overlapping cues are warnings, because some workflows show two captions at once on purpose. Cues shorter than 0.8 seconds or longer than 7 seconds, and an optional minimum gap between cues, are also reported as warnings.</p>
<h3>Reading speed</h3>
<p>Reading speed is measured in characters per second (CPS): the characters a viewer sees, without formatting tags, divided by how long the cue is on screen. The default limit is 20 CPS.</p>
<h3>Line length and lines per cue</h3>
<p>By default a line may have up to 42 characters and a cue up to two lines. Characters are counted as people read them, so an emoji or a Chinese character counts once.</p>
<h3>Encoding and hidden characters</h3>
<p>Files are read as UTF-8, with or without a byte-order mark, and UTF-16 files with a byte-order mark are read too. If a file is not valid UTF-8, the checker shows the line and lets you choose to read it as Windows-1252, instead of silently replacing characters. Spaces around text, invisible control characters, mixed line endings and repeated adjacent captions are reported as well.</p>
<p>All limits are configurable general-purpose defaults, not the rules of any particular platform. Change them in Rule settings to match your style guide.</p>
<h2>Common Subtitle Errors and How to Fix Them</h2>
<ul>
<li><strong>Wrong millisecond separator.</strong> 00:00:01.000 in an SRT file, or 00:00:01,000 in WebVTT. The corrected file rewrites it without changing the time.</li>
<li><strong>Missing WEBVTT header.</strong> A WebVTT file must start with the line WEBVTT. The corrected file adds it.</li>
<li><strong>Missing blank line between cues.</strong> Players may read two cues as one. The corrected file adds the blank line and renumbers SRT cues.</li>
<li><strong>End time before start time.</strong> Usually a typing mistake. Correct it in your subtitle editor; the checker never guesses a new time.</li>
<li><strong>Overlapping cues.</strong> If the overlap is not intended, the optional fix ends each cue when the next one starts.</li>
<li><strong>Reading speed too high.</strong> Shorten the text, or keep the cue on screen for longer.</li>
<li><strong>Lines too long.</strong> The optional rewrap fix re-breaks long lines; dialogue cues are left as they are.</li>
<li><strong>Unreadable characters.</strong> The file is not saved as UTF-8. Re-save it as UTF-8 in your subtitle editor.</li>
</ul>
<p>If you burn captions into vertical videos, also check that the app interface will not cover them with the <a href="/tools/social-video-safe-zone-checker">Social Video Safe Zone Checker</a>.</p>
<h2>SRT vs VTT: Which Format Should You Use?</h2>
<table>
<thead><tr><th></th><th>SRT</th><th>WebVTT</th></tr></thead>
<tbody>
<tr><td>First line</td><td>The first cue number</td><td>WEBVTT</td></tr>
<tr><td>Timestamps</td><td>00:00:01,000 (comma)</td><td>00:00:01.000 (full stop); hours optional</td></tr>
<tr><td>Cue numbers</td><td>Required</td><td>Optional identifiers</td></tr>
<tr><td>Positioning</td><td>Not part of the format</td><td>Cue settings such as line, position and align</td></tr>
<tr><td>Styling</td><td>Basic tags such as &lt;i&gt; in many players</td><td>Tags, plus CSS in STYLE blocks</td></tr>
<tr><td>Comments</td><td>No</td><td>NOTE blocks</td></tr>
</tbody>
</table>
<p>SRT is the simpler format and is widely accepted by video editors and upload forms. WebVTT is the format HTML video players use with the <code>&lt;track&gt;</code> element, and it adds positioning, styling and comments. Check which formats your platform accepts: the corrected file can be saved in either format, and anything SRT cannot hold is listed before you download.</p>
<p>Softphoria builds tools like this one into websites and business systems. If your team needs a custom subtitle workflow or another web tool, see our <a href="/services/custom-software">custom software</a> service.</p>
HTML,
            'important_notes' => <<<'HTML'
<p>Your subtitle file is read and checked in your browser; it is not uploaded or stored. The page itself loads normally and, if you accept analytics cookies, sends anonymous usage events without file names or subtitle text.</p>
<p>A clean report means the file is well-formed and readable by the rules you set. It does not check the words against the audio or guarantee that a platform will accept the file.</p>
HTML,
            'cta_heading' => 'Need a Custom Web Tool or Business Application?',
            'cta_text' => 'Softphoria builds custom web applications, business tools, integrations and content platforms tailored to your workflow.',
            'cta_label' => 'Discuss your project',
            'cta_url' => '/contact',
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Free Subtitle Validator – Check SRT & VTT Files Online',
            'meta_description' => 'Validate SRT and VTT subtitle files for timestamp errors, overlaps, reading speed and line length. Fix common problems and download corrected subtitles free.',
        ]);

        foreach (self::faqs() as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        // Related tools: only ones that exist, in this order.
        $related = Tool::query()->whereIn('slug', self::RELATED)->pluck('id', 'slug');
        foreach (self::RELATED as $order => $slug) {
            if ($related->has($slug)) {
                $tool->relatedTools()->attach($related[$slug], ['sort_order' => $order]);
            }
        }

        $this->command?->info('Subtitle Validator created as a draft. Review and publish it in Admin → Tools.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function faqs(): array
    {
        return [
            ['What is a subtitle validator?', 'A tool that checks a subtitle file\'s format and timing before you publish it. This one reads SRT and WebVTT files and reports structural errors, invalid timestamps, overlaps and readability problems, with the cue and line where each one is.'],
            ['How do I validate an SRT file online?', 'Choose or drop the .srt file at the top of this page, or paste its text. It is checked straight away. Review the findings, then download a corrected copy or the report.'],
            ['Can I check VTT and WebVTT files?', 'Yes. .vtt and .webvtt files are checked as WebVTT, including the WEBVTT header, cue identifiers, cue settings and NOTE, STYLE and REGION blocks.'],
            ['What causes invalid SRT timestamps?', 'Most often a full stop instead of a comma before the milliseconds, missing hours, milliseconds without three digits, or no space around the arrow. The correct form is HH:MM:SS,mmm --> HH:MM:SS,mmm, for example 00:01:02,500 --> 00:01:04,000.'],
            ['How do I fix overlapping subtitles?', 'Make each cue end at or before the moment the next one starts. The optional "Trim overlapping cues" fix does this by shortening the earlier cue. Leave an overlap alone if two captions are meant to show at the same time.'],
            ['What is a good subtitle reading speed in CPS?', 'There is no single standard. Many style guides set a limit somewhere around 15 to 20 characters per second, and lower for young audiences. The default here is 20 CPS; change it in Rule settings to match your guide.'],
            ['How many characters should a subtitle line contain?', 'A common guideline is up to about 42 characters per line and two lines per cue, which is this checker\'s default. Some guides use fewer, and languages such as Chinese or Japanese use much lower limits, so set your own in Rule settings.'],
            ['Can the tool fix subtitle errors automatically?', 'Some. Formatting problems such as a missing WEBVTT header, numbering, blank lines and timestamp separators are fixed without changing what is shown or when. Fixes that change text, order or timing, such as removing empty cues, sorting, merging repeats, trimming overlaps or rewrapping lines, are only applied if you tick them. Problems that need a decision, such as an end time before the start, are left for you to correct.'],
            ['Are subtitle files uploaded or stored on a server?', 'No. The file is read and checked in your browser and is not sent to our server or stored. The page still loads its own files, and if you accept analytics cookies it records anonymous events such as a completed check, never the file name or its text.'],
            ['Does a clean report guarantee platform acceptance?', 'No. It means the file is well-formed and meets the rules you set. Video platforms have their own requirements and can change them, so check their current guidelines too.'],
            ['Can the tool check subtitles against spoken audio?', 'No. It checks the file itself: format, timing and readability. It cannot hear the video, so it cannot tell whether the words or timing match what is said.'],
            ['Does the tool support ASS and SSA?', 'Not at the moment: it checks SRT and WebVTT only. Convert ASS or SSA files to SRT or WebVTT in your subtitle editor first; styling those formats cannot hold may be lost.'],
        ];
    }
}
