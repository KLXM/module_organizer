<?php

namespace KLXM\ModuleOrganizer;

use rex_addon;

/**
 * Anzeigename der Module in der Blockauswahl: entfernt Nummern-Präfixe wie „001 :: “,
 * die viele Developer zum Sortieren der Modulliste nutzen. Die Regel ist einstellbar
 * (eigener regulärer Ausdruck), weil jeder Developer seine Module anders benennt.
 */
final class TitleFormatter
{
    /** Standard: „001 :: Text“, „12 – Text“, „003. Text“, „A01 | Text“ */
    public const DEFAULT_PATTERN = '^\s*[A-Za-z]?\d+[a-z]?\s*(::|[-–—|:.])\s*';

    public static function isEnabled(): bool
    {
        return (bool) rex_addon::get('module_organizer')->getConfig('strip_title_prefix', true);
    }

    public static function getPattern(): string
    {
        $custom = trim((string) rex_addon::get('module_organizer')->getConfig('title_prefix_pattern', ''));

        return '' !== $custom ? $custom : self::DEFAULT_PATTERN;
    }

    /** Prüft einen regulären Ausdruck (ohne Begrenzer) */
    public static function isValidPattern(string $pattern): bool
    {
        return false !== @preg_match(self::delimit($pattern), '');
    }

    public static function display(string $title): string
    {
        if (!self::isEnabled()) {
            return $title;
        }
        $pattern = self::getPattern();
        if (!self::isValidPattern($pattern)) {
            return $title;
        }
        $clean = trim((string) preg_replace(self::delimit($pattern), '', $title, 1));

        return '' !== $clean ? $clean : $title;
    }

    private static function delimit(string $pattern): string
    {
        return '~' . str_replace('~', '\~', $pattern) . '~u';
    }
}
