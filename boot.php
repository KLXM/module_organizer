<?php

/** @var rex_addon $this */

rex_api_function::register('module_organizer_reorder', KLXM\ModuleOrganizer\Api\Reorder::class);
rex_api_function::register('module_organizer_save_meta', KLXM\ModuleOrganizer\Api\SaveMeta::class);
rex_api_function::register('module_organizer_category', KLXM\ModuleOrganizer\Api\Category::class);
rex_api_function::register('module_organizer_toggle_user_favorite', KLXM\ModuleOrganizer\Api\ToggleUserFavorite::class);
rex_api_function::register('module_organizer_save_custom_icon', KLXM\ModuleOrganizer\Api\SaveCustomIcon::class);
rex_api_function::register('module_organizer_preview_custom_icon', KLXM\ModuleOrganizer\Api\PreviewCustomIcon::class);
rex_api_function::register('module_organizer_delete_custom_icon', KLXM\ModuleOrganizer\Api\DeleteCustomIcon::class);
rex_api_function::register('module_organizer_save_preview', KLXM\ModuleOrganizer\Api\SavePreview::class);
rex_api_function::register('module_organizer_editor_templates', KLXM\ModuleOrganizer\Api\EditorTemplates::class);

// Zuletzt verwendet: beim Einfügen eines Blocks für den Benutzer merken
rex_extension::register('SLICE_ADDED', static function (rex_extension_point $ep) {
    $user = rex::getUser();
    if (null !== $user) {
        KLXM\ModuleOrganizer\Repository\UserRecentRepository::add((int) $user->getId(), (int) $ep->getParam('module_id'));
    }
    return $ep->getSubject();
});

if (rex::isBackend() && rex::getUser()) {
    $bust = function (string $file) {
        return '?v=' . filemtime($this->getPath('assets/' . $file));
    };
    $bust = $bust->bindTo($this);

    $isContentEdit = 'index.php?page=content/edit' === rex_url::currentBackendPage();

    // Icon-Stil Duotone: Klasse und Farben am body – gilt für Popover, Overlay und die Organizer-Seite
    if (\KLXM\ModuleOrganizer\IconStyle::isDuotone()) {
        rex_extension::register('PAGE_BODY_ATTR', static function (rex_extension_point $ep): array {
            $attr = $ep->getSubject();
            $attr['class'][] = 'mo-icons-duotone';
            // der Core gibt Body-Attribute nur als Array aus (Werte mit Leerzeichen verbunden)
            $attr['style'] = (array) ($attr['style'] ?? []);
            $attr['style'][] = \KLXM\ModuleOrganizer\IconStyle::bodyStyle() . ';';
            return $attr;
        });
    }
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
    // aktive Locale aufgeloest und dem Backend-globalen "rex"-JS-Objekt
    // mitgegeben - gebraucht sowohl im Content-Editor (Popover/Overlay) als
    // auch auf der eigenen Organizer-Seite (Icon-Editor, Sortierstatus).
    // Ohne das zeigen alle t()-Aufrufe in den JS-Dateien nur die rohen
    // Lang-Keys statt Text. rex_view::setJsProperty() ist der REDAXO-eigene
    // Weg dafuer (siehe z.B. mediaplace/boot.php) - kein eigenes <script>-Tag
    // noetig, kein manuelles Nonce-Handling, da der Core-Layout
    // (fragments/core/top.php) das "rex"-Objekt bereits selbst mit
    // rex_response::getNonce() ausliefert.
    if ($isContentEdit || $isOwnPage) {
        $i18nMap = [];
        $langFile = $this->getPath('lang/de_de.lang');
        $langLines = is_file($langFile) ? file($langFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        if (false !== $langLines) {
            foreach ($langLines as $line) {
                if (preg_match('/^([a-zA-Z0-9_]+)\s*=/', $line, $m)) {
                    $i18nMap[$m[1]] = rex_i18n::msg($m[1]);
                }
            }
        }

        // Media-Base-URL: SVG-Dateien koennen NICHT ueber den Media-Manager
        // ausgeliefert werden (REDAXO-Kernverhalten, kein Bug hier) - fuer
        // media:-Icons mit .svg-Endung nutzen beide JS-Dateien stattdessen
        // diese rohe Medienpool-URL direkt, ohne rex_media_type-Umweg.
        rex_view::setJsProperty('module_organizer', [
            'i18n' => $i18nMap,
            'mediaBaseUrl' => rex_url::media(),
        ]);
    }
}
