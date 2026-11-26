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
$avatar    = $hasAvatar
    ? Uri::base() . 'images/comprofiler/' . $userprofile['avatar']
    : Uri::base() . 'components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png';

?>
<a href="<?php echo Uri::base(); ?>index.php?option=com_comprofiler&task=userProfile&user=<?php echo $userprofile['id']; ?>">
    <img src="<?php echo $avatar; ?>" alt="<?php echo $userprofile['name']; ?>" title="<?php echo $userprofile['name']; ?>" class="cbprofile-avatar" width="<?php echo $imagesize['width']; ?>" height="<?php echo $imagesize['height']; ?>">
</a>
