<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Converts ordinary text into decorative Unicode styles (bold, script,
 * Fraktur, bubble, upside down, Zalgo and more) with copy buttons,
 * favourites and a symbol library. Runs entirely in the browser
 * (resources/js/tools/fancy-text-generator/): the visitor's text is never
 * sent to the server, stored or included in analytics. Favourites are kept
 * in the visitor's own browser (localStorage) as style ids only.
 *
 * The symbol library is rendered here, server-side, so it is visible and
 * selectable without JavaScript; the script adds copy and search.
 */
class FancyTextGenerator extends ToolFunctionality
{
    public const KEY = 'fancy-text-generator';

    public const NAME = 'Fancy Text Generator';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Turns text into 100+ decorative Unicode styles to copy and paste, with favourites, platform presets and a symbol library.';

    public const APPLICATION_CATEGORY = 'DesignApplication';

    public function viewData(): array
    {
        return ['symbolGroups' => self::symbols()];
    }

    /**
     * The symbol library: group id => [label, list of [symbol, name]].
     * Names are what the symbol search matches and what screen readers
     * announce on the copy button.
     *
     * @return array<string, array{0: string, 1: list<array{0: string, 1: string}>}>
     */
    public static function symbols(): array
    {
        return [
            'hearts' => ['Hearts', [
                ['♥', 'Black heart'], ['♡', 'White heart'], ['❤', 'Heavy heart'], ['❥', 'Rotated heart bullet'], ['❣', 'Heart exclamation'],
                ['❦', 'Floral heart'], ['❧', 'Rotated floral heart'], ['ღ', 'Heart-shaped letter'], ['💕', 'Two hearts'], ['💖', 'Sparkling heart'],
                ['💘', 'Heart with arrow'], ['💗', 'Growing heart'], ['💞', 'Revolving hearts'], ['💜', 'Purple heart'], ['🖤', 'Black heart emoji'],
                ['🤍', 'White heart emoji'], ['💙', 'Blue heart'], ['💚', 'Green heart'],
            ]],
            'stars' => ['Stars', [
                ['★', 'Black star'], ['☆', 'White star'], ['✦', 'Four-pointed star'], ['✧', 'White four-pointed star'], ['✩', 'Outlined star'],
                ['✪', 'Circled star'], ['✫', 'Open centre star'], ['✬', 'Black centre star'], ['✭', 'Outlined black star'], ['✮', 'Heavy outlined star'],
                ['✯', 'Pinwheel star'], ['✰', 'Shadowed star'], ['⋆', 'Star operator'], ['✶', 'Six-pointed star'], ['✷', 'Eight-pointed star'],
                ['✸', 'Heavy eight-pointed star'], ['✹', 'Twelve-pointed star'], ['⁂', 'Asterism'], ['⭐', 'Star emoji'], ['🌟', 'Glowing star'],
                ['✨', 'Sparkles'], ['💫', 'Dizzy star'],
            ]],
            'flowers' => ['Flowers', [
                ['✿', 'Black florette'], ['❀', 'White florette'], ['❁', 'Eight-petal florette'], ['✾', 'Six-petal florette'], ['❃', 'Teardrop florette'],
                ['❋', 'Propeller asterisk'], ['✽', 'Teardrop asterisk'], ['✻', 'Teardrop-spoked asterisk'], ['❊', 'Eight teardrop asterisk'], ['⚘', 'Flower'],
                ['❦', 'Floral heart'], ['🌸', 'Cherry blossom'], ['🌺', 'Hibiscus'], ['🌷', 'Tulip'], ['🌹', 'Rose'], ['🌻', 'Sunflower'],
                ['🌼', 'Blossom'], ['💮', 'White flower'], ['☘', 'Shamrock'], ['🍀', 'Four-leaf clover'],
            ]],
            'arrows' => ['Arrows', [
                ['→', 'Right arrow'], ['←', 'Left arrow'], ['↑', 'Up arrow'], ['↓', 'Down arrow'], ['↔', 'Left-right arrow'], ['↕', 'Up-down arrow'],
                ['⇒', 'Double right arrow'], ['⇐', 'Double left arrow'], ['⇔', 'Double left-right arrow'], ['➜', 'Heavy round-tipped arrow'],
                ['➤', 'Arrowhead'], ['➳', 'Feathered arrow'], ['➵', 'Black feathered arrow'], ['➶', 'Up-right feathered arrow'], ['➷', 'Down-right feathered arrow'],
                ['↩', 'Return arrow'], ['↪', 'Forward return arrow'], ['⤴', 'Curving up arrow'], ['⤵', 'Curving down arrow'], ['↻', 'Clockwise arrow'],
                ['↺', 'Anticlockwise arrow'], ['⟶', 'Long right arrow'], ['⟵', 'Long left arrow'], ['⇢', 'Dashed right arrow'],
            ]],
            'decorations' => ['Decorations', [
                ['꧁', 'Javanese left ornament'], ['꧂', 'Javanese right ornament'], ['༺', 'Tibetan left ornament'], ['༻', 'Tibetan right ornament'],
                ['☙', 'Reversed floral heart'], ['❖', 'Diamond with cross'], ['◈', 'Diamond in diamond'], ['◇', 'White diamond'], ['◆', 'Black diamond'],
                ['❈', 'Heavy sparkle'], ['⚜', 'Fleur-de-lis'], ['✥', 'Four club-spoked asterisk'], ['ꕥ', 'Vai flower'], ['ꕤ', 'Vai decoration'],
                ['୨୧', 'Ribbon'], ['ʚɞ', 'Butterfly wings'], ['⊹', 'Sparkle cross'], ['❝', 'Opening quote'], ['❞', 'Closing quote'], ['〖〗', 'Lenticular brackets'],
                ['⋆｡°✩', 'Aesthetic stars'], ['✧･ﾟ: *✧･ﾟ:*', 'Sparkle trail'], ['｡･:*:･ﾟ★', 'Star trail'],
            ]],
            'lines' => ['Lines and dividers', [
                ['━━━━━━━━━━', 'Heavy line'], ['──────────', 'Thin line'], ['══════════', 'Double line'], ['┈┈┈┈┈┈┈┈┈┈', 'Dotted line'],
                ['▬▬▬▬▬▬▬▬', 'Bar line'], ['﹏﹏﹏﹏﹏﹏', 'Wavy line'], ['•┈┈┈┈┈┈┈┈•', 'Dotted line with ends'], ['⊱ ──────── ⊰', 'Bracketed line'],
                ['✦•┈┈┈┈┈┈•✦', 'Sparkle divider'], ['≪ ◦ ❖ ◦ ≫', 'Ornament divider'], ['꒷꒦꒷꒦꒷꒦꒷꒦', 'Scalloped divider'], ['❀•°•═════•°•❀', 'Flower divider'],
                ['│', 'Vertical line'], ['║', 'Double vertical line'], ['┊', 'Dotted vertical line'],
            ]],
            'music' => ['Music symbols', [
                ['♩', 'Quarter note'], ['♪', 'Eighth note'], ['♫', 'Beamed eighth notes'], ['♬', 'Beamed sixteenth notes'], ['♭', 'Flat'], ['♮', 'Natural'],
                ['♯', 'Sharp'], ['𝄞', 'Treble clef'], ['𝄢', 'Bass clef'], ['🎵', 'Musical note'], ['🎶', 'Musical notes'], ['🎧', 'Headphones'],
                ['🎤', 'Microphone'], ['🎸', 'Guitar'], ['🎹', 'Piano keyboard'], ['🥁', 'Drum'], ['🎷', 'Saxophone'], ['🎻', 'Violin'],
            ]],
            'gaming' => ['Gaming symbols', [
                ['⚔', 'Crossed swords'], ['🗡', 'Dagger'], ['🛡', 'Shield'], ['☠', 'Skull and crossbones'], ['💀', 'Skull'], ['👑', 'Crown'],
                ['♛', 'Black queen'], ['♚', 'Black king'], ['♜', 'Black rook'], ['♞', 'Black knight'], ['🎮', 'Game controller'], ['🕹', 'Joystick'],
                ['🏆', 'Trophy'], ['🎯', 'Target'], ['⚡', 'Lightning'], ['🔥', 'Fire'], ['💎', 'Gem'], ['♠', 'Spade'], ['♣', 'Club'], ['♦', 'Diamond suit'],
                ['✠', 'Maltese cross'], ['⚑', 'Flag'],
            ]],
            'math' => ['Mathematical symbols', [
                ['±', 'Plus-minus'], ['×', 'Multiplication'], ['÷', 'Division'], ['≈', 'Almost equal'], ['≠', 'Not equal'], ['≤', 'Less than or equal'],
                ['≥', 'Greater than or equal'], ['∞', 'Infinity'], ['√', 'Square root'], ['∑', 'Sum'], ['∏', 'Product'], ['∫', 'Integral'],
                ['∂', 'Partial derivative'], ['∆', 'Increment'], ['π', 'Pi'], ['∈', 'Element of'], ['∉', 'Not an element of'], ['∩', 'Intersection'],
                ['∪', 'Union'], ['⊂', 'Subset'], ['∀', 'For all'], ['∃', 'There exists'], ['∅', 'Empty set'], ['∴', 'Therefore'], ['∵', 'Because'],
                ['°', 'Degree'], ['‰', 'Per mille'], ['²', 'Superscript two'], ['³', 'Superscript three'], ['½', 'One half'], ['¼', 'One quarter'], ['¾', 'Three quarters'],
            ]],
            'currency' => ['Currency symbols', [
                ['$', 'Dollar'], ['€', 'Euro'], ['£', 'Pound'], ['¥', 'Yen and yuan'], ['₹', 'Indian rupee'], ['₩', 'Won'], ['₽', 'Ruble'], ['₺', 'Turkish lira'],
                ['₦', 'Naira'], ['₱', 'Peso'], ['฿', 'Baht'], ['₫', 'Dong'], ['₴', 'Hryvnia'], ['₪', 'Shekel'], ['¢', 'Cent'], ['₿', 'Bitcoin'], ['₡', 'Colón'], ['₭', 'Kip'],
            ]],
            'smileys' => ['Smileys', [
                ['☺', 'White smiling face'], ['☻', 'Black smiling face'], ['ツ', 'Katakana smile'], ['㋡', 'Circled smile'], ['☹', 'Frowning face'],
                ['😊', 'Smiling face'], ['😂', 'Tears of joy'], ['🥰', 'Smiling face with hearts'], ['😍', 'Heart eyes'], ['😎', 'Sunglasses'],
                ['🤩', 'Star-struck'], ['😇', 'Halo'], ['🥺', 'Pleading face'], ['😭', 'Crying face'], ['🙃', 'Upside-down face'], ['😴', 'Sleeping face'],
                ['🤔', 'Thinking face'], ['✌', 'Victory hand'],
            ]],
            'kaomoji' => ['Kaomoji', [
                ['(◕‿◕)', 'Happy'], ['ʕ•ᴥ•ʔ', 'Bear'], ['(ᵔᴥᵔ)', 'Cute bear'], ['¯\_(ツ)_/¯', 'Shrug'], ['(╯°□°)╯︵ ┻━┻', 'Table flip'],
                ['┬─┬ノ( º _ ºノ)', 'Table put back'], ['(づ｡◕‿‿◕｡)づ', 'Hug'], ['(｡♥‿♥｡)', 'In love'], ['(≧◡≦)', 'Delighted'], ['(ノ◕ヮ◕)ノ*:･ﾟ✧', 'Excited sparkles'],
                ['ಠ_ಠ', 'Disapproval'], ['(•_•)', 'Blank stare'], ['(⌐■_■)', 'Cool sunglasses'], ['(=^･ω･^=)', 'Cat'], ['(っ˘ω˘ς )', 'Cosy'],
                ['✧◝(⁰▿⁰)◜✧', 'Amazed'], ['( ˘ ³˘)♥', 'Kiss'], ['(ᗒᗣᗕ)՞', 'Sobbing'],
            ]],
        ];
    }
}
