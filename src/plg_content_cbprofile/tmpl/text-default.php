<?php

/**
 * @package    CB module & content
 * @copyright  2022 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Uri\Uri;

/**
 * @var array $userprofile  The data of the user, safe to show
 * @var array $imagesize    The width and height of the avatar
 */

$hasAvatar = (int) $userprofile['avatarapproved'] === 1 && (string) $userprofile['avatar'] !== '';

?>
<a href="<?php echo Uri::base(); ?>index.php?option=com_comprofiler&task=userProfile&user=<?php echo $userprofile['id']; ?>">
    <?php echo $userprofile['name']; ?>
    <?php if ($hasAvatar) : // Only an approved avatar, otherwise just the name ?>
        <img src="<?php echo Uri::base() . 'images/comprofiler/' . $userprofile['avatar']; ?>" alt="<?php echo $userprofile['name']; ?>" title="<?php echo $userprofile['name']; ?>" class="cbprofile-avatar" width="<?php echo $imagesize['width']; ?>" height="<?php echo $imagesize['height']; ?>">
    <?php endif; ?>
</a>
