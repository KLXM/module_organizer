<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

/**
 * Selbst gezeichnete Icons und Vorschaubilder (Editor, assets/module_organizer_icon_editor.js).
 * kind: icon | preview; shapes: Formen aus dem Editor (JSON), damit ein Werk wieder bearbeitet
 * oder als Vorlage genommen werden kann (bei eingefügtem SVG-Code leer).
 * Ein Custom-Icon ist wiederverwendbar - mehrere Module koennen per
 * icon_key = "custom:<id>" auf dieselbe gezeichnete Vorschau verweisen,
 * analog dazu, dass mehrere Module dasselbe media:-Bild referenzieren
 * koennen. Das SVG-Markup selbst wird ausschliesslich serverseitig erzeugt
 * (siehe CustomIconRenderer) - hier wird nur das fertige Ergebnis abgelegt.
 */
class CustomIconRepository
{
    public const KINDS = ['icon', 'preview'];

    /** @return list<array{id: int, title: ?string, svg: string, kind: string, shapes: ?string}> */
    public static function getAll(string $kind = 'icon'): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id, title, svg, kind, shapes FROM ' . rex::getTable('module_organizer_custom_icon') . ' WHERE kind = :kind ORDER BY id DESC',
            ['kind' => $kind],
        );

        return array_map(self::row(...), $rows);
    }

    /** @return array{id: int, title: ?string, svg: string, kind: string, shapes: ?string}|null */
    public static function get(int $id): ?array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id, title, svg, kind, shapes FROM ' . rex::getTable('module_organizer_custom_icon') . ' WHERE id = :id',
            ['id' => $id],
        );

        return [] === $rows ? null : self::row($rows[0]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, title: ?string, svg: string, kind: string, shapes: ?string}
     */
    private static function row(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => null === $row['title'] ? null : (string) $row['title'],
            'svg' => (string) $row['svg'],
            'kind' => in_array($row['kind'] ?? 'icon', self::KINDS, true) ? (string) $row['kind'] : 'icon',
            'shapes' => null === ($row['shapes'] ?? null) || '' === $row['shapes'] ? null : (string) $row['shapes'],
        ];
    }

    /**
     * Speichert ein Icon. Das SVG wird immer bereinigt (SvgSanitizer) – egal ob es aus dem Icon-Editor,
     * aus eingefügtem SVG-Code oder aus einem Setup-Skript kommt.
     *
     * @throws \InvalidArgumentException wenn kein gültiges SVG übrig bleibt
     */
    /** @param string|null $shapes bereits geprüfte Formen als JSON (CustomIconRenderer::sanitizeShapes) */
    public static function save(?int $id, ?string $title, string $svg, string $kind = 'icon', ?string $shapes = null): int
    {
        $svg = \KLXM\ModuleOrganizer\SvgSanitizer::sanitize($svg);
        if (null === $svg) {
            throw new \InvalidArgumentException('Invalid SVG');
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_custom_icon'));
        $sql->setValue('title', '' !== trim((string) $title) ? trim((string) $title) : null);
        $sql->setValue('svg', $svg);
        $sql->setValue('kind', in_array($kind, self::KINDS, true) ? $kind : 'icon');
        $sql->setValue('shapes', $shapes);

        if (null !== $id) {
            $sql->setWhere(['id' => $id]);
            $sql->addGlobalUpdateFields();
            $sql->update();

            return $id;
        }

        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        return (int) $sql->getLastId();
    }

    public static function delete(int $id): void
    {
        ModuleMetaRepository::clearCustomIconAssignments($id);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_custom_icon'));
        $sql->setWhere(['id' => $id]);
        $sql->delete();
    }
}
