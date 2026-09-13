<?php

$addon = rex_addon::get('module_organizer');

if ('' !== rex_post('mo_save', 'string', '')) {
    $displayMode = rex_post('display_mode', 'string', 'popover');
    if (!in_array($displayMode, ['popover', 'overlay'], true)) {
        $displayMode = 'popover';
    }
    $addon->setConfig('display_mode', $displayMode);
    $addon->setConfig('warn_module_preview_conflict', '' !== rex_post('warn_module_preview_conflict', 'string', ''));
    echo rex_view::success($addon->i18n('settings_saved'));
}

$displayMode = $addon->getConfig('display_mode', 'popover');
$warnConflict = $addon->getConfig('warn_module_preview_conflict', true);

$form = '<form action="' . rex_url::currentBackendPage() . '" method="post">';
$form .= '<input type="hidden" name="mo_save" value="1">';
$form .= '<fieldset>';

$form .= '<div class="form-group"><label>' . $addon->i18n('display_mode') . '</label>';
$form .= '<div class="radio"><label><input type="radio" name="display_mode" value="popover"' . ('popover' === $displayMode ? ' checked' : '') . '> ' . $addon->i18n('display_mode_popover') . '</label></div>';
$form .= '<p class="help-block" style="margin-left:20px;margin-top:-4px;">' . $addon->i18n('display_mode_popover_notice') . '</p>';
$form .= '<div class="radio"><label><input type="radio" name="display_mode" value="overlay"' . ('overlay' === $displayMode ? ' checked' : '') . '> ' . $addon->i18n('display_mode_overlay') . '</label></div>';
$form .= '<p class="help-block" style="margin-left:20px;margin-top:-4px;">' . $addon->i18n('display_mode_overlay_notice') . '</p>';
$form .= '</div>';

$form .= '<div class="checkbox"><label><input type="checkbox" name="warn_module_preview_conflict" value="1"' . ($warnConflict ? ' checked' : '') . '> ' . $addon->i18n('warn_module_preview_conflict') . '</label></div>';

$form .= '</fieldset>';
$form .= '<div class="rex-form-panel-footer">';
$form .= '<button class="btn btn-save rex-form-aligned" type="submit"><i class="rex-icon rex-icon-save"></i> ' . $addon->i18n('save') . '</button>';
$form .= '</div>';
$form .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('settings'), false);
$fragment->setVar('body', $form, false);
echo $fragment->parse('core/page/section.php');
