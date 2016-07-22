<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * CMS Content Collection
 * Fetches CMS specific content items, including node related info.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28696 $
 * @since $Date: 2008-12-04 16:24:20 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Collection_Layout extends vB_Collection
{
	/*Item==========================================================================*/

	/**
	 * The package identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_package = 'vBCms';

	/**
	 * The class identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_class = 'layout';

	protected $query_hook  = 'vbcms_collection_layout_querydata';

	/*Constants=====================================================================*/

	/**
	 * The total flags for all info.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 15;

	/**
	 * Query types.
	 */
	const QUERY_WIDGETS = 2;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => 7,
		self::QUERY_WIDGETS => 8
	);



	/*LoadInfo======================================================================*/

	/**
	 * Applies the result of the load query.
	 * Child classes should extend or override to determine what was loaded based
	 * on $required_query and $required_info.
	 *
	 * This method should only ever be used directly after performing the queries so
	 * that $this->required_info accurately reflects the query result.
	 *
	 * @param resource $result					- The db result resource
	 * @param int $load_query					- The query that the result is from
	 */
	protected function applyLoad($result, $load_query)
	{
		if (self::QUERY_WIDGETS == $load_query)
		{
			$widgets = $locations = array();
			while ($widget = vB::$db->fetch_array($result))
			{
				$itemid = $widget['itemid'];
				if (!isset($widgets[$itemid]))
				{
					$widgets[$itemid] = $locations[$itemid] = array();
				}

				$widgets[$itemid][] = $widget['widgetid'];
				$locations[$itemid][$widget['column']][$widget['index']] = $widget['widgetid'];
			}

			foreach ($widgets AS $itemid => $widgetlist)
			{
				$this->collection[$itemid]->setWidgets($widgetlist);
				$this->collection[$itemid]->setLocations($locations[$itemid]);
			}

			// mark widget info as loaded
			$this->loaded_info |= vBCms_Item_Layout::INFO_WIDGETS;

			return true;
		}

		return parent::applyLoad($result, $load_query);
	}


	/**
	 * Fetches the SQL for loading.
	 * $required_query is used to identify which query to build for classes that
	 * have multiple queries for fetching info.
	 *
	 * This can safely be based on $this->required_info as long as a consitent
	 * flag is used for identifying the query.
	 *
	 * @param int $required_query				- The required query
	 * @param bool $force_rebuild				- Whether to rebuild the string
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query = '', $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			return
				"SELECT layout.layoutid AS itemid " .
				($this->requireLoad(vBCms_Item_Layout::INFO_BASIC) ?
					",	layout.title " : '') .
				($this->requireLoad(vBCms_Item_Layout::INFO_CONFIG) ?
					",	layout.gridid, layout.contentcolumn, layout.contentindex" : '') .
				($this->requireLoad(vBCms_Item_Layout::INFO_GRID) ?
					",	layout.gridid, grid.title AS gridtitle " : '') .
				$hook_query_fields . "
				FROM " . TABLE_PREFIX . "cms_layout AS layout" .
				($this->requireLoad(vBCms_Item_Layout::INFO_GRID) ?
				"INNER JOIN " . TABLE_PREFIX . "cms_grid AS grid ON grid.gridid = layout.gridid " : '') .
				$hook_query_joins . "
				WHERE 1=1 " .
				($this->itemid ? "AND layout.layoutid IN (" . implode(',', $this->itemid) . ")" : '') . "
				$hook_query_where";
		}
		else if (self::QUERY_WIDGETS == $required_query)
		{
			return "
				SELECT layoutid AS itemid, widgetid, layoutcolumn AS `column`, layoutindex AS `index` " .
				$hook_query_fields . "
				FROM " . TABLE_PREFIX . "cms_layoutwidget " .
				$hook_query_joins . "
				WHERE nodeid IN (" . implode(',', $this->itemdid) . ")
				$hook_query_where";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars_uni($required_query) . '\'specified for layout collection: ' . htmlspecialchars_uni($query)));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28696 $
|| ####################################################################
\*======================================================================*/