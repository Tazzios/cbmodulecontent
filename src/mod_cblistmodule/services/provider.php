<?php

/**
 * @package    CB module & content
 * @copyright  2014 Magnus Hasselquist, 2021 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\Tazzios\\Module\\CbList'));
        $container->registerServiceProvider(new HelperFactory('\\Tazzios\\Module\\CbList\\Site\\Helper'));
        $container->registerServiceProvider(new Module());
    }
};
