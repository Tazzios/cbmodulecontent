<?php

/**
 * @package    CB module & content
 * @copyright  2014 Magnus Hasselquist, 2021 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Module\Cblistmodule\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher of mod_cblistmodule.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Shows the layout. The output goes through the content plugins, so tags like {cbprofile} work in the module.
     *
     * @return  void
     */
    public function dispatch()
    {
        ob_start();

        try {
            parent::dispatch();
        } finally {
            $output = ob_get_clean();
        }

        echo HTMLHelper::_('content.prepare', $output, new Registry($this->module->params), 'mod_cblistmodule.content');
    }

    /**
     * @return  array
     */
    protected function getLayoutData()
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];
        $helper = $this->getHelperFactory()->getHelper('CbListHelper');

        return $data + $helper->getList($params, $this->getApplication()->getIdentity()) + [
            'columns'   => $helper->getColumns($params),
            'minWidth'  => $helper->getMinWidth($params),
            'textAbove' => (string) $params->get('text-above', ''),
            'textBelow' => (string) $params->get('text-below', ''),
        ];
    }
}
