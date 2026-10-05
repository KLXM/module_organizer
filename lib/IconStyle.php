<?php

namespace KLXM\ModuleOrganizer;

use rex_addon;

/**
 * Icon-Stil der Blockauswahl: monochrom (currentColor) oder Duotone.
 *
 * Duotone nutzt den Aufbau aller Vorlagen-Icons: Konturen/Texte in der ersten Farbe,
 * Flächen (fill-opacity) in der zweiten – zarte Grundflächen als Hauch, kräftigere als Akzent.
 * Kategorien können eine eigene Akzentfarbe haben.
 */
final class IconStyle
{
    /** @var array<string, array{0: string, 1: string, 2: string}> Schlüssel => [Bezeichnung, Kontur, Akzent] */
    public const PALETTES = [
        'redaxo' => ['REDAXO', '#324050', '#4b9ad9'],
        'ocean' => ['Ozean', '#1e3a5f', '#14b8a6'],
        'warm' => ['Warm', '#3f2d20', '#f59e0b'],
        'nature' => ['Natur', '#1f3d2b', '#22c55e'],
        'berry' => ['Beere', '#3b1f3a', '#ec4899'],
        'violet' => ['Violett', '#2e1f5e', '#8b5cf6'],
    ];

    public static function isDuotone(): bool
    {
        return 'duotone' === rex_addon::get('module_organizer')->getConfig('icon_style', 'mono');
    }

    /** @return array{0: string, 1: string} [Kontur, Akzent] */
    public static function colors(): array
    {
        $addon = rex_addon::get('module_organizer');
        $palette = (string) $addon->getConfig('icon_palette', 'redaxo');
        if ('custom' === $palette) {
            return [
                self::color((string) $addon->getConfig('icon_color_primary', ''), self::PALETTES['redaxo'][1]),
                self::color((string) $addon->getConfig('icon_color_secondary', ''), self::PALETTES['redaxo'][2]),
            ];
        }
        $p = self::PALETTES[$palette] ?? self::PALETTES['redaxo'];

        return [$p[1], $p[2]];
    }

    /** Hex-Farbe prüfen (#rgb / #rrggbb), sonst Ersatz */
    public static function color(?string $value, string $fallback = ''): string
    {
        $value = trim((string) $value);

        return preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $value) ? strtolower($value) : $fallback;
    }

    /** CSS-Variablen für das body-Element (PAGE_BODY_ATTR) */
    public static function bodyStyle(): string
    {
        [$primary, $secondary] = self::colors();

        return '--mo-duo-1-light:' . $primary . ';--mo-duo-2:' . $secondary;
    }
}
