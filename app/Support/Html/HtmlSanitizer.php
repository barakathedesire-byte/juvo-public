<?php

namespace App\Support\Html;

/**
 * Allowlist HTML sanitizer for user-authored rich content (product
 * descriptions, page bodies, category copy). Rich formatting is preserved for
 * a fixed set of block/inline tags, attributes and URL schemes; everything
 * else — scripts, event handlers, embedded objects, javascript:/data: URLs —
 * is removed. Parsing is done with DOMDocument rather than regex so that
 * malformed markup cannot smuggle a payload past the filter.
 */
class HtmlSanitizer
{
    /** Tags kept in output, mapped to the attributes they may carry. */
    protected const ALLOWED = [
        'a' => ['href', 'title', 'target', 'rel'],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [],
        'p' => ['style'], 'br' => [], 'hr' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => ['cite'],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'span' => ['style'], 'div' => ['style'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'pre' => [], 'code' => [],
        'sub' => [], 'sup' => [],
    ];

    /** Allowed URL schemes (case-insensitive) for href/src. */
    protected const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Allowed CSS properties in inline style attributes. */
    protected const ALLOWED_STYLE = [
        'text-align', 'font-weight', 'font-style', 'text-decoration', 'color',
        'background-color', 'width', 'height', 'max-width', 'padding', 'margin',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        if (! class_exists(\DOMDocument::class)) {
            return self::fallback($html);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.'<div id="__juvo_root__">'.$html.'</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__juvo_root__');

        if (! $root) {
            return self::fallback($html);
        }

        self::scrub($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    protected static function scrub(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof \DOMElement) {
                self::scrub($child);

                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::unwrap($child);

                continue;
            }

            self::scrubAttributes($child, $tag);
            self::scrub($child);
        }
    }

    protected static function scrubAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue;

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'style') {
                $clean = self::cleanStyle($value);
                $clean === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $clean);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::safeUrl($value)) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        if ($tag === 'a') {
            $target = strtolower((string) $el->getAttribute('target'));
            if ($target === '_blank') {
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    protected static function cleanStyle(string $style): string
    {
        $out = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$prop, $val] = explode(':', $declaration, 2);
            $prop = strtolower(trim($prop));
            $val = trim($val);

            if ($prop === '' || ! in_array($prop, self::ALLOWED_STYLE, true)) {
                continue;
            }

            if (preg_match('/url\s*\(|expression\s*\(|javascript:/i', $val)) {
                continue;
            }

            $out[] = $prop.': '.$val;
        }

        return implode('; ', $out);
    }

    protected static function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return true;
        }

        // Strip characters browsers ignore, then look for a scheme.
        $probe = preg_replace('/[\x00-\x20\x7f]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)) {
            $scheme = strtolower($m[1]);

            if ($scheme === 'data' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url)) {
                return true;
            }

            return in_array($scheme, self::ALLOWED_SCHEMES, true);
        }

        return true;
    }

    /** Replace a disallowed element with its (cleaned) children. */
    protected static function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;

        if (! $parent) {
            return;
        }

        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }

    /** Regex fallback used only when DOM is unavailable. */
    protected static function fallback(string $html): string
    {
        $html = preg_replace('#<\s*(script|style|object|embed|iframe|link|meta|svg|form)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        $html = preg_replace('#<\s*(script|style|object|embed|iframe|link|meta|svg|form)\b[^>]*/?>#is', '', (string) $html);
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html);
        $html = preg_replace('/(javascript|vbscript|data)\s*:/i', '', (string) $html);

        return trim((string) $html);
    }
}
