<?php

/**
 * @package    CB module & content
 * @copyright  2014 Magnus Hasselquist, 2021 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Module\CbList\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Replaces the [tags] in the texts of the administrator (template and rules). Tags in user data are not replaced.
 */
final class TagRenderer
{
    /**
     * @var array<string, array{access: int[], html: string, htmlNo: string}>
     */
    private array $rules = [];

    /**
     * @param   array  $rules       The rules of the module parameters.
     * @param   int[]  $viewLevels  The view levels of the visitor.
     */
    public function __construct(array $rules, private array $viewLevels)
    {
        foreach ($rules as $rule) {
            $rule = (array) $rule;
            $name = strtolower(trim((string) ($rule['tag_name'] ?? '')));

            // The first rule wins from other rules with the same name
            if ($name !== '' && !isset($this->rules[$name])) {
                $this->rules[$name] = [
                    'access' => (array) ($rule['accesslevel'] ?? []),
                    'html'   => (string) ($rule['htmlcode'] ?? ''),
                    'htmlNo' => (string) ($rule['htmlcode_no'] ?? ''),
                ];
            }
        }
    }

    /**
     * @param   string  $text  The text with tags.
     * @param   array   $tags  The tags of the user, safe to show.
     */
    public function render(string $text, array $tags): string
    {
        return $this->replace($text, $tags, []);
    }

    /**
     * @param   string[]  $open  Names of the rules this text is in.
     */
    private function replace(string $text, array $tags, array $open): string
    {
        return (string) preg_replace_callback(
            '/\[([^\[\]]+)\]/',
            function (array $match) use ($tags, $open): string {
                $name = strtolower(trim($match[1]));

                // A rule wins from a CB field. Inside its own rule the tag is the value of the field, this also stops loops.
                if (isset($this->rules[$name]) && !\in_array($name, $open, true)) {
                    return $this->renderRule($name, $tags, $open);
                }

                if (isset($tags[$name])) {
                    $value = $tags[$name]['value'];

                    return $value instanceof \Closure ? $value() : $value;
                }

                // A rule inside itself without a field is empty, an unknown tag is not changed
                return isset($this->rules[$name]) ? '' : $match[0];
            },
            $text
        );
    }

    private function renderRule(string $name, array $tags, array $open): string
    {
        $rule = $this->rules[$name];

        // Without access levels everybody sees it
        if ($rule['access'] && !array_intersect($this->viewLevels, $rule['access'])) {
            return '';
        }

        // A rule without a field always has data
        $hasData = !isset($tags[$name]) || $tags[$name]['hasData'];

        return $this->replace($hasData ? $rule['html'] : $rule['htmlNo'], $tags, array_merge($open, [$name]));
    }
}
