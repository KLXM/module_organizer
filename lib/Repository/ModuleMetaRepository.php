<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

class ModuleMetaRepository
{
    /** @var array<int, array{id: int, module_id: int, category_id: ?int, is_favorite: bool, description: ?string, icon_key: ?string, preview_key: ?string, priority: int}>|null */
    private static ?array $cache = null;

    /**
     * @return array<int, array{id: int, module_id: int, category_id: ?int, is_favorite: bool, description: ?string, icon_key: ?string, preview_key: ?string, priority: int}>
     */
    public static function getAllIndexedByModuleId(): array
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . rex::getTable('module_organizer_module'),
        );

        $indexed = [];
        foreach ($rows as $row) {
            $moduleId = (int) $row['module_id'];
            $indexed[$moduleId] = [
                'id' => (int) $row['id'],
                'module_id' => $moduleId,
                'category_id' => null !== $row['category_id'] ? (int) $row['category_id'] : null,
                'is_favorite' => (bool) $row['is_favorite'],
                'description' => null === $row['description'] ? null : (string) $row['description'],
                'icon_key' => null === $row['icon_key'] ? null : (string) $row['icon_key'],
                'preview_key' => null === ($row['preview_key'] ?? null) ? null : (string) $row['preview_key'],
                'priority' => (int) $row['priority'],
            ];
        }

        self::$cache = $indexed;

        return $indexed;
    }

    /**
     * @return array{id: int, module_id: int, category_id: ?int, is_favorite: bool, description: ?string, icon_key: ?string, preview_key: ?string, priority: int}|null
     */
    public static function getByModuleId(int $moduleId): ?array
    {
        return self::getAllIndexedByModuleId()[$moduleId] ?? null;
    }

    public static function save(int $moduleId, ?int $categoryId, bool $isFavorite, ?string $description, ?string $iconKey): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_module'));
        $sql->setValue('module_id', $moduleId);
        $sql->setValue('category_id', $categoryId);
        $sql->setValue('is_favorite', $isFavorite ? 1 : 0);
        $sql->setValue('description', $description);
        $sql->setValue('icon_key', $iconKey);

        $existing = self::getByModuleId($moduleId);
        if (null !== $existing) {
            $sql->setWhere(['id' => $existing['id']]);
            $sql->addGlobalUpdateFields();
            $sql->update();
        } else {
            $maxPriority = (int) rex_sql::factory()->getArray(
                'SELECT COALESCE(MAX(priority), 0) AS max_priority FROM ' . rex::getTable('module_organizer_module'),
            )[0]['max_priority'];
            $sql->setValue('priority', $maxPriority + 1);
            $sql->addGlobalCreateFields();
            $sql->addGlobalUpdateFields();
            $sql->insert();
        }

        self::$cache = null;
    }

    /** Vorschaubild eines Moduls speichern (null = automatisch) */
    public static function savePreview(int $moduleId, ?string $previewKey): void
    {
        if (null === self::getByModuleId($moduleId)) {
            self::save($moduleId, null, false, null, null);
        }
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module_organizer_module'));
        $sql->setWhere(['module_id' => $moduleId]);
        $sql->setValue('preview_key', $previewKey);
        $sql->addGlobalUpdateFields();
        $sql->update();
        self::$cache = null;
    }

    /**
     * Setzt Kategorie-Zuordnung + Reihenfolge INNERHALB dieser Kategorie
     * (bzw. innerhalb der "ohne Kategorie"-Gruppe bei null) in einem Zug -
     * fuer Drag&Drop im Strukturbaum (pages/modules.organizer.overview.php),
     * wo ein Modul sowohl die Kategorie wechseln als auch seine Position
     * darin aendern kann. $orderedModuleIds ist die VOLLSTAENDIGE, neue
     * Reihenfolge aller Module dieser einen Kategorie-Gruppe.
     *
     * @param list<int> $orderedModuleIds
     */
    public static function saveGroupOrder(?int $categoryId, array $orderedModuleIds): void
    {
        foreach ($orderedModuleIds as $index => $moduleId) {
            $moduleId = (int) $moduleId;
            $meta = self::getByModuleId($moduleId);
            $priority = $index + 1;

            if (null === $meta) {
                $insert = rex_sql::factory();
                $insert->setTable(rex::getTable('module_organizer_module'));
                $insert->setValue('module_id', $moduleId);
                $insert->setValue('category_id', $categoryId);
                $insert->setValue('priority', $priority);
                $insert->addGlobalCreateFields();
                $insert->addGlobalUpdateFields();
                $insert->insert();
                continue;
            }

            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('module_organizer_module'));
            $sql->setValue('category_id', $categoryId);
            $sql->setValue('priority', $priority);
            $sql->setWhere(['id' => $meta['id']]);
            $sql->addGlobalUpdateFields();
            $sql->update();
        }

        self::$cache = null;
    }

    public static function clearCategoryAssignments(int $categoryId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('module_organizer_module') . ' SET category_id = NULL WHERE category_id = :cid',
            ['cid' => $categoryId],
        );

        self::$cache = null;
    }

    /**
     * Setzt icon_key auf null fuer alle Module, die auf ein geloeschtes
     * Custom-Icon verweisen (icon_key = "custom:<id>") - analog zu
     * clearCategoryAssignments() fuer geloeschte Kategorien.
     */
    public static function clearCustomIconAssignments(int $customIconId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('module_organizer_module') . ' SET icon_key = NULL WHERE icon_key = :key',
            ['key' => 'custom:' . $customIconId],
        );
        // gezeichnete Vorschaubilder: zurück auf „automatisch“
        $sql->setQuery(
            'UPDATE ' . rex::getTable('module_organizer_module') . ' SET preview_key = NULL WHERE preview_key = :key',
            ['key' => 'custom:' . $customIconId],
        );

        self::$cache = null;
    }
}
