<?php

use KLXM\ModuleOrganizer\IconStyle;
use KLXM\ModuleOrganizer\TitleFormatter;

$addon = rex_addon::get('module_organizer');

if ('' !== rex_post('mo_save', 'string', '')) {
    $displayMode = rex_post('display_mode', 'string', 'popover');
    if (!in_array($displayMode, ['popover', 'overlay'], true)) {
        $displayMode = 'popover';
    }
    $addon->setConfig('display_mode', $displayMode);
    $addon->setConfig('warn_module_preview_conflict', '' !== rex_post('warn_module_preview_conflict', 'string', ''));
    $addon->setConfig('strip_title_prefix', '' !== rex_post('strip_title_prefix', 'string', ''));
    $addon->setConfig('icon_style', 'duotone' === rex_post('icon_style', 'string', '') ? 'duotone' : 'mono');
    $palette = rex_post('icon_palette', 'string', 'redaxo');
    $addon->setConfig('icon_palette', isset(IconStyle::PALETTES[$palette]) || 'custom' === $palette ? $palette : 'redaxo');
    $addon->setConfig('icon_color_primary', IconStyle::color(rex_post('icon_color_primary', 'string', ''), IconStyle::PALETTES['redaxo'][1]));
    $addon->setConfig('icon_color_secondary', IconStyle::color(rex_post('icon_color_secondary', 'string', ''), IconStyle::PALETTES['redaxo'][2]));
    $pattern = trim(rex_post('title_prefix_pattern', 'string', ''));
    if ('' !== $pattern && !TitleFormatter::isValidPattern($pattern)) {
        echo rex_view::error($addon->i18n('title_prefix_pattern_invalid'));
    } else {
        $addon->setConfig('title_prefix_pattern', $pattern);
        echo rex_view::success($addon->i18n('settings_saved'));
    }
}

$displayMode = $addon->getConfig('display_mode', 'popover');
$warnConflict = $addon->getConfig('warn_module_preview_conflict', true);
$stripPrefix = TitleFormatter::isEnabled();
$iconStyle = IconStyle::isDuotone() ? 'duotone' : 'mono';
$iconPalette = (string) $addon->getConfig('icon_palette', 'redaxo');
[$colorPrimary, $colorSecondary] = IconStyle::colors();
$prefixPattern = (string) $addon->getConfig('title_prefix_pattern', '');

// Vorschau: so erscheinen die vorhandenen Module in der Blockauswahl
$preview = '';
foreach (array_slice(rex_sql::factory()->getArray('SELECT name FROM ' . rex::getTable('module') . ' ORDER BY name'), 0, 6) as $row) {
    $name = rex_i18n::translate((string) $row['name'], false);
    $preview .= '<li><code>' . rex_escape($name) . '</code> → <strong>' . rex_escape(TitleFormatter::display($name)) . '</strong></li>';
}

$form = '<form action="' . rex_url::currentBackendPage() . '" method="post">';
$form .= '<input type="hidden" name="mo_save" value="1">';
$form .= '<fieldset>';

$form .= '<div class="form-group"><label>' . $addon->i18n('display_mode') . '</label>';
$form .= '<div class="radio"><label><input type="radio" name="display_mode" value="popover"' . ('popover' === $displayMode ? ' checked' : '') . '> ' . $addon->i18n('display_mode_popover') . '</label></div>';
$form .= '<p class="help-block" style="margin-left:20px;margin-top:-4px;">' . $addon->i18n('display_mode_popover_notice') . '</p>';
$form .= '<div class="radio"><label><input type="radio" name="display_mode" value="overlay"' . ('overlay' === $displayMode ? ' checked' : '') . '> ' . $addon->i18n('display_mode_overlay') . '</label></div>';
$form .= '<p class="help-block" style="margin-left:20px;margin-top:-4px;">' . $addon->i18n('display_mode_overlay_notice') . '</p>';
$form .= '</div>';

// Icon-Stil mit Live-Vorschau
$previewIcons = '';
foreach (['hero', 'image-text', 'cards', 'gallery', 'calendar', 'timeline', 'downloads', 'contact', 'folder', 'wifi'] as $previewKey) {
    $file = $addon->getPath('assets/icons/layout-' . $previewKey . '.svg');
    $previewIcons .= is_file($file) ? '<span>' . file_get_contents($file) . '</span>' : '';
}
$form .= '<div class="form-group" id="mo-icon-style"><label>' . $addon->i18n('icon_style') . '</label>';
foreach (['mono', 'duotone'] as $style) {
    $form .= '<div class="radio"><label><input type="radio" name="icon_style" value="' . $style . '"' . ($iconStyle === $style ? ' checked' : '') . '> ' . $addon->i18n('icon_style_' . $style) . '</label></div>';
}
$form .= '<div class="mo-icon-palette" style="margin-left:20px">';
$form .= '<label>' . $addon->i18n('icon_palette') . '</label>';
foreach (IconStyle::PALETTES as $key => [$label, $p1, $p2]) {
    $form .= '<div class="radio"><label><input type="radio" name="icon_palette" value="' . $key . '" data-p1="' . $p1 . '" data-p2="' . $p2 . '"' . ($iconPalette === $key ? ' checked' : '') . '> ' . rex_escape($label)
        . ' <span class="mo-palette-swatch" style="background:' . $p1 . '"></span><span class="mo-palette-swatch" style="background:' . $p2 . '"></span></label></div>';
}
$form .= '<div class="radio"><label><input type="radio" name="icon_palette" value="custom"' . ('custom' === $iconPalette ? ' checked' : '') . '> ' . $addon->i18n('icon_palette_custom') . '</label> ';
$form .= '<label style="margin-left:12px;font-weight:400">' . $addon->i18n('icon_color_primary') . ' <input type="color" name="icon_color_primary" value="' . rex_escape($colorPrimary) . '"></label> ';
$form .= '<label style="margin-left:8px;font-weight:400">' . $addon->i18n('icon_color_secondary') . ' <input type="color" name="icon_color_secondary" value="' . rex_escape($colorSecondary) . '"></label></div>';
$form .= '</div>';
$form .= '<div class="mo-duo-preview' . ('duotone' === $iconStyle ? ' is-duotone' : '') . '" style="--mo-duo-1:' . rex_escape($colorPrimary) . ';--mo-duo-2:' . rex_escape($colorSecondary) . '">' . $previewIcons . '</div>';
$form .= '<p class="help-block">' . $addon->i18n('icon_style_notice') . '</p>';
$form .= '</div>';
$form .= '<script nonce="' . rex_response::getNonce() . '">(function () {
    var box = document.getElementById("mo-icon-style");
    var preview = box.querySelector(".mo-duo-preview");
    function update() {
        var style = box.querySelector("input[name=icon_style]:checked").value;
        var palette = box.querySelector("input[name=icon_palette]:checked");
        var p1 = palette && palette.value !== "custom" ? palette.getAttribute("data-p1") : box.querySelector("input[name=icon_color_primary]").value;
        var p2 = palette && palette.value !== "custom" ? palette.getAttribute("data-p2") : box.querySelector("input[name=icon_color_secondary]").value;
        preview.classList.toggle("is-duotone", style === "duotone");
        preview.style.setProperty("--mo-duo-1", p1);
        preview.style.setProperty("--mo-duo-2", p2);
        box.querySelector(".mo-icon-palette").style.opacity = style === "duotone" ? "1" : ".5";
    }
    box.addEventListener("input", update);
    box.addEventListener("change", update);
    update();
})();</script>';

$form .= '<div class="checkbox"><label><input type="checkbox" name="warn_module_preview_conflict" value="1"' . ($warnConflict ? ' checked' : '') . '> ' . $addon->i18n('warn_module_preview_conflict') . '</label></div>';

$form .= '<div class="form-group"><label>' . $addon->i18n('title_prefix') . '</label>';
$form .= '<div class="checkbox"><label><input type="checkbox" name="strip_title_prefix" value="1"' . ($stripPrefix ? ' checked' : '') . '> ' . $addon->i18n('strip_title_prefix') . '</label></div>';
$form .= '<label for="mo-title-prefix-pattern" class="control-label">' . $addon->i18n('title_prefix_pattern') . '</label>';
$form .= '<input type="text" class="form-control" id="mo-title-prefix-pattern" name="title_prefix_pattern" value="' . rex_escape($prefixPattern) . '" placeholder="' . rex_escape(TitleFormatter::DEFAULT_PATTERN) . '" spellcheck="false" style="font-family:monospace">';
$form .= '<p class="help-block">' . $addon->i18n('title_prefix_pattern_notice') . '</p>';
if ('' !== $preview) {
    $form .= '<p class="help-block">' . $addon->i18n('title_prefix_preview') . '</p><ul class="list-unstyled help-block">' . $preview . '</ul>';
}
$form .= '</div>';

$form .= '</fieldset>';
$form .= '<div class="rex-form-panel-footer">';
$form .= '<button class="btn btn-save rex-form-aligned" type="submit"><i class="rex-icon rex-icon-save"></i> ' . $addon->i18n('save') . '</button>';
$form .= '</div>';
$form .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('settings'), false);
$fragment->setVar('body', $form, false);
echo $fragment->parse('core/page/section.php');
