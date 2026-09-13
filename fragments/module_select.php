<?php
/**
 * Override des Core-Fragments structure/plugins/content/fragments/module_select.php.
 * Erhaelt dieselben Variablen wie das Original von article_content_editor::getModuleSelect():
 *
 * @var rex_fragment $this
 * @var bool $block
 * @var string $button_label
 * @var array<int, array{id: string, key: string, title: string, href: string}> $items
 * @psalm-scope-this rex_fragment
 */

use KLXM\ModuleOrganizer\IconRegistry;
use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;
use KLXM\ModuleOrganizer\Repository\UserFavoriteRepository;

$items = $this->getVar('items');
$buttonLabel = $this->getVar('button_label');
$block = $this->getVar('block', false);
$displayMode = rex_addon::get('module_organizer')->getConfig('display_mode', 'popover');

$meta = ModuleMetaRepository::getAllIndexedByModuleId();
$categoryNames = array_column(CategoryRepository::getAll(), 'name', 'id');
$user = rex::getUser();
$userFavorites = null !== $user ? UserFavoriteRepository::getForUser((int) $user->getId()) : [];

$enrichedItems = [];
foreach ($items as $item) {
    $moduleId = (int) $item['id'];
    $moduleMeta = $meta[$moduleId] ?? null;
    $categoryId = $moduleMeta['category_id'] ?? null;
    $isGlobalFavorite = $moduleMeta['is_favorite'] ?? false;
    $isUserFavorite = isset($userFavorites[$moduleId]);
    $iconKey = $moduleMeta['icon_key'] ?? null;

    // custom:<id>-Icons liefern ihr fertiges SVG direkt inline mit, damit das
    // JS keinen zusaetzlichen Fetch braucht (media:-Icons nutzen stattdessen
    // eine <img>-URL, Presets werden per fetch() von der SVG-Datei geladen).
    $customSvg = null;
    if (IconRegistry::isCustomIcon($iconKey)) {
        $customIconId = IconRegistry::getCustomId($iconKey);
        $customIcon = null !== $customIconId ? CustomIconRepository::get($customIconId) : null;
        $customSvg = $customIcon['svg'] ?? null;
    }

    $enrichedItems[] = [
        'id' => $moduleId,
        'key' => $item['key'],
        'title' => $item['title'],
        // $item['href'] kommt vom Core bereits HTML-escaped (rex_context::
        // getUrl() escaped standardmaessig "&" zu "&amp;", gedacht fuer den
        // Einsatz in href="..."-HTML-Attributen). Wir liefern die URL hier
        // aber als reinen String im JSON-Payload, den das JS per
        // tile.href = item.href als JS-Property setzt (keine HTML-Analyse,
        // kein automatisches Dekodieren) - ohne Rueckdekodierung wuerde
        // buchstaeblich "&amp;" statt "&" im Query-String landen und die
        // Parameter (module_id, slice_id, ...) zerstoeren.
        'href' => htmlspecialchars_decode($item['href']),
        'category_id' => $categoryId,
        'category_name' => null !== $categoryId ? ($categoryNames[$categoryId] ?? null) : null,
        // is_favorite = global (admin-gepflegt) ODER persoenlich durch den
        // aktuellen User markiert - beide erscheinen gemeinsam in der
        // Favoriten-Gruppe der Blockauswahl. is_global_favorite bleibt
        // separat, damit der Stern in der Blockauswahl nur den persoenlichen
        // Anteil toggeln kann (globale Favoriten sind dort read-only,
        // Aenderung nur ueber den Organizer-Strukturbaum).
        'is_favorite' => $isGlobalFavorite || $isUserFavorite,
        'is_global_favorite' => $isGlobalFavorite,
        'is_user_favorite' => $isUserFavorite,
        'description' => $moduleMeta['description'] ?? null,
        'icon_key' => $iconKey,
        'custom_svg' => $customSvg,
        'priority' => $moduleMeta['priority'] ?? 0,
    ];
}
?>
<div class="dropdown<?= $block ? ' btn-block' : '' ?>">
    <button type="button" class="btn btn-default<?= $block ? ' btn-block' : '' ?> mo-trigger" data-mo-mode="<?= rex_escape($displayMode) ?>" data-mo-items="<?= rex_escape(json_encode($enrichedItems)) ?>">
        <b><?= $buttonLabel ?></b>
        <i class="rex-icon fa-th-large" aria-hidden="true"></i>
    </button>
</div>
