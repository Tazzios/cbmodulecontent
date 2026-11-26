<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Site;

final class ContentPluginTest extends TestCase
{
    protected function setUp(): void
    {
        Site::reset();
        Site::cbList(4);
    }

    private function prepare(string $text, string $context = 'com_content.article', array $settings = []): string
    {
        $article = (object) ['text' => $text];
        Site::prepare($article, $context, $settings + ['layout' => 'text-default', 'imagewidth' => 150, 'imageheight' => 140]);

        return $article->text;
    }

    /** @return int[] the users that are shown, in order */
    private function shown(string $text): array
    {
        preg_match_all('/&user=(\d+)/', $this->prepare($text), $m);

        return array_map('intval', $m[1]);
    }

    public function testTextWithoutTagIsUntouched(): void
    {
        $this->assertSame('<p>Hello</p>', $this->prepare('<p>Hello</p>'));
        $this->assertSame([], Site::$log);
    }

    public function testSmartSearchIndexerIsSkipped(): void
    {
        $text = '{cbprofile username=Dolphine}';

        $this->assertSame($text, $this->prepare($text, 'com_finder.indexer'));
    }

    public static function tags(): array
    {
        [$dolly, $elly, $duck] = [Site::DOLLY, Site::ELLY, Site::DUCK];

        return [
            'username'                     => ['{cbprofile username=Dolphine}', [$dolly]],
            'usernames in the given order' => ['{cbprofile username=DuckT|Dolphine}', [$duck, $dolly]],
            'email'                        => ['{cbprofile email=duckt@example.org}', [$duck]],
            'list id'                      => ['{cbprofile cblistid=4}', [$dolly, $elly, $duck]],
            'list name with a space'       => ['{cbprofile cblistname="all users"}', [$dolly, $elly, $duck]],
            'top'                          => ['{cbprofile cblistid=4 top=2}', [$dolly, $elly]],
            'order desc'                   => ['{cbprofile cblistid=4 order=desc}', [$duck, $elly, $dolly]],
            'orderby'                      => ['{cbprofile cblistid=4 orderby=name}', [$dolly, $duck, $elly]],
            'orderby and order'            => ['{cbprofile cblistid=4 orderby=registerdate order=desc}', [$dolly, $elly, $duck]],
            'username with order desc'     => ['{cbprofile username=Dolphine|DuckT order=desc}', [$duck, $dolly]],
            'parameter names any case'     => ['{cbprofile UserName=Dolphine}', [$dolly]],
            'spaces around ='              => ['{cbprofile username = Dolphine}', [$dolly]],
            'space after {'                => ['{ cbprofile username=Dolphine}', [$dolly]],
            'html inside the tag'          => ['{cbprofile <strong>username=Dolphine</strong>}', [$dolly]],
            'blocked user is not shown'    => ['{cbprofile username=dirk}', []],
            'two tags'                     => ['{cbprofile username=Dolphine} and {cbprofile username=DuckT}', [$dolly, $duck]],
        ];
    }

    #[DataProvider('tags')]
    public function testTag(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->shown($text));
        $this->assertStringNotContainsString('{', $this->prepare($text));
    }

    public function testRandomOrderShowsTheSameUsers(): void
    {
        $users = $this->shown('{cbprofile cblistid=4 order=random}');
        sort($users);

        $this->assertSame([Site::DOLLY, Site::ELLY, Site::DUCK], $users);
    }

    public function testListFiltersAreUsed(): void
    {
        Site::cbList(5, [['name', '<>||ISNULL', 'Dolly'], ['name', 'LIKE', 'D']], 'like');
        Site::cbList(6, [['name', 'IN', 'Dolly,Duck Toy']], 'in');

        $this->assertSame([Site::DUCK], $this->shown('{cbprofile cblistid=5}'));
        $this->assertSame([Site::DOLLY, Site::DUCK], $this->shown('{cbprofile cblistid=6}'));
    }

    public static function maliciousTags(): array
    {
        return [
            'username'      => ["{cbprofile username=x')/**/OR/**/1>0--}", []],
            'username sub'  => ["{cbprofile username=x')/**/UNION/**/SELECT/**/password/**/FROM/**/jos_users--}", []],
            'email'         => ["{cbprofile email=x')/**/OR/**/1>0--}", []],
            'list id'       => ['{cbprofile cblistid=4/**/OR/**/1>0}', [653, 654, 655]],
            'list name'     => ["{cbprofile cblistname=x'/**/OR/**/'1'>'0}", []],
            'orderby'       => ['{cbprofile username=DuckT|Dolphine orderby=(SELECT/**/password/**/FROM/**/jos_users)}', [655, 653]],
            'orderby pass'  => ['{cbprofile username=DuckT|Dolphine orderby=password}', [655, 653]],
            'order'         => ['{cbprofile username=DuckT|Dolphine order=asc;DROP/**/TABLE/**/jos_users}', [655, 653]],
            'top'           => ['{cbprofile cblistid=4 top=1;DROP}', [653, 654, 655]],
            'layout'        => ['{cbprofile username=DuckT layout=../../../tests/Stubs/joomla}', [655]],
        ];
    }

    #[DataProvider('maliciousTags')]
    public function testMaliciousTag(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->shown($text));
        $this->assertSame('4', (string) Site::$db->setQuery('SELECT COUNT(*) FROM #__users')->loadResult());

        foreach (Site::$log as $query) {
            // Nothing from the tag outside a quoted value.
            $this->assertDoesNotMatchRegularExpression('#/\*\*/|password|DROP#', preg_replace("/'(?:[^']|'')*'/", "''", $query));
        }
    }

    public function testSpecialCharactersInTag(): void
    {
        Site::user(661, 'Plus', 'plus+user', 'Utrecht');

        $this->assertSame([661], $this->shown('before {cbprofile username=plus+user} after'));
        $this->assertSame('a No userprofiles found b', $this->prepare('a {cbprofile username=x**(} b'));
    }

    public function testUsernameWithSpace(): void
    {
        Site::user(662, 'Jan', 'Jan Jansen', 'Utrecht');

        $this->assertSame([662], $this->shown('{cbprofile username="Jan Jansen"}'));
        $this->assertSame([662, Site::DUCK], $this->shown('{cbprofile username="Jan Jansen|DuckT"}'));
        $this->assertSame([Site::DUCK, 662], $this->shown('{cbprofile username="DuckT|Jan Jansen" layout=div-avatar imagewidth=20}'));
        $this->assertSame([662], $this->shown('a {cbprofile layout=div-avatar username="Jan Jansen"} b'));
        $this->assertSame([], $this->shown('{cbprofile username="Jan  Jansen"}'));
        $this->assertSame([], $this->shown('{cbprofile username="JanJansen"}'));
    }

    public function testRealPercent20InUsernameIsKept(): void
    {
        Site::user(663, 'Percent', 'a%20b', 'Utrecht');
        Site::user(664, 'Space', 'a b', 'Utrecht');

        $this->assertSame([663], $this->shown('{cbprofile username=a%20b}'));
        $this->assertSame([664], $this->shown('{cbprofile username="a b"}'));
        $this->assertSame([663, 664], $this->shown('{cbprofile username="a%20b|a b"}'));
    }

    public function testListNameWithPercent20StillWorks(): void
    {
        $this->assertSame([653, 654, 655], $this->shown('{cbprofile cblistname=all%20users}'));
    }

    public function testEmailInQuotes(): void
    {
        Site::exec('UPDATE jos_users SET email = ? WHERE id = ?', ['duck toy@example.org', Site::DUCK]);

        $this->assertSame([Site::DOLLY], $this->shown('{cbprofile email="dolphine@example.org"}'));
        $this->assertSame([Site::DOLLY, Site::ELLY], $this->shown('{cbprofile email="dolphine@example.org|elifante@example.org"}'));
        $this->assertSame([Site::DUCK], $this->shown('{cbprofile email="duck toy@example.org"}'));
    }

    public function testMaliciousUsernameWithSpaces(): void
    {
        Site::user(662, 'Jan', 'Jan Jansen', 'Utrecht');

        $this->assertSame([], $this->shown('{cbprofile username="x\') OR 1>0 -- "}'));
        $this->assertSame([], $this->shown('{cbprofile email="x\') OR 1>0 -- "}'));
    }

    public function testHtmlInTagValuesIsEscaped(): void
    {
        $html = $this->prepare('{cbprofile username=Dolphine imagewidth=1"onload="alert(1) float=left"onclick="alert(1)}');

        $this->assertStringNotContainsString('onload="', $html);
        $this->assertStringNotContainsString('onclick="', $html);
    }

    public function testUserDataIsEscaped(): void
    {
        Site::user(665, 'x" onerror="alert(1)<script>', 'xss', 'Utrecht');

        foreach (['text-default', 'text-avatar', 'div-default', 'div-avatar'] as $layout) {
            $html = $this->prepare("{cbprofile username=xss layout=$layout}");
            $this->assertStringContainsString('alt="x&quot; onerror=&quot;alert(1)&lt;script&gt;"', $html, $layout);
            $this->assertStringNotContainsString('<script', $html, $layout);
        }
    }

    public function testOldSettingDoesNotTurnOffEscaping(): void
    {
        Site::user(665, '<i>Jan</i>{loadposition x}', 'html', 'Utrecht');
        $html = $this->prepare('{cbprofile username=html}', 'com_content.article', ['escape_values' => '0']);

        $this->assertStringContainsString('&lt;i&gt;Jan&lt;/i&gt;&#123;loadposition x&#125;', $html);
    }

    public function testEditorFieldInOwnLayoutShowsHtmlWithoutScripts(): void
    {
        Joomla\CMS\Plugin\PluginHelper::$layoutPath = \dirname(__DIR__) . '/Stubs/layout.php';
        $about = '<p>Hi <strong>there</strong></p><script>alert(1)</script><img src="x.png" onerror="alert(1)"><a href="javascript:alert(1)">x</a>{loadposition x}';
        Site::exec('UPDATE jos_comprofiler SET cb_about = ?, cb_city = ? WHERE id = ?', [$about, '<i>Utrecht</i>', Site::DOLLY]);
        $html = $this->prepare('{cbprofile username=Dolphine}');

        $this->assertStringContainsString('<p>Hi <strong>there</strong></p>', $html);
        $this->assertStringContainsString('&#123;loadposition x&#125;', $html);
        $this->assertStringContainsString('city=&lt;i&gt;Utrecht&lt;/i&gt;', $html);
        $this->assertDoesNotMatchRegularExpression('~<script|onerror\s*=|href="?javascript~i', $html);
    }

    public function testTagInUserDataIsNotLeftForOtherPlugins(): void
    {
        Site::user(660, '{loadposition secret}', 'evil', 'Utrecht');
        $html = $this->prepare('{cbprofile username=evil}');

        $this->assertStringContainsString('&#123;loadposition secret&#125;', $html);
        $this->assertStringNotContainsString('{', $html);
    }

    public function testItemWithoutTextIsSkipped(): void
    {
        $article = new stdClass();
        Site::prepare($article, 'com_content.article', []);

        $this->assertSame([], Site::$log);
    }

    public function testSubscribesToContentPrepare(): void
    {
        $this->assertSame(
            ['onContentPrepare' => 'onContentPrepare'],
            Tazzios\Plugin\Content\Cbprofile\Extension\Cbprofile::getSubscribedEvents()
        );
    }

    public function testOrderAndTopWithUsernames(): void
    {
        $this->assertSame([655, 654], $this->shown('{cbprofile username=Dolphine|elifantE|DuckT orderby=registerdate order=asc top=2}'));
        $this->assertSame([653, 654], $this->shown('{cbprofile username=Dolphine|elifantE|DuckT top=2}'));
        $this->assertSame([655, 654], $this->shown('{cbprofile username=Dolphine|elifantE|DuckT order=desc top=2}'));
        $this->assertSame([655, 653], $this->shown('{cbprofile username=DUCKT|dolphine}'));
        $this->assertCount(2, $this->shown('{cbprofile cblistid=4 order=random orderby=name top=2}'));
        $this->assertSame([654, 655], $this->shown('{cbprofile cblistid=4 orderby=name order=desc top=2}'));
    }

    public function testUnknownUserGivesMessage(): void
    {
        $this->assertSame('<p>No userprofiles found</p>', $this->prepare('<p>{cbprofile username=nobody}</p>'));
    }

    public function testEmptyListGivesMessage(): void
    {
        Site::cbList(8, [['name', '=', 'nobody']], 'empty');

        $this->assertSame('No userprofiles found', $this->prepare('{cbprofile cblistid=8}'));
        $this->assertSame('No userprofiles found', $this->prepare('{cbprofile cblistname=empty}'));
        $this->assertSame('No userprofiles found', $this->prepare('{cbprofile cblistid=99}'));
        $this->assertSame('No userprofiles found', $this->prepare('{cbprofile}'));
    }

    public function testEmptyListDoesNotShowUsersOfTheTagBefore(): void
    {
        Site::cbList(8, [['name', '=', 'nobody']], 'empty');

        $this->assertSame([Site::DOLLY], $this->shown('{cbprofile username=Dolphine} {cbprofile cblistid=8}'));
    }

    public function testTextDefaultShowsOnlyTheNameWithoutApprovedAvatar(): void
    {
        $tag = '{cbprofile username=Dolphine layout=text-default}';
        $this->assertStringContainsString('src="https://example.org/images/comprofiler/gallery/653.png"', $this->prepare($tag));

        foreach (['avatarapproved = 0', 'avatarapproved = 1, avatar = NULL', "avatarapproved = 1, avatar = ''"] as $set) {
            Site::exec("UPDATE jos_comprofiler SET $set");
            $html = $this->prepare($tag);
            $this->assertStringNotContainsString('<img', $html, $set);
            $this->assertStringContainsString('Dolly', $html, $set);
        }
    }

    public function testDivsAreBalanced(): void
    {
        foreach (['username=Dolphine', 'username=nobody', 'username=Dolphine intext=true', 'username=nobody intext=true'] as $tag) {
            $html = strtolower($this->prepare("{cbprofile $tag layout=div-avatar}"));
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div'), $tag);
        }
    }

    public static function avatarLayouts(): array
    {
        return [['div-avatar'], ['div-default'], ['text-avatar']];
    }

    #[DataProvider('avatarLayouts')]
    public function testOnlyApprovedAvatarIsShown(string $layout): void
    {
        $tag = "{cbprofile username=Dolphine layout=$layout}";
        $this->assertStringContainsString('gallery/653.png', $this->prepare($tag));

        Site::exec('UPDATE jos_comprofiler SET avatarapproved = 0');
        $this->assertStringContainsString('tnnophoto_n.png', $this->prepare($tag));
        $this->assertStringNotContainsString('gallery/653.png', $this->prepare($tag));

        Site::exec('UPDATE jos_comprofiler SET avatarapproved = 1, avatar = NULL');
        $this->assertStringContainsString('tnnophoto_n.png', $this->prepare($tag));
    }

    public function testImageSizeFromPluginSettingsAndFromTag(): void
    {
        $this->assertStringContainsString('width="150" height="140"', $this->prepare('{cbprofile username=Dolphine}'));
        $this->assertStringContainsString('width="20" height="30"', $this->prepare('{cbprofile username=Dolphine imagewidth=20 imageheight=30}'));
    }

    public function testLayoutFromTag(): void
    {
        $this->assertStringContainsString('cbprofile-userprofile', $this->prepare('{cbprofile username=Dolphine layout=div-default}'));
    }

    public function testIntextHasNoBlock(): void
    {
        $this->assertStringContainsString('cbprofile-block', $this->prepare('{cbprofile username=Dolphine}'));
        $this->assertStringNotContainsString('cbprofile-block', $this->prepare('{cbprofile username=Dolphine intext=true}'));
    }

    public function testFloat(): void
    {
        $this->assertStringContainsString('float:right;', $this->prepare('{cbprofile username=Dolphine float=right}'));
    }

    public function testTextAroundTagIsKept(): void
    {
        $this->assertMatchesRegularExpression('#^<p>before .* after</p>$#s', $this->prepare('<p>before {cbprofile username=Dolphine} after</p>'));
    }
}
