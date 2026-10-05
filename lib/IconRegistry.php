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
            'downloads' => 'Downloads',
            'slideshow' => 'Slideshow',
            'image-teaser' => 'Bild-Teaser',
            'page-nav' => 'Seiten-Navigation',
            'timeline' => 'Zeitstrahl',
            'side-decoration' => 'Deko am Seitenrand',
            'greeting' => 'Begrüßung',
            'locations' => 'Standorte',
            'contact' => 'Kontakt & Anfahrt',
            'calendar' => 'Kalender',
            'catalog' => 'Katalog mit Ort',
            'restricted-start' => 'Geschützter Bereich (Beginn)',
            'wifi' => 'WLAN',
            'restricted-end' => 'Geschützter Bereich (Ende)',
            'info-cards' => 'Info-Karten',
            'folder' => 'Mappe / Themen',
            'embed' => 'Inhalt einbinden',
            'tabs' => 'Tabs',
            'testimonial' => 'Kundenstimmen',
            'logos' => 'Logo-Wand',
            'steps' => 'Ablauf / Schritte',
            'countdown' => 'Countdown',
            'news' => 'News / Beiträge',
            'product' => 'Produkt',
            'before-after' => 'Vorher / Nachher',
            'audio' => 'Audio / Podcast',
            'chart' => 'Diagramm',
            'hours' => 'Öffnungszeiten',
            'price-list' => 'Preisliste / Karte',
            'notice' => 'Hinweisbox',
            'carousel' => 'Karussell',
            'masonry' => 'Masonry',
            'search' => 'Suche',
            'stacked-cards' => 'Gestapelte Karten (Scroll)',
            'grid' => 'Raster / Grid',
            'text' => 'Nur Text',
        ];
    }

    /**
     * Zusätzliche Suchbegriffe je Vorlage (Synonyme, englische Namen), damit die Suche
     * z. B. „Accordion“, „Reiter“ oder „Kacheln“ findet.
     *
     * @return array<string, string>
     */
    public static function getPresetKeywords(): array
    {
        return [
            'accordion' => 'accordion aufklappen ausklappen collapse toggle details',
            'tabs' => 'tab reiter registerkarten register umschalten',
            'grid' => 'raster kacheln tiles spalten zeilen gitter teaser',
            'text' => 'fließtext fliesstext absatz paragraph editor wysiwyg richtext rte copy',
            'cards' => 'karten teaser kacheln',
            'columns' => 'spalten mehrspaltig',
            'faq' => 'fragen antworten accordion',
            'hero' => 'bühne buehne header stage banner',
            'cta' => 'call to action handlungsaufforderung button aktion',
            'gallery' => 'bilder fotos lightbox',
            'slideshow' => 'slider karussell bilder',
            'carousel' => 'slider slideshow',
            'image-text' => 'text media bild',
            'text-media-left' => 'bild text media',
            'heading' => 'headline titel h1 h2',
            'divider' => 'trennlinie abstand spacer',
            'quote' => 'zitat blockquote',
            'table' => 'tabelle daten',
            'form' => 'formular kontaktformular eingabe',
            'map' => 'karte standort anfahrt',
            'downloads' => 'dateien pdf dokumente',
            'stats' => 'zahlen kennzahlen counter',
            'testimonial' => 'bewertungen kundenstimmen referenzen',
            'notice' => 'hinweis info alert box',
            'section' => 'container bereich wrapper',
            'stacked-cards' => 'stapel scroll karten',
            'stack-cards' => 'stapel karten',
        ];
    }

    /** Text für die Suche: Bezeichnung, Schlüssel und Suchbegriffe (klein geschrieben) */
    public static function getSearchText(string $key, string $label = ''): string
    {
        return mb_strtolower(trim($label . ' ' . $key . ' ' . (self::getPresetKeywords()[$key] ?? '')));
    }

    /**
     * Bezeichnungen alphabetisch (deutsche Sortierung, falls intl verfügbar)
     *
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    public static function sortLabels(array $labels): array
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('de_DE');
            uasort($labels, static fn (string $a, string $b): int => (int) $collator->compare($a, $b));
        } else {
            asort($labels, SORT_NATURAL | SORT_FLAG_CASE);
        }
        return $labels;
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
