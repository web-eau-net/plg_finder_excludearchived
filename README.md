# Smart Search - Exclude Archived

A small Joomla plugin that keeps **archived articles out of Smart Search results** without unpublishing them.

Joomla's Smart Search indexes archived articles (state = 2) as if they were published, so they keep showing up in your site search. This plugin marks their index entries as unpublished:

- when the index is built or rebuilt (full index, incremental index, article save);
- immediately when an article is archived from the administrator, without waiting for a reindex.

Articles themselves are never modified: their state, dates and public page stay as they are. Only the Smart Search index is affected.

## Requirements

- Joomla 4.4, 5.x or 6.x
- Smart Search (com_finder) enabled, with the core *Smart Search - Content* plugin

## Installation

1. Download the latest `plg_finder_excludearchived_x.y.z.zip` from the [Releases](../../releases) page.
2. In the Joomla administrator, go to **System → Install → Extensions** and upload the zip.
3. Enable **Smart Search - Exclude Archived** in **System → Plugins**.
4. Go to **Components → Smart Search**, click **Clear Index**, then **Index**.

Updates are then offered through the standard Joomla updater.

## Options

| Option | Description |
|---|---|
| Categories | Only exclude archived articles from these categories. Leave empty to exclude archived articles from all categories. |

## How it works

The plugin listens to two Smart Search events, at the lowest priority so that it always runs after the core content plugin:

- `onPrepareFinderContent`: if the item being indexed is an archived article, its `state` and `published` values are set to 0;
- `onFinderChangeState`: when articles are archived, their existing entries in `#__finder_links` are set to unpublished.

It supports both the Joomla 4 event arguments and the Joomla 5+ event classes.

## Troubleshooting

If an archived article still appears in the results after a full reindex, check its index entry:

```sql
SELECT l.url, l.state, l.published
FROM #__finder_links l
JOIN #__content c ON l.url = CONCAT('index.php?option=com_content&view=article&id=', c.id)
WHERE c.state = 2
LIMIT 5;
```

Replace `#__` with your table prefix. `state` and `published` should both be `0`.

## License

GNU General Public License version 2 or later, see [LICENSE](LICENSE).

Developed by [web-eau.net](https://www.web-eau.net) for [TemplateJoomla](https://templatejoomla.com).
