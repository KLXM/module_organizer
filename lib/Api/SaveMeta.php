<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\IconRegistry;
use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * AJAX-Endpunkt fuer die interaktive Sidebar in pages/modules.organizer.php
 * (Vorbild: mform-Formbuilder-Sidebar) - speichert Kategorie/Favorit/
 * Beschreibung/Icon eines Moduls, sobald der Redakteur ein Feld aendert.
 */
class SaveMeta extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $moduleId = rex_request('module_id', 'int', 0);
        if ($moduleId <= 0) {
            throw new rex_api_exception('Missing module_id.');
        }

        $categoryId = rex_request('category_id', 'int', 0);
        $isFavorite = rex_request('is_favorite', 'bool', false);
        $description = trim(rex_request('description', 'string', ''));
        $iconKey = IconRegistry::sanitize(rex_request('icon_key', 'string', ''));

        $validCategoryIds = array_column(CategoryRepository::getAll(), 'id');

        ModuleMetaRepository::save(
            $moduleId,
            $categoryId > 0 && in_array($categoryId, $validCategoryIds, true) ? $categoryId : null,
            $isFavorite,
            '' !== $description ? $description : null,
            $iconKey,
        );

        rex_response::sendJson(['success' => true]);
        exit;
    }
}
