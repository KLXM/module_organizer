<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Inline-Kategorieverwaltung im Strukturbaum (pages/modules.organizer.overview.php)
 * - kein eigener Seitenwechsel mehr fuer Anlegen/Umbenennen/Loeschen.
 */
class Category extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $op = rex_request('op', 'string', '');

        if ('create' === $op) {
            $name = trim(rex_request('name', 'string', ''));
            if ('' === $name) {
                rex_response::sendJson(['success' => false]);
                exit;
            }
            $category = CategoryRepository::create($name);
            rex_response::sendJson(['success' => true, 'category' => $category]);
            exit;
        }

        if ('rename' === $op) {
            $id = rex_request('id', 'int', 0);
            $name = trim(rex_request('name', 'string', ''));
            if ($id <= 0 || '' === $name) {
                rex_response::sendJson(['success' => false]);
                exit;
            }
            CategoryRepository::rename($id, $name);
            rex_response::sendJson(['success' => true]);
            exit;
        }

        if ('delete' === $op) {
            $id = rex_request('id', 'int', 0);
            if ($id <= 0) {
                rex_response::sendJson(['success' => false]);
                exit;
            }
            CategoryRepository::delete($id);
            rex_response::sendJson(['success' => true]);
            exit;
        }

        rex_response::sendJson(['success' => false]);
        exit;
    }
}
