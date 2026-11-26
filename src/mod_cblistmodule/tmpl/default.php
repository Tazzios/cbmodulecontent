<?php

/**
 * @package    CB module & content
 * @copyright  2014 Magnus Hasselquist, 2021 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

/**
 * @var string[] $users      The HTML of each user, already safe to show
 * @var string   $debug      The query, only for Super Users who turned on debug
 * @var bool     $isEmpty    There are no users to show
 * @var string   $columns    Number of columns or auto-fit
 * @var string   $minWidth   Minimum width of a column in rem
 * @var string   $textAbove  HTML above the list
 * @var string   $textBelow  HTML under the list
 */

?>
<?php echo $textAbove; ?>
<?php if ($debug !== '') : ?>
    <p>DEBUG: <pre><?php echo $debug; ?></pre></p>
    <?php if ($isEmpty) : ?>
        <p>DEBUG: <?php echo Text::_('MOD_CBLISTMODULE_EMPTY_LIST'); ?></p>
    <?php endif; ?>
<?php endif; ?>
<div style="margin: 0 auto; display: grid; grid-gap: 0.2rem;grid-template-columns: repeat(<?php echo $columns; ?>, minmax(<?php echo $minWidth; ?>rem, 1fr));" class="cblist">
    <?php foreach ($users as $user) : ?>
        <div style="padding: 5px;overflow-wrap: break-word;" class="cblist-user"><?php echo $user; ?></div>
    <?php endforeach; ?>
</div>
<?php echo $textBelow; ?>
