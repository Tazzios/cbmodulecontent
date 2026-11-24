<?php
/**
 * Shared helper for the "CB module & content" package (pkg_cbmodulecontent).
 *
 * Builds the query to get the profiles in the same way as the Community Builder list.
 * Used by both mod_cblistmodule and plg_content_cbprofile, load it with:
 *   require_once JPATH_LIBRARIES . '/cblisthelper/cblisthelper.php';
 *
 * @copyright   2022
 * @author      Tazzios
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

if (!function_exists('cblisthelper_createquery')) {

function cblisthelper_createquery($cblistid,$cblistname) {
	$where = '' ;
	if (!empty($cblistid)) {
		$where = 'listid = '. (int) $cblistid ;
	}
	else {
		$where = 'title = '. Factory::getDbo()->quote($cblistname);
	}

	// Obtain a database connection
	$db = Factory::getDbo();
	// Retrieve the selected list
	$query = $db->getQuery(true)
	->select('params')
	->select('usergroupids')
	->from('#__comprofiler_lists')
	->where($where . ' AND published=1');
		//->order('ordering ASC');
	$db->setQuery($query);

	// Load the List row.
	$row = $db->loadAssoc();

	// A query without users, for an unknown or unpublished list and a list without user groups
	$nousers = array(
		'cblistselect' => 'SELECT ue.id FROM #__users u JOIN #__comprofiler ue ON ue.id = u.id WHERE 1 = 0',
		'cblistsortby' => 'ue.id',
		'cblistsortorder' => 'ASC',
	);

	if (empty($row)) {
		return $nousers;
	}

	// Usergroups, the first one can be empty if you also have selected '--- Select User group (CTR/CMD-Click: multiple)---'
	$usergroupids = array_filter(array_map('intval', explode('|*|', str_replace(',', '|*|', (string) $row['usergroupids']))));
	if (empty($usergroupids)) {
		return $nousers;
	}

	// Process the filterfields to make it usefull for SQL query
	$json_a = (array) json_decode((string) $row['params'], true);
	$filter_mode = isset($json_a['filter_mode']) ? (int) $json_a['filter_mode'] : 0;
	$select_sql = '';

	if ($filter_mode == 0) {
		$conditions = array();
		$filters_basic = isset($json_a['filter_basic']) ? (array) $json_a['filter_basic'] : array();
		foreach ($filters_basic as $filter) {
			if (!empty($filter['column']) and !empty($filter['operator'])) {
				$conditions[] = cblisthelper_condition($filter['column'], $filter['operator'], isset($filter['value']) ? (string) $filter['value'] : '');
			}
		}
		$select_sql = implode(' AND ', $conditions);
	}
	else if ($filter_mode == 1) {
		$select_sql = isset($json_a['filter_advanced']) ? trim($json_a['filter_advanced']) : '';
	}

	// Set a base-sql for connecting users, fields and lists
	$fetch_sql = "SELECT DISTINCT ue.id FROM #__users u JOIN #__user_usergroup_map g ON g.`user_id` = u.`id` JOIN #__comprofiler ue ON ue.`id` = u.`id` WHERE g.group_id IN (".implode(',', $usergroupids).")";
	if (empty($json_a['list_show_blocked'])) {$fetch_sql.=" AND u.block = 0 ";}
	if (empty($json_a['list_show_unapproved'])) {$fetch_sql.=" AND ue.approved = 1 ";}
	if (empty($json_a['list_show_unconfirmed'])) {$fetch_sql.=" AND ue.confirmed = 1 ";}

	// add CB list filters only if there are any
	if ($select_sql <> '') $fetch_sql = $fetch_sql . " AND (" . $select_sql . ")";
	$cblistquery['cblistselect'] = $fetch_sql;

	//Add ordering if list is configured for that
	// order will be given seperates so it can be overwritten
	$sort = isset($json_a['sort_basic'][0]) ? (array) $json_a['sort_basic'][0] : array();
	$sortby = isset($sort['column']) ? cblisthelper_column($sort['column']) : '';
	$cblistquery['cblistsortby'] = $sortby <> '' ? $sortby : 'ue.id';
	$cblistquery['cblistsortorder'] = (isset($sort['direction']) and strtoupper($sort['direction']) == 'DESC') ? 'DESC' : 'ASC';

	return $cblistquery;

}

// The column name with its table (u or ue), so that it is never ambiguous. Empty if the column does not exist.
function cblisthelper_column($column) {

	$db = Factory::getDbo();
	$tables = array('u' => '#__users', 'ue' => '#__comprofiler');
	static $columns = array();

	$column = str_replace(array('`', ' '), '', (string) $column);
	$alias = '';
	if (strpos($column, '.') !== false) {
		list($alias, $column) = explode('.', $column, 2);
	}

	foreach ($tables as $key => $table) {
		if ($alias == '' or $alias == $key) {
			if (!isset($columns[$table])) {
				$columns[$table] = array_keys($db->getTableColumns($table));
			}
			foreach ($columns[$table] as $name) {
				if (strtolower($name) == strtolower($column)) {
					return $key . '.' . $db->quoteName($name);
				}
			}
		}
	}

	return '';
}

// One filter of a list. An unknown column or operator gives no users, showing too many users would be worse.
function cblisthelper_condition($column, $operator, $value) {

	$db = Factory::getDbo();
	$column = cblisthelper_column($column);
	$operator = strtoupper(trim($operator));

	// CB "not" operators also match users without a value
	$isnull = false;
	if (substr($operator, -8) == '||ISNULL') {
		$isnull = true;
		$operator = substr($operator, 0, -8);
	}

	if ($column == '') {
		return '(1 = 0)';
	}

	// add qoutes if value is text.
	$quoted = is_numeric($value) ? $value : $db->quote($value);

	switch ($operator) {
		case '=':
		case '<>':
		case '<':
		case '<=':
		case '>':
		case '>=':
		case 'REGEXP':
		case 'NOT REGEXP':
			$condition = $column . ' ' . $operator . ' ' . $quoted;
			break;

		case 'LIKE': //CB Does contain
		case 'NOT LIKE': //CB Does not contain
			// % and _ in the value are normal characters
			$like = str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), trim($value, '\'"'));
			$condition = $column . ' ' . $operator . ' ' . $db->quote('%' . $like . '%') . " ESCAPE '!'";
			break;

		case 'IN': //CB is one of
		case 'NOT IN': //CB is not one of
			$include = array();
			foreach (explode(',', $value) as $invalue) {
				$invalue = trim(trim($invalue), '\'"');
				$include[] = is_numeric($invalue) ? $invalue : $db->quote($invalue);
			}
			$condition = $column . ' ' . $operator . ' (' . implode(', ', $include) . ')';
			break;

		case 'EMPTY': //CB is empty
			$condition = $column . " = '' OR " . $column . ' IS NULL';
			break;

		case 'NOT EMPTY': //CB is not empty
			$condition = $column . " <> ''";
			break;

		default:
			return '(1 = 0)';
	}

	if ($isnull) {
		$condition .= ' OR ' . $column . ' IS NULL';
	}

	return '(' . $condition . ')';
}

// User data that is safe to show in html. Only a CB editor field keeps its html, without scripts.
function cblisthelper_safevalue($value, $fieldtype) {

	if ($fieldtype == 'editorta') {
		$tags = array('a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'dd', 'del', 'div', 'dl', 'dt', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'hr', 'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul');
		$attributes = array('href', 'src', 'alt', 'title', 'width', 'height', 'target', 'rel', 'class', 'colspan', 'rowspan');
		$value = InputFilter::getInstance($tags, $attributes, 0, 0, 1)->clean((string) $value, 'html');
	} else {
		$value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	// No tags for content plugins, the output goes through them (content.prepare)
	return str_replace(array('{', '}'), array('&#123;', '&#125;'), $value);
}

// The columns of the CB editor fields
function cblisthelper_editorfields() {

	$db = Factory::getDbo();
	$db->setQuery("SELECT fields.name FROM #__comprofiler_fields as fields WHERE fields.type = 'editorta'");

	return array_column((array) $db->loadAssocList(), 'name');
}

} // end function_exists
