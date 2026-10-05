<?php

rex_sql_table::get(rex::getTable('module_organizer_category'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('priority', 'int(11)', false, '0'))
    // Nur in diesen Strukturkategorien anbieten (kommagetrennte IDs, leer = überall), optional inkl. Unterkategorien
    ->ensureColumn(new rex_sql_column('structure_ids', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('structure_children', 'tinyint(1)', false, '1'))
    // Akzentfarbe der Icons dieser Kategorie (Duotone), leer = Palette
    ->ensureColumn(new rex_sql_column('color', 'varchar(7)', true))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('createuser', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updateuser', 'varchar(191)'))
    ->ensure();

rex_sql_table::get(rex::getTable('module_organizer_module'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('module_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('category_id', 'int(11)', true))
    ->ensureColumn(new rex_sql_column('is_favorite', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('icon_key', 'varchar(191)', true))
    // Vorschaubild: leer = automatisch aus dem Icon, „none“ = keins, Vorlagen-Schlüssel oder media:<datei>
    ->ensureColumn(new rex_sql_column('preview_key', 'varchar(191)', true))
    ->ensureColumn(new rex_sql_column('priority', 'int(11)', false, '0'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('createuser', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updateuser', 'varchar(191)'))
    ->ensureIndex(new rex_sql_index('module_id', ['module_id'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('module_organizer_user_favorite'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('module_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureIndex(new rex_sql_index('user_module', ['user_id', 'module_id'], rex_sql_index::UNIQUE))
    ->ensure();

// Zuletzt verwendete Module je Benutzer (Blockauswahl „Zuletzt verwendet“)
rex_sql_table::get(rex::getTable('module_organizer_user_recent'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('module_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('used_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('user_module', ['user_id', 'module_id'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('module_organizer_custom_icon'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('title', 'varchar(191)', true))
    ->ensureColumn(new rex_sql_column('svg', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('createuser', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updateuser', 'varchar(191)'))
    ->ensure();

// Vorhandene Module in bisheriger ID-Reihenfolge vorbefuellen, damit die
// Sortierseite von Anfang an sinnvolle priority-Werte zeigt statt lauter 0.
$existing = rex_sql::factory()->getArray(
    'SELECT module_id FROM ' . rex::getTable('module_organizer_module'),
);
if ([] === $existing) {
    $modules = rex_sql::factory()->getArray(
        'SELECT id FROM ' . rex::getTable('module') . ' ORDER BY id',
    );
    $insert = rex_sql::factory();
    foreach ($modules as $index => $module) {
        $insert->setTable(rex::getTable('module_organizer_module'));
        $insert->setValue('module_id', (int) $module['id']);
        $insert->setValue('priority', $index + 1);
        $insert->addGlobalCreateFields();
        $insert->addGlobalUpdateFields();
        $insert->insert();
    }
}
