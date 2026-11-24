<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/joomla.php';
require_once __DIR__ . '/Stubs/Site.php';

define('_JEXEC', 1);
// The module and the plugin load the library from JPATH_LIBRARIES, as on a live site.
define('JPATH_LIBRARIES', sys_get_temp_dir() . '/cbmodulecontent-test-' . getmypid() . '/libraries');

mkdir(JPATH_LIBRARIES . '/cblisthelper', 0700, true);
copy(dirname(__DIR__) . '/src/lib_cblisthelper/cblisthelper.php', JPATH_LIBRARIES . '/cblisthelper/cblisthelper.php');
register_shutdown_function(static function (): void {
    @unlink(JPATH_LIBRARIES . '/cblisthelper/cblisthelper.php');
    @rmdir(JPATH_LIBRARIES . '/cblisthelper');
    @rmdir(JPATH_LIBRARIES);
    @rmdir(dirname(JPATH_LIBRARIES));
});

require_once dirname(__DIR__) . '/src/plg_content_cbprofile/cbprofile.php';
require_once dirname(__DIR__) . '/src/mod_cblistmodule/helper.php';
