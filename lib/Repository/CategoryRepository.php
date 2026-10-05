<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

class CategoryRepository
{
    /**
     * @return list<array{id: int, name: string, priority: int, structure_ids: list<int>, structure_children: bool}>
     */
    public static function getAll(): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . rex::getTable('module_organizer_category') . ' ORDER BY priority, name',
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'priority' => (int) $row['priority'],
                'structure_ids' => array_values(array_filter(array_map('intval', explode(',', (string) ($row['structure_ids'] ?? ''))))),
                'structure_children' => (bool) ($row['structure_children'] ?? true),
            ];
        }

        return $result;
    }

    /**
     * Kategorie nur in bestimmten Strukturkategorien anbieten (leer = überall).
     *
     * @param list<int> $structureIds
     */
    public static function saveAreas(int $id, array $structureIds, bool $includeChildren): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $structureIds))));
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_category'));
        $sql->setWhere(['id' => $id]);
        $sql->setValue('structure_ids', [] !== $ids ? implode(',', $ids) : null);
        $sql->setValue('structure_children', $includeChildren ? 1 : 0);
        $sql->addGlobalUpdateFields();
        $sql->update();
    }

    /**
     * Ist eine Kategorie in der Strukturkategorie $categoryId (inkl. Pfad) verfügbar?
     *
     * @param array{structure_ids: list<int>, structure_children: bool} $category
     * @param list<int> $path Elternkategorien der aktuellen Kategorie (von oben)
     */
    public static function isAvailableIn(array $category, int $categoryId, array $path): bool
    {
        if ([] === $category['structure_ids']) {
            return true;
        }
        if (in_array($categoryId, $category['structure_ids'], true)) {
            return true;
        }

        return $category['structure_children'] && [] !== array_intersect($path, $category['structure_ids']);
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
