<?php

/**
 * @package    CB module & content
 * @copyright  2022 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Library\CbListHelper;

use Joomla\CMS\Filter\InputFilter;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Makes user data safe to show in HTML.
 */
final class UserData
{
    /**
     * Type of the CB field with HTML.
     */
    public const EDITOR = 'editorta';

    private const TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'dd', 'del', 'div', 'dl', 'dt', 'em',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 's', 'small', 'span',
        'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const ATTRIBUTES = [
        'href', 'src', 'alt', 'title', 'width', 'height', 'target', 'rel', 'class', 'colspan', 'rowspan',
    ];

    /**
     * Only a CB editor field keeps its HTML, without scripts.
     *
     * @param   string  $value      The value of the user.
     * @param   string  $fieldType  The type of the CB field.
     */
    public static function safe(string $value, string $fieldType = ''): string
    {
        if ($fieldType === self::EDITOR) {
            $value = InputFilter::getInstance(self::TAGS, self::ATTRIBUTES, 0, 0, 1)->clean($value, 'html');
        } else {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        // No tags for content plugins, the output goes through them
        return str_replace(['{', '}'], ['&#123;', '&#125;'], $value);
    }

    /**
     * The names of the CB editor fields.
     *
     * @return  string[]
     */
    public static function getEditorFields(DatabaseInterface $db): array
    {
        $query = $db->createQuery()
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__comprofiler_fields'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::EDITOR));

        return $db->setQuery($query)->loadColumn() ?: [];
    }
}
