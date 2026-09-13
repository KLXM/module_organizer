<?php

rex_api_function::register('module_organizer_reorder', KLXM\ModuleOrganizer\Api\Reorder::class);
rex_api_function::register('module_organizer_save_meta', KLXM\ModuleOrganizer\Api\SaveMeta::class);
rex_api_function::register('module_organizer_category', KLXM\ModuleOrganizer\Api\Category::class);
rex_api_function::register('module_organizer_toggle_user_favorite', KLXM\ModuleOrganizer\Api\ToggleUserFavorite::class);
rex_api_function::register('module_organizer_save_custom_icon', KLXM\ModuleOrganizer\Api\SaveCustomIcon::class);
rex_api_function::register('module_organizer_preview_custom_icon', KLXM\ModuleOrganizer\Api\PreviewCustomIcon::class);
rex_api_function::register('module_organizer_delete_custom_icon', KLXM\ModuleOrganizer\Api\DeleteCustomIcon::class);

if (rex::isBackend() && rex::getUser()) {
    $bust = function (string $file) {
        return '?v=' . filemtime($this->getPath('assets/' . $file));
    };
    $bust = $bust->bindTo($this);

    $isContentEdit = 'index.php?page=content/edit' === rex_url::currentBackendPage();
    $isOwnPage = str_starts_with((string) rex_request('page', 'string', ''), 'modules/organizer');

    if ($isContentEdit) {
        // Beide Darstellungen (Popover + Overlay) werden geladen, da jede nur
        // auf ihren eigenen [data-mo-mode]-Wert reagiert - die Einstellung
        // (modules/organizer/settings) entscheidet zur Renderzeit im Fragment,
        // welcher data-mo-mode am Trigger-Button steht.
        rex_view::addCssFile($this->getAssetsUrl('module_organizer.css') . $bust('module_organizer.css'));
        rex_view::addJsFile($this->getAssetsUrl('module_organizer.js') . $bust('module_organizer.js'));
        rex_view::addJsFile($this->getAssetsUrl('module_organizer_popover.js') . $bust('module_organizer_popover.js'));
    }

    if ($isOwnPage) {
        // Sortierung nutzt die native HTML5 Drag&Drop-API (draggable-Attribut +
        // dragstart/dragover/dragend) - keine zusaetzliche JS-Bibliothek noetig.
        rex_view::addCssFile($this->getAssetsUrl('module_organizer.css') . $bust('module_organizer.css'));
        rex_view::addJsFile($this->getAssetsUrl('module_organizer_organizer.js') . $bust('module_organizer_organizer.js'));
        rex_view::addJsFile($this->getAssetsUrl('module_organizer_icon_editor.js') . $bust('module_organizer_icon_editor.js'));
    }

    // JS-Uebersetzungen: jeder Schluessel aus lang/de_de.lang wird fuer die
    // aktive Locale aufgeloest und als JSON eingebettet - gebraucht sowohl im
    // Content-Editor (Popover/Overlay) als auch auf der eigenen Organizer-
    // Seite (Icon-Editor, Sortierstatus). Ohne dieses Tag zeigen alle t()-
    // Aufrufe in den JS-Dateien nur die rohen Lang-Keys statt Text.
    if ($isContentEdit || $isOwnPage) {
        $i18nMap = [];
        $langFile = $this->getPath('lang/de_de.lang');
        if (is_file($langFile)) {
            foreach (file($langFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^([a-zA-Z0-9_]+)\s*=/', $line, $m)) {
                    $i18nMap[$m[1]] = rex_i18n::msg($m[1]);
                }
            }
        }

        // Media-Base-URL: SVG-Dateien koennen NICHT ueber den Media-Manager
        // ausgeliefert werden (REDAXO-Kernverhalten, kein Bug hier) - fuer
        // media:-Icons mit .svg-Endung nutzen beide JS-Dateien stattdessen
        // diese rohe Medienpool-URL direkt, ohne rex_media_type-Umweg.
        // Vorbild: mediaplace/boot.php, data-media-base-url via rex_url::media().
        $mediaBaseUrl = rex_url::media();

        rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) use ($i18nMap, $mediaBaseUrl) {
            $script = '<script type="application/json" id="mo-i18n-data">'
                . json_encode($i18nMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
                . '</script>'
                . '<script type="application/json" id="mo-media-base-url">'
                . json_encode($mediaBaseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
                . '</script>';

            return str_ireplace('</body>', $script . '</body>', $ep->getSubject());
        });
    }
}
