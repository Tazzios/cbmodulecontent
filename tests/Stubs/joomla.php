<?php

/**
 * Minimal stand-ins for the Joomla CMS classes the extensions use.
 *
 * The packages of the Joomla Framework (database, event, filter, registry) are the real ones,
 * installed through Composer. The database is SQLite in memory (see Site.php), so the queries
 * are really executed.
 */

namespace Joomla\CMS\User {
    class User
    {
        public function __construct(private array $viewLevels, private bool $superUser)
        {
        }

        public function getAuthorisedViewLevels()
        {
            return $this->viewLevels;
        }

        public function authorise($action)
        {
            return $action === 'core.admin' && $this->superUser;
        }
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        private static ?array $strings = null;

        // The English texts of the extensions.
        public static function _($key)
        {
            if (self::$strings === null) {
                self::$strings = [];

                foreach (glob(\dirname(__DIR__, 2) . '/src/*/language/en-GB/*.ini') as $file) {
                    self::$strings += parse_ini_file($file);
                }
            }

            return self::$strings[$key] ?? $key;
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

namespace Joomla\CMS\Filter {
    // The real filter of the Joomla Framework, the CMS class only adds getInstance for this use.
    class InputFilter extends \Joomla\Filter\InputFilter
    {
        public static function getInstance($tagsArray = [], $attrArray = [], $tagsMethod = 0, $attrMethod = 0, $xssAuto = 1)
        {
            return new self($tagsArray, $attrArray, $tagsMethod, $attrMethod, $xssAuto);
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

namespace Joomla\CMS\Event\Content {
    class ContentPrepareEvent
    {
        public function __construct(string $name, private array $arguments)
        {
        }

        public function getContext(): string
        {
            return $this->arguments['context'];
        }

        public function getItem(): object
        {
            return $this->arguments['subject'];
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        /** @var callable[] content plugins: fn(string $context, object $article) */
        public static array $contentPlugins = [];

        // Only content.prepare. Like the real one, plugins get an object with the text.
        public static function _(string $key, ...$args)
        {
            if ($key !== 'content.prepare') {
                throw new \InvalidArgumentException($key);
            }

            [$text, , $context] = $args + ['', null, 'text'];

            $article       = new \stdClass();
            $article->text = $text;

            foreach (static::$contentPlugins as $plugin) {
                $plugin($context, $article);
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

        public function __construct($config = [])
        {
            $this->params = new Registry($config['params'] ?? []);
        }

        public function loadLanguage()
        {
            return true;
        }
    }

    class PluginHelper
    {
        public static string $layoutPath = '';

        public static function getLayoutPath($type, $name, $layout = 'default')
        {
            return static::$layoutPath ?: \dirname(__DIR__, 2) . "/src/plg_{$type}_{$name}/tmpl/$layout.php";
        }
    }
}

namespace Joomla\CMS\Helper {
    interface HelperFactoryAwareInterface
    {
    }

    trait HelperFactoryAwareTrait
    {
        private $helperFactory;

        public function getHelperFactory()
        {
            return $this->helperFactory;
        }

        public function setHelperFactory($helperFactory): void
        {
            $this->helperFactory = $helperFactory;
        }
    }
}

namespace Joomla\CMS\Dispatcher {
    use Joomla\Registry\Registry;

    // Runs the layout of the module like the real one.
    abstract class AbstractModuleDispatcher
    {
        public function __construct(protected \stdClass $module, protected $app)
        {
        }

        public function dispatch()
        {
            $loader = static function (array $displayData) {
                extract($displayData);
                unset($displayData);

                require \dirname(__DIR__, 2) . '/src/' . $module->module . '/tmpl/default.php';
            };

            $loader($this->getLayoutData());
        }

        protected function getLayoutData()
        {
            return ['module' => $this->module, 'app' => $this->app, 'params' => new Registry($this->module->params)];
        }

        protected function getApplication()
        {
            return $this->app;
        }
    }
}
