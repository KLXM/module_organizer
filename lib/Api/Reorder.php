<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Sortierung im Strukturbaum (pages/modules.organizer.overview.php):
 * mode=categories sortiert die 1. Ebene (Kategorien) neu, mode=modules
 * sortiert die Module INNERHALB einer Kategorie-Gruppe (bzw. der "ohne
 * Kategorie"-Gruppe bei category_id=0) und setzt dabei ggf. auch deren
 * category_id neu, falls das Modul per Drag&Drop in eine andere Kategorie
 * verschoben wurde.
 */
class Reorder extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $mode = rex_request('mode', 'string', 'modules');
        $order = json_decode(rex_request('order', 'string', ''), true);
        if (!is_array($order)) {
            $order = [];
        }

        if ('categories' === $mode) {
            CategoryRepository::saveOrder(array_map('intval', $order));
        } else {
            $categoryId = rex_request('category_id', 'int', 0);
            ModuleMetaRepository::saveGroupOrder($categoryId > 0 ? $categoryId : null, array_map('intval', $order));
        }

        rex_response::sendJson(['success' => true]);
        exit;
    }
}
