<?php

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
        $sql  = cblisthelper_createquery($listId, null)['cblistselect'];
        $rows = Factory::$db->setQuery($sql . ' ORDER BY ue.id')->loadAssocList();

        return array_map('intval', array_column($rows, 'id'));
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

        $this->assertStringContainsString("name = 'Dolly'", cblisthelper_createquery(null, 'only dolly')['cblistselect']);
    }

    public function testSortOfTheListIsReturnedSeparately(): void
    {
        Site::cbList(1, [], 'x', ['sort_basic' => [['column' => 'name', 'direction' => 'DESC']]]);
        $query = cblisthelper_createquery(1, null);

        $this->assertSame('name', $query['cblistsortby']);
        $this->assertSame('DESC', $query['cblistsortorder']);
        $this->assertStringNotContainsString('ORDER BY', $query['cblistselect']);
    }
}
