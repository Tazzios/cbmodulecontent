<?php 
/**
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 * @author      Magnus Hasselquist <magnus.hasselquist@gmail.com> - http://mintekniskasida.blogspot.se/ till version 2.1.2
 * @author     Tazzios 2021
 */
 
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Shared CB list query builder, installed by pkg_cbmodulecontent as library 'cblisthelper'
require_once JPATH_LIBRARIES . '/cblisthelper/cblisthelper.php';






class modcbListHelper
{
    /**
     * Retrieves the Result
     *
     * @param array $params An object containing the module parameters
     * @access public
     */


public static function getData( $params )
	{
		
		//retrieve $rules
		$subform = $params->get('rules');
		$arr = (array) $subform;
		
		$rules = array();
		foreach ($arr as $value)
		{
			// a rule wins from a CB field with the same name, the first rule wins from other rules
			$tag_name = strtolower(trim($value->tag_name ?? ''));
			if ($tag_name <> '' and !isset($rules[$tag_name])) {
				$rules[$tag_name] = array(
					'accesslevel' => (array) ($value->accesslevel ?? array()),
					'htmlcode' => (string) ($value->htmlcode ?? ''),
					'htmlcode_no' => (string) ($value->htmlcode_no ?? ''),
				);
			}
		}


		// get all the fields that could possibly be part of template to be replaced. Columns with security data can not be used.
		$db = Factory::getDbo();
		$query = "SELECT fields.fieldid, fields.name, fields.type FROM #__comprofiler_fields as fields
			WHERE (fields.table = '#__users' OR fields.table = '#__comprofiler') and fields.tablecolumns <> ''
			and fields.name not in ('password','params','otpKey','otep','activation','requireReset','resetCount','lastResetTime','authProvider','cbactivation')";
		$db->setQuery($query);
		$fields = $db->loadAssocList();


    	$result=''; //reset result
		// Get the parameters
		$list_id = $params->get('listid');
		$list_orderby = $params->get('orderby');
		$list_sortorder = $params->get('sortorder');
		$list_template = $params->get('template');
		$list_textabove = $params->get('text-above');
		$list_textbelow = $params->get('text-below');
		$list_debug = $params->get('debug');	


		
		$cblistqueryArray = cblisthelper_createquery($list_id,null);
		$fetch_sql = $cblistqueryArray['cblistselect'];
		$cblistsortby = $cblistqueryArray['cblistsortby'];
		$cblistsortorder = $cblistqueryArray['cblistsortorder'];
		
		
	// the field to order by with its table, the list default if it does not exist
	$orderby_column = ($list_orderby=='list_default' or $list_orderby=='') ? '' : cblisthelper_column($list_orderby);
	$list_orderby = $orderby_column <> '' ? $orderby_column : $cblistsortby;



		// Sort order 
	   switch  ($list_sortorder) {
		   case "desc":
		   case "asc":
			$userlistorder = " ORDER BY ". $list_orderby . " " . $list_sortorder;
			break;
		   case "random":
			$userlistorder = ' ORDER BY rand()';
			break;
		default:
			// Default way to order
			$userlistorder = " ORDER BY ". $list_orderby . " " . $cblistsortorder;
			break;
	   }

	// echo $fetch_sql . "<br>";
	//$fetch_sql .= ' GROUP BY u.id';
	//Add ordering if list is configured for that
	if ($userlistorder <>'') { $fetch_sql .= " ".$userlistorder; }
		
	//Apply limit, 0 is all users
	$userlimit = (int) $params->get('user-limit', 10);
	if ($userlimit > 0) { $fetch_sql .= " LIMIT ".$userlimit; }

	// autofit or fixed amount of columns
	if ($params->get('columns') == 0 ) { 
		$columns= "auto-fit";
	} 
	else {
		$columns= $params->get('columns');
	}
			
	$minwidth = "5" ;/* prevent errors and give default value when not is numeric*/
	if (is_numeric($params->get('Minwidth'))) {
		$minwidth = $params->get('Minwidth') ;
	}		
		
	$result .= " <div style=\" margin: 0 auto; display: grid; grid-gap: 0.2rem;grid-template-columns: repeat(". $columns .", minmax(".$minwidth."rem, 1fr));\" class=\"cblist\"> " ;

	// Now, lets use the final SQL to get all Users from Joomla/CB
	
	$debug_text= '';
	// The query is only shown to Super Users
	$list_debug = ($list_debug == 1 and Factory::getUser()->authorise('core.admin')) ? 1 : 0;
	if ($list_debug == 1) {  $debug_text .= "<p>DEBUG: <pre>".str_replace(array('{', '}'), array('&#123;', '&#125;'), htmlspecialchars($fetch_sql, ENT_QUOTES, 'UTF-8'))."</pre></p>"; }
	$db->setQuery($fetch_sql);
	$persons = $db->loadAssocList();
	$escape = (int) $params->get('escape_values', 1) == 1;
	if (!empty($persons)){
		foreach ($persons as $person) {
		 	// Lets loop over the Users and create the output using the Template, replacing [fileds] in Template
			$result .= "<div style=\"padding: 5px;overflow-wrap: break-word;\" class=\"cblist-user\" >". db_field_replace($list_template, $person['id'],$rules,$fields,$escape) ."</div >" ;
		}
	} else if ($list_debug == 1) { $debug_text .= "<p>DEBUG: Empty list?!</p>"; }

	$result .= " </div >";
			

	$content = $list_textabove . $debug_text . $result . $list_textbelow;

	// Trigger onContentPrepare so content plugins can process tags in the output. content.prepare passes an object, Joomla 5+ does not accept a string here
	return HTMLHelper::_('content.prepare', $content, $params, 'mod_cblistmodule.content');

    	}
		
		
}

function db_field_replace($before_str, $user_id,$rules,$fields,$escape=true) {

	//Get data from the user to show
	$db = Factory::getDbo();
	$query = "select * from #__users inner join #__comprofiler on #__users.id = #__comprofiler.user_id WHERE #__users.id =". (int) $user_id;
	$db->setQuery($query);
	$person = $db->loadAssoc();

	// The value for each [tag]
	$values = array(
		'id' => array('value' => (string) (int) $user_id, 'hasdata' => true),
		'user_id' => array('value' => (string) (int) $user_id, 'hasdata' => true),
		'site_url' => array('value' => Uri::root(), 'hasdata' => true),
		// only made when the tag is used
		'profile_url' => array('value' => function () use ($user_id) {
			return Route::_('index.php?option=com_comprofiler&view=userprofile&user=' . (int) $user_id);
		}, 'hasdata' => true),
	);

	foreach ($fields as $field) {
		$fieldtouse = $field['name'];
		$fieldtype = $field['type'];

		/*set value to insert for normal fields*/
		$datatoinsert = isset($person[$fieldtouse]) ? (string) $person[$fieldtouse] : '';
		$hasdata = !empty($datatoinsert);

		/*set value to insert for images */
		// if it is an image check the approved and create full url
		//if there is an '[fieldname]approved' column it is an image.
		if (isset($person[$fieldtouse.'approved']) ) {
			if ( $person[$fieldtouse.'approved']==0 or !$hasdata ) {
				$hasdata = false;
				$datatoinsert = 'no image available';
			} else {
				//url to the default canvas images are incorrect in stored in the database
				if ($fieldtouse=='canvas') {
					$datatoinsert = str_ireplace('Gallery/', 'gallery/canvas/', $datatoinsert);
				}
				//create the full image path
				$datatoinsert =  Uri::base(). "images/comprofiler/" .$datatoinsert;
			}
		}

		/*set value to insert for multiple value fields */
		//Fieldtypes with a label name in the comprofiler_field_values
		// TODO normal checkbox currently returns a 0 or 1
		if ( $hasdata and ($fieldtype=='multicheckbox' or $fieldtype=='multiselect' or $fieldtype=='select' or $fieldtype=='radio')) {
			$labels = array();
			foreach (explode("|*|", $datatoinsert) as $value)	{
				//Get label from value, of this field only. Show the value if there is no label
				$db->setQuery("select fieldlabel from #__comprofiler_field_values WHERE fieldid = ". (int) $field['fieldid'] ." and fieldtitle = ". $db->quote($value));
				$label = (array) $db->loadAssoc();
				$labels[] = !empty($label['fieldlabel']) ? $label['fieldlabel'] : $value;
			}
			$datatoinsert = implode(' ', $labels);
		}

		// User data may not break out of the html
		if ($escape) {
			$datatoinsert = htmlspecialchars($datatoinsert, ENT_QUOTES, 'UTF-8');
		}
		// User data may not contain tags for content plugins, the output goes through them (content.prepare)
		$datatoinsert = str_replace(array('{', '}'), array('&#123;', '&#125;'), $datatoinsert);

		$values[strtolower($fieldtouse)] = array('value' => $datatoinsert, 'hasdata' => $hasdata);
	}

	return cblistmodule_replacetags((string) $before_str, $values, $rules, Factory::getUser()->getAuthorisedViewLevels(), array());
}

// Replaces the [tags] in a text of the administrator (template or rule). Tags in user data are not replaced.
function cblistmodule_replacetags($text, $values, $rules, $accesslevels, $openrules) {

	return preg_replace_callback('/\[([^\[\]]+)\]/', function ($match) use ($values, $rules, $accesslevels, $openrules) {
		$name = strtolower(trim($match[1]));

		// A rule can have the same name as an CB field. Inside its own rule the tag is the value of the field, this also stops loops.
		if (isset($rules[$name]) and !in_array($name, $openrules)) {
			$rule = $rules[$name];
			// if not set show the data, set to empty when not autorised
			if (!empty($rule['accesslevel']) and count(array_intersect($accesslevels, $rule['accesslevel'])) == 0) {
				return '';
			}
			// a rule without a field always has data
			$html = (!isset($values[$name]) or $values[$name]['hasdata']) ? $rule['htmlcode'] : $rule['htmlcode_no'];
			$openrules[] = $name;
			return cblistmodule_replacetags($html, $values, $rules, $accesslevels, $openrules);
		}

		if (isset($values[$name])) {
			$value = $values[$name]['value'];
			return $value instanceof Closure ? $value() : $value;
		}

		// the tag of a rule inside itself without a field is empty, an unknown tag is not changed
		return isset($rules[$name]) ? '' : $match[0];
	}, $text);
}
