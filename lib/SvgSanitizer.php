<?php

namespace KLXM\ModuleOrganizer;

use DOMAttr;
use DOMDocument;
use DOMElement;

/**
 * Bereinigt SVG-Markup für eigene Icons (eingefügter SVG-Code, Repository-Aufrufe aus Skripten).
 *
 * Icons werden per innerHTML ausgegeben – daher bleibt nur, was ein Icon braucht:
 * Formen, Gruppen, Verläufe, Text. Entfernt werden Skripte, Event-Attribute (on…),
 * foreignObject, externe Bezüge (href/xlink:href außer #intern), url() außer #intern,
 * javascript:/data:-Werte und alles außerhalb der Allowlist.
 */
final class SvgSanitizer
{
    /** @var list<string> */
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'use', 'title', 'desc',
    ];

    /** @var list<string> */
    private const ATTRIBUTES = [
        'xmlns', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'transform', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit', 'opacity',
        'clip-path', 'clip-rule', 'mask', 'id', 'class', 'offset', 'stop-color', 'stop-opacity', 'gradientunits',
        'gradienttransform', 'font-size', 'font-weight', 'font-family', 'text-anchor', 'dominant-baseline',
        'role', 'aria-hidden', 'focusable', 'preserveaspectratio', 'href', 'xlink:href', 'style',
    ];

    /** Maximal erlaubte Größe des eingefügten Codes (Bytes) */
    public const MAX_LENGTH = 50000;

    /**
     * @return string|null bereinigtes SVG oder null, wenn kein gültiges SVG übrig bleibt
     */
    public static function sanitize(string $svg): ?string
    {
        $svg = trim($svg);
        if ('' === $svg || strlen($svg) > self::MAX_LENGTH) {
            return null;
        }
        // keine DOCTYPE/Entities (XXE, Entity-Expansion)
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            return null;
        }
        $svg = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$doc->documentElement || 'svg' !== strtolower($doc->documentElement->localName)) {
            return null;
        }

        self::clean($doc->documentElement);

        $root = $doc->documentElement;
        if (0 === $root->getElementsByTagName('*')->length) {
            return null; // nichts Zeichenbares übrig
        }
        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        if (!$root->hasAttribute('viewBox') && $root->hasAttribute('width') && $root->hasAttribute('height')) {
            $root->setAttribute('viewBox', '0 0 ' . (float) $root->getAttribute('width') . ' ' . (float) $root->getAttribute('height'));
        }
        // Größe kommt aus dem CSS der Blockauswahl
        $root->removeAttribute('width');
        $root->removeAttribute('height');
        $root->setAttribute('aria-hidden', 'true');

        $out = $doc->saveXML($root);

        return false === $out || '' === $out ? null : $out;
    }

    private static function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (!in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $element->removeChild($child);
                    continue;
                }
                self::clean($child);
            } elseif (XML_TEXT_NODE !== $child->nodeType) {
                // Kommentare, CDATA, Processing Instructions
                $element->removeChild($child);
            }
        }

        /** @var list<DOMAttr> $attributes */
        $attributes = iterator_to_array($element->attributes, false);
        foreach ($attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $value = trim((string) $attr->nodeValue);
            $keep = in_array($name, self::ATTRIBUTES, true) && !str_starts_with($name, 'on');
            if ($keep && in_array($name, ['href', 'xlink:href'], true)) {
                $keep = str_starts_with($value, '#');
            }
            if ($keep && 'style' === $name) {
                $keep = !preg_match('/url\s*\(\s*[\'"]?\s*(?!#)|expression|javascript:|@import|behavior/i', $value);
            }
            if ($keep && preg_match('/javascript:|data:|vbscript:/i', $value)) {
                $keep = false;
            }
            if ($keep && preg_match('/url\s*\(\s*[\'"]?\s*(?!#)/i', $value)) {
                $keep = false;
            }
            if (!$keep) {
                $element->removeAttributeNode($attr);
            }
        }
    }
}
