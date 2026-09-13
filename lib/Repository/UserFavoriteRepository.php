<?php

namespace KLXM\ModuleOrganizer\Repository;

use rex;
use rex_sql;

/**
 * Persoenliche Favoriten pro Backend-User (nicht zu verwechseln mit den
 * global admin-gepflegten Favoriten in module_organizer_module.is_favorite).
 * Jeder eingeloggte Redakteur kann sich unabhaengig von den globalen
 * Favoriten eigene Module in der Blockauswahl vormerken - Stern-Klick direkt
 * im Popover/Overlay, siehe lib/Api/ToggleUserFavorite.php.
 */
class UserFavoriteRepository
{
    /** @return array<int, true> module_id => true, fuer schnellen isset()-Check */
    public static function getForUser(int $userId): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT module_id FROM ' . rex::getTable('module_organizer_user_favorite') . ' WHERE user_id = :uid',
            ['uid' => $userId],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['module_id']] = true;
        }

        return $result;
    }

    public static function toggle(int $userId, int $moduleId, bool $isFavorite): void
    {
        if ($isFavorite) {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'INSERT IGNORE INTO ' . rex::getTable('module_organizer_user_favorite')
                . ' (user_id, module_id, createdate) VALUES (:uid, :mid, NOW())',
                ['uid' => $userId, 'mid' => $moduleId],
            );

            return;
        }

        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('module_organizer_user_favorite') . ' WHERE user_id = :uid AND module_id = :mid',
            ['uid' => $userId, 'mid' => $moduleId],
        );
    }
}
