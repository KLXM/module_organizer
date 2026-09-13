<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

class CategoryRepository
{
    /**
     * @return list<array{id: int, name: string, priority: int}>
     */
    public static function getAll(): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id, name, priority FROM ' . rex::getTable('module_organizer_category') . ' ORDER BY priority, name',
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'priority' => (int) $row['priority'],
            ];
        }

        return $result;
    }

    public static function save(?int $id, string $name, int $priority): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_category'));
        $sql->setValue('name', $name);
        $sql->setValue('priority', $priority);

        if (null !== $id) {
            $sql->setWhere(['id' => $id]);
            $sql->addGlobalUpdateFields();
            $sql->update();
        } else {
            $sql->addGlobalCreateFields();
            $sql->addGlobalUpdateFields();
            $sql->insert();
        }
    }

    /**
     * Legt eine neue Kategorie mit automatischer Prioritaet (ans Ende der
     * 1. Ebene) an - fuer den Inline-"+ Kategorie"-Button im Strukturbaum.
     *
     * @return array{id: int, name: string, priority: int}
     */
    public static function create(string $name): array
    {
        $maxPriority = (int) rex_sql::factory()->getArray(
            'SELECT COALESCE(MAX(priority), 0) AS max_priority FROM ' . rex::getTable('module_organizer_category'),
        )[0]['max_priority'];
        $priority = $maxPriority + 1;

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_category'));
        $sql->setValue('name', $name);
        $sql->setValue('priority', $priority);
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        return ['id' => (int) $sql->getLastId(), 'name' => $name, 'priority' => $priority];
    }

    public static function rename(int $id, string $name): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_category'));
        $sql->setValue('name', $name);
        $sql->setWhere(['id' => $id]);
        $sql->addGlobalUpdateFields();
        $sql->update();
    }

    /**
     * @param list<int> $orderedCategoryIds
     */
    public static function saveOrder(array $orderedCategoryIds): void
    {
        $sql = rex_sql::factory();
        foreach ($orderedCategoryIds as $index => $categoryId) {
            $sql->setQuery(
                'UPDATE ' . rex::getTable('module_organizer_category') . ' SET priority = :prio WHERE id = :id',
                ['prio' => $index + 1, 'id' => (int) $categoryId],
            );
        }
    }

    public static function delete(int $id): void
    {
        ModuleMetaRepository::clearCategoryAssignments($id);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_category'));
        $sql->setWhere(['id' => $id]);
        $sql->delete();
    }
}
