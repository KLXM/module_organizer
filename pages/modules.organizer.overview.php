<?php

use KLXM\ModuleOrganizer\IconRegistry;
use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;

$addon = rex_addon::get('module_organizer');

$conflicts = array_values(array_filter(
    ['module_preview', 'nv_modulepreview'],
    static fn ($name) => rex_addon::exists($name) && rex_addon::get($name)->isAvailable(),
));
if ([] !== $conflicts && $addon->getConfig('warn_module_preview_conflict', true)) {
    echo rex_view::warning($addon->i18n('conflict_warning', implode(', ', $conflicts)));
}

$iconLabels = IconRegistry::getPresetLabels();
$mediaPlaceAvailable = rex_addon::exists('mediaplace') && rex_addon::get('mediaplace')->isAvailable();

$sql = 'SELECT m.id, m.name, m.key
        FROM ' . rex::getTable('module') . ' m
        LEFT JOIN ' . rex::getTable('module_organizer_module') . ' mo ON mo.module_id = m.id
        ORDER BY COALESCE(mo.priority, 999999), m.name';
$rows = rex_sql::factory()->getArray($sql);

$meta = ModuleMetaRepository::getAllIndexedByModuleId();
$categories = CategoryRepository::getAll();

// Alle Modul-Metadaten als JSON einbetten, damit die Sidebar (module_organizer_organizer.js)
// beim Klick auf ein Element ohne AJAX-Request die aktuellen Werte anzeigen kann -
// Vorbild: mform-Formbuilder-Sidebar (Klick -> sofortige Properties-Anzeige).
$modulesData = [];
foreach ($rows as $row) {
    $moduleId = (int) $row['id'];
    $moduleMeta = $meta[$moduleId] ?? null;
    $modulesData[$moduleId] = [
        'name' => rex_i18n::translate((string) $row['name'], false),
        'key' => $row['key'],
        'category_id' => $moduleMeta['category_id'] ?? null,
        'is_favorite' => $moduleMeta['is_favorite'] ?? false,
        'description' => $moduleMeta['description'] ?? null,
        'icon_key' => $moduleMeta['icon_key'] ?? null,
    ];
}

// Strukturbaum: 1. Ebene = Kategorien (inkl. virtuelle "Favoriten"-Gruppe
// ganz oben und "ohne Kategorie" ganz unten), 2. Ebene = Module darin.
// Favoriten ist eine rein visuelle Gruppe (basiert auf is_favorite), keine
// echte Kategorie in der DB - ein favorisiertes Modul behaelt seine
// tatsaechliche category_id und erscheint dort zusaetzlich zur Favoriten-
// Gruppe wieder in der 2. Ebene seiner echten Kategorie.
$favoriteRows = [];
$byCategory = [];
$uncategorizedRows = [];
foreach ($categories as $category) {
    $byCategory[$category['id']] = [];
}
foreach ($rows as $row) {
    $moduleId = (int) $row['id'];
    $moduleMeta = $meta[$moduleId] ?? null;
    if ($moduleMeta['is_favorite'] ?? false) {
        $favoriteRows[] = $row;
    }
    $categoryId = $moduleMeta['category_id'] ?? null;
    if (null !== $categoryId && isset($byCategory[$categoryId])) {
        $byCategory[$categoryId][] = $row;
    } else {
        $uncategorizedRows[] = $row;
    }
}

/**
 * @param array<string, mixed> $row
 * @param array<int, array{id: int, module_id: int, category_id: ?int, is_favorite: bool, description: ?string, icon_key: ?string, priority: int}> $meta
 */
function mo_render_module_row(array $row, array $meta): string
{
    $moduleId = (int) $row['id'];
    $name = rex_escape(rex_i18n::translate((string) $row['name'], false));
    $isFavorite = $meta[$moduleId]['is_favorite'] ?? false;
    $html = '<li class="mo-tree-module" data-id="' . $moduleId . '">';
    $html .= '<i class="rex-icon fa-arrows mo-tree-handle" aria-hidden="true"></i> ';
    $html .= '<span class="mo-tree-module-name">' . $name . '</span>';
    $html .= $isFavorite ? ' <i class="rex-icon fa-star mo-tree-favorite" aria-hidden="true"></i>' : '';
    $html .= '</li>';

    return $html;
}

$tree = '<ul id="mo-tree" class="mo-tree">';

if ([] !== $favoriteRows) {
    $tree .= '<li class="mo-tree-category mo-tree-category-favorites" data-category-id="0">';
    $tree .= '<div class="mo-tree-category-header">';
    $tree .= '<i class="rex-icon fa-caret-down mo-tree-toggle" aria-hidden="true"></i>';
    $tree .= '<i class="rex-icon fa-star mo-tree-category-icon" aria-hidden="true"></i>';
    $tree .= '<span class="mo-tree-category-name">' . $addon->i18n('filter_favorites') . '</span>';
    $tree .= '</div>';
    $tree .= '<ul class="mo-tree-modules mo-tree-modules-readonly" data-category-id="favorites">';
    foreach ($favoriteRows as $row) {
        $tree .= mo_render_module_row($row, $meta);
    }
    $tree .= '</ul>';
    $tree .= '</li>';
}

foreach ($categories as $category) {
    $tree .= '<li class="mo-tree-category" data-category-id="' . $category['id'] . '">';
    $tree .= '<div class="mo-tree-category-header">';
    $tree .= '<i class="rex-icon fa-caret-down mo-tree-toggle" aria-hidden="true"></i>';
    $tree .= '<span class="mo-tree-category-name" data-category-name="' . rex_escape($category['name']) . '">' . rex_escape($category['name']) . '</span>';
    $tree .= '<button type="button" class="mo-tree-category-rename" title="' . rex_escape($addon->i18n('category_rename')) . '"><i class="rex-icon fa-pencil"></i></button>';
    $tree .= '<button type="button" class="mo-tree-category-delete" title="' . rex_escape($addon->i18n('delete')) . '"><i class="rex-icon fa-trash-o"></i></button>';
    $tree .= '</div>';
    $tree .= '<ul class="mo-tree-modules" data-category-id="' . $category['id'] . '">';
    foreach ($byCategory[$category['id']] as $row) {
        $tree .= mo_render_module_row($row, $meta);
    }
    $tree .= '</ul>';
    $tree .= '</li>';
}

$tree .= '<li class="mo-tree-category mo-tree-category-uncategorized" data-category-id="0">';
$tree .= '<div class="mo-tree-category-header">';
$tree .= '<i class="rex-icon fa-caret-down mo-tree-toggle" aria-hidden="true"></i>';
$tree .= '<span class="mo-tree-category-name">' . $addon->i18n('no_category') . '</span>';
$tree .= '</div>';
$tree .= '<ul class="mo-tree-modules" data-category-id="0">';
foreach ($uncategorizedRows as $row) {
    $tree .= mo_render_module_row($row, $meta);
}
$tree .= '</ul>';
$tree .= '</li>';

$tree .= '</ul>';
$tree .= '<button type="button" id="mo-tree-add-category" class="btn btn-default btn-sm"><i class="rex-icon fa-plus"></i> ' . $addon->i18n('category_add') . '</button>';

$iconOptionsHtml = '';
foreach ($iconLabels as $iconKey => $iconLabel) {
    $iconPath = $addon->getPath('assets/icons/layout-' . $iconKey . '.svg');
    $iconSvg = is_file($iconPath) ? file_get_contents($iconPath) : '';
    $iconOptionsHtml .= '<label class="mo-icon-radio" data-icon-option="' . rex_escape($iconKey) . '">';
    $iconOptionsHtml .= '<input type="radio" name="mo-icon-key" value="' . rex_escape($iconKey) . '">';
    $iconOptionsHtml .= $iconSvg;
    $iconOptionsHtml .= '<span>' . rex_escape($iconLabel) . '</span></label>';
}

$sidebar = '<div id="mo-sidebar-empty" class="mo-sidebar-empty">' . $addon->i18n('sidebar_empty_hint') . '</div>';
$sidebar .= '<form id="mo-sidebar-form" class="mo-sidebar-form" hidden>';
$sidebar .= '<input type="hidden" id="mo-field-module-id" value="">';
$sidebar .= '<h4 id="mo-sidebar-title" class="mo-sidebar-title"></h4>';

$sidebar .= '<div class="checkbox"><label><input type="checkbox" id="mo-field-favorite"> ' . $addon->i18n('favorite') . '</label></div>';

$sidebar .= '<div class="form-group"><label for="mo-field-description">' . $addon->i18n('description') . '</label>';
$sidebar .= '<textarea class="form-control" id="mo-field-description" rows="2" maxlength="160"></textarea>';
$sidebar .= '<p class="help-block">' . $addon->i18n('description_notice') . '</p></div>';

$sidebar .= '<div class="form-group"><label>' . $addon->i18n('icon') . '</label>';
$sidebar .= '<div class="mo-icon-radio-grid">';
$sidebar .= '<label class="mo-icon-radio" data-icon-option="">';
$sidebar .= '<input type="radio" name="mo-icon-key" value=""><span>' . $addon->i18n('no_icon') . '</span></label>';
$sidebar .= $iconOptionsHtml;
$sidebar .= '</div>';

$customIcons = CustomIconRepository::getAll();
if ([] !== $customIcons) {
    $sidebar .= '<div class="mo-custom-icon-gallery">';
    foreach ($customIcons as $customIcon) {
        $iconKey = 'custom:' . $customIcon['id'];
        $sidebar .= '<div class="mo-custom-icon-gallery-item">';
        $sidebar .= '<label class="mo-icon-radio" data-icon-option="' . rex_escape($iconKey) . '">';
        $sidebar .= '<input type="radio" name="mo-icon-key" value="' . rex_escape($iconKey) . '">';
        $sidebar .= $customIcon['svg'];
        $sidebar .= '<span>' . rex_escape($customIcon['title'] ?: ('#' . $customIcon['id'])) . '</span></label>';
        $sidebar .= '<button type="button" class="mo-custom-icon-delete" data-custom-icon-id="' . $customIcon['id'] . '" title="' . rex_escape($addon->i18n('delete')) . '">&times;</button>';
        $sidebar .= '</div>';
    }
    $sidebar .= '</div>';
}

if ($mediaPlaceAvailable) {
    $sidebar .= '<div class="mo-icon-media">';
    $sidebar .= '<div id="mo-icon-media-preview" class="mo-icon-media-preview" hidden>';
    $sidebar .= '<img id="mo-icon-media-preview-img" src="" alt="">';
    $sidebar .= '<span id="mo-icon-media-preview-name"></span>';
    $sidebar .= '<button type="button" id="mo-icon-media-remove" class="btn btn-default btn-xs">' . $addon->i18n('icon_media_remove') . '</button>';
    $sidebar .= '</div>';
    $sidebar .= '<button type="button" id="mo-icon-media-pick" class="btn btn-default btn-sm"><i class="rex-icon fa-photo-film"></i> ' . $addon->i18n('icon_media_pick') . '</button>';
    $sidebar .= '</div>';
}

$sidebar .= '<button type="button" id="mo-icon-editor-open" class="btn btn-default btn-sm"><i class="rex-icon fa-pencil-square-o"></i> ' . $addon->i18n('icon_editor_open') . '</button>';

$sidebar .= '</div>';

$sidebar .= '<div id="mo-save-status" class="mo-save-status" role="status" aria-live="polite"'
    . ' data-msg-saved="' . rex_escape($addon->i18n('meta_saved')) . '"'
    . ' data-msg-failed="' . rex_escape($addon->i18n('save_failed')) . '"></div>';
$sidebar .= '</form>';

$content = '<p class="help-block">' . $addon->i18n('tree_notice') . '</p>';
$content .= '<div id="mo-organizer" data-modules="' . rex_escape(json_encode($modulesData)) . '"'
    . ' data-order-msg-saved="' . rex_escape($addon->i18n('order_saved')) . '"'
    . ' data-order-msg-failed="' . rex_escape($addon->i18n('save_failed')) . '"'
    . ' data-category-name-prompt="' . rex_escape($addon->i18n('category_name_prompt')) . '"'
    . ' data-category-delete-confirm="' . rex_escape($addon->i18n('category_delete_confirm')) . '">';
$content .= '<div class="mo-organizer-tree">' . $tree . '</div>';
$content .= '<div class="mo-organizer-sidebar">' . $sidebar . '</div>';
$content .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('title'), false);
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
