<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\FancyTextGenerator;
use Illuminate\Database\Seeder;

/**
 * The Fancy Text Generator's landing page (Admin → Tools) as a DRAFT:
 * content, SEO, FAQ, related tools and CTA for /tools/fancy-text-generator.
 * It is never published here — review it, then publish from the admin.
 *
 * Wording rules: describe only what resources/js/tools/fancy-text-generator
 * implements; no promise that a platform accepts or displays a style; the
 * style count must stay true (FancyTextGeneratorToolTest checks it against
 * the engine); privacy claims match the code (browser-only conversion,
 * favourites as style ids in localStorage, analytics only with consent and
 * never containing the text). Related tools: only ones that exist. No
 * related service: the sidebar would narrow the long content panel.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class FancyTextGeneratorToolSeeder extends Seeder
{
    public const SLUG = 'fancy-text-generator';

    public const RELATED = ['image-requirements-checker', 'utm-builder', 'social-video-safe-zone-checker'];

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Fancy Text Generator already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Fancy Text Generator',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'marketing')->value('id'),
            'functionality' => FancyTextGenerator::KEY,
            'icon' => 'sparkles',
            'short_description' => 'Turn ordinary text into 100+ copy-and-paste styles: bold, cursive, gothic, bubble, small caps, upside down and more, plus a library of symbols.',
            'heading' => 'Fancy Text Generator – Copy and Paste Stylish Fonts',
            'introduction' => 'Type once and see your words in more than 100 stylish Unicode styles, from bold and cursive to gothic, bubble and upside-down text. Copy any style with one click and paste it into Instagram, TikTok, WhatsApp, Discord or wherever you write. Free, with no sign-up, and your text never leaves your browser.',
            'how_it_works' => null,
            'additional_content' => <<<'HTML'
<h2>What Is a Fancy Text Generator?</h2>
<p>A fancy text generator swaps the ordinary letters you type for other characters from the Unicode standard that happen to look like styled letters. Unicode is the catalogue of characters every modern phone and computer shares, and it contains far more than the alphabet on your keyboard: there are complete bold, italic, script, Fraktur and double-struck alphabets made for mathematics, letters inside circles and squares, small capitals and superscript letters used in phonetics, and full-width letters from East Asian typography.</p>
<p>Because each styled letter is a real character rather than formatting, it survives copy and paste. Places that strip bold and italic, such as most social media bios, still show the styled characters, provided the device reading them has a font that includes them.</p>
<h2>How to Use the Fancy Text Generator</h2>
<ol>
<li>Type or paste your text into the box at the top. Every style updates as you type.</li>
<li>Browse the categories, search for a style such as <em>cursive</em> or <em>tiny</em>, or choose a platform to see suggested styles.</li>
<li>Select <strong>Copy</strong> on the style you like, then paste it where you need it. <strong>Copy all</strong> copies every style currently shown.</li>
<li>Select the star on styles you use often. They appear under <strong>Favourites</strong> next time, saved in your own browser.</li>
<li>Need a symbol? Scroll to the symbol library and select any heart, star, arrow, divider or kaomoji to copy it.</li>
</ol>
<h2>Where Can You Use Fancy Text?</h2>
<ul>
<li><strong>Instagram.</strong> Bios, captions and comments accept styled characters. Keep your name and username in plain letters, because people search for you by them.</li>
<li><strong>Facebook.</strong> Posts and comments display it. Profile names must use standard characters, so a styled name is usually rejected.</li>
<li><strong>TikTok.</strong> Use it in your bio and display name. Keep the words you want to be found for in plain letters in captions.</li>
<li><strong>WhatsApp.</strong> Styled text works in messages, status and your About text. For simple emphasis, WhatsApp's own *bold* and _italic_ formatting reads better with screen readers.</li>
<li><strong>Discord.</strong> Popular for nicknames, server names and channel names. Messages already support bold, italic and underline formatting.</li>
<li><strong>Gaming profiles.</strong> Some games accept Unicode names; many allow only letters, numbers and a few symbols. Leetspeak, Gamer Tag and Alternating Caps use ordinary keyboard characters, so they are the safest choice.</li>
</ul>
<p>Pair a styled bio with a profile photo and cover image at the right size using the <a href="/tools/image-requirements-checker">Image Requirements Checker</a>, and track the link in your bio with the <a href="/tools/utm-builder">UTM Builder</a>.</p>
<h2>Are Fancy Fonts Real Fonts?</h2>
<p>No. A font is a file installed on a device that decides how ordinary letters are drawn, and you cannot send a font with a social media post. Fancy text changes the characters themselves: 𝐀 (mathematical bold capital A) is a different character from A, the way é is different from e. Your device draws it with whatever font it has that contains that character.</p>
<p>That is why fancy text works almost anywhere you can paste text, and also why it cannot be edited like formatted text: to change the style you convert the original words again. If you need a real typeface for a website or a design, use a font file instead.</p>
<h2>Why Does Fancy Text Sometimes Display as Boxes?</h2>
<p>An empty box, a question mark or a blank space appears when the device showing the text has no font containing that character. Newer additions to Unicode and rare blocks are the most likely to be missing on older phones, older versions of Windows and some smart TVs and game consoles. The same text can look perfect on your phone and broken on someone else's.</p>
<p>Some apps also filter or normalise unusual characters, turning them back into plain letters or removing them, and some count them as two characters towards a length limit. The bold, italic, script and double-struck styles are among the most widely supported. If it matters, paste your text and look at it on another device before you publish.</p>
<h2>Is Fancy Text Accessible?</h2>
<p>Not very. Screen readers read characters, not their appearance, so a styled word may be read letter by letter as "mathematical bold capital H…", read as a different language, or skipped entirely. Upside-down, mirrored and Zalgo text are the hardest to understand, and look-alike styles borrow letters from other alphabets, which can be read out as those languages. Search engines and the platforms' own search may not match styled words either.</p>
<p>Use fancy text as decoration, for a name, a short heading or a few words, and keep important information in ordinary text: prices, dates, contact details, instructions and anything a visitor must be able to read. Never use it for alt text, link text or form labels.</p>
<h2>How This Tool Handles Unicode</h2>
<p>Each style uses an explicit character map, including the letters that sit outside their alphabet's main block in Unicode, such as script ℬ and Fraktur ℭ. Characters a style has no version of, such as accented letters, other alphabets and most punctuation, are kept as they are, and emoji, flags and accents are never split apart. A few honest limits:</p>
<ul>
<li>Some alphabets are incomplete. There is no superscript Q, subscript exists for only 17 letters, and squared and black-bubble letters exist only as capitals.</li>
<li>Reversed, mirrored and upside-down text work character by character. Only characters with a convincing flipped look-alike change, and right-to-left scripts such as Arabic and Hebrew do not reverse meaningfully.</li>
<li>Zalgo adds stacked combining marks. Its intensity is adjustable and its length is capped, but some apps trim or block it.</li>
</ul>
HTML,
            'important_notes' => <<<'HTML'
<p>Your text is converted in your browser; it is not uploaded, stored or included in analytics. Favourites are saved only as style names in your own browser. The page itself loads normally and, if you accept analytics cookies, records anonymous events such as a copy, never the text.</p>
<p>Whether a style displays correctly depends on the app and device that shows it. Platforms can filter, normalise or reject unusual characters, so no style is guaranteed to work everywhere.</p>
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
            'meta_title' => 'Fancy Text Generator – 100+ Stylish Fonts to Copy & Paste',
            'meta_description' => 'Create 100+ fancy text styles: bold, cursive, gothic, bubble and aesthetic fonts. Copy and paste into Instagram, TikTok, WhatsApp and more. Free, no signup.',
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

        $this->command?->info('Fancy Text Generator created as a draft. Review and publish it in Admin → Tools.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function faqs(): array
    {
        return [
            ['Is this Fancy Text Generator free?', 'Yes. Every style, the symbol library and favourites are free to use, with no account, no sign-up and no limit on how often you use them.'],
            ['Can I use fancy text on Instagram?', 'Yes, in your bio, captions, comments and display name. Keep your username and the words people search for in plain letters, because Instagram search may not match styled characters, and check the result on another phone if it matters.'],
            ['Does this tool work on Android and iPhone?', 'Yes. The generator runs in any modern mobile or desktop browser. How the copied text looks depends on the fonts of the phone that displays it: recent Android and iOS versions show most styles, while older devices may show boxes for rarer characters.'],
            ['Can I copy and paste fancy fonts into WhatsApp?', 'Yes. Copy a style here and paste it into a chat, your status or your About text. For simple bold or italic, WhatsApp\'s built-in formatting (*bold*, _italic_, ~strikethrough~) is easier for screen readers.'],
            ['Are these real fonts?', 'No. They are Unicode characters that look like styled letters, such as the mathematical bold or script alphabets. That is why they survive copy and paste into apps that do not let you choose a font, and also why you cannot install them like a font file.'],
            ['Can I use fancy text in gaming usernames?', 'Sometimes. Many games only accept letters, numbers and a few symbols in names, and some filter unusual characters. Choose the Gaming Usernames preset: Leetspeak, Gamer Tag and Alternating Caps use ordinary keyboard characters, so they are the most likely to be accepted.'],
            ['Why are some characters displaying as boxes?', 'The device showing the text has no font containing that character. It is most common with rarer styles on older phones, computers, TVs and consoles. Try a widely supported style such as bold, italic, script or double-struck.'],
            ['Is my text uploaded to a server?', 'No. Your text is converted in your browser and is not sent to our server, stored or included in analytics. Favourites are saved as style names in your own browser only, and you can clear them by removing the star or clearing your browser data.'],
        ];
    }
}
