<?php

/**
 * @package    CB module & content
 * @copyright  2022 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Library\CbListHelper;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the query to get the profiles in the same way as the Community Builder list.
 */
final class ListQuery
{
    /**
     * Tables that can be used in a filter or order, by their alias in the query.
     */
    private const TABLES = ['u' => '#__users', 'ue' => '#__comprofiler'];

    /**
     * @var array<string, string[]>  Column names by table alias.
     */
    private array $columns = [];

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Loads a published list by its id.
     *
     * @return  array{params: array, groups: int[]}|null  Null if there is no such list.
     */
    public function getListById(int $id): ?array
    {
        return $this->getList($this->db->quoteName('listid') . ' = ' . $id);
    }

    /**
     * Loads a published list by its name.
     *
     * @return  array{params: array, groups: int[]}|null  Null if there is no such list.
     */
    public function getListByTitle(string $title): ?array
    {
        return $this->getList($this->db->quoteName('title') . ' = ' . $this->db->quote($title));
    }

    /**
     * The query for the ids of the users in a list, without order and limit.
     *
     * @param   array|null  $list  The list, a missing list or a list without user groups has no users.
     */
    public function getQuery(?array $list): QueryInterface
    {
        $db    = $this->db;
        $query = $db->createQuery()
            ->select($db->quoteName('ue.id'))
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__comprofiler', 'ue') . ' ON ' . $db->quoteName('ue.id') . ' = ' . $db->quoteName('u.id'));

        if (empty($list['groups'])) {
            return $query->where('1 = 0');
        }

        // A user can be in more than one group of the list
        $groups = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__user_usergroup_map', 'g'))
            ->where($db->quoteName('g.user_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('g.group_id') . ' IN (' . implode(',', $list['groups']) . ')');

        $query->where('EXISTS (' . $groups . ')');

        $params = $list['params'];

        if (empty($params['list_show_blocked'])) {
            $query->where($db->quoteName('u.block') . ' = 0');
        }

        if (empty($params['list_show_unapproved'])) {
            $query->where($db->quoteName('ue.approved') . ' = 1');
        }

        if (empty($params['list_show_unconfirmed'])) {
            $query->where($db->quoteName('ue.confirmed') . ' = 1');
        }

        $filter = $this->getFilter($params);

        if ($filter !== '') {
            $query->where('(' . $filter . ')');
        }

        return $query;
    }

    /**
     * The order of the list itself.
     *
     * @return  array{0: string, 1: string}  Quoted column and direction.
     */
    public function getOrder(?array $list): array
    {
        $sort      = (array) ($list['params']['sort_basic'][0] ?? []);
        $column    = $this->getColumn((string) ($sort['column'] ?? ''));
        $direction = strtoupper((string) ($sort['direction'] ?? '')) === 'DESC' ? 'DESC' : 'ASC';

        return [$column !== '' ? $column : $this->db->quoteName('ue.id'), $direction];
    }

    /**
     * The quoted column with its table, so that it is never ambiguous.
     *
     * @return  string  Empty if the column does not exist.
     */
    public function getColumn(string $column): string
    {
        $column = str_replace(['`', ' '], '', $column);
        $alias  = '';

        if (str_contains($column, '.')) {
            [$alias, $column] = explode('.', $column, 2);
        }

        foreach (self::TABLES as $key => $table) {
            if ($alias !== '' && $alias !== $key) {
                continue;
            }

            $this->columns[$key] ??= array_keys($this->db->getTableColumns($table));

            foreach ($this->columns[$key] as $name) {
                if (strcasecmp($name, $column) === 0) {
                    return $this->db->quoteName($key . '.' . $name);
                }
            }
        }

        return '';
    }

    private function getList(string $where): ?array
    {
        $db    = $this->db;
        $query = $db->createQuery()
            ->select($db->quoteName(['params', 'usergroupids']))
            ->from($db->quoteName('#__comprofiler_lists'))
            ->where($where)
            ->where($db->quoteName('published') . ' = 1');

        $row = $db->setQuery($query)->loadAssoc();

        if (!$row) {
            return null;
        }

        // The first group can be empty, '--- Select User group ---' is selected too then
        $groups = explode('|*|', str_replace(',', '|*|', (string) $row['usergroupids']));

        return [
            'params' => (array) json_decode((string) $row['params'], true),
            'groups' => array_values(array_filter(array_map('intval', $groups))),
        ];
    }

    private function getFilter(array $params): string
    {
        // Advanced: the administrator wrote the SQL, the same as CB itself runs it
        if ((int) ($params['filter_mode'] ?? 0) === 1) {
            return trim((string) ($params['filter_advanced'] ?? ''));
        }

        $conditions = [];

        foreach ((array) ($params['filter_basic'] ?? []) as $filter) {
            if (!empty($filter['column']) && !empty($filter['operator'])) {
                $conditions[] = $this->getCondition(
                    (string) $filter['column'],
                    (string) $filter['operator'],
                    (string) ($filter['value'] ?? '')
                );
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * One filter of a list. An unknown column or operator gives no users, showing too many users would be worse.
     */
    private function getCondition(string $column, string $operator, string $value): string
    {
        $db       = $this->db;
        $column   = $this->getColumn($column);
        $operator = strtoupper(trim($operator));
        $isNull   = false;

        // The "not" operators of CB also match users without a value
        if (str_ends_with($operator, '||ISNULL')) {
            $isNull   = true;
            $operator = substr($operator, 0, -8);
        }

        if ($column === '') {
            return '(1 = 0)';
        }

        switch ($operator) {
            case '=':
            case '<>':
            case '<':
            case '<=':
            case '>':
            case '>=':
            case 'REGEXP':
            case 'NOT REGEXP':
                $condition = $column . ' ' . $operator . ' ' . $this->quoteValue($value);
                break;

            case 'LIKE':
            case 'NOT LIKE':
                // % and _ in the value are normal characters
                $like      = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value, '\'"'));
                $condition = $column . ' ' . $operator . ' ' . $db->quote('%' . $like . '%') . " ESCAPE '!'";
                break;

            case 'IN':
            case 'NOT IN':
                $values    = array_map(fn ($part) => $this->quoteValue(trim(trim($part), '\'"')), explode(',', $value));
                $condition = $column . ' ' . $operator . ' (' . implode(', ', $values) . ')';
                break;

            case 'EMPTY':
                $condition = $column . " = '' OR " . $column . ' IS NULL';
                break;

            case 'NOT EMPTY':
                $condition = $column . " <> ''";
                break;

            default:
                return '(1 = 0)';
        }

        if ($isNull) {
            $condition .= ' OR ' . $column . ' IS NULL';
        }

        return '(' . $condition . ')';
    }

    private function quoteValue(string $value): string
    {
        return is_numeric($value) ? $value : $this->db->quote($value);
    }
}
