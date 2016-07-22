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
 * Fetches CMS nodes.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28696 $
 * @since $Date: 2008-12-04 16:24:20 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Collection_Content extends vB_Collection
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
	protected $item_class = 'Content';

	/**
	 * Whether this collection type supports pagination.
	 *
	 * @var bool
	 */
	protected $can_paginate = true;

	protected $visible_only = true;

	protected $query_hook = 'vbcms_collection_content_querydata';

	/*Constants=====================================================================*/

	/**
	 * The total flags for all info.
	 * Don't include INFO_CONTENT.  This will have to be added by
	 *
	 * @var int
	 */
	protected $INFO_ALL = 127;

	/**
	 * Query types.
	 */
	const QUERY_PARENTS = 2;
	const QUERY_CONFIG = 3;
	const QUERY_CONTENT = 4;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => /* vB_Item::INFO_BASIC | vB_Item::INFO_NODE | vB_Item::INFO_DEPTH */ 7,
		self::QUERY_PARENTS => vBCms_Item_Content::INFO_PARENTS,
		self::QUERY_CONFIG => vBCms_Item_Content::INFO_CONFIG,
		self::QUERY_CONTENT => vBCms_Item_Content::INFO_CONTENT
	);




	/*Filters=======================================================================*/

	/**
	 * If a node id is specified then content below that node will be fetched.
	 *
	 * @var int
	 */
	protected $filter_node;

	/**
	 * Filter by contenttype id.
	 *
	 * @var int
	 */
	protected $filter_contenttype;

	/**
	 * Filter out contenttypes.
	 * Array of int ids to filter out.
	 *
	 * var array
	 */
	protected $filter_notcontenttype = array();

	/**
	 * Find a specific content id
	 *
	 * @var int
	 */
	protected $filter_contentid;

	/**
	 * Don't check permissions.
	 *
	 * @var bool
	 */
	protected $filter_nopermissions;

	/**
	 * Don't fetch sections.
	 *
	 * @var bool
	 */
	protected $filter_nosections;

	/**
	 * Only fetch sections.
	 *
	 * @var bool
	 */
	protected $filter_onlysections;

	/**
	 * Fetch content created by a specific user.
	 *
	 * @var int
	 */
	protected $filter_userid;

	/**
	 * Fetch content that is published.
	 *
	 * @var bool
	 */
	protected $filter_published = false;

	/**
	 * Fetch content that is not published.
	 *
	 * @var bool
	 */
	protected $filter_unpublished = false;

	protected $filter_node_exact = false;

	protected $filter_ignorepermissions = false;

	protected $max_records = false;

	/*Filters=======================================================================*/
	/*** This flag sets the collection to display on items in the selected section
	 * **/
	public function setFilterNodeExact($value = true)
	{
		$this->filter_node_exact = $value;
	}

	/**
	 * Sets the nodeid for the node that the widgets are being displayed on.
	 * If a layoutid or itemid is also set then this will only affect the config that is
	 * loaded.
	 *
	 * @param int $nodeid
	 */
	public function filterNode($nodeid)
	{
		if ($this->filter_node != $nodeid)
		{
			$this->filter_node = $nodeid;
			$this->reset();
		}
	}


	/**
	 * Sets the a contenttype to filter.
	 *
	 * @param int $layoutid
	 */
	public function filterContentType($contenttypeid)
	{
		if ($this->filter_contenttype != $contenttypeid)
		{
			$this->filter_contenttype = $contenttypeid;
			$this->reset();
		}
	}


	/**
	 * Sets a contenttype to exclude.
	 *
	 * @param int $contenttypeid				- The contenttype to exclude
	 */
	public function filterNotContentType($contenttypeid)
	{
		$contenttypeid = intval($contenttypeid);

		if (!in_array($contenttypeid, $this->filter_notcontenttype))
		{
			$this->filter_notcontenttype[] = $contenttypeid;
			$this->reset();
		}
	}


	/**
	 * Sets a specific content item to locate.
	 *
	 * @param int $contenttypeid
	 * @param int $contentid
	 */
	public function filterContentID($contenttypeid, $contentid)
	{
		$this->filterContentType($contenttypeid);
		$this->filter_contentid = $contentid;
	}


	/**
	 * Sets whether to ignore permissions.
	 *
	 * @param bool $filter_ignorepermissions
	 */
	public function filterIgnorePermissions($filter_ignorepermissions  = true)
	{
		if ($this->filter_ignorepermissions != $filter_ignorepermissions)
		{
			$this->filter_ignorepermissions = $filter_ignorepermissions;
			$this->reset();
		}
	}


	/**
	 * Sets whether to only fetch section nodes.
	 *
	 * @param bool $filter
	 */
	public function filterOnlySections($filter_sections = true)
	{
		if ($this->filter_sections != $filter_sections)
		{
			$this->filter_onlysections = $filter_sections;
			$this->filterNoSections(false);
			$this->reset();
		}
	}


	/**
	 * Sets whether to not fetch section nodes.
	 *
	 * @param bool $filter_nosections
	 */
	public function filterNoSections($filter_nosections = true)
	{
		if ($this->filter_nosections != $filter_nosections)
		{
			$this->filter_nosections = $filter_nosections;
			$this->filterOnlySections(false);
			$this->reset();
		}
	}


	/**
	 * Filter content by a particular user.
	 *
	 * @param int $filter_userid
	 */
	public function filterUserId($filter_userid)
	{
		if ($this->filter_userid != $filter_userid)
		{
			$this->filter_userid = $filter_userid;
			$this->reset();
		}
	}


	/**
	 * Filter content that is published.
	 *
	 * @param bool $filter_published
	 */
	public function filterPublished($filter_published = true)
	{
		if ($this->filter_published != $filter_published)
		{
			$this->filter_published = $filter_published;
			$this->reset();
		}
	}

	/**
	 * Filter content that is not published.
	 *
	 * @param bool $filter_unpublished
	 */
	public function filterUnPublished($filter_unpublished = true)
	{
		if ($this->filter_unpublished != $filter_unpublished)
		{
			$this->filter_unpublished = $filter_unpublished;

			if ($this->filter_unpublished)
			{
				$this->filter_published = false;
			}

			$this->reset();
		}
	}


	/**
	 * Removes all filters.
	 */
	public function removeFilters()
	{
		$this->filter_node = false;
		$this->filter_contenttype = false;
		$this->filter_contentid = false;
		$this->filter_nosections = false;
		$this->filter_onlysections = false;
		$this->filter_userid = false;
		$this->filter_published = false;
		$this->filter_unpublished = false;
	}

	/**
	 * Removes all filters.
	 */
	public function filterVisible($visible_only = true)
	{
		$this->visible_only = $visible_only;
	}

	/**
	 * sets the maximum number of records to be returned
	 *
	 * @param int
	 */
	public function setMaxRecords($count)
	{
		$this->max_records = $count;
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
		if (self::QUERY_PARENTS == $load_query)
		{
			$parents = array();
			if (vB::$db->num_rows($result))
			{
				while ($parent = vB::$db->fetch_array($result))
				{
					if (!isset($parents[$parent['itemid']]))
					{
						$parents[$parent['itemid']] = array();
					}

					$parents[$parent['itemid']][$parent['nodeid']] = $parent;
				}
			}

			foreach ($parents AS $itemid => $parentlist)
			{
				$this->collection[$itemid]->setParents($parentlist);
			}

			// mark parents as loaded
			$this->loaded_info |= vBCms_Item_Content::INFO_PARENTS;

			return true;
		}
		else if (self::QUERY_CONFIG == $load_query)
		{
			// sort configs into individual widgets
			$configs = array();
			while ($cvar = vB::$db->fetch_array($result))
			{
				if (!isset($configs[$cvar['itemid']]))
				{
					$configs[$cvar['itemid']] = array();
				}

				$configs[$cvar['itemid']][$cvar['name']] = $cvar['value'];
			}

			// set the configs on the items
			foreach ($configs AS $itemid => $config)
			{
				$this->collection[$itemid]->setConfig($config, true);
			}

			// mark config as loaded
			$this->loaded_info |= vBCms_Item_Content::INFO_CONFIG;

			return true;
		}

		return parent::applyLoad($result, $load_query);
	}

	//for paging, we need to get the count of items.
	public function getCount()
	{
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;
		// Internal hooks for loading content with QUERY_BASIC

		$content_query_fields = $content_query_joins = $content_query_where = '';
		//		if ($this->requireLoad(vBCms_Item_Content::INFO_CONTENT))
		//		{
		$content_query_fields = $this->getContentQueryFields();
		$content_query_joins = $this->getContentQueryJoins();
		$content_query_where = $this->getContentQueryWhere();
		//		}

		$filter_notcontenttype = $this->getFilterNotContentTypeSql();

		//make sure permissions are loaded.
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		if (!count(vB::$vbulletin->userinfo['permissions']['cms']['allview']))
		{
			return false;
		}
		$publishfilter = '';
		if ($this->filter_published)
		{
			$sql1 = "node.setpublish = '1' AND node.publishdate <= " . intval(TIMENOW) . " ";
			if (!$this->filter_userid OR $this->filter_userid == vB::$vbulletin->userinfo['userid'])
			{
				$publishfilter .= " AND (($sql1) OR node.userid = " . vB::$vbulletin->userinfo['userid'] . ") ";
			}
			else
			{
				$publishfilter .= " AND $sql1 ";
			}
		}
		$sql = "SELECT count(node.nodeid) AS qty
		FROM " . TABLE_PREFIX . "cms_node AS node"
		.	($this->filter_node ?
		" INNER JOIN " . TABLE_PREFIX . "cms_node AS rootnode
			ON rootnode.nodeid = " . intval($this->filter_node) : '') .
		"	$content_query_joins
		$hook_query_joins
		WHERE (1=1) " .
		($this->filter_contenttype ? "AND node.contenttypeid = " . intval($this->filter_contenttype) . " " : '') .
		($this->filter_contentid ? "AND node.contentid = " . intval($this->contentid) . " ": '') .
		($this->filter_node ? "AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <=rootnode.noderight) AND node.nodeleft != rootnode.nodeleft " : '') .
		($this->filter_nosections ? "AND node.issection != '1' " : '') .
		($this->filter_onlysections ? "AND node.issection = '1' " : '') .
		($this->filter_userid ? "AND node.userid = " . intval($this->filter_userid) . " " : '') .
		$publishfilter .
		($this->filter_unpublished ? "AND node.setpublish = '0' OR node.publishdate > " . intval(TIMENOW) . " " : '') . "
		" . ((($this->filter_contenttype AND ($this->filter_contenttype == vB_Types::instance()->getContentTypeID("vBCms_Section"))) OR $this->filter_onlysections)
		? '' : "AND node.new != 1 ")
		.
		($this->filter_ignorepermissions ? '' : " AND " .  vBCMS_Permissions::getPermissionString())
		.
		"
		$filter_notcontenttype
		$content_query_where
		$hook_query_where ";

		if ($record = vB::$vbulletin->db->query_first($sql))
		{
			return intval($record['qty']);
		}
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
		// Internal hooks for loading content with QUERY_BASIC

		$content_query_fields = $content_query_joins = $content_query_where = '';
//		if ($this->requireLoad(vBCms_Item_Content::INFO_CONTENT))
//		{
 			$content_query_fields = $this->getContentQueryFields();
			$content_query_joins = $this->getContentQueryJoins();
			$content_query_where = $this->getContentQueryWhere();
//		}

		// Content item queries
		if (self::QUERY_BASIC == $required_query)
		{
			$calc_rows = $this->requireLoad(vBCms_Item_Content::INFO_BASIC) ? 'SQL_CALC_FOUND_ROWS' : '';
			if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
			{
				require_once DIR . '/packages/vbcms/permissions.php';
				vBCMS_Permissions::getUserPerms();
			}

			//We need a nodeid for the displayorder below
			if ($this->filter_node_exact AND !$this->filter_node )
			{
				$this->filter_node = $this->filter_node_exact;
			}

			//enforce the max_records limits
			if ($this->max_records)
			{
				$this->paginate = true;

				if (!$this->start)
				{
					$this->start = 0;
				}
				$this->quantity = $this->max_records;
			}

			$filter_notcontenttype = $this->getFilterNotContentTypeSql();

			$publishfilter = '';
			if ($this->filter_published)
			{
				$sql1 = "node.setpublish = '1' AND node.publishdate <= " . intval(TIMENOW) . " ";
				if (!$this->filter_userid OR $this->filter_userid == vB::$vbulletin->userinfo['userid'])
				{
					$publishfilter .= " AND (($sql1) OR node.userid = " . vB::$vbulletin->userinfo['userid'] . ") ";
				}
				else
				{
					$publishfilter .= " AND $sql1 ";
				}
			}

				$sql = "SELECT $calc_rows node.nodeid AS itemid" .
				($this->requireLoad(vBCms_Item_Content::INFO_BASIC) ?
					"   ,(node.nodeleft = 1) AS isroot, node.nodeid, node.contenttypeid, node.contentid, node.url, node.parentnode, node.styleid, node.userid,
						node.layoutid, node.publishdate, node.setpublish, node.issection, parent.permissionsfrom as parentpermissions,
						node.showrating,
						node.permissionsfrom, node.publicpreview, node.shownav, node.hidden, node.nosearch " : '') .
				($this->requireLoad(vBCms_Item_Content::INFO_NODE) ?
					 ", info.description, info.title, info.viewcount, info.creationdate, info.workflowdate,
					 info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid,
					 user.username, sectionorder.displayorder" : '') .
				($this->requireLoad(vBCms_Item_Content::INFO_DEPTH) ?
					", (COUNT(pdepth.nodeid) - 1) AS depth" : '') . "
					 $content_query_fields
					 $hook_query_fields
				FROM " . TABLE_PREFIX . "cms_node AS node " .
				($this->requireLoad(vBCms_Item_Content::INFO_NODE) ? "
				INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid
				LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS sectionorder ON sectionorder.sectionid = node.parentnode
				AND sectionorder.nodeid = node.nodeid" : '')
				. ($this->requireLoad(self::INFO_BASIC) ? "
				LEFT JOIN " . TABLE_PREFIX . "cms_node AS parent ON parent.nodeid = node.parentnode " : '')
				.	($this->filter_node ?
				"INNER JOIN " . TABLE_PREFIX . "cms_node AS rootnode
					ON rootnode.nodeid = " . intval($this->filter_node) : '') .
				($this->requireLoad(vBCms_Item_Content::INFO_DEPTH) ?
				" LEFT JOIN " . TABLE_PREFIX . "cms_node AS pdepth ON (node.nodeleft >= pdepth.nodeleft AND node.nodeleft <=pdepth.noderight>" : '') .
				"	$content_query_joins
				$hook_query_joins
				WHERE node.new != 1 " .
				($this->itemid ? " AND node.nodeid IN (" . implode(',', $this->itemid) . ") " : '') .
				($this->filter_ignorepermissions ? '' : " AND " . vBCMS_Permissions::getPermissionString())
				 .
				((($this->filter_contenttype AND ($this->filter_contenttype == vB_Types::instance()->getContentTypeID("vBCms_Section"))) OR $this->filter_onlysections)
					? '' : "AND node.new != 1 ") .
				($this->filter_contenttype ? "AND node.contenttypeid = " . intval($this->filter_contenttype) . " " : '') .
				($this->filter_contentid ? "AND node.contentid = " . intval($this->contentid) . " ": '') .
				($this->filter_node ? "AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft " : '') .
				($this->filter_nosections ? "AND node.issection != '1' " : '') .
				($this->filter_onlysections ? "AND node.issection = '1' " : '') .
				($this->filter_userid ? "AND node.userid = " . intval($this->filter_userid) . " " : '') .
				($this->visible_only ? "AND node.hidden = 0 " : '') .
				(intval($this->filter_node_exact) ? "AND (node.parentnode = " .
					$this->filter_node_exact . " OR sectionorder.displayorder > 0 )": '').
				$publishfilter . 
				($this->filter_unpublished ? "AND node.setpublish = '0' OR node.publishdate > " . intval(TIMENOW) . " " : '') . "
				$content_query_where
				$hook_query_where " .
				($this->requireLoad(vBCms_Item_Content::INFO_DEPTH) ?
					" GROUP BY node.nodeid " : '') .
				(isset($this->orderby) ? " ORDER BY " . $this->orderby :
					($this->requireLoad(vBCms_Item_Content::INFO_NODE) ? " ORDER BY CASE WHEN sectionorder.displayorder > 0 THEN sectionorder.displayorder ELSE 9999999 END ASC,
					 node.publishdate DESC" : 'ORDER BY node.setpublish DESC, node.publishdate DESC' ))

			 .
				($this->paginate ?
					" LIMIT " . intval($this->start) . ', ' . intval($this->quantity) : '');

			return $sql;


		}
		else if (self::QUERY_PARENTS == $required_query)
		{
			return
				"SELECT node.nodeid AS itemid, parent.nodeid, parent.url, parent.styleid, parent.layoutid, parent.publishdate,
						parent.setpublish, parent.hidden, info.title, info.description
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_node AS node
				INNER JOIN " . TABLE_PREFIX . "cms_node AS parent ON (node.nodeleft >= parent.nodeleft AND node.nodeleft <= parent.noderight)
				INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = parent.nodeid
				LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS ord ON ord.nodeid = node.nodeid AND ord.sectionid = node.parentnode " .
					$hook_query_joins . "
				WHERE node.nodeid IN (" . implode(',', $this->itemid) . ")
				AND parent.nodeid != node.nodeid
				$hook_query_where
				ORDER BY parent.nodeleft, ord.displayorder"
			;
		}
		else if (self::QUERY_CONFIG == $required_query)
		{
			return
				"SELECT nodeid AS itemid, name, value, serialized
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_nodeconfig
				$hook_query_joins
				WHERE nodeid IN (" . implode(',', $this->itemdid) . ")
				$hook_query_where
			";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars_uni($required_query) . '\' specified for collection'));
	}


	/**
	 * Fetches additional fields for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryFields()
	{
		return '';
	}

	/*** Sets additional where clause for a content query
	*
	* @param string
	*****/
	public function setContentQueryWhere($where)
	{
		$this->content_query_where = $where;
	}

	/*** Sets additional join text for a content query
	 *
	 * @param string
	 *****/
	public function setContentQueryJoins($joins)
	{
		$this->content_query_joins = $joins;
	}

	/**
	 * Fetches additional join for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryJoins()
	{
		return $this->content_query_joins;
	}


	/**
	 * Fetches additional conditions for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryWhere()
	{
		return $this->content_query_where;
	}



	/*** Gets additional Fileter text for a content query
	 *
	 * @return string
	 *****/
	protected function getFilterNotContentTypeSql()
	{
		if (empty($this->filter_notcontenttype))
		{
			return '';
		}

		return 'AND node.contenttypeid NOT IN(' . implode(',', $this->filter_notcontenttype) . ')';
	}


	/**
	 * Creates a content item to add to the collection.
	 *
	 * @param array mixed $iteminfo				- The known properties of the new item
	 * @return vB_Item							- The created item
	 */
	protected function createItem($iteminfo, $load_flags = false)
	{
		$class = vBCms_Types::instance()->getContentTypeClass($iteminfo['contenttypeid']);
		$package = vBCms_Types::instance()->getContentTypePackage($iteminfo['contenttypeid']);

		$item = vB_Item_Content::create($package, $class, $iteminfo[$this->primary_key]);
		$item->setInfo($iteminfo, $load_flags);

		return $item;
	}


	/**
	 * Checks if an item of a valid type to be in the collection.
	 *
	 * @param $item
	 * @return bool
	 */
	protected function validCollectionItem($item)
	{
		if (!($item instanceof vBCms_Item_Content))
		{
			return false;
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28696 $
|| ####################################################################
\*======================================================================*/
