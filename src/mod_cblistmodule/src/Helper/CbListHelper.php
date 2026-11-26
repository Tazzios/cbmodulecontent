<?php

/**
 * @package    CB module & content
 * @copyright  2014 Magnus Hasselquist, 2021 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Module\Cblistmodule\Site\Helper;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;
use Tazzios\Library\CbListHelper\ListQuery;
use Tazzios\Library\CbListHelper\UserData;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Gets the users of the list and renders them with the template.
 */
class CbListHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Columns with security data, these can not be used as tag.
     */
    private const HIDDEN = [
        'password', 'params', 'otpkey', 'otep', 'activation', 'requirereset', 'resetcount', 'lastresettime',
        'authprovider', 'cbactivation',
    ];

    /**
     * Field types with a label for each value.
     */
    private const LABELLED = ['multicheckbox', 'multiselect', 'select', 'radio'];

    /**
     * @param   Registry   $params  The module parameters.
     * @param   User|null  $user    The visitor.
     *
     * @return  array{users: string[], debug: string, isEmpty: bool}  The HTML of each user. The query is only given to Super Users.
     */
    public function getList(Registry $params, ?User $user): array
    {
        $db        = $this->getDatabase();
        $listQuery = new ListQuery($db);
        $list      = $listQuery->getListById((int) $params->get('listid'));
        $query     = $listQuery->getQuery($list)->order($this->getOrder($listQuery, $list, $params));
        $limit     = (int) $params->get('user-limit', 10);

        // 0 shows all users
        if ($limit > 0) {
            $query->setLimit($limit);
        }

        $ids   = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
        $debug = (int) $params->get('debug', 0) === 1 && $user !== null && $user->authorise('core.admin');

        return [
            'users'   => $ids ? $this->renderUsers($ids, $params, $user) : [],
            'debug'   => $debug ? UserData::safe((string) $query) : '',
            'isEmpty' => !$ids,
        ];
    }

    /**
     * The number of columns for the grid.
     */
    public function getColumns(Registry $params): string
    {
        $columns = (int) $params->get('columns', 0);

        return $columns > 0 ? (string) $columns : 'auto-fit';
    }

    /**
     * The minimum width of a column in rem.
     */
    public function getMinWidth(Registry $params): string
    {
        $width = $params->get('Minwidth');

        return is_numeric($width) ? (string) (float) $width : '5';
    }

    private function getOrder(ListQuery $listQuery, ?array $list, Registry $params): string
    {
        $direction = strtolower((string) $params->get('sortorder'));

        if ($direction === 'random') {
            return 'RAND()';
        }

        [$listColumn, $listDirection] = $listQuery->getOrder($list);

        $orderBy = (string) $params->get('orderby');
        $column  = \in_array($orderBy, ['', 'list_default'], true) ? '' : $listQuery->getColumn($orderBy);

        // The order of the list if the field does not exist
        $column = $column !== '' ? $column : $listColumn;

        return $column . ' ' . (\in_array($direction, ['asc', 'desc'], true) ? strtoupper($direction) : $listDirection);
    }

    /**
     * @param   int[]  $ids  The users to show, in order.
     *
     * @return  string[]
     */
    private function renderUsers(array $ids, Registry $params, ?User $visitor): array
    {
        $fields   = $this->getFields();
        $labels   = $this->getLabels($fields);
        $persons  = $this->getPersons($ids);
        $renderer = new TagRenderer((array) $params->get('rules'), $visitor ? $visitor->getAuthorisedViewLevels() : [1]);
        $template = (string) $params->get('template', '');
        $users    = [];

        foreach ($ids as $id) {
            if (isset($persons[$id])) {
                $users[] = $renderer->render($template, $this->getTags($id, $persons[$id], $fields, $labels));
            }
        }

        return $users;
    }

    /**
     * The CB fields that can be used as tag.
     */
    private function getFields(): array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['fieldid', 'name', 'type']))
            ->from($db->quoteName('#__comprofiler_fields'))
            ->where($db->quoteName('table') . ' IN (' . $db->quote('#__users') . ', ' . $db->quote('#__comprofiler') . ')')
            ->where($db->quoteName('tablecolumns') . ' <> ' . $db->quote(''));

        return array_filter(
            $db->setQuery($query)->loadAssocList() ?: [],
            static fn ($field) => !\in_array(strtolower($field['name']), self::HIDDEN, true)
        );
    }

    /**
     * The labels of the values, for each field.
     *
     * @return  array<int, array<string, string>>
     */
    private function getLabels(array $fields): array
    {
        $ids = [];

        foreach ($fields as $field) {
            if (\in_array($field['type'], self::LABELLED, true)) {
                $ids[] = (int) $field['fieldid'];
            }
        }

        if (!$ids) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['fieldid', 'fieldtitle', 'fieldlabel']))
            ->from($db->quoteName('#__comprofiler_field_values'))
            ->where($db->quoteName('fieldid') . ' IN (' . implode(',', $ids) . ')');

        $labels = [];

        foreach ($db->setQuery($query)->loadAssocList() ?: [] as $row) {
            if ((string) $row['fieldlabel'] !== '') {
                $labels[(int) $row['fieldid']][(string) $row['fieldtitle']] = (string) $row['fieldlabel'];
            }
        }

        return $labels;
    }

    /**
     * @param   int[]  $ids  The user ids.
     *
     * @return  array<int, array>  The data of the users by id.
     */
    private function getPersons(array $ids): array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select(['u.*', 'ue.*'])
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__comprofiler', 'ue') . ' ON ' . $db->quoteName('ue.user_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('u.id') . ' IN (' . implode(',', $ids) . ')');

        $persons = [];

        foreach ($db->setQuery($query)->loadAssocList() ?: [] as $row) {
            $persons[(int) $row['user_id']] = $row;
        }

        return $persons;
    }

    /**
     * The value of each tag for one user.
     *
     * @return  array<string, array{value: string|\Closure, hasData: bool}>
     */
    private function getTags(int $id, array $person, array $fields, array $labels): array
    {
        $tags = [
            'id'          => ['value' => (string) $id, 'hasData' => true],
            'user_id'     => ['value' => (string) $id, 'hasData' => true],
            'site_url'    => ['value' => Uri::root(), 'hasData' => true],
            // Only made when the tag is used
            'profile_url' => [
                'value'   => static fn () => Route::_('index.php?option=com_comprofiler&view=userprofile&user=' . $id),
                'hasData' => true,
            ],
        ];

        foreach ($fields as $field) {
            $name    = $field['name'];
            $value   = (string) ($person[$name] ?? '');
            $hasData = !empty($value);

            if (isset($person[$name . 'approved'])) {
                // An image, only shown when it is approved
                if ((int) $person[$name . 'approved'] === 0 || !$hasData) {
                    $hasData = false;
                    $value   = 'no image available';
                } else {
                    // The default canvas images are stored with an incorrect path
                    $value = $name === 'canvas' ? str_ireplace('Gallery/', 'gallery/canvas/', $value) : $value;
                    $value = Uri::base() . 'images/comprofiler/' . $value;
                }
            } elseif ($hasData && \in_array($field['type'], self::LABELLED, true)) {
                $fieldLabels = $labels[(int) $field['fieldid']] ?? [];
                $value       = implode(' ', array_map(static fn ($part) => $fieldLabels[$part] ?? $part, explode('|*|', $value)));
            }

            $tags[strtolower($name)] = ['value' => UserData::safe($value, $field['type']), 'hasData' => $hasData];
        }

        return $tags;
    }
}
