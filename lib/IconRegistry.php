<?php

namespace KLXM\ModuleOrganizer;

use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use rex_media;

/**
 * Ein icon_key ist eines von drei Dingen:
 * - einer der mitgelieferten Preset-Namen (SVG unter assets/icons/layout-<key>.svg)
 * - ein eigenes Medienpool-Bild im Format "media:<dateiname>" - gewaehlt per
 *   MediaPlace-Picker (window.MP.open())
 * - ein selbst gezeichnetes Icon im Format "custom:<id>" - erzeugt im
 *   Mini-Editor (assets/module_organizer_icon_editor.js), serverseitig als
 *   SVG generiert und in rex_module_organizer_custom_icon gespeichert
 * Gepflegt in der Sidebar von pages/modules.organizer.overview.php.
 */
class IconRegistry
{
    public const MEDIA_PREFIX = 'media:';
    public const CUSTOM_PREFIX = 'custom:';

    /**
     * Preset-Icons mit sprechendem Label fuer die Auswahl-UI. Orientiert an
     * den tatsaechlich vorhandenen Element-/Modultypen der Addons "builder"
     * (VvvebJs-Elemente) und "ncss" (Design-System-Komponenten), damit jedes
     * Icon zu einem real nutzbaren Modul passt statt frei erfunden zu sein.
     *
     * @return array<string, string> icon_key => Label
     */
    public static function getPresetLabels(): array
    {
        return [
            'image' => 'Bild',
            'cards' => 'Cards',
            'video' => 'Video',
            'image-text' => 'Bild + Text',
            'form' => 'Formular',
            'list' => 'Liste',
            'heading' => 'Überschrift',
            'columns' => 'Spalten',
            'text-media-left' => 'Text + Media (links)',
            'hero' => 'Hero',
            'cta' => 'Call-to-Action',
            'quote' => 'Zitat',
            'accordion' => 'Akkordeon',
            'faq' => 'FAQ',
            'stats' => 'Statistiken',
            'table' => 'Tabelle',
            'button' => 'Button',
            'divider' => 'Trenner',
            'stack-cards' => 'Card-Stapel',
            'fullscreen-sections' => 'Vollbild-Sektionen',
            'link-list' => 'Link-Liste',
            'media-overlay' => 'Media mit Overlay',
            'section' => 'Section/Container',
            'header-nav' => 'Header + Navigation',
            'footer' => 'Footer',
            'gallery' => 'Galerie',
            'team' => 'Team',
            'pricing' => 'Preistabelle',
            'newsletter' => 'Newsletter',
            'map' => 'Karte/Standort',
            'social' => 'Social Media',
            'breadcrumb' => 'Breadcrumb',
            'sidebar' => 'Sidebar-Layout',
        ];
    }

    /** @return list<string> */
    public static function getPresetKeys(): array
    {
        return array_keys(self::getPresetLabels());
    }

    public static function isMediaIcon(?string $iconKey): bool
    {
        return null !== $iconKey && str_starts_with($iconKey, self::MEDIA_PREFIX);
    }

    public static function getMediaFilename(?string $iconKey): ?string
    {
        if (null === $iconKey || !str_starts_with($iconKey, self::MEDIA_PREFIX)) {
            return null;
        }

        return substr($iconKey, strlen(self::MEDIA_PREFIX));
    }

    public static function isCustomIcon(?string $iconKey): bool
    {
        return null !== $iconKey && str_starts_with($iconKey, self::CUSTOM_PREFIX);
    }

    public static function getCustomId(?string $iconKey): ?int
    {
        if (null === $iconKey || !str_starts_with($iconKey, self::CUSTOM_PREFIX)) {
            return null;
        }

        return (int) substr($iconKey, strlen(self::CUSTOM_PREFIX));
    }

    /**
     * Normalisiert einen vom Client gesendeten icon_key: gueltiger Preset-Name,
     * "media:<existierende Mediendatei>" oder "custom:<existierende ID>"
     * bleibt erhalten, alles andere wird zu null (kein Icon).
     */
    public static function sanitize(?string $iconKey): ?string
    {
        if (null === $iconKey || '' === $iconKey) {
            return null;
        }

        if (in_array($iconKey, self::getPresetKeys(), true)) {
            return $iconKey;
        }

        if (self::isMediaIcon($iconKey)) {
            $filename = self::getMediaFilename($iconKey);
            if (null !== $filename && '' !== $filename && null !== rex_media::get($filename)) {
                return $iconKey;
            }
        }

        if (self::isCustomIcon($iconKey)) {
            $id = self::getCustomId($iconKey);
            if (null !== $id && $id > 0 && null !== CustomIconRepository::get($id)) {
                return $iconKey;
            }
        }

        return null;
    }
}
