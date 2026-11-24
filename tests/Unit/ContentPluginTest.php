<?php

use Joomla\CMS\Factory;
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

    private function prepare(string $text, string $context = 'com_content.article'): string
    {
        $plugin  = new PlgContentcbprofile(null, ['params' => ['layout' => 'text-default', 'imagewidth' => 150, 'imageheight' => 140]]);
        $article = (object) ['text' => $text];
        $params  = null;
        $plugin->onContentPrepare($context, $article, $params);

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
        $this->assertSame([], Factory::$db->log);
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

    public function testUnknownUserGivesMessage(): void
    {
        $this->assertStringContainsString('No userprofiles found', $this->prepare('{cbprofile username=nobody}'));
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
