<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\IconRegistry;
use KLXM\ModuleOrganizer\PreviewRegistry;
use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use rex;
use rex_addon;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_response;

/**
 * Vorlagen für den Editor (assets/module_organizer_icon_editor.js):
 * GET ?rex-api-call=module_organizer_editor_templates&kind=icon|preview
 *
 * presets: Vorlagen des Addons – Vorschaubilder mit bearbeitbaren Formen (assets/previews/<key>.json),
 *          Icons nur als Durchpause-Ebene (SVG-Adresse);
 * own:     eigene Werke derselben Art mit Formen (wieder bearbeitbar bzw. als Vorlage).
 */
class EditorTemplates extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $kind = 'preview' === rex_request('kind', 'string', 'icon') ? 'preview' : 'icon';
        $addon = rex_addon::get('module_organizer');
        $presets = [];
        if ('preview' === $kind) {
            foreach (PreviewRegistry::getPresetLabels() as $key => $label) {
                $shapesFile = $addon->getPath('assets/previews/' . $key . '.json');
                $presets[] = [
                    'key' => $key,
                    'label' => $label,
                    'svg' => $addon->getAssetsUrl('previews/' . $key . '.svg'),
                    'shapes' => is_file($shapesFile) ? json_decode((string) file_get_contents($shapesFile), true) : null,
                ];
            }
        } else {
            foreach (IconRegistry::getPresetLabels() as $key => $label) {
                $presets[] = ['key' => $key, 'label' => $label, 'svg' => $addon->getAssetsUrl('icons/layout-' . $key . '.svg'), 'shapes' => null];
            }
        }

        $own = [];
        foreach (CustomIconRepository::getAll($kind) as $item) {
            $own[] = [
                'id' => $item['id'],
                'title' => $item['title'] ?? ('#' . $item['id']),
                'svg' => $item['svg'],
                'shapes' => null !== $item['shapes'] ? json_decode($item['shapes'], true) : null,
            ];
        }

        rex_response::sendJson(['kind' => $kind, 'presets' => $presets, 'own' => $own]);
        exit;
    }
}
