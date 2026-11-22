<?php
/**
 * @package     Webeau.Plugin
 * @subpackage  Finder.ExcludeArchived
 * @copyright   (C) 2026 web-eau.net
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Keeps archived articles out of Smart Search results without unpublishing them.
 * Joomla indexes archived articles (state = 2) as published; this plugin marks
 * their index entries as unpublished, both when the index is (re)built and when
 * an article is archived from the administrator. Compatible with Joomla 4.4 / 5 / 6.
 */

namespace Webeau\Plugin\Finder\ExcludeArchived\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\EventInterface;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

final class ExcludeArchived extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /** URL stored by the core Content finder plugin for an article */
    private const URL_PREFIX = 'index.php?option=com_content&view=article&id=';

    /** Joomla state value for archived items */
    private const STATE_ARCHIVED = 2;

    public static function getSubscribedEvents(): array
    {
        // Lowest priority: run after the core Smart Search - Content plugin,
        // so its own state handling cannot overwrite ours.
        return [
            'onPrepareFinderContent' => ['onPrepareFinderContent', Priority::MIN],
            'onFinderChangeState'    => ['onFinderChangeState', Priority::MIN],
        ];
    }

    /**
     * Called for every item being indexed (full index, incremental index, article save).
     */
    public function onPrepareFinderContent(EventInterface $event): void
    {
        $item = method_exists($event, 'getItem') ? $event->getItem() : $this->argument($event, 'item', 0);

        if (!\is_object($item) || empty($item->url) || strpos((string) $item->url, self::URL_PREFIX) !== 0) {
            return;
        }

        $id = (int) ($item->id ?? 0) ?: (int) substr((string) $item->url, \strlen(self::URL_PREFIX));

        if ($id && $this->archivedIds([$id])) {
            $item->state     = 0;
            $item->published = 0;
        }
    }

    /**
     * Called when articles change state from the administrator (publish, archive, ...).
     */
    public function onFinderChangeState(EventInterface $event): void
    {
        $context = method_exists($event, 'getContext') ? $event->getContext() : $this->argument($event, 'context', 0);
        $pks     = method_exists($event, 'getPks') ? $event->getPks() : $this->argument($event, 'subject', 1, 'pks');
        $value   = method_exists($event, 'getValue') ? $event->getValue() : $this->argument($event, 'value', 2);

        if ($context !== 'com_content.article' || (int) $value !== self::STATE_ARCHIVED || empty($pks)) {
            return;
        }

        $ids = $this->archivedIds(array_map('intval', (array) $pks));

        if (!$ids) {
            return;
        }

        $urls  = array_map(static fn ($id) => self::URL_PREFIX . (int) $id, $ids);
        $db    = $this->getDatabase();
        $query = $this->newQuery()
            ->update($db->quoteName('#__finder_links'))
            ->set($db->quoteName('state') . ' = 0')
            ->set($db->quoteName('published') . ' = 0')
            ->whereIn($db->quoteName('url'), $urls, ParameterType::STRING);

        $db->setQuery($query)->execute();
    }

    /**
     * Returns the ids, among those given, of archived articles in the configured categories
     * (all categories when none is selected).
     */
    private function archivedIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $this->newQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('id'), $ids)
            ->where($db->quoteName('state') . ' = ' . self::STATE_ARCHIVED);

        $catids = array_values(array_filter(array_map('intval', (array) $this->params->get('catids', []))));

        if ($catids) {
            $query->whereIn($db->quoteName('catid'), $catids);
        }

        return array_map('intval', (array) $db->setQuery($query)->loadColumn());
    }

    private function newQuery()
    {
        $db = $this->getDatabase();

        return method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
    }

    /**
     * Reads an event argument by name, or by position for events dispatched with a plain array.
     */
    private function argument(EventInterface $event, string $name, int $position, ?string $altName = null)
    {
        $args = $event->getArguments();

        if (\array_key_exists($name, $args)) {
            return $args[$name];
        }

        if ($altName !== null && \array_key_exists($altName, $args)) {
            return $args[$altName];
        }

        return $args[$position] ?? null;
    }
}
