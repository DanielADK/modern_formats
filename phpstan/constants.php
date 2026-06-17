<?php

// Piwigo core constants, declared so PHPStan resolves them. Runtime values come
// from Piwigo itself; the values here are placeholders for static analysis only.

define('PHPWG_ROOT_PATH', './');
define('PHPWG_PLUGINS_PATH', './plugins/');
define('IMAGES_TABLE', 'images');
define('CATEGORIES_TABLE', 'categories');
define('IMAGE_CATEGORY_TABLE', 'image_category');
define('WS_TYPE_INT', 1);
define('WS_TYPE_POSITIVE', 2);
define('ACCESS_ADMINISTRATOR', 1);
define('EVENT_HANDLER_PRIORITY_NEUTRAL', 50);
