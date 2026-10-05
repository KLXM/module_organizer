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

// Nutzung: wie oft und auf welchen Seiten jedes Modul eingesetzt ist (nur Live-Version, keine Arbeitsversion)
$usage = [];
$usageRows = rex_sql::factory()->getArray(
    'SELECT s.module_id, s.article_id, s.clang_id, COUNT(*) AS n
     FROM ' . rex::getTable('article_slice') . ' s
     WHERE s.revision = 0
     GROUP BY s.module_id, s.article_id, s.clang_id
     ORDER BY s.article_id',
);
foreach ($usageRows as $usageRow) {
    $moduleId = (int) $usageRow['module_id'];
    $usage[$moduleId] ??= ['count' => 0, 'pages' => []];
    $usage[$moduleId]['count'] += (int) $usageRow['n'];
    if (count($usage[$moduleId]['pages']) < 30) {
        $article = rex_article::get((int) $usageRow['article_id'], (int) $usageRow['clang_id']);
        if (null !== $article) {
            $usage[$moduleId]['pages'][] = [
                // Kategorie dazu, damit gleichnamige Seiten unterscheidbar sind
                'name' => $article->getName()
                    . (null !== $article->getCategory() && $article->getCategory()->getId() !== $article->getId() ? ' – ' . $article->getCategory()->getName() : (null !== $article->getParent() ? ' – ' . $article->getParent()->getName() : ''))
                    . (count(rex_clang::getAll()) > 1 ? ' (' . rex_clang::get((int) $usageRow['clang_id'])?->getCode() . ')' : ''),
                'url' => rex_url::backendPage('content/edit', ['article_id' => $article->getId(), 'clang' => $article->getClangId(), 'mode' => 'edit'], false),
                'n' => (int) $usageRow['n'],
            ];
        }
    }
}

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
        'preview_key' => $moduleMeta['preview_key'] ?? null,
        'usage' => $usage[$moduleId] ?? ['count' => 0, 'pages' => []],
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
function mo_render_module_row(array $row, array $meta, array $usage = []): string
{
    $moduleId = (int) $row['id'];
    $name = rex_escape(rex_i18n::translate((string) $row['name'], false));
    $isFavorite = $meta[$moduleId]['is_favorite'] ?? false;
    $html = '<li class="mo-tree-module" data-id="' . $moduleId . '">';
    $html .= '<i class="rex-icon fa-arrows mo-tree-handle" aria-hidden="true"></i> ';
    $html .= '<span class="mo-tree-module-name">' . $name . '</span>';
    $html .= $isFavorite ? ' <i class="rex-icon fa-star mo-tree-favorite" aria-hidden="true"></i>' : '';
    $count = (int) ($usage[$moduleId]['count'] ?? 0);
    $html .= ' <span class="mo-tree-usage' . (0 === $count ? ' is-unused' : '') . '" title="' . rex_escape(rex_i18n::msg('module_organizer_usage_title', (string) $count)) . '">' . $count . '</span>';
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
        $tree .= mo_render_module_row($row, $meta, $usage);
    }
    $tree .= '</ul>';
    $tree .= '</li>';
}

foreach ($categories as $category) {
    $tree .= '<li class="mo-tree-category" data-category-id="' . $category['id'] . '">';
    $tree .= '<div class="mo-tree-category-header">';
    $tree .= '<i class="rex-icon fa-caret-down mo-tree-toggle" aria-hidden="true"></i>';
    if ('' !== $category['color']) {
        $tree .= '<span class="mo-tree-category-color" style="--mo-cat-color:' . rex_escape($category['color']) . '" aria-hidden="true"></span>';
    }
    $tree .= '<span class="mo-tree-category-name" data-category-name="' . rex_escape($category['name']) . '">' . rex_escape($category['name']) . '</span>';
    // Bereiche: Kategorie nur in bestimmten Strukturkategorien anbieten
    $areaNames = array_values(array_filter(array_map(static fn (int $id): ?string => rex_category::get($id)?->getName(), $category['structure_ids'])));
    if ([] !== $areaNames) {
        $tree .= '<span class="mo-tree-category-areas-badge" title="' . rex_escape($addon->i18n('category_areas_only')) . '"><i class="rex-icon fa-sitemap" aria-hidden="true"></i> ' . rex_escape(implode(', ', $areaNames)) . ($category['structure_children'] ? ' +' : '') . '</span>';
    }
    $tree .= '<button type="button" class="mo-tree-category-areas" title="' . rex_escape($addon->i18n('category_settings')) . '" aria-expanded="false"><i class="rex-icon fa-sliders"></i></button>';
    $tree .= '<button type="button" class="mo-tree-category-rename" title="' . rex_escape($addon->i18n('category_rename')) . '"><i class="rex-icon fa-pencil"></i></button>';
    $tree .= '<button type="button" class="mo-tree-category-delete" title="' . rex_escape($addon->i18n('delete')) . '"><i class="rex-icon fa-trash-o"></i></button>';
    $tree .= '</div>';
    $select = new rex_category_select(false, false, false, false);
    $select->setName('structure_ids[]');
    $select->setId('mo-areas-' . $category['id']);
    $select->setMultiple();
    $select->setSize(8);
    $select->setAttribute('class', 'form-control');
    foreach ($category['structure_ids'] as $structureId) {
        $select->setSelected($structureId);
    }
    $tree .= '<div class="mo-tree-category-areas-panel" hidden>';
    $tree .= '<label for="mo-areas-' . $category['id'] . '">' . $addon->i18n('category_areas_label') . '</label>';
    $tree .= $select->get();
    $tree .= '<div class="checkbox"><label><input type="checkbox" class="mo-areas-children"' . ($category['structure_children'] ? ' checked' : '') . '> ' . $addon->i18n('category_areas_children') . '</label></div>';
    $tree .= '<p class="help-block">' . $addon->i18n('category_areas_notice') . '</p>';
    $tree .= '<div class="form-group mo-areas-color"><label><input type="checkbox" class="mo-areas-use-color"' . ('' !== $category['color'] ? ' checked' : '') . '> ' . $addon->i18n('category_color') . '</label> ';
    $tree .= '<input type="color" class="mo-areas-color-input" value="' . rex_escape('' !== $category['color'] ? $category['color'] : \KLXM\ModuleOrganizer\IconStyle::colors()[1]) . '" aria-label="' . rex_escape($addon->i18n('category_color')) . '">';
    $tree .= '<p class="help-block">' . $addon->i18n('category_color_notice') . '</p></div>';
    $tree .= '<button type="button" class="btn btn-save btn-xs mo-areas-save">' . $addon->i18n('save') . '</button> ';
    $tree .= '<button type="button" class="btn btn-default btn-xs mo-areas-reset">' . $addon->i18n('category_areas_reset') . '</button>';
    $tree .= '</div>';
    $tree .= '<ul class="mo-tree-modules" data-category-id="' . $category['id'] . '">';
    foreach ($byCategory[$category['id']] as $row) {
        $tree .= mo_render_module_row($row, $meta, $usage);
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
    $tree .= mo_render_module_row($row, $meta, $usage);
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

$sidebar .= '<div class="mo-usage" id="mo-usage" data-label-none="' . rex_escape($addon->i18n('usage_none')) . '" data-label-count="' . rex_escape($addon->i18n('usage_count')) . '" data-label-more="' . rex_escape($addon->i18n('usage_more')) . '"></div>';
$sidebar .= '<div class="checkbox"><label><input type="checkbox" id="mo-field-favorite"> ' . $addon->i18n('favorite') . '</label></div>';

$sidebar .= '<div class="form-group"><label for="mo-field-description">' . $addon->i18n('description') . '</label>';
$sidebar .= '<textarea class="form-control" id="mo-field-description" rows="2" maxlength="160"></textarea>';
$sidebar .= '<p class="help-block">' . $addon->i18n('description_notice') . '</p></div>';

// Vorschaubild (Darstellungen „Kacheln mit Vorschaubildern“ und „Liste mit Vorschau“)
$previewOptions = '<option value="">' . rex_escape($addon->i18n('preview_auto')) . '</option>';
$previewOptions .= '<option value="none">' . rex_escape($addon->i18n('preview_none')) . '</option>';
// eigene, im Editor gezeichnete Vorschaubilder (custom:<id>)
$customPreviews = CustomIconRepository::getAll('preview');
$customPreviewData = [];
if ([] !== $customPreviews) {
    $previewOptions .= '<optgroup label="' . rex_escape($addon->i18n('preview_own')) . '">';
    foreach ($customPreviews as $customPreview) {
        $title = $customPreview['title'] ?: ('#' . $customPreview['id']);
        $previewOptions .= '<option value="custom:' . $customPreview['id'] . '">' . rex_escape($title) . '</option>';
        $customPreviewData[$customPreview['id']] = ['svg' => $customPreview['svg'], 'title' => $title, 'shapes' => null !== $customPreview['shapes'] ? json_decode($customPreview['shapes'], true) : null];
    }
    $previewOptions .= '</optgroup><optgroup label="' . rex_escape($addon->i18n('editor_template_presets')) . '">';
}
foreach (\KLXM\ModuleOrganizer\PreviewRegistry::getPresetLabels() as $previewKey => $previewLabel) {
    $previewOptions .= '<option value="' . rex_escape($previewKey) . '">' . rex_escape($previewLabel) . '</option>';
}
if ([] !== $customPreviews) {
    $previewOptions .= '</optgroup>';
}
$sidebar .= '<div class="form-group mo-preview-field"><label for="mo-field-preview">' . $addon->i18n('preview') . '</label>';
$sidebar .= '<select class="form-control" id="mo-field-preview">' . $previewOptions . '<option value="media" hidden></option></select>';
$sidebar .= '<div class="mo-preview-actions">';
$sidebar .= '<button type="button" id="mo-preview-draw" class="btn btn-default btn-xs"><i class="rex-icon fa-pen-ruler"></i> ' . $addon->i18n('preview_draw') . '</button>';
$sidebar .= '<button type="button" id="mo-preview-edit" class="btn btn-default btn-xs" hidden><i class="rex-icon fa-pen"></i> ' . $addon->i18n('preview_edit') . '</button>';
if ($mediaPlaceAvailable) {
    $sidebar .= '<button type="button" id="mo-preview-media-pick" class="btn btn-default btn-xs"><i class="rex-icon fa-image"></i> ' . $addon->i18n('preview_media_pick') . '</button>';
}
$sidebar .= '</div>';
$sidebar .= '<div class="mo-preview-thumb" id="mo-preview-thumb"></div>';
$sidebar .= '<p class="help-block">' . $addon->i18n('preview_notice') . '</p></div>';

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
        if (null !== $customIcon['shapes']) {
            $sidebar .= '<button type="button" class="mo-custom-icon-edit" data-custom-icon-id="' . $customIcon['id'] . '" data-title="' . rex_escape((string) $customIcon['title']) . '" data-shapes="' . rex_escape($customIcon['shapes']) . '" title="' . rex_escape($addon->i18n('icon_edit')) . '" aria-label="' . rex_escape($addon->i18n('icon_edit')) . '"><i class="rex-icon fa-pen" aria-hidden="true"></i></button>';
        }
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

$sidebar .= '<div class="mo-icon-actions">';
$sidebar .= '<button type="button" id="mo-icon-editor-open" class="btn btn-default btn-sm"><i class="rex-icon fa-pencil-square-o"></i> ' . $addon->i18n('icon_editor_open') . '</button>';
$sidebar .= '<button type="button" id="mo-svg-paste-open" class="btn btn-default btn-sm" aria-expanded="false" aria-controls="mo-svg-paste"><i class="rex-icon fa-code"></i> ' . $addon->i18n('icon_svg_paste_open') . '</button>';
$sidebar .= '</div>';
// SVG-Code einfügen: wird serverseitig bereinigt (SvgSanitizer), danach wie ein gezeichnetes Icon gespeichert
$sidebar .= '<div id="mo-svg-paste" class="mo-svg-paste" hidden>';
$sidebar .= '<div class="form-group"><label for="mo-svg-paste-title">' . $addon->i18n('icon_svg_paste_title') . '</label>';
$sidebar .= '<input type="text" class="form-control" id="mo-svg-paste-title" maxlength="100"></div>';
$sidebar .= '<div class="form-group"><label for="mo-svg-paste-code">' . $addon->i18n('icon_svg_paste_code') . '</label>';
$sidebar .= '<textarea class="form-control mo-svg-paste-code" id="mo-svg-paste-code" rows="5" spellcheck="false" placeholder="&lt;svg viewBox=&quot;0 0 24 18&quot;&gt;…&lt;/svg&gt;"></textarea>';
$sidebar .= '<p class="help-block">' . $addon->i18n('icon_svg_paste_notice') . '</p></div>';
$sidebar .= '<p class="mo-svg-paste-error text-danger" id="mo-svg-paste-error" role="alert" hidden>' . $addon->i18n('icon_svg_paste_invalid') . '</p>';
$sidebar .= '<button type="button" id="mo-svg-paste-save" class="btn btn-save btn-sm">' . $addon->i18n('icon_editor_save') . '</button> ';
$sidebar .= '<button type="button" id="mo-svg-paste-cancel" class="btn btn-default btn-sm">' . $addon->i18n('icon_editor_cancel') . '</button>';
$sidebar .= '</div>';

$sidebar .= '</div>';

$sidebar .= '<div id="mo-save-status" class="mo-save-status" role="status" aria-live="polite"'
    . ' data-msg-saved="' . rex_escape($addon->i18n('meta_saved')) . '"'
    . ' data-msg-failed="' . rex_escape($addon->i18n('save_failed')) . '"></div>';
$sidebar .= '</form>';

$content = '<p class="help-block">' . $addon->i18n('tree_notice') . '</p>';
$content .= '<div id="mo-organizer" data-modules="' . rex_escape(json_encode($modulesData)) . '"'
    . ' data-previews-url="' . rex_escape($addon->getAssetsUrl('previews/')) . '"'
    . ' data-custom-previews="' . rex_escape((string) json_encode((object) $customPreviewData)) . '"'
    . ' data-icons-url="' . rex_escape($addon->getAssetsUrl('icons/')) . '"'
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
