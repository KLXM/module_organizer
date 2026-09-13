<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\CustomIconRenderer;
use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Speichert ein im Mini-Editor gezeichnetes Icon. Nimmt ausschliesslich eine
 * strukturierte Rechteck-Liste entgegen (Typ + Koordinaten), niemals rohes
 * SVG-Markup vom Client - das serverseitige CustomIconRenderer baut daraus
 * das tatsaechliche SVG. Admin-only wie die uebrige Organizer-Verwaltung
 * (im Unterschied zum admin-losen persoenlichen Favoriten-Toggle): Icons
 * zeichnen ist eine Gestaltungsaufgabe, kein reiner Redakteur-Komfort.
 */
class SaveCustomIcon extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $rawShapes = json_decode(rex_request('shapes', 'string', ''), true);
        $shapes = CustomIconRenderer::sanitizeShapes($rawShapes);

        if ([] === $shapes) {
            rex_response::sendJson(['success' => false, 'error' => 'no_shapes']);
            exit;
        }

        $svg = CustomIconRenderer::render($shapes);
        $id = rex_request('id', 'int', 0);
        $title = rex_request('title', 'string', '');

        $savedId = CustomIconRepository::save($id > 0 ? $id : null, $title, $svg);

        rex_response::sendJson(['success' => true, 'id' => $savedId, 'svg' => $svg]);
        exit;
    }
}
