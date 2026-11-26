<?php

/**
 * @package    CB module & content
 * @copyright  2026 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;

return new class () implements InstallerScriptInterface {
    private const MINIMUM_PHP = '8.1.0';

    private const MINIMUM_JOOMLA = '5.0.0';

    /**
     * Files of versions before 5.1.0.
     */
    private const OLD_FILES = [
        'modules/mod_cblistmodule/mod_cblistmodule.php',
        'modules/mod_cblistmodule/helper.php',
        'modules/mod_cblistmodule/cblisthelper.php',
        'modules/mod_cblistmodule/index.html',
        'plugins/content/cbprofile/cbprofile.php',
        'plugins/content/cbprofile/cblisthelper.php',
        'plugins/content/cbprofile/index.html',
        'libraries/cblisthelper/cblisthelper.php',
        'libraries/cblisthelper/index.html',
    ];

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', self::MINIMUM_PHP), 'error');

            return false;
        }

        if (version_compare(JVERSION, self::MINIMUM_JOOMLA, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', self::MINIMUM_JOOMLA), 'error');

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        foreach (self::OLD_FILES as $file) {
            if (is_file(JPATH_ROOT . '/' . $file)) {
                @unlink(JPATH_ROOT . '/' . $file);
            }
        }

        return true;
    }
};
