<?php

namespace KLXM\ModuleOrganizer;

use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use rex_addon;
use rex_media;
use rex_media_manager;
use rex_url;

/**
 * Vorschaubilder der Module (Darstellungen „Vorschaubilder“ und „Liste mit Vorschau“).
 *
 * Vorlagen: allgemeine Mockups im Screenshot-Stil (assets/previews/<schlüssel>.svg), bewusst ohne
 * Projektinhalte. Sie folgen dem Icon-Aufbau (currentColor + fill-opacity) und übernehmen so
 * Duotone-Palette und Kategorie-Farbe. Ohne eigene Wahl wird die Vorlage zum Icon genutzt.
 *
 * preview_key: null/'' = automatisch (aus dem Icon), 'none' = keins, Vorlagen-Schlüssel, 'media:<datei>',
 * 'custom:<id>' = im Editor gezeichnetes Vorschaubild
 */
final class PreviewRegistry
{
    public const NONE = 'none';

    /** @return list<string> */
    public static function getPresetKeys(): array
    {
        static $keys = null;
        if (null === $keys) {
            $keys = [];
            foreach (glob(rex_addon::get('module_organizer')->getPath('assets/previews/*.svg')) ?: [] as $file) {
                $keys[] = basename($file, '.svg');
            }
            sort($keys);
        }

        return $keys;
    }

    /** Vorlagen mit Bezeichnung (aus den Icon-Bezeichnungen) */
    /** @return array<string, string> */
    public static function getPresetLabels(): array
    {
        $labels = IconRegistry::getPresetLabels();
        $out = [];
        foreach (self::getPresetKeys() as $key) {
            $out[$key] = $labels[$key] ?? $key;
        }

        return IconRegistry::sortLabels($out);
    }

    public static function sanitize(?string $key): ?string
    {
        $key = trim((string) $key);
        if ('' === $key) {
            return null;
        }
        if (self::NONE === $key || in_array($key, self::getPresetKeys(), true)) {
            return $key;
        }
        if (str_starts_with($key, 'media:') && null !== rex_media::get(substr($key, 6))) {
            return $key;
        }
        if (null !== self::custom($key)) {
            return $key;
        }

        return null;
    }

    /**
     * Tatsächliches Vorschaubild eines Moduls.
     *
     * @return array{type: string, key?: string, url?: string, svg?: string}|null type: preset | media | custom
     */
    public static function resolve(?string $previewKey, ?string $iconKey): ?array
    {
        $previewKey = (string) $previewKey;
        if (self::NONE === $previewKey) {
            return null;
        }
        if (str_starts_with($previewKey, 'media:')) {
            $file = substr($previewKey, 6);
            if (null === rex_media::get($file)) {
                return null;
            }
            $isSvg = 'svg' === strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return ['type' => 'media', 'url' => $isSvg ? rex_url::media($file) : rex_media_manager::getUrl('rex_media_medium', $file)];
        }
        $custom = self::custom($previewKey);
        if (null !== $custom) {
            // bereits beim Speichern bereinigt (SvgSanitizer)
            return ['type' => 'custom', 'svg' => $custom['svg']];
        }
        $key = '' !== $previewKey ? $previewKey : (string) $iconKey;

        return in_array($key, self::getPresetKeys(), true) ? ['type' => 'preset', 'key' => $key] : null;
    }

    /** @return array{id: int, title: ?string, svg: string, kind: string, shapes: ?string}|null */
    private static function custom(string $key): ?array
    {
        if (!preg_match('/^custom:(\d+)$/', $key, $m)) {
            return null;
        }
        $item = CustomIconRepository::get((int) $m[1]);

        return null !== $item && 'preview' === $item['kind'] ? $item : null;
    }
}
