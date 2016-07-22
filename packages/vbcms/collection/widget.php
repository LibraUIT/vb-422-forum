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
 * Widget Collection
 * Fetches a collection of widgets with the given criteria.
 *
 * ItemId
 * An int itemid refers to a node, an array refers to an array of widget ids.
 *
 * The collection is responsible for fetching the config of the widget.  What config
 * vars are loaded depends on:
 *
 *  The widget's itemid
 *  If there is a known nodeid
 *
 * How cvars are inherited / overwritten can also depend on the config, but this is
 * down to individual widgets to decide.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28696 $
 * @since $Date: 2008-12-04 16:24:20 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Collection_Widget extends vB_Collection
{
	/*Properties==========================================================*/

	/**
	 * If a node is set then the config will be loaded for that node.
	 *
	 * @var int
	 */
	protected $nodeid;


	protected $query_hook = 'vbcms_collection_widget_querydata';

	/*Filters=======================================================================*/

	/**
	 * Only load widgets that are placed on the specified layout.
	 *
	 * @var int
	 */
	protected $filter_layout;



	/*Constants=====================================================================*/

	/**
	 * The total flags for all info.
	 * This is based on the vBCms_Item_Widget info flags.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 0x3;

	/**
	 * Query types.
	 */
	const QUERY_CONFIG = 2;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => 0x1,
		self::QUERY_CONFIG => 0x2
	);



	/*Filters=======================================================================*/

	/**
	 * Sets the node to load the widget config for.
	 *
	 * @param int $nodeid
	 */
	public function setConfigNode($nodeid)
	{
		if ($this->nodeid != $nodeid)
		{
			$this->nodeid = $nodeid;
			$this->Reset();
		}
	}


	/**
	 * Filters widgets to those placed on the specified layout.
	 *
	 * @param int $layoutid
	 */
	public function filterLayout($layoutid)
	{
		if ($this->filter_layout != $layoutid)
		{
			$this->filter_layout = $layoutid;
			$this->Reset();
		}
	}



	/*LoadInfo======================================================================*/

	/**
	 * Applies the result of the load query.
	 *
	 * @param resource $result					- The db result resource
	 * @param int $load_query					- The query that the result is from
	 */
	protected function applyLoad($result, $load_query)
	{
		if (self::QUERY_CONFIG == $load_query)
		{
			// sort configs into individual widgets
			$widget_configs = array();
			while ($cvar = vB::$db->fetch_array($result))
			{
				if (!isset($widget_configs[$cvar['itemid']]))
				{
					$widget_configs[$cvar['itemid']] = array('instance' => array(), 'node' => array());
				}

				$widget_configs[$cvar['itemid']][($cvar['instance'] ? 'instance' : 'node')][$cvar['name']]
					= ($cvar['serialized'] ? unserialize($cvar['value']) : $cvar['value']);
			}

			// merge and apply configs to widgets
			foreach ($widget_configs AS $itemid => &$config)
			{
				$config = array_merge($config['instance'], $config['node']);

				if (isset($this->collection[$itemid]))
				{
					$this->collection[$itemid]->setConfig($config, true);
				}
			}

			// mark config as loaded
			$this->loaded_info |= vBCms_Item_Widget::INFO_CONFIG;

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
	protected function getLoadQuery($required_query = self::QUERY_BASIC, $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			return
				"SELECT widget.widgetid AS itemid " .
				($this->requireLoad(self::INFO_BASIC) ?
					"   ,widget.title, widget.description, widget.widgettypeid " : '') . "
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_widget AS widget " .
				($this->filter_layout ?
				"INNER JOIN " . TABLE_PREFIX . "cms_layoutwidget AS layoutwidget
					ON layoutwidget.layoutid = " . intval($this->filter_layout) . "
					AND layoutwidget.widgetid = widget.widgetid " : '') . "
				$hook_query_joins
				WHERE 1=1 " .
				($this->itemid ? "AND widget.widgetid IN (" . implode(',', $this->itemid) . ")" : '') . "
				ORDER BY widget.title ASC
				$hook_query_where";
		}

		if (self::QUERY_CONFIG == $required_query)
		{
			return
				"SELECT widgetid AS itemid, name, value, (nodeid = 0) instance, serialized
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_widgetconfig
				$hook_query_joins
				WHERE widgetid IN (" . implode(',', $this->itemid) . ")
				AND nodeid = 0 " .
				($this->nodeid ?
					"OR nodeid = " . intval($this->nodeid) : '') . "
				$hook_query_where";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars_uni($required_query) . '\' specified for widget collection'));
	}


	/**
	 * Creates a widget to add to the collection.
	 *
	 * @param array mixed $iteminfo				- The known properties of the new item
	 * @return vB_Item							- The created item
	 */
	protected function createItem($iteminfo, $load_flags = false)
	{
		$class = vBCms_Types::instance()->getWidgetTypeClass($iteminfo['widgettypeid']);
		$package = vBCms_Types::instance()->getWidgetTypePackage($iteminfo['widgettypeid']);

		$item_class = $package . '_Item_Widget_' . $class;
		$item = new $item_class($iteminfo[$this->primary_key]);

		$item->setInfo($iteminfo, $load_flags);

		if ($this->nodeid)
		{
			$item->setNodeId($this->nodeid);
		}

		return $item;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28696 $
|| ####################################################################
\*======================================================================*/