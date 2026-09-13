<?php

rex_sql_table::get(rex::getTable('module_organizer_custom_icon'))->drop();
rex_sql_table::get(rex::getTable('module_organizer_user_favorite'))->drop();
rex_sql_table::get(rex::getTable('module_organizer_module'))->drop();
rex_sql_table::get(rex::getTable('module_organizer_category'))->drop();
