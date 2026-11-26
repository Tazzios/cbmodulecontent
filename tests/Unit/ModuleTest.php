<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
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
        preg_match_all('#<b>(.*?)</b>#', Site::module($this->params($params)), $m);

        return $m[1];
    }

    private function usePlugin(): void
    {
        HTMLHelper::$contentPlugins[] = static function ($context, $article): void {
            Site::prepare($article, $context, ['layout' => 'text-default', 'imagewidth' => 150, 'imageheight' => 150]);
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
        $this->assertStringContainsString('repeat(auto-fit, minmax(5rem, 1fr))', Site::module($this->params()));
        $this->assertStringContainsString('repeat(2, minmax(5rem, 1fr))', Site::module($this->params(['columns' => '2'])));
    }

    public function testRuleWithAccessLevel(): void
    {
        $rules = ['rules0' => ['tag_name' => 'Linked_name', 'accesslevel' => ['2'], 'htmlcode' => '<i>[name]</i>', 'htmlcode_no' => '']];
        $set   = ['rules' => $rules, 'template' => '<b>[Linked_name]</b>'];

        $this->assertSame(['', '', ''], $this->names($set));
        Site::$viewLevels = [1, 2];
        $this->assertSame(['<i>Dolly</i>', '<i>Elly elifant</i>', '<i>Duck Toy</i>'], $this->names($set));
    }

    public function testOutputGoesThroughContentPlugins(): void
    {
        $seen = [];
        HTMLHelper::$contentPlugins[] = static function ($context, $article) use (&$seen): void {
            $seen[]        = [$context, \is_object($article)];
            $article->text = strtoupper($article->text);
        };
        $html = Site::module($this->params(['text-above' => 'above', 'text-below' => 'below']));

        // Joomla 5+ only accepts an object for onContentPrepare.
        $this->assertSame([['mod_cblistmodule.content', true]], $seen);
        $this->assertStringStartsWith('ABOVE', $html);
        $this->assertStringEndsWith('BELOW', $html);
    }

    public function testCbprofileTagAboveAndBelowTheList(): void
    {
        $this->usePlugin();
        $html = Site::module($this->params([
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
        Site::exec('UPDATE jos_comprofiler SET cb_city = ? WHERE user_id = ?', ['{cbprofile username=DuckT}', Site::DOLLY]);
        $html = Site::module($this->params(['template' => '<b>[cb_city]</b>']));

        $this->assertStringContainsString('<b>&#123;cbprofile username=DuckT&#125;</b>', $html);
        $this->assertStringNotContainsString('&user=', $html);
        $this->assertStringNotContainsString('{', $html);
    }

    public function testTagInUserDataIsNotProcessedInARule(): void
    {
        $this->usePlugin();
        Site::exec('UPDATE jos_comprofiler SET cb_city = ? WHERE user_id = ?', ['{cbprofile username=DuckT}', Site::DOLLY]);
        $html = Site::module($this->params([
            'template' => '<b>[cb_city]</b>',
            'rules'    => ['rules0' => ['tag_name' => 'cb_city', 'accesslevel' => ['1'], 'htmlcode' => 'in [cb_city]', 'htmlcode_no' => '']],
        ]));

        $this->assertStringContainsString('in &#123;cbprofile', $html);
        $this->assertStringNotContainsString('&user=', $html);
    }

    public function testTagInTheTemplateIsProcessed(): void
    {
        $this->usePlugin();
        $html = Site::module($this->params(['template' => '{cbprofile username=[username]}']));

        $this->assertStringNotContainsString('{', $html);
    }

    public function testDebugIsOnlyForSuperUsers(): void
    {
        $this->assertStringNotContainsString('DEBUG', Site::module($this->params(['debug' => '1'])));

        Site::$superUser = true;
        $this->assertMatchesRegularExpression('~DEBUG: <pre>\s*SELECT `ue`\.`id`~', Site::module($this->params(['debug' => '1'])));
        $this->assertStringNotContainsString('DEBUG', Site::module($this->params()));
    }

    public function testDebugQueryIsEscaped(): void
    {
        Site::$superUser = true;
        Site::cbList(5, [['name', '=', '<b>{loadposition x}']], 'html');
        $html = Site::module($this->params(['listid' => '5', 'debug' => '1']));

        $this->assertStringContainsString('&lt;b&gt;&#123;loadposition x&#125;', $html);
    }

    private function setUserData(string $column, $value): void
    {
        $table = $column === 'name' ? 'jos_users' : 'jos_comprofiler';
        Site::exec("UPDATE $table SET $column = ? WHERE id = ?", [$value, Site::DOLLY]);
    }

    public function testUserDataIsEscaped(): void
    {
        $this->setUserData('name', 'x" onerror="alert(1)');
        $this->setUserData('cb_city', '<script>alert(1)</script>\'');
        $html = Site::module($this->params(['template' => '<img alt="[name]"> [cb_city]']));

        $this->assertStringContainsString('<img alt="x&quot; onerror=&quot;alert(1)"> &lt;script&gt;alert(1)&lt;/script&gt;&#039;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testUserDataIsEscapedInARule(): void
    {
        $this->setUserData('name', 'x" onerror="alert(1)');
        $html = Site::module($this->params([
            'template' => '[show]',
            'rules'    => ['r' => ['tag_name' => 'show', 'htmlcode' => '<img alt="[name]">', 'htmlcode_no' => '']],
        ]));

        $this->assertStringContainsString('<img alt="x&quot; onerror=&quot;alert(1)">', $html);
    }

    public static function editorValues(): array
    {
        return [
            'markup stays'      => ['<p>Hi <strong>there</strong> <a href="https://example.org/x">link</a></p>', '<p>Hi <strong>there</strong> <a href="https://example.org/x">link</a></p>'],
            'script'            => ['<p>a</p><script>alert(1)</script>', null],
            'onerror'           => ['<img src="x.png" onerror="alert(1)">', null],
            'onerror no quotes' => ['<img src=x.png onerror=alert(1)>', null],
            'onerror slash'     => ['<img/src="x.png"/onerror="alert(1)">', null],
            'javascript link'   => ['<a href="javascript:alert(1)">x</a>', null],
            'javascript mixed'  => ["<a href=\" JaVa\tScRiPt:alert(1)\">x</a>", null],
            'javascript entity' => ['<a href="&#106;avascript:alert(1)">x</a>', null],
            'data link'         => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', null],
            'iframe'            => ['<iframe src="https://example.org"></iframe>', null],
            'svg'               => ['<svg onload="alert(1)"><circle/></svg>', null],
            'style'             => ['<p style="position:fixed;top:0">x</p>', null],
            'form'              => ['<form action="https://evil"><input name="p"></form>', null],
            'nested script'     => ['<scr<script>ipt>alert(1)</scr</script>ipt>', null],
            'broken tag'        => ['<img src="x.png" onerror="alert(1)"', null],
        ];
    }

    #[DataProvider('editorValues')]
    public function testEditorFieldShowsHtmlWithoutScripts(string $value, ?string $expected): void
    {
        $this->setUserData('cb_about', $value);
        $html = $this->names(['template' => '<b>[cb_about]</b>', 'user-limit' => 1])[0];

        if ($expected !== null) {
            $this->assertSame($expected, $html);
        }

        // As the browser reads it: entities decoded, spaces and control characters ignored.
        $read = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        $this->assertDoesNotMatchRegularExpression('~(href|src)=["\']?(javascript|data|vbscript):~i', $read);
        $this->assertDoesNotMatchRegularExpression('~<(script|iframe|svg|form|input)|<[^>]*\son\w+\s*=|<[^>]*/on\w+\s*=|<[^>]*(javascript|data)\s*:|<[^>]*style\s*=~i', $html);
    }

    public function testTagsInEditorFieldAreHarmless(): void
    {
        $this->usePlugin();
        $this->setUserData('cb_about', '<i>x</i> {cbprofile username=DuckT} [username]');
        $html = Site::module($this->params(['template' => '<b>[cb_about]</b>', 'user-limit' => 1]));

        $this->assertStringContainsString('<b><i>x</i> &#123;cbprofile username=DuckT&#125; [username]</b>', $html);
        $this->assertStringNotContainsString('&user=', $html);
    }

    public function testOldSettingDoesNotTurnOffEscaping(): void
    {
        $this->setUserData('cb_city', '<i>Utrecht</i>');
        $html = Site::module($this->params(['template' => '<b>[cb_city]</b>', 'escape_values' => '0']));

        $this->assertStringContainsString('<b>&lt;i&gt;Utrecht&lt;/i&gt;</b>', $html);
    }

    public function testTagsInUserDataAreNotReplaced(): void
    {
        $this->setUserData('cb_city', '[username] [secret]');
        $html = Site::module($this->params([
            'template' => '<b>[cb_city]</b>',
            'rules'    => ['r' => ['tag_name' => 'secret', 'htmlcode' => 'SECRET', 'htmlcode_no' => '']],
        ]));

        $this->assertStringContainsString('<b>[username] [secret]</b>', $html);
        $this->assertStringNotContainsString('SECRET', $html);
    }

    public function testUnknownTagStaysAndNamesAreCaseInsensitive(): void
    {
        $this->assertSame(['Dolly [unknown]', 'Elly elifant [unknown]', 'Duck Toy [unknown]'], $this->names(['template' => '<b>[NAME] [unknown]</b>']));
    }

    public function testPasswordCanNotBeUsedAsTag(): void
    {
        $html = Site::module($this->params(['template' => '<b>[password]</b>']));

        $this->assertStringContainsString('<b>[password]</b>', $html);
        $this->assertStringNotContainsString('hash653', $html);
    }

    public function testRulesForAvatarLikeTheDefaultSettings(): void
    {
        $rules = [
            'rules0' => ['tag_name' => 'avatar', 'htmlcode' => '[avatar]', 'htmlcode_no' => 'nophoto.png'],
            'rules1' => ['tag_name' => 'show_avatar', 'htmlcode' => '<a href="p/[user_id]"><img src="[avatar]" alt="[name]"></a>', 'htmlcode_no' => 'x'],
        ];
        $set = ['template' => '<b>[show_avatar]</b>', 'rules' => $rules, 'user-limit' => 1];

        $this->assertSame(['<a href="p/653"><img src="https://example.org/images/comprofiler/gallery/653.png" alt="Dolly"></a>'], $this->names($set));
        $this->setUserData('avatarapproved', 0);
        $this->assertSame(['<a href="p/653"><img src="nophoto.png" alt="Dolly"></a>'], $this->names($set));
        $this->assertSame(['no image available'], $this->names(['template' => '<b>[avatar]</b>', 'user-limit' => 1]));
    }

    public function testRuleForFieldWithAndWithoutData(): void
    {
        $rules = ['r' => ['tag_name' => 'cb_city', 'htmlcode' => 'in [cb_city]', 'htmlcode_no' => 'nowhere']];
        $set   = ['template' => '<b>[cb_city]</b>', 'rules' => $rules, 'user-limit' => 1];

        $this->assertSame(['in Utrecht'], $this->names($set));
        $this->setUserData('cb_city', '');
        $this->assertSame(['nowhere'], $this->names($set));
    }

    public function testRulesThatUseEachOtherStop(): void
    {
        $rules = [
            'a' => ['tag_name' => 'one', 'htmlcode' => '1[two]', 'htmlcode_no' => ''],
            'b' => ['tag_name' => 'two', 'htmlcode' => '2[one]', 'htmlcode_no' => ''],
        ];

        $this->assertSame(['12'], $this->names(['template' => '<b>[one]</b>', 'rules' => $rules, 'user-limit' => 1]));
    }

    public function testLabelIsFromTheFieldItself(): void
    {
        Site::exec("INSERT INTO jos_comprofiler_field_values VALUES (99,'r','Wrong')");
        Site::exec("INSERT INTO jos_comprofiler_field_values VALUES (6,'r','Red')");
        Site::exec("INSERT INTO jos_comprofiler_field_values VALUES (6,'b','')");
        $set = ['template' => '<b>[cb_color]</b>', 'user-limit' => 1];

        foreach (['r' => 'Red', 'b' => 'b', 'r|*|b' => 'Red b', "o'x" => 'o&#039;x', 'gone' => 'gone'] as $value => $shown) {
            $this->setUserData('cb_color', $value);
            $this->assertSame([$shown], $this->names($set), $value);
        }
    }

    public function testLimitZeroShowsEverybody(): void
    {
        $this->assertCount(3, $this->names(['user-limit' => 0]));
        $this->assertCount(1, $this->names(['user-limit' => 1]));
    }

    public function testProfileUrlAndSiteUrl(): void
    {
        $this->assertSame(
            ['/route?option=com_comprofiler&amp;view=userprofile&amp;user=653 https://example.org/'],
            $this->names(['template' => '<b>[profile_url] [site_url]</b>', 'user-limit' => 1])
        );
    }

    public function testProfileUrlIsOnlyMadeWhenUsed(): void
    {
        \Joomla\CMS\Router\Route::$calls = 0;
        $this->names();
        $this->assertSame(0, \Joomla\CMS\Router\Route::$calls);

        $this->names(['template' => '[show]', 'rules' => ['r' => ['tag_name' => 'show', 'htmlcode' => '[profile_url]', 'htmlcode_no' => '']]]);
        $this->assertSame(3, \Joomla\CMS\Router\Route::$calls);
    }

    public function testOneQueryForAllUsers(): void
    {
        $this->names(['user-limit' => 1]);
        $one = \count(Site::$log);
        Site::$log = [];
        $this->names();

        $this->assertCount($one, Site::$log);
    }

    public function testSettingsThatWereNeverSaved(): void
    {
        $html = Site::module(new Registry(['listid' => '4', 'template' => '<b>[name]</b>']));

        $this->assertSame(3, substr_count($html, 'cblist-user'));
        $this->assertStringContainsString('repeat(auto-fit, minmax(5rem, 1fr))', $html);
    }

    public function testOrderByIdAndUnknownField(): void
    {
        $this->assertSame(['Duck Toy', 'Elly elifant', 'Dolly'], $this->names(['orderby' => 'id', 'sortorder' => 'desc']));
        $this->assertSame(['Dolly', 'Elly elifant', 'Duck Toy'], $this->names(['orderby' => 'cb_gone', 'sortorder' => 'asc']));
    }

    public function testEmptyListGivesNoUsers(): void
    {
        Site::cbList(7, [['name', '=', 'nobody']], 'empty');

        $this->assertSame([], $this->names(['listid' => '7']));
        Site::$superUser = true;
        $this->assertStringContainsString('The list is empty.', Site::module($this->params(['listid' => '7', 'debug' => '1'])));
    }
}
