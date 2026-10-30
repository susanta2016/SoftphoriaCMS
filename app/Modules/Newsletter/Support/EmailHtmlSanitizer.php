<?php

namespace App\Modules\Newsletter\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Strips anything executable from an email HTML document, while keeping
 * what email layouts need (tables, inline styles, <style> blocks, MSO
 * conditional comments): script/iframe/object/embed/form and friends,
 * every on* event attribute, javascript:/vbscript:/data: URLs (bar inline
 * images) and CSS expression()/javascript: in style attributes. Admins are
 * trusted to design emails, not to run code in recipients' mail clients or
 * in other admins' browsers (the preview).
 */
final class EmailHtmlSanitizer
{
    private const REMOVED_ELEMENTS = [
        'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form',
        'input', 'textarea', 'select', 'button', 'base', 'link', 'noscript', 'portal',
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'background', 'poster', 'xlink:href', 'srcset', 'lowsrc', 'dynsrc'];

    public static function sanitizeDocument(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (self::REMOVED_ELEMENTS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//meta[@http-equiv]')) as $meta) {
            $meta->parentNode?->removeChild($meta);
        }

        /** @var DOMElement $element */
        foreach (iterator_to_array($xpath->query('//*[@*]')) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = mb_strtolower($attribute->nodeName);
                $value = (string) $attribute->nodeValue;

                if (str_starts_with($name, 'on')
                    || (in_array($name, self::URL_ATTRIBUTES, true) && self::isDangerousUrl($value, $name, $element->tagName))
                    || ($name === 'style' && preg_match('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', $value))) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }
    }

    private static function isDangerousUrl(string $value, string $attribute, string $tag): bool
    {
        // Browsers ignore embedded whitespace/control characters in a
        // scheme ("java\tscript:"), so compare without them.
        $normalized = mb_strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
            return true;
        }

        if (str_starts_with($normalized, 'data:')) {
            return ! ($attribute === 'src' && mb_strtolower($tag) === 'img' && preg_match('#^data:image/(png|gif|jpe?g|webp);#', $normalized));
        }

        return false;
    }
}
