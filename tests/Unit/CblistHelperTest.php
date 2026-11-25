<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tazzios\Library\CbListHelper\ListQuery;
use Tests\Stubs\Site;

final class CblistHelperTest extends TestCase
{
    protected function setUp(): void
    {
        Site::reset();
    }

    /** @return int[] the users the query of the list really finds */
    private function users(int $listId = 1): array
    {
        $listQuery = new ListQuery(Site::$db);
        $query     = $listQuery->getQuery($listQuery->getListById($listId))->order('ue.id');

        return array_map('intval', Site::$db->setQuery($query)->loadColumn());
    }

    private function order(int $listId): array
    {
        $listQuery = new ListQuery(Site::$db);

        return $listQuery->getOrder($listQuery->getListById($listId));
    }

    public static function filters(): array
    {
        [$dolly, $elly, $duck] = [Site::DOLLY, Site::ELLY, Site::DUCK];
        $notDolly = ['name', '<>||ISNULL', 'Dolly'];
        $city     = ['cb_city', '=', 'Rotterdam'];

        return [
            'no filter'                     => [[], [$dolly, $elly, $duck]],
            'equal to text'                 => [[['name', '=', 'Dolly']], [$dolly]],
            'equal to number'               => [[['u.id', '=', '655']], [$duck]],
            'not equal to'                  => [[$notDolly], [$elly, $duck]],
            'contains'                      => [[['name', 'LIKE', 'D']], [$dolly, $duck]],
            'does not contain'              => [[['name', 'NOT LIKE||ISNULL', 'D']], [$elly]],
            'plain filter, then contains'   => [[$notDolly, ['name', 'LIKE', 'D']], [$duck]],
            'contains, then plain filter'   => [[['name', 'LIKE', 'D'], $notDolly], [$duck]],
            'two contains'                  => [[['name', 'LIKE', 'D'], ['name', 'LIKE', 'Toy']], [$duck]],
            'plain, contains, plain'        => [[$city, ['name', 'LIKE', 'l'], $notDolly], [$elly]],
            'in, two texts'                 => [[['name', 'IN', 'Dolly,Duck Toy']], [$dolly, $duck]],
            'in, space after comma'         => [[['name', 'IN', 'Dolly, Duck Toy']], [$dolly, $duck]],
            'in, quoted texts'              => [[['name', 'IN', "'Dolly','Duck Toy'"]], [$dolly, $duck]],
            'in, one text'                  => [[['name', 'IN', 'Dolly']], [$dolly]],
            'in, numbers'                   => [[['u.id', 'IN', '653,655']], [$dolly, $duck]],
            'plain filter, then in'         => [[$notDolly, ['name', 'IN', 'Dolly,Duck Toy']], [$duck]],
            'in, then plain filter'         => [[['name', 'IN', 'Dolly,Duck Toy'], $city], [$duck]],
            'in, then contains'             => [[['name', 'IN', 'Dolly,Elly elifant'], ['name', 'LIKE', 'elif']], [$elly]],
            'is one of, apostrophe'         => [[['name', 'IN', "O'Neill,Dolly"]], [$dolly]],
            'is not one of'                 => [[['name', 'NOT IN||ISNULL', 'Dolly,Duck Toy']], [$elly]],
            'is not one of, no value'       => [[['cb_color', 'NOT IN||ISNULL', 'red']], [$dolly, $elly, $duck]],
            'is not one of, then contains'  => [[['name', 'NOT IN||ISNULL', 'Dolly'], ['name', 'LIKE', 'D']], [$duck]],
            'is empty'                      => [[['cb_color', 'EMPTY', '']], [$dolly, $elly, $duck]],
            'is empty, none'                => [[['cb_city', 'EMPTY', '']], []],
            'is empty, then plain'          => [[['cb_color', 'EMPTY', ''], ['name', '=', 'Dolly']], [$dolly]],
            'is not empty'                  => [[['cb_city', 'NOT EMPTY', '']], [$dolly, $elly, $duck]],
            'is not empty, none'            => [[['cb_color', 'NOT EMPTY', '']], []],
            'equal to, apostrophe'          => [[['name', '=', "O'Neill"]], []],
            'not equal to, apostrophe'      => [[['name', '<>||ISNULL', "O'Neill"]], [$dolly, $elly, $duck]],
            'contains, apostrophe'          => [[['name', 'LIKE', "O'N"]], []],
            'contains %'                    => [[['name', 'LIKE', '%']], []],
            'contains _'                    => [[['name', 'LIKE', '_']], []],
            'contains !'                    => [[['name', 'LIKE', '!']], []],
            'does not contain %'            => [[['name', 'NOT LIKE||ISNULL', '%']], [$dolly, $elly, $duck]],
            'regexp'                        => [[['name', 'REGEXP', '^D']], [$dolly, $duck]],
            'not regexp'                    => [[['name', 'NOT REGEXP||ISNULL', '^D']], [$elly]],
            'column id is not ambiguous'    => [[['id', '>', '653']], [$elly, $duck]],
            'column with table'             => [[['ue.cb_city', '=', 'Rotterdam']], [$elly, $duck]],
            'column with backticks'         => [[['ue.`cb_city`', '=', 'Rotterdam']], [$elly, $duck]],
            'unknown column shows nobody'   => [[['cb_gone', '=', 'x']], []],
            'sql as column shows nobody'    => [[['1=1) OR (u.id', '>', '0']], []],
            'unknown operator shows nobody' => [[['name', 'SOUNDS LIKE', 'Dolly']], []],
            'sql as operator shows nobody'  => [[['name', "<> '' OR 1 =", '1']], []],
            'empty filter row is skipped'   => [[['', '', ''], $city], [$elly, $duck]],
        ];
    }

    #[DataProvider('filters')]
    public function testFilters(array $filters, array $expected): void
    {
        Site::cbList(1, $filters);

        $this->assertSame($expected, $this->users());
    }

    public function testApostropheInValueOfInIsEscaped(): void
    {
        Site::cbList(1, [['name', 'IN', "O'Neill,Dolly"]]);

        $this->assertSame([Site::DOLLY], $this->users());
    }

    public function testAdvancedFilter(): void
    {
        Site::cbList(1, [], 'x', ['filter_mode' => '1', 'filter_advanced' => "name = 'Dolly' OR name = 'Duck Toy'"]);

        $this->assertSame([Site::DOLLY, Site::DUCK], $this->users());
    }

    public function testBlockedUsersOnlyWhenTheListShowsThem(): void
    {
        Site::cbList(1, [], 'x', ['list_show_blocked' => '1']);

        $this->assertContains(656, $this->users());
    }

    public function testListByName(): void
    {
        Site::cbList(3, [['name', '=', 'Dolly']], 'only dolly');

        $this->assertStringContainsString("`name` = 'Dolly'", (string) (new ListQuery(Site::$db))->getQuery((new ListQuery(Site::$db))->getListByTitle('only dolly')));
    }

    public function testContainsPercentFindsTheRealCharacter(): void
    {
        Site::user(670, '100% Dolly', 'percent', 'Utrecht');
        Site::user(671, 'a_b', 'underscore', 'Utrecht');
        Site::user(672, 'wow!', 'bang', 'Utrecht');
        Site::cbList(1, [['name', 'LIKE', '0%']]);
        Site::cbList(2, [['name', 'LIKE', 'a_']]);
        Site::cbList(3, [['name', 'LIKE', '!']]);

        $this->assertSame([670], $this->users(1));
        $this->assertSame([671], $this->users(2));
        $this->assertSame([672], $this->users(3));
    }

    public function testApostropheInValueIsFound(): void
    {
        Site::user(673, "O'Neill", 'oneill', 'Utrecht');
        Site::cbList(1, [['name', '=', "O'Neill"]]);
        Site::cbList(2, [['name', 'IN', "Dolly,O'Neill"]]);

        $this->assertSame([673], $this->users(1));
        $this->assertSame([Site::DOLLY, 673], $this->users(2));
    }

    public function testEmptyAlsoMatchesAnEmptyText(): void
    {
        Site::exec("UPDATE jos_comprofiler SET cb_color = '' WHERE user_id = 653");
        Site::exec("UPDATE jos_comprofiler SET cb_color = 'red' WHERE user_id = 654");
        Site::cbList(1, [['cb_color', 'EMPTY', '']]);
        Site::cbList(2, [['cb_color', 'NOT EMPTY', '']]);

        $this->assertSame([Site::DOLLY, Site::DUCK], $this->users(1));
        $this->assertSame([Site::ELLY], $this->users(2));
    }

    public function testListWithoutUserGroupsHasNoUsers(): void
    {
        foreach (['', '|*|', '0'] as $i => $groups) {
            Site::cbList($i + 1, [], 'x', [], $groups);
            $this->assertSame([], $this->users($i + 1));
        }
    }

    public function testListWithoutSortOrParams(): void
    {
        Site::cbList(1, [], 'x', ['sort_basic' => []]);
        Site::exec("INSERT INTO jos_comprofiler_lists VALUES (2,'y',1,'2','')");

        $this->assertSame(['`ue`.`id`', 'ASC'], $this->order(1));
        $this->assertSame([Site::DOLLY, Site::ELLY, Site::DUCK], $this->users(2));
    }

    public function testSortColumnAndDirectionAreChecked(): void
    {
        Site::cbList(1, [], 'x', ['sort_basic' => [['column' => '(SELECT 1)', 'direction' => 'ASC; DROP']]]);

        $this->assertSame(['`ue`.`id`', 'ASC'], $this->order(1));
    }

    public function testUserInTwoGroupsIsFoundOnce(): void
    {
        Site::exec('INSERT INTO jos_user_usergroup_map VALUES (?,3)', [Site::DOLLY]);
        Site::cbList(1, [], 'x', [], '2|*|3');

        $this->assertSame([Site::DOLLY, Site::ELLY, Site::DUCK], $this->users());
    }

    public function testUnknownListHasNoUsers(): void
    {
        $this->assertSame([], $this->users(99));
    }

    public function testListNameIsSafeInTheQuery(): void
    {
        $this->assertNull((new ListQuery(Site::$db))->getListByTitle("x' OR '1'='1"));
        $this->assertStringContainsString("`title` = 'x'' OR ''1''=''1'", end(Site::$log));
    }

    public function testSortOfTheListIsReturnedSeparately(): void
    {
        Site::cbList(1, [], 'x', ['sort_basic' => [['column' => 'name', 'direction' => 'DESC']]]);

        $this->assertSame(['`u`.`name`', 'DESC'], $this->order(1));
        $this->assertStringNotContainsString('ORDER BY', (string) (new ListQuery(Site::$db))->getQuery((new ListQuery(Site::$db))->getListById(1)));
    }
}
