<?php

namespace Modules\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Nettoie le HTML libre saisi dans un bloc d'information : balises de mise en forme uniquement,
 * pas de scripts, d'attributs d'événements ni de liens « javascript: ».
 */
class HtmlSanitizer
{
    private const TAGS = ['b', 'strong', 'i', 'em', 'u', 'br', 'hr', 'p', 'small', 'span', 'div', 'ul', 'ol', 'li', 'a', 'sup', 'sub', 'h1', 'h2', 'h3'];

    private const ATTRIBUTES = ['style', 'class', 'href', 'title'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('root');
        if (! $root) {
            return e($html);
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (! in_array(strtolower($child->tagName), self::TAGS, true)) {
                // Balise inconnue : on garde son texte (sauf script/style), pas la balise.
                if (in_array(strtolower($child->tagName), ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $node->removeChild($child);
                } else {
                    self::walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = strtolower(trim($attribute->value));

                if (! in_array($name, self::ATTRIBUTES, true)
                    || ($name === 'href' && ! preg_match('~^(https?://|mailto:|/|#)~', $value))
                    || ($name === 'style' && (str_contains($value, 'expression') || str_contains($value, 'url(') || str_contains($value, 'javascript')))) {
                    $child->removeAttribute($attribute->name);
                }
            }

            self::walk($child);
        }
    }
}
