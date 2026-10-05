<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\PreviewRegistry;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Speichert das Vorschaubild eines Moduls (leer = automatisch aus dem Icon, none, Vorlage, media:<datei>).
 */
class SavePreview extends rex_api_function
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

        $key = PreviewRegistry::sanitize(rex_request('preview_key', 'string', ''));
        ModuleMetaRepository::savePreview($moduleId, $key);
        $meta = ModuleMetaRepository::getByModuleId($moduleId);

        rex_response::sendJson(['success' => true, 'preview' => PreviewRegistry::resolve($key, $meta['icon_key'] ?? null)]);
        exit;
    }
}
