<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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
 * @version $Revision: 30602 $
 * @since $Date: 2009-04-30 17:05:50 -0700 (Thu, 30 Apr 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Collection_Content_Section extends vBCms_Collection_Content
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
	protected $item_class = 'Section';

	protected $orderby = 3;
	protected $sortby = false;
	protected $visible_only = true;
	protected $contentfrom = false;
	protected $count_records = true;

	protected $filter_node_exact = false;

	protected $query_hook = 'vbcms_collection_section_querydata';

	/**
	 * Include content that is hidden by permissions but has the preview flag checked
	 *
	 * @var int
	 */
	protected $filter_includepreview = false;

	/*Constants=====================================================================*/

	/**
	 * Map of query => info.
	 * INFO_CONTENT is queried with QUERY_BASIC.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => /* vB_Item::INFO_BASIC | vB_Item::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 19,
		self::QUERY_PARENTS => vBCms_Item_Content::INFO_PARENTS,
		self::QUERY_CONFIG => vBCms_Item_Content::INFO_CONFIG
		);


	/**** sets the order. We need this primarily for section views
	* @param string $orderby : the string, WITHOUT the phrase "ORDER BY"
	* ****/
	public function setOrderBy($orderby)
	{
		/***
		* The meaning of this flag is :
		* 1) manual_then_by_date
		* 2) newest_first
		* 3) newest_per_section- i.e. newest first but only display one per section
		* 4) list_alphabetically
		* 5) manual_only
		* ***/
		$this->orderby = $orderby;
	}

	/**** sets the sort order to a string. We need this primarily for section views
	 * @param string $orderby : the string, WITHOUT the phrase "ORDER BY"
	 * ****/
	public function setSortBy($orderby)
	{
		$this->sortby = $orderby;
	}

	/**** sets the order. We need this primarily for section views
	 * ****/
	public function getOrderBy()
	{
		return $this->orderby;
	}


	/*** This flag sets the collection to display on items in the selected section
	 * **/
	public function setFilterHidden($value = true)
	{
		$this->visible_only = $value;
	}

	/*** This flag sets the collection to display on items in the selected section
	 * **/
	public function setFilterNodeExact($value = true)
	{
		$this->filter_node_exact = $value;
	}
	/*LoadInfo======================================================================*/

	/**
	 * Fetches additional fields for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryFields()
	{
		return "";

	}


	/**
	 * Fetches additional join for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return stringnode.nodeleft
	 */
	protected function getContentQueryJoins()
	{
		return "";
	}

	/**
	 * sets or unsets the "include preview" flag
	 *
	 * @param 	int	$value
	 */
	public function setIncludepreview($value = true)
	{
		$this->filter_includepreview = $value;
	}

	/**
	 * sets or unsets the "count records" flag
	 *
	 * @param 	int	$value
	 */
	public function setCount($value = true)
	{
		$this->count_records = $value;
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
			if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
			{
				vBCMS_Permissions::getUserPerms();
			}

			$extrasql =	'' ;

			$permissionstring = "( (" . vBCMS_Permissions::getPermissionString() .
				($this->filter_includepreview ? ") OR (node.setpublish AND node.publishdate <" . TIMENOW .
				" AND node.publicpreview > 0" : '' ) . "))" ;

			if (!$this->sortby)
			{
				if ($this->orderby == 3)
				{
					$extrasql =	" INNER JOIN (SELECT parentnode, MAX(lastupdated) AS lastupdated
					FROM " . TABLE_PREFIX . "cms_node  AS node WHERE contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
					" AND	" .  vBCMS_Permissions::getPermissionString() .
					" GROUP BY parentnode ) AS ordering ON ordering.parentnode = node.parentnode
					AND node.lastupdated = ordering.lastupdated WHERE 1=1";

					$this->sortby = " ORDER BY node.setpublish DESC, node.publishdate DESC ";
				}
				else if ($this->orderby == 2)
				{
					$this->sortby = " ORDER BY node.publishdate DESC ";
				}
				else if ($this->orderby == 4)
				{
					$this->sortby = " ORDER BY info.title ASC ";
				}
				else if ($this->orderby == 5)
				{
					$this->sortby = " ORDER BY sectionorder.displayorder ASC ";
				}
				else
				{
					$this->sortby =	" ORDER BY CASE WHEN sectionorder.displayorder > 0 THEN sectionorder.displayorder ELSE 9999999 END ASC,
					 node.publishdate DESC";
				}
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

			$sql = "SELECT " . ($this->count_records ? 'SQL_CALC_FOUND_ROWS': '') .
					" node.nodeid AS itemid,
					(node.nodeleft = 1) AS isroot, node.nodeid, node.contenttypeid, node.contentid, node.url, node.parentnode, node.styleid, node.userid,
					node.layoutid, node.publishdate, node.setpublish, node.issection, parent.permissionsfrom as parentpermissions,
					node.permissionsfrom, node.publicpreview, node.showtitle, node.showuser, node.showpreviewonly, node.showall,
					node.showupdated, node.showviewcount, node.showpublishdate, node.settingsforboth, node.includechildren, node.editshowchildren,
					node.shownav, node.hidden, node.nosearch, node.nodeleft,
					info.description, info.title, info.html_title, info.viewcount, info.creationdate, info.workflowdate,
					info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid,
					user.username, sectionorder.displayorder, thread.replycount, parentinfo.title AS parenttitle
					$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_node AS node
				INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
				$hook_query_joins
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
				LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS sectionorder ON sectionorder.sectionid = " . intval($this->filter_node) ."
					AND sectionorder.nodeid = node.nodeid
				LEFT JOIN " . TABLE_PREFIX . "cms_node AS parent ON parent.nodeid = node.parentnode
				LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
				"
				. (intval($this->filter_node) ? " INNER JOIN " . TABLE_PREFIX . "cms_node AS rootnode
					ON rootnode.nodeid = " . intval($this->filter_node) .
					" AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft " : '')
				  . $extrasql .
				" AND node.contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
				" AND node.new != 1 " .
				($this->itemid ? "AND node.nodeid IN (" . implode(',', $this->itemid) . ") " : '') .
				($this->filter_contenttype ? "AND node.contenttypeid = " . intval($this->filter_contenttype) . " " : '') .
				($this->filter_contentid ? "AND node.contentid = " . intval($this->contentid) . " ": '') .
				($this->filter_nosections ? "AND node.issection != '1' " : '') .
				($this->filter_onlysections ? "AND node.issection = '1' " : '') .
				($this->filter_ignorepermissions ? '' : " AND " . $permissionstring) .
				($this->filter_userid ? "AND node.userid = " . intval($this->filter_userid) . " " : '') .
				($this->visible_only ? "AND node.hidden = 0 " : '') .
				$publishfilter .
				($this->filter_unpublished ? "AND node.setpublish = '0' OR node.publishdate > " . intval(TIMENOW) . " " : '') . " " .
				$this->getFilterNotContentTypeSql() .
				 (intval($this->filter_node_exact) ? "AND (node.parentnode = " . $this->filter_node_exact . " OR sectionorder.displayorder > 0 )": '')
				. (($this->orderby == 5) ? " AND sectionorder.displayorder > 0 " : '')
				. "
				$content_query_where
				$hook_query_where " . $this->sortby
			 .
				( ($this->paginate AND intval($this->quantity)) ?
					(" LIMIT " . intval($this->start) . ', ' . intval($this->quantity))  : '')	;

			return $sql;

		}
		else
		{
			return parent::getLoadQuery();
		}

	}

	//for paging, we need to get the count of items. We have a slight addition
	// from the parent, because if the
	public function getCount()
	{
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		// Internal hooks for loading content with QUERY_BASIC


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
		$sql = "SELECT count(node.nodeid) AS qty
		FROM " . TABLE_PREFIX . "cms_node AS node"
		.	($this->filter_node ?
		" INNER JOIN " . TABLE_PREFIX . "cms_node AS rootnode
			ON rootnode.nodeid = " . intval($this->filter_node) : '') .
		"	$content_query_joins
		$hook_query_joins
		LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS sectionorder ON sectionorder.sectionid = " . intval($this->filter_node) ."
			AND sectionorder.nodeid = node.nodeid
		WHERE (1=1) ".
		($this->filter_contenttype ? "AND node.contenttypeid = " . intval($this->filter_contenttype) . " " : '') .
		($this->filter_contentid ? "AND node.contentid = " . intval($this->contentid) . " ": '') .
		($this->filter_node ? "AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft " : '') .
		($this->filter_nosections ? "AND node.issection != '1' " : '') .
		($this->filter_onlysections ? "AND node.issection = '1' " : '') .
		($this->filter_userid ? "AND node.userid = " . intval($this->filter_userid) . " " : '') .
		($this->filter_published ? "AND node.setpublish = '1' AND node.publishdate <= " . intval(TIMENOW) . " " : '') .
		($this->filter_unpublished ? "AND node.setpublish = '0' OR node.publishdate > " . intval(TIMENOW) . " " : '') . "
		" . ((($this->filter_contenttype AND ($this->filter_contenttype == vB_Types::instance()->getContentTypeID("vBCms_Section"))) OR $this->filter_onlysections)
		? '' : "AND node.new != 1 ")
		. 				 (intval($this->filter_node_exact) ? "AND (node.parentnode = " . $this->filter_node_exact . " OR sectionorder.displayorder > 0 )": '')
		. (($this->orderby == 5) ? " AND sectionorder.displayorder > 0 " : '') .

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

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 30602 $
|| ####################################################################
\*======================================================================*/