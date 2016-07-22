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
 * Layout item class.
 * A layout contains the grid information for a node.  The grid information can be
 * resolved to a grid template and the widgets that have been selected for the
 * layout, along with their locations and the main content location.
 *
 * @author vBulletin Development Team
 * @version 4.2.2
 * @since 1st Dec, 2008
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Layout extends vB_Item
{
	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 */
	const INFO_CONFIG = 2;
	const INFO_GRID = 4;
	const INFO_WIDGETS = 8;

	/**
	 * The total flags for all info.
	 * This should be overridden by children based on the total of their info flags.
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


	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid item model properties.
	 * This is used to check if a class property can be set as a property.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_BASIC==================*/
		'title',	'gridid',

		/*INFO_CONFIG=================*/
		'contentcolumn', 	'contentindex',

		/*INFO_GRID===================*/
		'gridtitle'
	);


	/*INFO_BASIC==================*/

	/**
	 * The title of the layout for UI selection
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Id of the grid that provides the html for the layout manager.
	 *
	 * @var string
	 */
	protected $gridid;

	/*INFO_CONFIG=================*/

	/**
	 * The column that content should be rendered in.
	 *
	 * @var int
	 */
	protected $contentcolumn;

	/**
	 * The order that the content should be rendered in within the defined column.
	 *
	 * @var int
	 */
	protected $contentindex;

	/**
	 * The locations of the widgets associated with this layout.
	 *
	 * @var array int
	 */
	protected $locations;

	/*INFO_GRID===================*/

	/**
	 * The title of the grid that the layout uses.
	 *
	 * @var string
	 */
	protected $gridtitle;



	/*ClassProperties===============================================================*/

	/**
	 * An array of widgets associated with the layout
	 *
	 * @var bool
	 */
	protected $widgets;


	protected $query_hook = 'vbcms_layout_querydata';

	
	/*LoadInfo======================================================================*/

	/**
	 * Applies the result of the load query.
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
				$widgets[] = $widget['widgetid'];
				$locations[$widget['column']][$widget['index']] = $widget['widgetid'];
			}

			$this->setWidgets($widgets);
			$this->setLocations($locations);

			// mark widget info as loaded
			$this->loaded_info |= self::INFO_WIDGETS;

			return true;
		}

		return parent::applyLoad($result, $load_query);
	}


	/**
	 * Fetches the SQL for loading.
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
				"SELECT layout.layoutid AS itemid" .
				($this->requireLoad(self::INFO_BASIC) ?
					",	layout.title " : '') .
				($this->requireLoad(self::INFO_CONFIG) ?
					",	layout.gridid, layout.contentcolumn, layout.contentindex" : '') .
				($this->requireLoad(self::INFO_GRID) ?
					",	layout.gridid, grid.title AS gridtitle " : '') .
				$hook_query_fields . "
				FROM " . TABLE_PREFIX . "cms_layout AS layout" .
				($this->requireLoad(self::INFO_GRID) ?
				"INNER JOIN " . TABLE_PREFIX . "cms_grid AS grid ON grid.gridid = layout.gridid " : '') .
				$hook_query_join . "
				WHERE layoutid = " . intval($this->itemid) . "
				$hook_query_where";
		}
		else if (self::QUERY_WIDGETS == $required_query)
		{
			return "
				SELECT widgetid, layoutcolumn AS `column`, layoutindex AS `index` " .
				$hook_query_fields . "
				FROM " . TABLE_PREFIX . "cms_layoutwidget " .
				$hook_query_joins . "
				WHERE layoutid = " . intval($this->itemid) . "
				$hook_query_where";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars_uni($required_query) . '\'specified for node item: ' . htmlspecialchars_uni($query)));
	}


	/**
	 * Sets the widget column index info.
	 *
	 * @param array int => int $widgets
	 */
	public function setWidgets($widgets)
	{
		$this->widgets = $widgets;
	}


	/**
	 * Sets the widget locations.
	 * The array is in the form column => index => widget id.
	 *
	 * @param array $locations
	 */
	public function setLocations($locations)
	{
		$this->locations = $locations;
	}



	/*Accessors====================================================================*/

	/**
	 * Fetches the layout title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		$this->Load();

		return $this->title;
	}


	/**
	 * Fetches the column that the content is placed in.
	 *
	 * @return int
	 */
	public function getContentColumn()
	{
		$this->Load(self::INFO_CONFIG);

		return $this->contentcolumn;
	}


	/**
	 * Fetches the index that the content is placed in.
	 *
	 * @return int
	 */
	public function getContentIndex()
	{
		$this->Load(self::INFO_CONFIG);

		return $this->contentindex;
	}


	/**
	 * Fetches and returns the widget ids.
	 *
	 * @return array int
	 */
	public function getWidgetIds()
	{
		$this->Load(self::INFO_WIDGETS);

		return $this->widgets;
	}


	/**
	 * Fetches and returns the widget location info.
	 *
	 * @return array column => index => widget id
	 */
	public function getWidgetLocations()
	{
		$this->Load(self::INFO_WIDGETS);

		return $this->locations;
	}


	/**
	 * Fetches the layout template name.
	 *
	 * @return string
	 */
	public function getTemplate()
	{
		$this->Load(self::INFO_CONFIG);

		return 'vbcms_grid_' . $this->gridid;
	}


	/**
	 * Fetches the title of the grid that the layout uses.
	 *
	 * @return string
	 */
	public function getGridTitle()
	{
		$this->Load(self::INFO_GRID);

		return $this->gridtitle;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77805 $
|| ####################################################################
\*======================================================================*/