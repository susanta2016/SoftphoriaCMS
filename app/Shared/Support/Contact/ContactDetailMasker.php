<?php

namespace App\Shared\Support\Contact;

/**
 * Keeps the site's contact email/phone/WhatsApp number out of the public
 * HTML. Pages render only a partial, masked form (e.g. "co•••••@•••••ia.com",
 * "+91 91••••••94") that is useless to harvesters; the real value is fetched
 * on demand through ContactController::reveal() — a CSRF-protected,
 * JS-only, throttled POST — and turned into a link in the browser. See
 * resources/views/components/site/contact-info.blade.php.
 *
 * The mask never reflects the hidden part's real length.
 */
class ContactDetailMasker
{
    public const CHANNELS = ['email', 'phone', 'whatsapp'];

    private const DOTS = '•••••';

    public static function mask(string $channel, string $value): string
    {
        return match ($channel) {
            'email' => self::maskEmail($value),
            'whatsapp' => self::maskPhone('+'.self::digits($value)),
            default => self::maskPhone($value),
        };
    }

    /**
     * @return array{display: string, href: string}
     */
    public static function reveal(string $channel, string $value): array
    {
        $value = trim($value);

        return match ($channel) {
            'email' => ['display' => $value, 'href' => 'mailto:'.$value],
            'whatsapp' => ['display' => '+'.self::digits($value), 'href' => 'https://wa.me/'.self::digits($value)],
            default => [
                'display' => $value,
                'href' => 'tel:'.(str_starts_with(ltrim($value), '+') ? '+' : '').self::digits($value),
            ],
        };
    }

    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false) {
            return self::DOTS;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $dot = strrpos($domain, '.');
        $name = $dot === false ? $domain : substr($domain, 0, $dot);
        $tld = $dot === false ? '' : substr($domain, $dot);

        $maskedLocal = mb_substr($local, 0, mb_strlen($local) > 2 ? 2 : 1).self::DOTS;
        $maskedName = self::DOTS.(mb_strlen($name) > 3 ? mb_substr($name, -2) : '');

        return $maskedLocal.'@'.$maskedName.$tld;
    }

    /**
     * Keeps the separators, the first 4 digits (country/area code) and the
     * last 2; every digit in between collapses into a fixed-width mask.
     */
    public static function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        $total = strlen(self::digits($phone));
        $keepHead = $total > 8 ? 4 : 0;
        $keepTail = min(2, $total);

        $out = '';
        $seen = 0;
        $masked = false;

        foreach (str_split($phone) as $char) {
            if (! ctype_digit($char)) {
                // Separators inside the hidden run would leak its shape.
                if (! $masked || $seen >= $total - $keepTail) {
                    $out .= $char;
                }

                continue;
            }

            if ($seen < $keepHead || $seen >= $total - $keepTail) {
                $out .= $char;
            } elseif (! $masked) {
                $out .= self::DOTS;
                $masked = true;
            }

            $seen++;
        }

        return $out;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
