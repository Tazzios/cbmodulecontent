<?php

/**
 * A small test site: Joomla's database interface on SQLite in memory, with the
 * tables of Joomla and Community Builder the extensions read.
 */

namespace Tests\Stubs;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

final class Query
{
    private array $select = [];
    private string $from  = '';
    private array $join   = [];
    private array $where  = [];
    private string $order = '';
    private int $limit    = 0;

    public function select($columns)
    {
        $this->select[] = $columns;

        return $this;
    }

    // Like Joomla: one argument, a second one (the plugin gives an alias) is ignored.
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    public function join($type, $table)
    {
        $this->join[] = "$type JOIN $table";

        return $this;
    }

    public function where($where)
    {
        $this->where[] = $where;

        return $this;
    }

    public function order($order)
    {
        $this->order = $order;

        return $this;
    }

    public function setLimit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }

    public function __toString()
    {
        return 'SELECT ' . implode(',', $this->select) . ' FROM ' . $this->from . ' ' . implode(' ', $this->join)
            . ($this->where ? ' WHERE ' . implode(' AND ', $this->where) : '')
            . ($this->order !== '' ? ' ORDER BY ' . $this->order : '')
            . ($this->limit ? ' LIMIT ' . $this->limit : '');
    }
}

final class Database
{
    /** @var string[] every query that was run */
    public array $log = [];
    private string $query = '';
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        // MySQL functions that SQLite does not have.
        $this->pdo->sqliteCreateFunction('FIELD', static function ($value, ...$list) {
            $i = array_search((string) $value, array_map('strval', $list), true);

            return $i === false ? 0 : $i + 1;
        });
        $this->pdo->sqliteCreateFunction('rand', static fn () => mt_rand());
    }

    public function getQuery($new = false)
    {
        return new Query();
    }

    public function quote($text)
    {
        return $this->pdo->quote($text);
    }

    public function quoteName($name)
    {
        return '`' . $name . '`';
    }

    public function getTableColumns($table)
    {
        $rows = $this->pdo->query('PRAGMA table_info(' . str_replace('#__', 'jos_', $table) . ')')->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($rows, 'type', 'name');
    }

    public function setQuery($query)
    {
        $this->query = (string) $query;

        return $this;
    }

    public function loadAssocList()
    {
        $this->log[] = trim($this->query);

        return $this->pdo->query($this->toSqlite($this->query))->fetchAll(\PDO::FETCH_ASSOC);
    }

    // The few places where MySQL accepts what SQLite does not.
    private function toSqlite(string $sql): string
    {
        $sql = str_replace('#__', 'jos_', $sql);
        $sql = str_replace('fields.table ', 'fields."table" ', $sql);
        // Joomla does not replace the prefix inside quoted values.
        $sql = str_replace("'jos_", "'#__", $sql);

        // An expression in ORDER BY of a UNION
        if (preg_match('/^(.*[[:space:]]UNION[[:space:]].*)(order by .*)$/is', $sql, $m)) {
            $sql = "SELECT * FROM ($m[1]) $m[2]";
        }

        return $sql;
    }

    public function loadAssoc()
    {
        return $this->loadAssocList()[0] ?? null;
    }

    public function exec(string $sql, array $values = []): void
    {
        $this->pdo->prepare($sql)->execute($values);
    }
}

final class Site
{
    public const DOLLY = 653;
    public const ELLY  = 654;
    public const DUCK  = 655;

    /** Starts a clean site with three users (names as on the demo site) plus a blocked one. */
    public static function reset(): Database
    {
        $db = Factory::$db = new Database();
        Factory::$viewLevels        = [1];
        HTMLHelper::$calls          = [];
        HTMLHelper::$contentPlugins = [];

        $db->exec('CREATE TABLE jos_users (id INT, name TEXT COLLATE NOCASE, username TEXT COLLATE NOCASE, email TEXT, block INT, password TEXT, registerDate TEXT)');
        $db->exec('CREATE TABLE jos_user_usergroup_map (user_id INT, group_id INT)');
        $db->exec('CREATE TABLE jos_comprofiler (id INT, user_id INT, approved INT, confirmed INT, avatar TEXT, avatarapproved INT, cb_city TEXT)');
        $db->exec('CREATE TABLE jos_comprofiler_lists (listid INTEGER PRIMARY KEY, title TEXT, published INT, usergroupids TEXT, params TEXT)');
        $db->exec('CREATE TABLE jos_comprofiler_fields (name TEXT, type TEXT, "table" TEXT, tablecolumns TEXT)');
        $db->exec('CREATE TABLE jos_comprofiler_field_values (fieldtitle TEXT, fieldlabel TEXT)');
        $db->exec("INSERT INTO jos_comprofiler_fields VALUES ('name','text','#__users','name')");
        $db->exec("INSERT INTO jos_comprofiler_fields VALUES ('username','predefined','#__users','username')");
        $db->exec("INSERT INTO jos_comprofiler_fields VALUES ('cb_city','text','#__comprofiler','cb_city')");

        self::user(self::DOLLY, 'Dolly', 'Dolphine', 'Utrecht');
        self::user(self::ELLY, 'Elly elifant', 'elifantE', 'Rotterdam');
        self::user(self::DUCK, 'Duck Toy', 'DuckT', 'Rotterdam');
        self::user(656, 'Dirk Blocked', 'dirk', 'Utrecht', 1);

        return $db;
    }

    public static function user(int $id, string $name, string $username, string $city, int $block = 0): void
    {
        $db = Factory::$db;
        $db->exec('INSERT INTO jos_users VALUES (?,?,?,?,?,?,?)', [$id, $name, $username, strtolower($username) . '@example.org', $block, "hash$id", '2026-01-' . (700 - $id)]);
        $db->exec('INSERT INTO jos_user_usergroup_map VALUES (?,2)', [$id]);
        $db->exec('INSERT INTO jos_comprofiler VALUES (?,?,1,1,?,1,?)', [$id, $id, "gallery/$id.png", $city]);
    }

    /** Adds a Community Builder list, filters as [column, operator, value]. */
    public static function cbList(int $id, array $filters = [], string $title = 'all users', array $params = []): void
    {
        $rows = array_map(static fn ($f) => ['column' => $f[0], 'operator' => $f[1], 'value' => $f[2]], $filters ?: [['', '', '']]);

        Factory::$db->exec('INSERT INTO jos_comprofiler_lists VALUES (?,?,1,?,?)', [$id, $title, '|*|2', json_encode($params + [
            'filter_mode'           => '0',
            'filter_basic'          => $rows,
            'list_show_unapproved'  => '0',
            'list_show_blocked'     => '0',
            'list_show_unconfirmed' => '0',
            'sort_basic'            => [['column' => 'u.id', 'direction' => 'ASC']],
        ])]);
    }
}
