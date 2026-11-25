<?php

use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public static function manifests(): array
    {
        return [
            'library' => ['src/lib_cblisthelper/cblisthelper.xml', 'Tazzios\\Library\\CbListHelper', ''],
            'module'  => ['src/mod_cblistmodule/mod_cblistmodule.xml', 'Tazzios\\Module\\Cblistmodule', '\\Site'],
            'plugin'  => ['src/plg_content_cbprofile/cbprofile.xml', 'Tazzios\\Plugin\\Content\\Cbprofile', ''],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('manifests')]
    public function testNamespaceOfTheClassesIsInTheManifest(string $manifest, string $namespace, string $suffix): void
    {
        $xml = simplexml_load_file(self::ROOT . '/' . $manifest);
        $dir = \dirname(self::ROOT . '/' . $manifest) . '/' . $xml->namespace['path'];
        $this->assertSame($namespace, (string) $xml->namespace);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $sub      = strtr(substr(\dirname($file->getPathname()), \strlen($dir)), '/', '\\');
            $expected = 'namespace ' . $namespace . $suffix . strtr($sub, ['\\\\' => '\\']) . ';';
            $this->assertStringContainsString($expected, file_get_contents($file->getPathname()), $file->getPathname());
        }
    }

    public function testEveryTextInTheManifestsIsTranslated(): void
    {
        foreach (['en-GB', 'nl-NL'] as $tag) {
            $strings = [];

            foreach (array_merge(glob(self::ROOT . "/src/*/language/$tag/*.ini"), glob(self::ROOT . "/package/language/$tag/*.ini")) as $file) {
                $strings += parse_ini_file($file);
            }

            $files = array_merge(
                glob(self::ROOT . '/src/*/*.xml'),
                [self::ROOT . '/pkg_cbmodulecontent.xml', self::ROOT . '/src/mod_cblistmodule/tmpl/default.php'],
                glob(self::ROOT . '/src/plg_content_cbprofile/src/Extension/*.php')
            );

            foreach ($files as $file) {
                preg_match_all('~\b(?:LIB|MOD|PKG|PLG)_[A-Z_]+\b~', file_get_contents($file), $keys);

                foreach (array_unique($keys[0]) as $key) {
                    $this->assertArrayHasKey($key, $strings, "$tag " . basename($file));
                }
            }
        }
    }

    public function testLanguagesHaveTheSameKeys(): void
    {
        foreach (glob(self::ROOT . '/{src/*,package}/language/en-GB/*.ini', GLOB_BRACE) as $file) {
            $dutch = str_replace('en-GB', 'nl-NL', $file);
            $this->assertSame(array_keys(parse_ini_file($file)), array_keys(parse_ini_file($dutch)), basename($file));
        }
    }

    public function testNoOldFilesOrApi(): void
    {
        $code = '';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/src', FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->assertNotSame('index.html', $file->getFilename());
            $code .= $file->getExtension() === 'php' ? file_get_contents($file->getPathname()) : '';
        }

        $this->assertDoesNotMatchRegularExpression('~getDbo\(|Factory::getUser|getQuery\(true|cblisthelper_|\bJ(Factory|Text|Uri|Route|Html)\b~i', $code);
    }
}
