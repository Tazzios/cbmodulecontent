<?php

/**
 * @package    CB module & content
 * @copyright  2022 Tazzios
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Tazzios\Plugin\Content\Cbprofile\Extension;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Tazzios\Library\CbListHelper\ListQuery;
use Tazzios\Library\CbListHelper\UserData;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shows CB profiles in content with {cbprofile ...}.
 */
final class Cbprofile extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Columns that can not be used to order by.
     */
    private const NOT_ORDERABLE = ['password', 'params', 'activation', 'otpkey', 'otep', 'cbactivation', 'registeripaddr'];

    /**
     * Stands for a space between quotes, can not be in a name.
     */
    private const SPACE = "\x1F";

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();

        // Not when the content is indexed, and only when there can be a tag
        if (
            $event->getContext() === 'com_finder.indexer'
            || !isset($item->text)
            || !str_contains((string) $item->text, 'cbprofile')
        ) {
            return;
        }

        preg_match_all('/{\s*cbprofile\s*(.*?)}/i', $item->text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $position = strpos($item->text, $match[0]);

            if ($position !== false) {
                $item->text = substr_replace($item->text, $this->getOutput($match[1]), $position, \strlen($match[0]));
            }
        }
    }

    /**
     * The HTML for one tag.
     */
    private function getOutput(string $tag): string
    {
        $tagParams = $this->getTagParams($tag);
        $profiles  = $this->getProfiles($tagParams);

        if (!$profiles) {
            $this->loadLanguage();

            return Text::_('PLG_CONTENT_CBPROFILE_NO_PROFILES');
        }

        // Only a name, no path
        $layout = $tagParams['layout'] ?? '';
        $layout = preg_match('/^[\w:-]+$/', $layout) ? $layout : (string) $this->params->get('layout', 'div-default');

        $imagesize = [
            'width'  => htmlspecialchars($tagParams['imagewidth'] ?? (string) $this->params->get('imagewidth', 150), ENT_QUOTES, 'UTF-8'),
            'height' => htmlspecialchars($tagParams['imageheight'] ?? (string) $this->params->get('imageheight', 150), ENT_QUOTES, 'UTF-8'),
        ];

        $editorFields = UserData::getEditorFields($this->getDatabase());
        $output       = '';

        foreach ($profiles as $profile) {
            $output .= $this->renderProfile($profile, $layout, $imagesize, $editorFields);
        }

        if (($tagParams['intext'] ?? '') === 'true') {
            return $output;
        }

        $style = isset($tagParams['float'])
            ? 'float:' . htmlspecialchars($tagParams['float'], ENT_QUOTES, 'UTF-8') . ';'
            : 'display: grid;grid-template-columns: repeat(auto-fit, minmax(calc(' . $imagesize['width'] . 'px + 10px), 1fr));';

        return '<div class="cbprofile-block" style="' . $style . '">' . $output . '</div>';
    }

    /**
     * The parameters of a tag, the names in lower case.
     *
     * @return  array<string, string>
     */
    private function getTagParams(string $tag): array
    {
        $tag = strip_tags((string) preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $tag));
        $tag = str_replace([' =', '= '], '=', $tag);

        // A space separates the parameters, but not between quotes
        $tag = (string) preg_replace('~\s+(?=([^"]*"[^"]*")*[^"]*$)~', ',', $tag);
        $tag = str_replace(' ', self::SPACE, $tag);

        preg_match_all('/([^,= ]+)=([^,= ]+)/', $tag, $matches);

        return array_change_key_case(array_combine($matches[1], $matches[2]), CASE_LOWER);
    }

    /**
     * The value of a parameter, without quotes and with its spaces.
     */
    private function getValue(string $value): string
    {
        return str_replace(self::SPACE, ' ', trim($value, '"'));
    }

    /**
     * The profiles to show, in order.
     */
    private function getProfiles(array $tagParams): array
    {
        if (isset($tagParams['cblistid'])) {
            return $this->getListProfiles((int) $tagParams['cblistid'], null, $tagParams);
        }

        if (isset($tagParams['cblistname'])) {
            return $this->getListProfiles(null, str_replace('%20', ' ', $this->getValue($tagParams['cblistname'])), $tagParams);
        }

        foreach (['username', 'email'] as $column) {
            if (isset($tagParams[$column])) {
                return $this->loadProfiles($column, explode('|', $this->getValue($tagParams[$column])), $tagParams);
            }
        }

        return [];
    }

    private function getListProfiles(?int $id, ?string $title, array $tagParams): array
    {
        $db        = $this->getDatabase();
        $listQuery = new ListQuery($db);
        $list      = $id !== null ? $listQuery->getListById($id) : $listQuery->getListByTitle((string) $title);

        [$column, $direction] = $listQuery->getOrder($list);

        $ids = $db->setQuery($listQuery->getQuery($list)->order($column . ' ' . $direction))->loadColumn() ?: [];

        return $ids ? $this->loadProfiles('user_id', array_map('strval', $ids), $tagParams) : [];
    }

    /**
     * @param   string    $column     user_id, username or email.
     * @param   string[]  $values     The users, in the order to show them.
     * @param   array     $tagParams  The parameters of the tag.
     */
    private function loadProfiles(string $column, array $values, array $tagParams): array
    {
        $db        = $this->getDatabase();
        $direction = strtolower($tagParams['order'] ?? '');
        $top       = isset($tagParams['top']) && is_numeric($tagParams['top']) ? (int) $tagParams['top'] : 0;
        $orderBy   = isset($tagParams['orderby']) ? $this->getOrderColumn($tagParams['orderby']) : '';

        $query = $db->createQuery()
            ->select($db->quoteName(['u.username', 'u.name', 'u.email']))
            ->select('ue.*')
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__comprofiler', 'ue') . ' ON ' . $db->quoteName('ue.user_id') . ' = ' . $db->quoteName('u.id'));

        if ($column === 'user_id') {
            // The order is known already, only load what is shown
            if ($orderBy === '' || $direction === 'random') {
                $values  = $this->limit($this->sort($values, $direction), $top);
                $orderBy = $direction = '';
                $top     = 0;
            }

            $query->where($db->quoteName('ue.user_id') . ' IN (' . implode(',', array_map('intval', $values)) . ')');
        } else {
            $query->where($db->quoteName('u.block') . ' = 0')
                ->where($db->quoteName('u.' . $column) . ' IN (' . implode(',', array_map([$db, 'quote'], $values)) . ')');
        }

        if ($orderBy !== '' && $direction !== 'random') {
            $query->order($orderBy . ($direction === 'desc' ? ' DESC' : ' ASC'));

            if ($top > 0) {
                $query->setLimit($top);
            }

            return $db->setQuery($query)->loadAssocList() ?: [];
        }

        $profiles = $db->setQuery($query)->loadAssocList() ?: [];
        $position = array_flip(array_map('mb_strtolower', $values));

        usort(
            $profiles,
            static fn ($a, $b) => ($position[mb_strtolower((string) $a[$column])] ?? 0) <=> ($position[mb_strtolower((string) $b[$column])] ?? 0)
        );

        return $this->limit($this->sort($profiles, $direction), $top);
    }

    private function sort(array $items, string $direction): array
    {
        if ($direction === 'random') {
            shuffle($items);
        }

        return $direction === 'desc' ? array_reverse($items) : $items;
    }

    private function limit(array $items, int $top): array
    {
        return $top > 0 ? \array_slice($items, 0, $top) : $items;
    }

    /**
     * The quoted column to order by, empty if it is not an allowed column.
     */
    private function getOrderColumn(string $column): string
    {
        if (!preg_match('/^\w+$/', $column) || \in_array(strtolower($column), self::NOT_ORDERABLE, true)) {
            return '';
        }

        return (new ListQuery($this->getDatabase()))->getColumn($column);
    }

    /**
     * Renders the layout for one user. A layout can use $userprofile (safe to show) and $imagesize.
     */
    private function renderProfile(array $profile, string $layout, array $imagesize, array $editorFields): string
    {
        $userprofile = [];

        foreach ($profile as $field => $value) {
            $userprofile[$field] = \is_string($value)
                ? UserData::safe($value, \in_array($field, $editorFields, true) ? UserData::EDITOR : '')
                : $value;
        }

        ob_start();

        try {
            include PluginHelper::getLayoutPath('content', 'cbprofile', $layout);
        } finally {
            $output = ob_get_clean();
        }

        return $output;
    }
}
