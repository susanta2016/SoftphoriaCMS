// Character tables for the Fancy Text Generator: explicit maps from an
// ordinary character to its styled replacement. Every table lists upper
// case, lower case and digits separately — never "add an offset and hope".
//
// The Mathematical Alphanumeric Symbols block (U+1D400–U+1D7FF) has holes
// where a letter was already encoded elsewhere (for example script B is
// U+212C, not U+1D49D); those letters come from the HOLES lists, which is
// why a plain range would print unassigned code points for them.
//
// Characters a table does not list are left exactly as they were.

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const lower = LETTERS.toLowerCase();
const DIGITS = '0123456789';
const cp = (n) => String.fromCodePoint(n);

// A table from code point starts, with exceptions.
function fromRanges({ upper = null, lower: lowerStart = null, digits = null, holes = {} }) {
    const table = {};
    for (let i = 0; i < 26; i++) {
        if (upper !== null) table[LETTERS[i]] = holes[LETTERS[i]] ?? cp(upper + i);
        if (lowerStart !== null) table[lower[i]] = holes[lower[i]] ?? cp(lowerStart + i);
    }
    if (digits !== null) for (let i = 0; i < 10; i++) table[DIGITS[i]] = cp(digits + i);
    return table;
}

// A table from space-separated lists (an entry may be several code points).
// `upper`/`lower` hold 26 entries, `digits` 10; '' or null skips a list.
function fromLists({ upper = '', lower: lowerList = '', digits = '', extra = {} }) {
    const table = {};
    const add = (keys, list) => {
        if (!list) return;
        const values = list.trim().split(/\s+/u);
        if (values.length !== keys.length) throw new Error(`Table needs ${keys.length} entries, got ${values.length}: ${list}`);
        values.forEach((value, i) => { if (value !== '_') table[keys[i]] = value; });
    };
    add(LETTERS, upper);
    add(lower, lowerList);
    add(DIGITS, digits);
    return { ...table, ...extra };
}

// --------------------------------------------------- mathematical alphabets

export const BOLD = fromRanges({ upper: 0x1d400, lower: 0x1d41a, digits: 0x1d7ce });
export const ITALIC = fromRanges({ upper: 0x1d434, lower: 0x1d44e, holes: { h: cp(0x210e) } });
export const BOLD_ITALIC = fromRanges({ upper: 0x1d468, lower: 0x1d482 });
export const SCRIPT = fromRanges({
    upper: 0x1d49c, lower: 0x1d4b6,
    holes: {
        B: cp(0x212c), E: cp(0x2130), F: cp(0x2131), H: cp(0x210b), I: cp(0x2110), L: cp(0x2112), M: cp(0x2133), R: cp(0x211b),
        e: cp(0x212f), g: cp(0x210a), o: cp(0x2134),
    },
});
export const BOLD_SCRIPT = fromRanges({ upper: 0x1d4d0, lower: 0x1d4ea });
export const FRAKTUR = fromRanges({
    upper: 0x1d504, lower: 0x1d51e,
    holes: { C: cp(0x212d), H: cp(0x210c), I: cp(0x2111), R: cp(0x211c), Z: cp(0x2128) },
});
export const DOUBLE_STRUCK = fromRanges({
    upper: 0x1d538, lower: 0x1d552, digits: 0x1d7d8,
    holes: { C: cp(0x2102), H: cp(0x210d), N: cp(0x2115), P: cp(0x2119), Q: cp(0x211a), R: cp(0x211d), Z: cp(0x2124) },
});
export const BOLD_FRAKTUR = fromRanges({ upper: 0x1d56c, lower: 0x1d586 });
export const SANS = fromRanges({ upper: 0x1d5a0, lower: 0x1d5ba, digits: 0x1d7e2 });
export const SANS_BOLD = fromRanges({ upper: 0x1d5d4, lower: 0x1d5ee, digits: 0x1d7ec });
export const SANS_ITALIC = fromRanges({ upper: 0x1d608, lower: 0x1d622 });
export const SANS_BOLD_ITALIC = fromRanges({ upper: 0x1d63c, lower: 0x1d656 });
export const MONOSPACE = fromRanges({ upper: 0x1d670, lower: 0x1d68a, digits: 0x1d7f6 });

// ------------------------------------------------------- enclosed alphabets

// Circled: Ⓐ U+24B6, ⓐ U+24D0, ① U+2460 (1–9), ⓪ U+24EA.
export const CIRCLED = {
    ...fromRanges({ upper: 0x24b6, lower: 0x24d0 }),
    ...Object.fromEntries([...'123456789'].map((d, i) => [d, cp(0x2460 + i)])),
    0: cp(0x24ea),
};

// Negative circled letters exist in capitals only (🅐 U+1F150), so lower
// case uses them too; digits ❶ U+2776 (1–9), ⓿ U+24FF.
const NEGATIVE_CIRCLED_LETTERS = fromRanges({ upper: 0x1f150, lower: 0x1f150 });
export const NEGATIVE_CIRCLED = {
    ...NEGATIVE_CIRCLED_LETTERS,
    ...Object.fromEntries([...'123456789'].map((d, i) => [d, cp(0x2776 + i)])),
    0: cp(0x24ff),
};

// Parenthesized: ⒜ U+249C (lower), 🄐 U+1F110 (upper), ⑴ U+2474 (1–9; no 0).
export const PARENTHESIZED = {
    ...fromRanges({ upper: 0x1f110, lower: 0x249c }),
    ...Object.fromEntries([...'123456789'].map((d, i) => [d, cp(0x2474 + i)])),
};

// Squared letters exist in capitals only: 🄰 U+1F130, negative 🅰 U+1F170.
export const SQUARED = fromRanges({ upper: 0x1f130, lower: 0x1f130 });
export const NEGATIVE_SQUARED = fromRanges({ upper: 0x1f170, lower: 0x1f170 });

// Regional indicator symbols 🇦 U+1F1E6. Two in a row can form a flag
// (U S → 🇺🇸), so the style puts a zero-width space after each one.
export const REGIONAL = fromRanges({ upper: 0x1f1e6, lower: 0x1f1e6 });

// Full-width forms: U+FF01–U+FF5E mirror ASCII U+0021–U+007E; the space
// becomes the ideographic space U+3000.
export const FULL_WIDTH = Object.fromEntries([
    ...Array.from({ length: 0x5e }, (_, i) => [cp(0x21 + i), cp(0xff01 + i)]),
    [' ', cp(0x3000)],
]);

// --------------------------------------------------- small letter alphabets

// Superscript: there is no superscript q, and capitals C F Q S X Y Z have
// none either, so those capitals use the lower-case superscript.
export const SUPERSCRIPT = fromLists({
    upper: 'ᴬ ᴮ ᶜ ᴰ ᴱ ᶠ ᴳ ᴴ ᴵ ᴶ ᴷ ᴸ ᴹ ᴺ ᴼ ᴾ _ ᴿ ˢ ᵀ ᵁ ⱽ ᵂ ˣ ʸ ᶻ',
    lower: 'ᵃ ᵇ ᶜ ᵈ ᵉ ᶠ ᵍ ʰ ⁱ ʲ ᵏ ˡ ᵐ ⁿ ᵒ ᵖ _ ʳ ˢ ᵗ ᵘ ᵛ ʷ ˣ ʸ ᶻ',
    digits: '⁰ ¹ ² ³ ⁴ ⁵ ⁶ ⁷ ⁸ ⁹',
    extra: { '+': '⁺', '-': '⁻', '=': '⁼', '(': '⁽', ')': '⁾' },
});

// Subscript letters exist for only 17 letters (no b c d f g q w y z).
export const SUBSCRIPT = fromLists({
    upper: 'ₐ _ _ _ ₑ _ _ ₕ ᵢ ⱼ ₖ ₗ ₘ ₙ ₒ ₚ _ ᵣ ₛ ₜ ᵤ ᵥ _ ₓ _ _',
    lower: 'ₐ _ _ _ ₑ _ _ ₕ ᵢ ⱼ ₖ ₗ ₘ ₙ ₒ ₚ _ ᵣ ₛ ₜ ᵤ ᵥ _ ₓ _ _',
    digits: '₀ ₁ ₂ ₃ ₄ ₅ ₆ ₇ ₈ ₉',
    extra: { '+': '₊', '-': '₋', '=': '₌', '(': '₍', ')': '₎' },
});

// Small capitals (IPA and phonetic letters). There is no widely supported
// small-capital Q or X: q uses ǫ, x stays x.
const SMALL_CAP_LIST = 'ᴀ ʙ ᴄ ᴅ ᴇ ꜰ ɢ ʜ ɪ ᴊ ᴋ ʟ ᴍ ɴ ᴏ ᴘ ǫ ʀ ꜱ ᴛ ᴜ ᴠ ᴡ x ʏ ᴢ';
export const SMALL_CAPS = fromLists({ lower: SMALL_CAP_LIST });
export const ALL_SMALL_CAPS = fromLists({ upper: SMALL_CAP_LIST, lower: SMALL_CAP_LIST });

// ----------------------------------------------------------- flips

// Upside down (rotated 180°). Letters with no rotated form (H I N O S X Z
// o s x z l) are symmetric and stay as they are. Q has no rotated form.
export const UPSIDE_DOWN = fromLists({
    upper: '∀ ꓭ Ɔ ꓷ Ǝ Ⅎ ⅁ H I ſ ꓘ ⅂ W N O Ԁ Q ꓤ S ꓕ ∩ Λ M X ⅄ Z',
    lower: 'ɐ q ɔ p ǝ ɟ ƃ ɥ ᴉ ɾ ʞ l ɯ u o d b ɹ s ʇ n ʌ ʍ x ʎ z',
    digits: '0 Ɩ ↊ ↋ ㄣ ϛ 9 ㄥ 8 6',
    extra: {
        '.': '˙', ',': '\'', '\'': ',', '"': '„', '?': '¿', '!': '¡', '(': ')', ')': '(', '[': ']', ']': '[',
        '{': '}', '}': '{', '<': '>', '>': '<', '&': '⅋', '_': '‾', '‿': '⁀', '⁀': '‿',
    },
});

// Mirrored (flipped left to right). Only characters with a convincing
// mirror image are replaced.
export const MIRRORED = fromLists({
    upper: 'A ᙠ Ɔ ᗡ Ǝ ꟻ Ꭾ H I Ⴑ ⋊ ⅃ M И O ꟼ Ọ Я Ꙅ T U V W X Y Ƹ',
    lower: 'ɒ d ɔ b ɘ ʇ ǫ ʜ i ꞁ ʞ l m n o q p ɿ ꙅ ƚ u v w x ʏ ƹ',
    digits: '0 1 _ Ɛ _ _ _ _ 8 _',
    extra: { '?': '⸮', '(': ')', ')': '(', '[': ']', ']': '[', '{': '}', '}': '{', '<': '>', '>': '<', '/': '\\', '\\': '/' },
});

// ---------------------------------------------------- look-alike alphabets
// Letters borrowed from other scripts because they resemble Latin ones.
// Only left-to-right characters are used, so the text keeps its order.

export const LOOKALIKES = {
    cyrillic: fromLists({
        upper: 'Д Б Ҁ ↁ Є F Ǥ Н І Ј Ќ L М И Ф Р Q Я Ѕ Г Ц V Щ Ж Ч Z',
        lower: 'а б с ↁ є f ǥ н і ј к l м и ф р q я ѕ т ц ѵ ш ж ч z',
    }),
    greek: fromLists({
        upper: 'Λ Ɓ Ͼ Ɗ Ξ Ƒ Ɠ Ή Ί Ɉ Ҡ Ŀ Μ Π Θ Ƥ Ɋ Я Ƨ Ƭ Ʊ Ѵ Ѡ Χ Ұ Ζ',
        lower: 'α в ¢ ∂ є ƒ g н ι ʝ к ℓ м η σ ρ q я ѕ т υ ν ω χ у z',
    }),
    japanese: (() => {
        const list = '卂 乃 匚 ᗪ 乇 千 Ꮆ 卄 丨 ﾌ Ҝ ㄥ 爪 几 ㄖ 卩 Ɋ 尺 丂 ㄒ ㄩ ᐯ 山 乂 ㄚ 乙';
        return fromLists({ upper: list, lower: list });
    })(),
    canadian: (() => {
        const list = 'ᗩ ᗷ ᑕ ᗪ E ᖴ G ᕼ I ᒍ K ᒪ ᗰ ᑎ O ᑭ ᑫ ᖇ ᔕ T ᑌ ᐯ ᗯ ᙭ Y ᘔ';
        return fromLists({ upper: list, lower: list });
    })(),
    squiggle: (() => {
        const list = 'ค ๒ ς ๔ є Ŧ ɠ ђ เ ʝ к ɭ ๓ ภ ๏ ρ ợ г ร Շ ย ν ฬ ҳ ყ չ';
        return fromLists({ upper: list, lower: list });
    })(),
    currency: (() => {
        const list = '₳ ฿ ₵ Đ Ɇ ₣ ₲ Ⱨ ł J ₭ Ⱡ ₥ ₦ Ø ₱ Q Ɽ ₴ ₮ Ʉ V ₩ Ӿ Ɏ Ⱬ';
        return fromLists({ upper: list, lower: list });
    })(),
    yi: (() => {
        const list = 'ꍏ ꌃ ꏳ ꀷ ꏂ ꎇ ꁅ ꀍ ꀤ ꒻ ꀘ ꒒ ꎭ ꈤ ꂦ ᖘ ꆰ ꋪ ꌚ ꓄ ꀎ ꒦ ꅐ ꉧ ꌩ ꁴ';
        return fromLists({ upper: list, lower: list });
    })(),
    ethiopic: (() => {
        const list = 'ል ጌ ር ዕ ቿ ቻ ኗ ዘ ጎ ጋ ጕ ረ ጠ ክ ዐ የ ዒ ዪ ነ ፕ ሁ ሀ ሠ ሸ ሃ ጊ';
        return fromLists({ upper: list, lower: list });
    })(),
    cherokee: (() => {
        const list = 'Ꮧ Ᏸ ፈ Ꮄ Ꮛ Ꭶ Ꮆ Ꮒ Ꭵ Ꮰ Ꮶ Ꮭ Ꮇ Ꮑ Ꭷ Ꭾ Ꭴ Ꮢ Ꮥ Ꮦ Ꮼ Ꮙ Ꮗ ጀ Ꭹ ፚ';
        return fromLists({ upper: list, lower: list });
    })(),
    hooked: (() => {
        const list = 'ą ɓ ƈ ɖ ɛ ʄ ɠ ɧ ı ʝ ƙ Ɩ ɱ ŋ ơ ℘ զ ɾ ʂ ɬ ų ʋ ῳ ҳ ყ ʑ';
        return fromLists({ upper: list, lower: list });
    })(),
    stroked: fromLists({
        upper: 'Ⱥ Ƀ Ȼ Đ Ɇ F Ǥ Ħ Ɨ Ɉ Ꝁ Ł M N Ø Ᵽ Ꝗ Ɍ S Ŧ Ʉ V W X Ɏ Ƶ',
        lower: 'ⱥ ƀ ȼ đ ɇ f ǥ ħ ɨ ɉ ꝁ ł m n ø ᵽ ꝗ ɍ s ŧ ᵾ v w x ɏ ƶ',
    }),
    accented: fromLists({
        upper: 'À Ɓ Ć Ď Ě Ƒ Ğ Ĥ Ĩ Ĵ Ķ Ĺ M Ń Ő Ƥ Ǫ Ř Ś Ť Ũ Ʋ Ŵ X Ŷ Ź',
        lower: 'à ɓ ć ď ě ƒ ğ ĥ ĩ ĵ ķ ĺ m ń ő ƥ ǫ ř ś ť ũ ʋ ŵ x ŷ ź',
    }),
    rockDots: fromLists({
        upper: 'Ä Ḅ Ċ Ḋ Ë Ḟ Ġ Ḧ Ï J Ḳ Ḷ Ṁ Ṅ Ö Ṗ Q Ṛ Ṡ Ṫ Ü Ṿ Ẅ Ẍ Ÿ Ż',
        lower: 'ä ḅ ċ ḋ ë ḟ ġ ḧ ï j ḳ ḷ ṁ ṅ ö ṗ q ṛ ṡ ẗ ü ṿ ẅ ẍ ÿ ż',
    }),
};

// Leetspeak stays within ASCII, so it works where Unicode is rejected.
export const LEET = fromLists({
    upper: '4 8 C D 3 F 6 H 1 J K L M N 0 P Q R 5 7 U V W X Y 2',
    lower: '4 8 c d 3 f 6 h 1 j k l m n 0 p q r 5 7 u v w x y 2',
});
