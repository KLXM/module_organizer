<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\Repository\UserFavoriteRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Persoenlicher Favoriten-Stern in der Blockauswahl (Popover/Overlay) -
 * bewusst OHNE Admin-Check: jeder eingeloggte Backend-User darf seine
 * eigenen Favoriten pflegen, im Unterschied zu den global admin-gepflegten
 * Favoriten im Organizer-Strukturbaum (siehe lib/Api/SaveMeta.php).
 */
class ToggleUserFavorite extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user) {
            throw new rex_api_exception('Login required.');
        }

        $moduleId = rex_request('module_id', 'int', 0);
        if ($moduleId <= 0) {
            throw new rex_api_exception('Missing module_id.');
        }

        $isFavorite = rex_request('is_favorite', 'bool', false);

        UserFavoriteRepository::toggle((int) $user->getId(), $moduleId, $isFavorite);

        rex_response::sendJson(['success' => true, 'is_favorite' => $isFavorite]);
        exit;
    }
}
