<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

/**
 * Selbst gezeichnete Icons (Mini-Editor, assets/module_organizer_icon_editor.js).
 * Ein Custom-Icon ist wiederverwendbar - mehrere Module koennen per
 * icon_key = "custom:<id>" auf dieselbe gezeichnete Vorschau verweisen,
 * analog dazu, dass mehrere Module dasselbe media:-Bild referenzieren
 * koennen. Das SVG-Markup selbst wird ausschliesslich serverseitig erzeugt
 * (siehe CustomIconRenderer) - hier wird nur das fertige Ergebnis abgelegt.
 */
class CustomIconRepository
{
    /** @return list<array{id: int, title: ?string, svg: string}> */
    public static function getAll(): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id, title, svg FROM ' . rex::getTable('module_organizer_custom_icon') . ' ORDER BY id DESC',
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'title' => null === $row['title'] ? null : (string) $row['title'],
                'svg' => (string) $row['svg'],
            ];
        }

        return $result;
    }

    /** @return array{id: int, title: ?string, svg: string}|null */
    public static function get(int $id): ?array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id, title, svg FROM ' . rex::getTable('module_organizer_custom_icon') . ' WHERE id = :id',
            ['id' => $id],
        );

        if ([] === $rows) {
            return null;
        }

        return [
            'id' => (int) $rows[0]['id'],
            'title' => null === $rows[0]['title'] ? null : (string) $rows[0]['title'],
            'svg' => (string) $rows[0]['svg'],
        ];
    }

    public static function save(?int $id, ?string $title, string $svg): int
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_custom_icon'));
        $sql->setValue('title', '' !== trim((string) $title) ? trim((string) $title) : null);
        $sql->setValue('svg', $svg);

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
