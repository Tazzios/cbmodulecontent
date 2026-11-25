<?php

/**
 * A small test site: the real database package of Joomla on SQLite in memory, with the tables
 * of Joomla and Community Builder the extensions read.
 */

namespace Tests\Stubs;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryMonitorInterface;
use Joomla\Registry\Registry;
use Tazzios\Module\CbList\Site\Dispatcher\Dispatcher;
use Tazzios\Module\CbList\Site\Helper\CbListHelper;
use Tazzios\Plugin\Content\Cbprofile\Extension\Cbprofile;

final class Site
{
    public const DOLLY = 653;
    public const ELLY  = 654;
    public const DUCK  = 655;

    public static DatabaseInterface $db;
    /** @var string[] every query that was run */
    public static array $log = [];
    /** @var int[] view levels of the visitor */
    public static array $viewLevels = [1];
    public static bool $superUser = false;

    /** Starts a clean site with three users (names as on the demo site) plus a blocked one. */
    public static function reset(): void
    {
        self::$viewLevels           = [1];
        self::$superUser            = false;
        HTMLHelper::$contentPlugins = [];
        PluginHelper::$layoutPath   = '';

        self::$db = (new DatabaseFactory())->getDriver('sqlite', ['database' => ':memory:', 'prefix' => 'jos_']);
        self::$db->connect();
        // MySQL has REGEXP and RAND, SQLite needs functions for them.
        self::$db->getConnection()->sqliteCreateFunction('rand', static fn () => mt_rand());
        self::$db->getConnection()->sqliteCreateFunction('regexp', static fn ($pattern, $value) => (int) preg_match("~$pattern~i", (string) $value));
        self::$db->setMonitor(new class () implements QueryMonitorInterface {
            public function startQuery(string $sql, ?array $boundParams = null): void
            {
                Site::$log[] = trim($sql);
            }

            public function stopQuery(): void
            {
            }
        });

        self::exec('CREATE TABLE jos_users (id INT, name TEXT COLLATE NOCASE, username TEXT COLLATE NOCASE, email TEXT COLLATE NOCASE, block INT, password TEXT, registerDate TEXT)');
        self::exec('CREATE TABLE jos_user_usergroup_map (user_id INT, group_id INT)');
        self::exec('CREATE TABLE jos_comprofiler (id INT, user_id INT, approved INT, confirmed INT, avatar TEXT, avatarapproved INT, cb_city TEXT, cb_color TEXT, cb_about TEXT)');
        self::exec('CREATE TABLE jos_comprofiler_lists (listid INTEGER PRIMARY KEY, title TEXT, published INT, usergroupids TEXT, params TEXT)');
        self::exec('CREATE TABLE jos_comprofiler_fields (fieldid INT, name TEXT, type TEXT, "table" TEXT, tablecolumns TEXT)');
        self::exec('CREATE TABLE jos_comprofiler_field_values (fieldid INT, fieldtitle TEXT, fieldlabel TEXT)');

        $fields = [
            ['name', 'text', '#__users', 'name'],
            ['username', 'predefined', '#__users', 'username'],
            ['cb_city', 'text', '#__comprofiler', 'cb_city'],
            ['avatar', 'image', '#__comprofiler', 'avatar,avatarapproved'],
            ['password', 'password', '#__users', 'password'],
            ['cb_color', 'select', '#__comprofiler', 'cb_color'],
            ['cb_about', 'editorta', '#__comprofiler', 'cb_about'],
        ];

        foreach ($fields as $i => $field) {
            self::exec('INSERT INTO jos_comprofiler_fields VALUES (?,?,?,?,?)', [$i + 1, ...$field]);
        }

        self::user(self::DOLLY, 'Dolly', 'Dolphine', 'Utrecht');
        self::user(self::ELLY, 'Elly elifant', 'elifantE', 'Rotterdam');
        self::user(self::DUCK, 'Duck Toy', 'DuckT', 'Rotterdam');
        self::user(656, 'Dirk Blocked', 'dirk', 'Utrecht', 1);

        self::$log = [];
    }

    public static function exec(string $sql, array $values = []): void
    {
        self::$db->getConnection()->prepare($sql)->execute($values);
    }

    public static function user(int $id, string $name, string $username, string $city, int $block = 0): void
    {
        self::exec('INSERT INTO jos_users VALUES (?,?,?,?,?,?,?)', [
            $id, $name, $username, strtolower($username) . '@example.org', $block, "hash$id", '2026-01-' . (700 - $id),
        ]);
        self::exec('INSERT INTO jos_user_usergroup_map VALUES (?,2)', [$id]);
        self::exec('INSERT INTO jos_comprofiler VALUES (?,?,1,1,?,1,?,NULL,NULL)', [$id, $id, "gallery/$id.png", $city]);
    }

    /** Adds a Community Builder list, filters as [column, operator, value]. */
    public static function cbList(
        int $id,
        array $filters = [],
        string $title = 'all users',
        array $params = [],
        string $groups = '|*|2'
    ): void {
        $rows = array_map(
            static fn ($f) => ['column' => $f[0], 'operator' => $f[1], 'value' => $f[2]],
            $filters ?: [['', '', '']]
        );

        self::exec('INSERT INTO jos_comprofiler_lists VALUES (?,?,1,?,?)', [$id, $title, $groups, json_encode($params + [
            'filter_mode'           => '0',
            'filter_basic'          => $rows,
            'list_show_unapproved'  => '0',
            'list_show_blocked'     => '0',
            'list_show_unconfirmed' => '0',
            'sort_basic'            => [['column' => 'u.id', 'direction' => 'ASC']],
        ])]);
    }

    /** The output of the module. */
    public static function module(Registry $params): string
    {
        $app = new class () {
            public function getIdentity(): User
            {
                return new User(Site::$viewLevels, Site::$superUser);
            }
        };
        $factory = new class () {
            public function getHelper(string $name): CbListHelper
            {
                $helper = new CbListHelper();
                $helper->setDatabase(Site::$db);

                return $helper;
            }
        };

        $dispatcher = new Dispatcher((object) ['module' => 'mod_cblistmodule', 'params' => (string) $params], $app);
        $dispatcher->setHelperFactory($factory);

        ob_start();

        try {
            $dispatcher->dispatch();
        } finally {
            $output = ob_get_clean();
        }

        return trim($output);
    }

    /** Runs the content plugin on an article. */
    public static function prepare(object $article, string $context, array $settings): void
    {
        $plugin = new Cbprofile(['params' => $settings]);
        $plugin->setDatabase(self::$db);
        $plugin->onContentPrepare(new ContentPrepareEvent('onContentPrepare', ['context' => $context, 'subject' => $article]));
    }
}
