<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

/**
 * Zuletzt verwendete Module je Benutzer – wird beim Einfügen eines Blocks (SLICE_ADDED) gepflegt.
 */
class UserRecentRepository
{
    public const LIMIT = 5;

    public static function add(int $userId, int $moduleId): void
    {
        if ($userId <= 0 || $moduleId <= 0) {
            return;
        }
        rex_sql::factory()->setQuery(
            'INSERT INTO ' . rex::getTable('module_organizer_user_recent') . ' (user_id, module_id, used_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE used_at = VALUES(used_at)',
            [$userId, $moduleId, date('Y-m-d H:i:s')],
        );
        // nur die letzten LIMIT behalten
        $keep = array_column(rex_sql::factory()->getArray(
            'SELECT id FROM ' . rex::getTable('module_organizer_user_recent') . ' WHERE user_id = ? ORDER BY used_at DESC, id DESC LIMIT ' . self::LIMIT,
            [$userId],
        ), 'id');
        if ([] !== $keep) {
            rex_sql::factory()->setQuery(
                'DELETE FROM ' . rex::getTable('module_organizer_user_recent') . ' WHERE user_id = ? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')',
                [$userId],
            );
        }
    }

    /** @return array<int, int> Modul-ID => Rang (1 = zuletzt) */
    public static function getForUser(int $userId): array
    {
        $rank = 0;
        $out = [];
        foreach (rex_sql::factory()->getArray(
            'SELECT module_id FROM ' . rex::getTable('module_organizer_user_recent') . ' WHERE user_id = ? ORDER BY used_at DESC, id DESC LIMIT ' . self::LIMIT,
            [$userId],
        ) as $row) {
            $out[(int) $row['module_id']] = ++$rank;
        }

        return $out;
    }
}
