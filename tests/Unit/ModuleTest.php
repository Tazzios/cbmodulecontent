<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Site;

final class ModuleTest extends TestCase
{
    protected function setUp(): void
    {
        Site::reset();
        Site::cbList(4);
    }

    private function params(array $params = []): Registry
    {
        return new Registry($params + [
            'listid'     => '4',
            'user-limit' => 10,
            'orderby'    => 'list_default',
            'sortorder'  => 'list_default',
            'text-above' => '',
            'text-below' => '',
            'columns'    => '0',
            'Minwidth'   => 5,
            'template'   => '<b>[name]</b>',
            'rules'      => [],
            'debug'      => '0',
        ]);
    }

    /** @return string[] the names that are shown, in order */
    private function names(array $params = []): array
    {
        preg_match_all('#<b>(.*?)</b>#', modcbListHelper::getData($this->params($params)), $m);

        return $m[1];
    }

    private function usePlugin(): void
    {
        HTMLHelper::$contentPlugins[] = static function ($context, $article, $params): void {
            (new PlgContentcbprofile(null, ['params' => ['layout' => 'text-default', 'imagewidth' => 150, 'imageheight' => 150]]))
                ->onContentPrepare($context, $article, $params);
        };
    }

    public function testShowsTheUsersOfTheList(): void
    {
        $this->assertSame(['Dolly', 'Elly elifant', 'Duck Toy'], $this->names());
    }

    public function testListFiltersAreUsed(): void
    {
        Site::cbList(5, [['name', '<>||ISNULL', 'Dolly'], ['name', 'LIKE', 'D']], 'like');
        Site::cbList(6, [['name', 'IN', 'Dolly,Duck Toy']], 'in');

        $this->assertSame(['Duck Toy'], $this->names(['listid' => '5']));
        $this->assertSame(['Dolly', 'Duck Toy'], $this->names(['listid' => '6']));
    }

    public function testOrderAndLimit(): void
    {
        $this->assertSame(['Elly elifant', 'Duck Toy', 'Dolly'], $this->names(['orderby' => 'name', 'sortorder' => 'desc']));
        $this->assertSame(['Dolly', 'Elly elifant'], $this->names(['user-limit' => 2]));
        $this->assertCount(3, $this->names(['sortorder' => 'random']));
    }

    public function testColumns(): void
    {
        $this->assertStringContainsString('repeat(auto-fit, minmax(5rem, 1fr))', modcbListHelper::getData($this->params()));
        $this->assertStringContainsString('repeat(2, minmax(5rem, 1fr))', modcbListHelper::getData($this->params(['columns' => '2'])));
    }

    public function testRuleWithAccessLevel(): void
    {
        $rules = ['rules0' => ['tag_name' => 'Linked_name', 'accesslevel' => ['2'], 'htmlcode' => '<i>[name]</i>', 'htmlcode_no' => '']];
        $set   = ['rules' => $rules, 'template' => '<b>[Linked_name]</b>'];

        $this->assertSame(['', '', ''], $this->names($set));
        Factory::$viewLevels = [1, 2];
        $this->assertSame(['<i>Dolly</i>', '<i>Elly elifant</i>', '<i>Duck Toy</i>'], $this->names($set));
    }

    public function testOutputGoesThroughContentPlugins(): void
    {
        $seen = [];
        HTMLHelper::$contentPlugins[] = static function ($context, $article) use (&$seen): void {
            $seen[]        = [$context, \is_object($article)];
            $article->text = strtoupper($article->text);
        };
        $html = modcbListHelper::getData($this->params(['text-above' => 'above', 'text-below' => 'below']));

        // Joomla 5+ only accepts an object for onContentPrepare.
        $this->assertSame([['mod_cblistmodule.content', true]], $seen);
        $this->assertStringStartsWith('ABOVE', $html);
        $this->assertStringEndsWith('BELOW', $html);
    }

    public function testCbprofileTagAboveAndBelowTheList(): void
    {
        $this->usePlugin();
        $html = modcbListHelper::getData($this->params([
            'text-above' => '<p>{cbprofile username=Dolphine}</p>',
            'text-below' => '<p>{cbprofile username=DuckT}</p>',
        ]));

        preg_match_all('/&user=(\d+)/', $html, $m);
        $this->assertSame(['653', '655'], $m[1]);
        $this->assertStringNotContainsString('{cbprofile', $html);
        $this->assertSame(3, substr_count($html, 'cblist-user'));
    }

    public function testTagInUserDataIsNotProcessed(): void
    {
        $this->usePlugin();
        Factory::$db->exec('UPDATE jos_comprofiler SET cb_city = ? WHERE user_id = ?', ['{cbprofile username=DuckT}', Site::DOLLY]);
        $html = modcbListHelper::getData($this->params(['template' => '<b>[cb_city]</b>']));

        $this->assertStringContainsString('<b>&#123;cbprofile username=DuckT&#125;</b>', $html);
        $this->assertStringNotContainsString('&user=', $html);
        $this->assertStringNotContainsString('{', $html);
    }

    public function testTagInUserDataIsNotProcessedInARule(): void
    {
        $this->usePlugin();
        Factory::$db->exec('UPDATE jos_comprofiler SET cb_city = ? WHERE user_id = ?', ['{cbprofile username=DuckT}', Site::DOLLY]);
        $html = modcbListHelper::getData($this->params([
            'template' => '<b>[cb_city]</b>',
            'rules'    => ['rules0' => ['tag_name' => 'cb_city', 'accesslevel' => ['1'], 'htmlcode' => 'in [cb_city]', 'htmlcode_no' => '']],
        ]));

        $this->assertStringContainsString('in &#123;cbprofile', $html);
        $this->assertStringNotContainsString('&user=', $html);
    }

    public function testTagInTheTemplateIsProcessed(): void
    {
        $this->usePlugin();
        $html = modcbListHelper::getData($this->params(['template' => '{cbprofile username=[username]}']));

        $this->assertStringNotContainsString('{', $html);
    }

    public function testDebugShowsTheQuery(): void
    {
        $this->assertStringContainsString('DEBUG: <pre>SELECT DISTINCT', modcbListHelper::getData($this->params(['debug' => '1'])));
    }

    public function testEmptyListGivesNoUsers(): void
    {
        Site::cbList(7, [['name', '=', 'nobody']], 'empty');

        $this->assertSame([], $this->names(['listid' => '7']));
        $this->assertStringContainsString('Empty list', modcbListHelper::getData($this->params(['listid' => '7', 'debug' => '1'])));
    }
}
