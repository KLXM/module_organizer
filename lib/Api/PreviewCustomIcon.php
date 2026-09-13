<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\CustomIconRenderer;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

/**
 * Rendert eine Shape-Liste zu SVG, OHNE sie zu speichern - fuer die
 * Live-Vorschau im Icon-Editor (assets/module_organizer_icon_editor.js),
 * damit waehrend des Zeichnens exakt das spaeter gespeicherte Ergebnis
 * sichtbar ist (kein separates, potenziell abweichendes JS-Rendering).
 * Gleiche Validierung/Berechtigung wie SaveCustomIcon, nur ohne Persistenz.
 */
class PreviewCustomIcon extends rex_api_function
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

        $svg = CustomIconRenderer::render($shapes);

        rex_response::sendJson(['success' => true, 'svg' => $svg]);
        exit;
    }
}
