<?php

/**
 * Minimal stand-ins for the Joomla CMS classes the extensions use.
 *
 * joomla/registry (parameters) is the real package, installed through Composer.
 * The database is a real SQLite database in memory (see Site.php), so the
 * queries are really executed.
 */

namespace Joomla\CMS {
    class Factory
    {
        public static ?\Tests\Stubs\Database $db = null;
        /** @var int[] view levels of the visitor */
        public static array $viewLevels = [1];
        public static bool $superUser = false;

        public static function getDbo()
        {
            return static::$db;
        }

        public static function getUser()
        {
            return new class {
                public function getAuthorisedViewLevels()
                {
                    return Factory::$viewLevels;
                }

                public function authorise($action)
                {
                    return $action === 'core.admin' && Factory::$superUser;
                }
            };
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static function base()
        {
            return 'https://example.org/';
        }

        public static function root()
        {
            return 'https://example.org/';
        }
    }
}

namespace Joomla\CMS\Router {
    class Route
    {
        public static int $calls = 0;

        public static function _($url)
        {
            static::$calls++;

            return '/route?' . htmlspecialchars(explode('?', $url)[1]);
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        /** @var array<int, array{context: string, text: string, params: mixed}> */
        public static array $calls = [];
        /** @var callable[] content plugins: fn(string $context, object $article, $params) */
        public static array $contentPlugins = [];

        // Only content.prepare. Like the real one, plugins get an object with the text.
        public static function _(string $key, ...$args)
        {
            if ($key !== 'content.prepare') {
                throw new \InvalidArgumentException($key);
            }

            [$text, $params, $context] = $args + ['', null, 'text'];
            static::$calls[] = ['context' => $context, 'text' => $text, 'params' => $params];

            $article       = new \stdClass();
            $article->text = $text;

            foreach (static::$contentPlugins as $plugin) {
                $plugin($context, $article, $params);
            }

            return $article->text;
        }
    }
}

namespace Joomla\CMS\Plugin {
    use Joomla\Registry\Registry;

    class CMSPlugin
    {
        public $params;

        public function __construct($subject = null, array $config = [])
        {
            $this->params = new Registry($config['params'] ?? []);
        }
    }

    class PluginHelper
    {
        public static function getLayoutPath($type, $name, $layout = 'default')
        {
            return \dirname(__DIR__, 2) . "/src/plg_{$type}_{$name}/tmpl/$layout.php";
        }
    }
}
