<?php
/*
* @copyright   2022
* @author      Tazzios 
* @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/* Only show an approved avatar, without one just show the name */

?>


<a href="<?php echo URI::base();?>index.php?option=com_comprofiler&task=userProfile&user=<?php echo $userprofile['id']; ?>">
	 <?php echo $userprofile['name']; ?>
<?php if ($userprofile['avatarapproved']==1 and strlen((string) $userprofile['avatar'])>0 ) { ?>
	 <img src="<?php echo URI::base()."images/comprofiler/".$userprofile['avatar']; ?>" alt="<?php echo $userprofile['name']; ?>" title="<?php echo $userprofile['name']; ?>" class="cbprofile-avatar" width="<?php echo $imagesize['width']; ?>" height="<?php echo $imagesize['height']; ?>">
<?php } ?>
	</a>
						
