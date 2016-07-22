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
 * CMS content class.
 * A node is a data instance of configured cms content.  Each node has a content
 * type and contentid, a layout, style and widgets, as well as permissions and any
 * content agnostic meta types that may be attached to the content, such as tags.
 *
 * @todo Get inherited permissions.
 *
 * @author vBulletin Development Team
 * @version 4.2.2
 * @since 26th Nov, 2008
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Content extends vB_Item_Content
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'Content';

	/**
	 * The class name of the most appropriate DM for managing the item's data.
	 *
	 * @var string
	 */
	protected $dm_class = 'vBCms_DM_Node';

	/**
	 * Whether the model info can be cached.
	 *
	 * @var bool
	 */
	protected $cachable = true;

	/**
	 * Whether the author has HTML rights. Note that
	 * we have to use something other than 0,1 so we know
	 * whether the value has been loaded.
	 * @var integer
	 * */
	protected $canusehtml = -1;

	/** The cache life ***/
	protected $cache_ttl = 1440;

	/** whether the data for this record has been cached ***/
	protected $cached_data = false;

	protected $query_hook = 'vbcms_content_querydata';

	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 *
	 * Note: INFO_CONTENT is a placeholder for child implementations.
	 */
	const INFO_NODE = 2;
	const INFO_DEPTH = 4;
	const INFO_PARENTS = 8;
	const INFO_CONFIG = 16;
	const INFO_CONTENT = 32;
	const INFO_NAVIGATION = 64;

	/**
	 * The total flags for all info.
	 * This would be a constant if we had late static binding.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 127;

	/**
	 * List of dependencies.
	 * If a particular info requires another info to be loaded then you can map them
	 * here.  The array should be in the form array(dependent => dependent on)
	 *
	 * @var array int
	 */
	protected $INFO_DEPENDENCIES = array(
		self::INFO_NAVIGATION => self::INFO_PARENTS
	);

	/**
	 * Query types.
	 */
	const QUERY_PARENTS = 2;
	const QUERY_CONFIG = 3;
	const QUERY_CONTENT = 4;
	const QUERY_NAVIGATION = 5;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => 7 /* self::INFO_BASIC | self::INFO_NODE | self::INFO_DEPTH */ ,
		self::QUERY_PARENTS => self::INFO_PARENTS,
		self::QUERY_CONFIG => self::INFO_CONFIG,
		self::QUERY_CONTENT => self::INFO_CONTENT,
		self::QUERY_NAVIGATION => self::INFO_NAVIGATION
	);



	/*ModelProperties===============================================================*/

	/**
	 * Extra item properties.
	 * These are merged with $item_properties on construction, providing a simple
	 * way for children to extend the model properties without duplicating or
	 * destroying the common properties.
	 *
	 * @var array
	 */
	protected $content_properties = array();

	/**
	 * Node model properties.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_BASIC==================*/
		'nodeid',			'isroot',			'contentid',
		'contenttypeid',	'url',				'userid',
		'parentnode',		'layoutid', 		'styleid',
		'publishdate',		'setpublish',		'issection',
		'permissionsfrom', 'parentpermissions', 'lastupdated',
		'publicpreview', 'comments_enabled',	'new',
		/*INFO_DEPTH==================*/
		'depth',
		/*INFO_NODE===================*/
		'username',	'title', 'html_title', 'description', 'nodeleft', 'noderight', 'showrating',
		'showtitle', 'showuser', 'showpreviewonly', 'showupdated', 'showviewcount',
		'showpublishdate', 'settingsforboth', 'includechildren', 'showall', 'editshowchildren',
		'ratingnum', 'ratingtotal', 'rating', 'hidden', 'shownav', 'nosearch',
		'creationdate', 'viewcount', 'workflowlevelid',
		'workflowstatus',	'workflowdate', 'workflowcheckedout', 'associatedthreadid',
		'replycount', 'keywords', 'parenttitle',
		/**  Comes from cms_sectionorder**/
		'displayorder',

		/*INFO_NAVIGATION=============*/
		'navigation_nodes',
		/*INFO_PARENTS=============*/
		'parents'

	);

	/*INFO_BASIC==================*/

	/**
	 * The id of the node.
	 *
	 * @var int
	 */
	protected $nodeid;

	/**
	 * Whether the node is the root node.
	 *
	 * @var bool
	 */
	protected $isroot;

	/**
	 * The id of the resolved content.
	 * This is interpreted by the content type handler and may even be null.
	 *
	 * @var int
	 */
	protected $contentid;

	/**
	 * The id of the resolved contenttype.
	 *
	 * @var int
	 */
	protected $contenttypeid;

	/**
	 * The url segment name for the node.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The id of the parent node
	 *
	 * @var int
	 */
	protected $parentnode;

	/**
	 * The layout id for the node.
	 * This may be resolved from a parent node.
	 *
	 * @var int
	 */
	protected $layoutid;

	/**
	 * The style id for the node.
	 * This may be resolved from a parent node.
	 *
	 * @var int
	 */
	protected $styleid;

	/**
	 * Whether the node is an aggregator.
	 *
	 * @var bool
	 */
	protected $issection;

	/**
	 * Username of the user who created the content.
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * The public publish date of the content.
	 * This may not necessarily reflect the actual date that the content was first
	 * created; depending on how the content is handled.
	 *
	 * @var int
	 */
	protected $publishdate;

	/**
	 * A newly created node which has not been edited has field 'new' = 1
	 *
	 * @var int
	 */
	protected $new;

	/**
	 * Whether a publish date has been set.
	 * If this is false the the publishdate only represents the creation date of
	 * the content.
	 *
	 * @var bool
	 */
	protected $setpublish;
	/*INFO_DEPTH====================*/

	/**
	 * The depth of the content in the node tree.
	 *
	 * @var int
	 */
	protected $depth;

	/*INFO_NODE=====================*/

	/**
	 * The date the node was created.
	 *
	 * @var int
	 */
	protected $creationdate;

	/**
	 * The number of times the node has been viewed post publish.
	 *
	 * @var int
	 */
	protected $viewcount;

	/**
	 * The current workflow level.
	 * Only users with permission to this level for this node may modify the node.
	 *
	 * @var int
	 */
	protected $workflowlevelid;

	/**
	 * The current workflow status.
	 *
	 * @var int
	 */
	protected $workflowstatus;

	/**
	 * The date of the last workflow status change.
	 *
	 * @var int
	 */
	protected $workflowdate;

	/**
	 * Whether the node is checked out for editing.
	 *
	 * @var int
	 */
	protected $workflowcheckedout;

	/**
	 * Whether the threadid for associated discussion.
	 *
	 * @var int
	 */
	protected $associatedthreadid;

	/* Is this publicly viewable */
	protected $publicCanView;

	/**
	 * Array of nodeids that make up the navigation menu.
	 *
	 * @var array int
	 */
	protected $navigation_nodes;



	/*ClassProperties===============================================================*/

	/**
	 * Unpublished parent.
	 * If any of the node's parent's are not published, this is the nearest
	 * unpublished parent.
	 */
	protected $pending_parent;

	/**
	 * The node's layout.
	 *
	 * @var vBCms_Item_Layout
	 */
	protected $layout;

	/**
	 * The layout setting for this specific node.
	 * This is the chosen layout before inheritance.  This is useful when
	 * configuring the node to get whether the layout is currently inherited or not.
	 *
	 * @var int
	 */
	protected $node_layout;

	/**
	 * The style setting for this specific node.
	 * This is the chosen style before inheritance.  This is useful when
	 * configuring the node to get whether the style is currently inherited or not.
	 *
	 * @var int
	 */
	protected $node_styleid;

	/**
	 * Info about the node's parent nodes.
	 *
	 * @var array mixed
	 */
	protected $parents;

	/**
	 * Config for the node.
	 *
	 * @var array cvar => value
	 */
	protected $config;

	protected $userid;

	protected $permissionsfrom;

	protected $parentpermissions;

	public $publicpreview;

	protected $replycount;

	protected $displayorder;

	protected $comments_enabled;

	protected $keywords;

	protected $pagetitle;

	protected $title;

	protected $html_title;

	protected $parenttitle;

	protected $nodeleft;

	protected $noderight;

	protected $lastupdated;

	protected $ratingnum;

	protected $ratingtotal;

	protected $rating;


	/**
	 * Whether this node has it's own navigation menu.
	 *
	 * @var bool
	 */
	protected $navigation_ownmenu;

	/**
	 * The nearest parent with a navigation menu.
	 *
	 * @var int
	 */
	protected $navigation_node;

	/**
	 * The title of the nearest parent with a navigation menu.
	 *
	 * @var string
	 */
	protected $navigation_parenttitle;

	/** flags for the display ***/
	protected $showtitle;

	/** whether to show the user ***/
	protected $showuser;

	/** show full article text or only preview ***/
	protected $showpreviewonly;

	/** whether to show the last updated date ***/
	protected $showupdated;

	/** whether to show the view count ***/
	protected $showviewcount;

	/** whether to show the ppublished date ***/
	protected $showpublishdate;

	/** whether to show the settings for view, preview, or both ***/
	protected $settingsforboth;

	/** whether to include subsection records ***/
	protected $includechildren;

	/** whether to show the "show all" link, now not used ***/
	protected $showall;

	/** whether to show the rating ***/
	protected $showrating;

	/** whether to show the subsection content in the edit screen ***/
	protected $editshowchildren;

	/** whether to hide the section***/
	protected $hidden;

	/** whether to make this non-section node available for subnav ***/
	protected $shownav;

	/** whether to hide from the search inde ***/
	protected $nosearch;

	/**
	 * The resolved styleid.
	 * @see getStyleId()
	 *
	 * @var int
	 */
	protected $resolved_styleid;


	/*Initialisation================================================================*/


	/**
	 * Sets the itemid of the item to be loaded.
	 * The node id can be the integer id, the x_url path segment or an
	 * array('contenttypeid' => int, 'contentid' => int).
	 *
	 * @param mixed $itemid							- The id of the node or content
	 */
	public function setItemId($itemid = false)
	{
		// Allow the item id to be set as contenttypeid, contentid info
		if (is_array($itemid))
		{
			if (!isset($itemid['contenttypeid']) OR !isset($itemid['contentid']))
			{
				$this->is_valid = false;
				return;
			}

			$this->contenttypeid = $itemid['contenttypeid'];
			$this->contentid = $itemid['contentid'];
			$this->itemid = false;
		}
		else if (!intval($itemid))
		{
			$this->url = $itemid;
			$this->itemid = false;
		}
		else
		{
			$this->itemid = $this->nodeid = intval($itemid);
			$this->loadCache($itemid);
		}
	}

	/** get the subsection Hash
	* @return string
	****/
	public function getContentCacheHash()
	{
		return isset($this->nodeid) ?
			'vbcms_item_' . $this->nodeid : false;
	}

	/** get the cache invalidation event
	*
	 * @return string
	 ****/
	public function getContentCacheEvent()
	{
		return isset($this->nodeid) ?
			'vbcms_item_' . $this->nodeid . '_updated' : false;
	}


	/*Initialisation================================================================*/

	/**
	 * Constructs the content item.
	 * The id passed will usually be the primary key of the model data in the
	 * database but as this is model specific it can be interpreted in other ways.
	 *
	 * @param mixed $itemid					- The id of the item
	 * @param int $load_flags				- Any required info prenotification
	 */
	public function __construct($itemid = false, $load_flags = false)
	{
		$this->item_properties = array_merge($this->item_properties, $this->content_properties);

		parent::__construct($itemid, $load_flags);

		if (intval($itemid))
		{
			$this->loadCache($itemid);
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
		if (self::QUERY_PARENTS == $load_query)
		{
			$parents = array();
			if (vB::$db->num_rows($result))
			{
				while ($parent = vB::$db->fetch_array($result))
				{
					$parents[$parent['nodeid']] = $parent;
				}
			}

			$this->setParentsArray($parents);

			return true;
		}
		else if (self::QUERY_CONFIG == $load_query)
		{
			$config = array();

			while ($cvar = vB::$db->fetch_array($result))
			{
				$config[$cvar['name']] = $cvar['value'];
			}

			$this->config = $config;

			$this->loaded_info = ($this->loaded_info | self::INFO_CONFIG);

			return true;
		}
		else if (self::QUERY_NAVIGATION == $load_query)
		{


			if ($nav = vB::$db->fetch_array($result))
			{
				if ($nav['nodeid'] != $this->nodeid)
				{
					$this->navigation_parenttitle = $nav['title'];
				}
				else
				{
					$this->navigation_ownmenu = true;
				}

				$this->navigation_node = $nav['nodeid'];

				$this->navigation_nodes = explode(',', $nav['nodelist']);

				foreach ($this->navigation_nodes AS $key => $nodeid)
				{
					if (!is_numeric($nodeid))
					{
						unset($this->navigation_nodes[$key]);
					}
				}
			}

			$this->loaded_info = ($this->loaded_info | self::INFO_NAVIGATION);

			return true;
		}

		$result = parent::applyLoad($result, $load_query);

		// Keep a copy of the actual node layout and style before inheritance
		if (self::QUERY_BASIC == $load_query)
		{
			$this->node_layout = $this->layoutid;
			$this->node_style = $this->styleid;
		}

		if (isset($this->permissionsfrom) AND isset($this->nodeid)
			AND isset($this->setpublish) AND isset($this->publishdate) AND isset($this->userid))
		{
			vBCMS_Permissions::setPermissionsfrom($this->nodeid, $this->permissionsfrom,
				$this->hidden, $this->setpublish, $this->publishdate, $this->userid);
		}

		return $result;
	}


	/**
	 * Copies info from this object to another of the same type.
	 * This is usefull when using a generic collection class that used a parent type
	 * to fetch the items.
	 *
	 * @param vBCms_Item_Content $target
	 */

	public function castInfo($target)
	{
		if (!($target instanceof $this))
		{
			throw (new vB_Exception_Model('Can not castInfo with mismatching types'));
		}
		
		parent::castInfo($target);

		$target->setConfig($this->config);
		$target->setParents($this->parents);
		//There isn't a good way to pass the loaded, so I have to do this if we loaded
		//from cache. There should be a better way;
		if ($this->cached_data AND $this->nodeid)
		{
			$target->setInfo(array('nodeid' => $this->nodeid), $this->cached_data);
		}
	}


	/**
	 * Fetches the SQL for loading.
	 * $required_query is used to identify which query to build for classes that
	 * have multiple queries for fetching info.
	 *
	 * This can safely be based on $this->required_info as long as a consistant
	 * flag is used for identifying the query.
	 *
	 * @param int $required_query				- The required query
	 * @param boolean $force_rebuild			- Added for PHP 5.4 strict standards compliance
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query = '', $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		// Internal hooks for loading content with QUERY_BASIC
		$content_query_fields = $content_query_joins = $content_query_where = '';
		if ($this->requireLoad(vBCms_Item_Content::INFO_CONTENT))
		{
			$content_query_fields = $this->getContentQueryFields();
			$content_query_joins = $this->getContentQueryJoins();
			$content_query_where = $this->getContentQueryWhere();
		}
		if (self::QUERY_BASIC == intval($required_query))
		{
			$sql =
				"SELECT node.nodeid " .
				($this->requireLoad(self::INFO_BASIC) ?
				", (node.nodeleft = 1) AS isroot, node.contenttypeid, node.contentid, node.url, node.parentnode,
				(CASE WHEN node.contenttypeid = " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
				" THEN node.styleid ELSE parent.styleid end ) AS styleid, node.userid,
					(CASE WHEN node.contenttypeid = " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
					" THEN node.layoutid ELSE parent.layoutid end ) AS layoutid,
					node.publishdate, node.setpublish, node.issection, node.permissionsfrom, node.nodeleft, node.noderight, node.new,
					node.userid, node.showtitle, node.showuser, node.showpreviewonly, node.lastupdated, node.showall, node.showrating,
					node.showupdated, node.showviewcount, node.showpublishdate, node.settingsforboth, node.includechildren, node.editshowchildren,
					parent.permissionsfrom as parentpermissions, node.publicpreview, node.comments_enabled, node.shownav,
					node.hidden, node.nosearch, node.new " : '') .
				($this->requireLoad(self::INFO_NODE) ?
					", info.description, info.title, info.html_title, info.viewcount, info.creationdate, info.workflowdate, info.keywords,
					info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid, info.creationdate, node.showrating,
					info.ratingnum, info.ratingtotal, info.rating,
					user.username, thread.replycount, sectionorder.displayorder " : '') .
				($this->requireLoad(self::INFO_DEPTH) ?
					", (COUNT(pdepth.nodeid) - 1) AS depth" : '') . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_node AS node" .
				($this->requireLoad(self::INFO_NODE) ? "
				INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
				LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS sectionorder ON sectionorder.sectionid = node.parentnode
					AND sectionorder.nodeid = node.nodeid"
				: '')
				. ($this->requireLoad(self::INFO_BASIC) ? "
				LEFT JOIN " . TABLE_PREFIX . "cms_node AS parent ON parent.nodeid = node.parentnode " : '') .
				($this->requireLoad(self::INFO_DEPTH) ?
				" LEFT JOIN " . TABLE_PREFIX . "cms_node AS pdepth ON (node.nodeleft >= pdepth.nodeleft AND node.nodeleft <=pdepth.noderight) " : '') . "
				$hook_query_joins
				WHERE ";

				if (is_numeric($this->itemid))
				{
					$sql .= 'node.nodeid = ' . intval($this->itemid);
				}
				else if (is_numeric($this->nodeid))
				{
					$sql .= 'node.nodeid = ' . intval($this->nodeid);
				}
				else if ($this->contenttypeid AND $this->contentid)
				{
					$sql .= 'node.contenttypeid = ' . intval($this->contenttypeid) . ' AND node.contentid = ' . intval($this->contentid);
				}

				$sql .=
					' ' . $hook_query_where .
					($this->requireLoad(self::INFO_DEPTH) ?
						" GROUP BY node.nodeid" : '');

				//If we don't have some actual content, return an empty string;
				if (strlen($sql) < 100)
				{
					return false;
				}


				return $sql;
		}
		else if (self::QUERY_PARENTS == $required_query)
		{
			return
				"SELECT parent.nodeid, parent.url, parent.publishdate, parent.setpublish, parent.issection, parent.hidden,
						info.title, info.html_title, info.description, node.nodeleft, parent.styleid
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_node AS node
				INNER JOIN " . TABLE_PREFIX . "cms_node AS parent ON (node.nodeleft >= parent.nodeleft AND node.nodeleft <= parent.noderight)
				INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = parent.nodeid" .
				$hook_query_joins . "
				WHERE node.nodeid = " . intval($this->itemid) . "
				AND parent.nodeid != node.nodeid
				$hook_query_where
				ORDER BY parent.nodeleft"
			;
		}
		else if (self::QUERY_CONFIG == $required_query)
		{
			return
				"SELECT nodeconfig.name, nodeconfig.value, nodeconfig.serialized
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_nodeconfig AS nodeconfig
				$hook_query_joins
				WHERE nodeid = " . intval($this->nodeid) . "
				$hook_query_where
			";
		}
		else if (self::QUERY_NAVIGATION == $required_query)
		{
			$source_nodes = intval($this->itemid) . (!empty($this->parents) ? ',' . implode(',', array_keys($this->parents)) : '');

			$sql =
				"SELECT navigation.nodeid, navigation.nodelist, nodeinfo.title, node.permissionsfrom,
				node.setpublish, node.publishdate
				$hook_query_fields
				FROM " . TABLE_PREFIX . "cms_navigation AS navigation
				INNER JOIN " . TABLE_PREFIX . "cms_node AS node ON node.nodeid = navigation.nodeid
				LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS nodeinfo ON nodeinfo.nodeid = navigation.nodeid
				$hook_query_joins
				WHERE navigation.nodeid IN ($source_nodes) AND navigation.nodelist IS NOT NULL AND (navigation.nodelist <> '')
				$hook_query_where
				ORDER BY node.nodeleft DESC LIMIT 0,1";
				return $sql;
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars_uni($required_query) . '\' specified for node item: ' . htmlspecialchars_uni($query)));
	}


	/**
	 * Sets parent info from an array of assoc arrays.
	 * The assoc arrays should have the following keys:
	 * 	nodeid, url, styleid, layoutid, publishdate, setpublish, title, html_title description
	 * Note: Parents are already ordered
	 *
	 * @param array mixed $parents
	 */
	protected function setParentsArray($parents)
	{
		if (sizeof($parents))
		{
			foreach ($parents AS $parent)
			{
				// get nearest parent
				$this->parent = $parent['nodeid'];

				// get nearest unpublished parent
				if (!$parent['publishdate'] OR !$parent['setpublish'] OR ($parent['publishdate'] > TIMENOW))
				{
					$this->pending_parent = $parent['nodeid'];
				}
			}
		}

		// TODO: use a collection?
		$this->parents = $parents;

		$this->loaded_info = ($this->loaded_info | self::INFO_PARENTS);
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


	/**
	 * Fetches additional join for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryJoins()
	{
		return '';
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
		return '';
	}


	/**
	 * Validates criteria.
	 * Child implementations should override this to validate criteria that affects
	 * queries, such as the specified itemid.
	 *
	 * @return bool
	 */
	public function validateCriteria()
	{
		return (is_numeric($this->itemid) OR is_numeric($this->nodeid) OR ($this->contenttypeid AND $this->contentid));
	}



	/*Cache=========================================================================*/
	/** Gives us a key we can use to store the item information
	 ****/

	protected function getCacheKey($nodeid = false)
	{
		if (intval($nodeid))
		{
			return 'vbcms_item_' . intval($nodeid) . '_data' ;
		}

		if (intval($this->nodeid))
		{
			return 'vbcms_item_' . intval($this->nodeid) . '_data' ;
		}
		return false ;
	}

	/**
	 * Writes the item info to the cache.
	 *
	 * @return int
	 */
	protected function writeCache()
	{

		// Check if we're cachable
		if (!$this->cachable OR !$this->is_valid)
		{
			//Don't cache invalid data
			return true;
		}

		if (!$this->loaded_info)
		{
			return true;
		}

		//Only cache if we have new data that isn't already in the cache
		if ($this->cached_data AND $this->loaded_info AND (($this->cached_data | $this->loaded_info) == $this->cached_data))
		{
			return true;
		}


		// Create a context to identify the cache entry
		if (!$key = $this->getCacheKey($this->nodeid))
		{
			return false;
		}

		// Add extra info that is not in item_properties
		$info = $this->saveCacheInfo();

		// Write the cache
		return vB_Cache::instance()->write($key, $this, $this->cache_ttl, $this->getCacheEvents());
	}

	/** This function checks to see if we have data to cache, and if so writes the
	 * cache record. It's just a wrapper for writeCache()
	 *
	 * @return
	 */
	public function cacheNow()
	{
		//See if there is data that's not already cached.
		if ($this->cachable AND (($this->cached_data | $this->loaded_info) != $this->cached_data))
		{
			$this->writeCache();
		}
	}


	/**
	 * Loads the model info from the cache.
	 * Note: The cache is written after setInfo() so direct assignment of the
	 * properties is needed.
	 *
	 * @return bool								- Success
	 */
	protected function loadCache($nodeid = false)
	{
		// Check if we're cachable
		if (!$this->cachable)
		{
			return false;
		}
		// Create a context to identify the cache entry
		if (!$key = $this->getCacheKey($nodeid))
		{
			return false;
		}

		//Check to see if we've already loaded everything we need
		if ($this->loaded_info AND (($this->loaded_info & $this->required_info) == $this->required_info))
		{
			return true;
		}

		//Check to see if we've already read from cache
		if ($this->cached_data)
		{
			return false;
		}

		// Fetch the cache info
		if ($info = vB_Cache::instance()->read($key, true, true))
		{
			//Now see if we have the right content type
			if (($this->class != 'Content') AND ($info->class == 'Content'))
			{
				//invalidate the cache. That means we'll update with the correct
				//values later
				$this->loaded_data = false;
				$this->cached_data = false;
				return false;
			}

			//Now see if the cache has data we don't already have.
			if ($this->loaded_info AND (($info->loaded_info | $this->loaded_info) == $this->loaded_info))
			{
				$this->cached_data |= $info->loaded_info;
				return false;
			}
			// load the info retrieved from the cache

			$this->is_valid = $info->is_valid;

			if ($this->nodeid AND $info->permissionsfrom AND $info->userid)
			{
				vBCMS_Permissions::setPermissionsfrom($this->nodeid, $info->permissionsfrom, $info->hidden,
					$info->setpublish, $info->publishdate, $info->userid);
			}

			if (is_array($info->item_properties) AND is_array($this->item_properties))
			{
				foreach(array_merge($this->item_properties, $info->item_properties) as $field)
				{

					if (isset($info->$field))
					{
						$this->$field = $info->$field;
					}
				}
				$this->cached_data |= $info->loaded_info;
				$this->loaded_info |= $info->loaded_info;
			}


			if (isset($info->config))
			{
				$this->config = $info->config;

			}

			return (($this->loaded_info & $this->required_info) == $this->required_info);

		}

		$this->cached_data = false;
		return false;
	}


	/**
	 * Saves non item properties as cachable info.
	 *
	 * @return array mixed $info				- The modified info array to cache
	 */
	protected function saveCacheInfo()
	{
		$info = parent::saveCacheInfo();

		if ($this->pending_parent)
		{
			$info['pending_parent'] = $this->pending_parent;
		}

		if ($this->node_layout)
		{
			$info['node_layout'] = $this->node_layout;
		}

		if ($this->node_styleid)
		{
			$info['node_styleid'] = $this->node_styleid;
		}

		if ($this->parents)
		{
			$info['parents'] = serialize($this->parents);
		}

		if ($this->config)
		{
			$info['config'] = serialize($this->config);
		}

		return $info;
	}

	/*** We call this from the controller when we instantiate the content node, because
	* if this is the first time we have some invalid data
	* ***/
	public function invalidateCached()
	{
		$this->cached_data = false;
	}

	/**
	 * Loads non item properties from a cache hit.
	 *
	 * @param mixed $info						- The info loaded from the cache
	 */
	protected function loadCacheInfo($info)
	{
		parent::loadCacheInfo($info);

		if (isset($info['pending_parent']))
		{
			$this->pending_parent = $info['pending_parent'];
		}

		if (isset($info['node_layout']))
		{
			$this->node_layout = $info['node_layout'];
		}

		if (isset($info['node_styleid']))
		{
			$this->node_styleid = $info['node_styleid'];
		}

		if (isset($info['parents']))
		{
			$this->parents = unserialize($info['parents']);
		}

		if (isset($info['config']))
		{
			$this->config = unserialize($info['config']);
		}

		return true;
	}


	/**
	 * Gets a consistent key for cache events.
	 *
	 * @return array string
	 */
	public function getCacheEvents()
	{
		$events = array('content_' . $this->contenttypeid . '_' . $this->nodeid);

		if ($thread = $this->getAssociatedThreadId())
		{
			$events[] = "cms_comments_change_$thread";
		}

		return $events;
	}


	/*Accessors=====================================================================*/

	/**
	 * Returns the content id
	 *
	 * @return int
	 */
	public function getId()
	{
		$this->Load();

		return $this->contentid;
	}

	/**
	 * Returns the keywords
	 *
	 * @return int
	 */
	public function getKeywords()
	{
		$this->Load();

		return $this->keywords;
	}


	/**
	 * Returns whether this node is the root.
	 *
	 * @return bool
	 */
	public function isRoot()
	{
		$this->Load();

		return $this->isroot;
	}


	/**
	 * Returns the node id
	 *
	 * @return int
	 */
	public function getNodeId()
	{
		$this->Load();

		return $this->nodeid;
	}

	/**
	 * Returns the nodeleft value
	 *
	 * @return int
	 */
	public function getNodeLeft()
	{
		$this->Load();

		return $this->nodeleft;
	}

	/**
	 * Returns the noderight
	 *
	 * @return int
	 */
	public function getNodeRight()
	{
		$this->Load();

		return $this->noderight;
	}

	/**
	 * Returns the "nosearch" flag
	 *
	 * @return int
	 */
	public function getNoSearch()
	{
		$this->Load();

		return $this->nosearch;
	}


	/**
	 * Returns the username of the user that created the content.
	 *
	 * @return string
	 */
	public function getShowRating()
	{
		$this->Load(self::INFO_BASIC);
		return $this->showrating;
	}


	/**
	 * Returns the username of the user that created the content.
	 *
	 * @return string
	 */
	public function getUsername()
	{
		$this->Load(self::INFO_NODE);

		return $this->username;
	}

	/**
	 * Returns the id of the user that created the content.
	 *
	 * @return string
	 */
	public function getUserId()
	{
		$this->Load(self::INFO_NODE);

		return $this->userid;
	}


	/**
	 * Returns the description for the node.
	 *
	 * @return string
	 */
	public function getDescription()
	{
		$this->Load(self::INFO_NODE);

		if ($this->canusehtml == -1)
		{
			$this->canusehtml = vBCMS_Permissions::canUseHtml($this->nodeid, $this->contenttypeid, $this->userid);
		}

		if (!$this->canusehtml)
		{
			return strip_tags($this->description);
		}

		return $this->description;
	}


	/**
	 * Returns whether the node is a section or not.
	 *
	 * @return bool
	 */
	public function isSection()
	{
		return $this->issection;
	}


	/**
	 * Fetches breadcrumb info.
	 *
	 * @return array mixed
	 */
	public function getBreadcrumbInfo()
	{
		// Ensure parent info is loaded
		$this->Load(self::INFO_PARENTS);

		$breadcrumbs = array();

		if ($this->parents)
		{
			$nodes = $this->parents;

			// don't include the root node in the breadcrumb
			array_shift($nodes);

			foreach ($nodes AS $node)
			{
				if (!$node['hidden'])
				{
					// TODO: use breadcrumb item?
					$breadcrumb = array();
					$breadcrumb['title'] = $node['title'];
					$breadcrumb['description'] = $node['description'];
					$breadcrumb['nodeid'] = $node['nodeid'];
					$breadcrumb['url'] = $node['url'];
					$breadcrumb['link'] = vBCms_Route_Content::getURL(array('node' => $node['nodeid'] . '-' . $node['url']));
					$breadcrumbs[] = $breadcrumb;
				}
			}
		}
		// The last item of cms breadcrumb should be no link and has been set in view/page.php. so we don't need this
		/*
		if ($this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Section") AND !$this->isRoot())
		{
			$breadcrumb = array();
			$breadcrumb['title'] = $this->title;
			$breadcrumb['description'] = $this->description;
			$breadcrumb['nodeid'] = $this->nodeid;
			$breadcrumb['url'] = $this->url;
			$breadcrumb['link'] = vBCms_Route_Content::getURL(array('node' => $this->nodeid . '-' . $this->url));
			$breadcrumbs[] = $breadcrumb;
		}
		*/

		return $breadcrumbs;
	}


	/**
	 * Returns the nearest parent.
	 *
	 * @return array mixed
	 */
	public function getParentId()
	{
		return $this->parentnode;
	}


	/**
	 * Returns an array of all parent section id's.
	 *
	 * @param bool $include_self				- Whether to include self if it's a section
	 * @return array int
	 */
	public function getParentIds($include_self = true)
	{
		$this->Load(self::INFO_PARENTS);

		$parents = array();

		if ($include_self AND $this->isSection())
		{
			$parents[] = $this->nodeid;
		}

		if ($this->parents)
		{
			foreach ($this->parents AS $parent)
			{
				$parents[] = $parent['nodeid'];
			}
		}

		return $parents;
	}


	/**
	 * Returns the title of the parent node.
	 *
	 * @return string
	 */
	public function getParentTitle()
	{

		if ($this->canusehtml == -1)
		{
			$this->canusehtml = vBCMS_Permissions::canUseHtml($this->nodeid, $this->contenttypeid, $this->userid);
		}

		if (isset($this->parenttitle))
		{

			if (!$this->canusehtml)
			{
				return strip_tags($this->parenttitle);
			}
			return $this->parenttitle;
		}

		if ($this->parentnode)
		{
			$this->Load(self::INFO_PARENTS);


			if (!$this->canusehtml)
			{
				return strip_tags($this->parents[$this->parentnode]['title']);
			}
			return $this->parents[$this->parentnode]['title'];
		}

		return false;
	}


	/**
	 * Returns the url segment of the parent node.
	 *
	 * @return string
	 */
	public function getParentURLSegment()
	{
		if ($this->parentnode)
		{
			$this->Load(self::INFO_PARENTS);

			return $this->parents[$this->parentnode]['url'] ? ($this->parentnode . '-' . $this->parents[$this->parentnode]['url']) : '';
		}
	}

	/**
	 * Returns the id of the section node.
	 * This may be this node if it is a section, or it's parent.
	 *
	 * @return int
	 */
	public function getSectionId()
	{
		return (1 == $this->itemid OR $this->isSection()) ? $this->itemid : $this->parentnode;
	}


	/**
	 * Returns the name of the nearest section that the node is in.
	 *
	 * @return string
	 */
	public function getSectionTitle()
	{
		return new vB_Phrase('vbcms', 'creating_page_in_x', ((1 == $this->itemid OR $this->isSection()) ?
															$this->getTitle() : $this->getParentTitle()));
	}

	/**
	 * Gets the url segment of the section that the content belongs to.
	 *
	 * @return string
	 */
	public function getSectionSegment()
	{
		if ($this->parentnode)
		{
			$this->Load(self::INFO_PARENTS);

			return $this->parents[$this->parentnode]['url'];
		}

		return $this->url;
	}


	/**
	 * Fetches the layout.
	 *
	 * @return vBCms_Item_Layout
	 */
	public function getLayout()
	{
		//Layouts are assigned for sections only.
		if (!$this->layout)
		{
			// ensure parent info is loaded
			$this->Load(self::INFO_PARENTS);

			//See if we have a layoutid.

			$this->layout = new vBCms_Item_Layout($this->getLayoutId());
			$this->layout->requireInfo(vBCms_Item_Layout::INFO_CONFIG | vBCms_Item_Layout::INFO_WIDGETS);

			if (!$this->layout->isValid())
			{
				throw (new vB_Exception_Model('Layout item object not valid for node item'));
			}
		}

		return $this->layout;
	}
	/** fetches the layout id
	* @return integer
	*  **/
	public function getLayoutId()
	{
		$this->Load();
		if (!$this->layoutid)
		{
			//If our parent doesn't have the style defined, we need to go up the chain until we find one.
			if (! ($record = vB::$vbulletin->db->query_first("SELECT layoutid FROM " . TABLE_PREFIX . "cms_node AS node
					WHERE (" .
				$this->nodeleft . " BETWEEN node.nodeleft AND node.noderight) AND layoutid > 0 ORDER BY nodeleft DESC LIMIT 1" ))
				or
				(! intval($record['layoutid'])))
			{
				//There appears to be nothing defined. All we can do is pull the first record.
				$record = vB::$vbulletin->db->query_first("SELECT layoutid FROM " . TABLE_PREFIX . "cms_layout LIMIT 1" );
			}

			$this->layoutid = $record['layoutid'];
		}
		return $this->layoutid;
	}

	/** fetches the replycount
	 * @return integer
	 *  **/
	public function getReplyCount()
	{
		$this->Load();
		return $this->replycount;
	}


	/** fetches the New status. New nodes haven't been edited & saved
	*
	* @return integer
	*
	* **/
	public function getNew()
	{
		$this->Load();
		return $this->new;
	}

	/**
	 * Fetches the specific layout setting for this node.
	 * If this node is inheriting the layout then this should return false. To get
	 * the actual layout that will be used by this node, @see vBCms_Item_Content::getLayout()
	 *
	 * @return int | false
	 */
	public function getLayoutSetting()
	{
		$this->Load();

		if ($this->layoutid)
		{
			return $this->layoutid;
		}
		return $this->node_layout ? $this->node_layout : false;
	}


	/**
	 * Fetches the styleid.
	 * @TODO: Check if useroverride is allowed, if so return the user preference.
	 *
	 * @return int
	 */
	public function getStyleId()
	{
			if (isset($this->resolved_styleid))
		{
				return $this->resolved_styleid;
		}

		// Ensure basic node info is loaded
		$this->Load(self::INFO_BASIC);

		// If we have our own styleid, use that
		if ($this->styleid)
		{
			return $this->resolved_styleid = $this->styleid;
		}

		// If 0, use the board / user settings
		if ('0' === $this->styleid)
		{
			return $this->resolved_styleid = false;
		}

		// Load parents
		$this->Load(self::INFO_PARENTS);

		$styleid = false;
		if ($this->parents)
		{
		foreach ($this->parents AS $parent)
		{
			if (isset($parent['styleid']))
			{
				$styleid = $parent['styleid'];
			}
		}
			return $this->resolved_styleid = $styleid;
	}

		return $this->resolved_styleid = false;
	}


	/**
	 * Fetches the specific style setting for this node.
	 * If this node is inheriting the style then this should return false. To get
	 * the actual layout that will be used by this node, @see vBCms_Item_Content::getLayout()
	 *
	 * @return int | false
	 */
	public function getStyleSetting()
	{
		$this->Load();
		if ($this->styleid)
		{
			return $this->styleid;
		}
		return $this->node_style;
	}


	/**
	 * Returns the publish status, including adjusting for local time.
	 *
	 * @return int
	 */
	public function getPublished()
	{
		$this->Load();
		return $this->setpublish AND ($this->getPublishdate() <= TIMENOW);
	}

	/**
	 * Returns the publish dateline of the content.
	 *
	 * @return int
	 */
	public function getPublishDate()
	{
		$this->Load();

		return $this->publishdate;
	}
	/**
	 * Returns the publish dateline of the content.
	 *
	 * @return int
	 */
	public function getPublishDateLocal()
	{
		$this->Load();

		// if the publish date has not been initialized, simply return null
		if (!isset($this->publishdate))
		{
			return $this->publishdate;
		}

		return $this->publishdate - vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo);
	}

	/**
	 * Returns the 'setpublish' status.
	 *
	 * @return int
	 */
	public function getSetPublish()
	{
		$this->Load();

		return $this->setpublish ? $this->publishdate : false;
	}


	/**
	 * Returns the 'setpublish' status.
	 *
	 * @return int
	 */
	public function getDisplayOrder($sectionid)
	{
		$this->Load();

		if (isset($this->displayorder) AND ($sectionid == $this->parentnode))
		{
			return $this->displayorder ;
		}
		$record = vB::$vbulletin->db->query_first($sql = "SELECT displayorder FROM " . TABLE_PREFIX .
			"cms_sectionorder WHERE nodeid = " . $this->nodeid . " AND sectionid = $sectionid");

		return $record['displayorder'];
	}


	/**
	 * Returns the page title- the HTML page header info
	 *
	 * @return int
	 */
	public function getTitle()
	{
		$this->Load(self::INFO_NODE);

		if ($this->canusehtml == -1)
		{
			$this->canusehtml = vBCMS_Permissions::canUseHtml($this->nodeid, $this->contenttypeid, $this->userid);
		}


		if (!$this->canusehtml)
		{
			return strip_tags($this->title);
		}
		return $this->title;
	}


	/**
	 * Returns the "hidden" status of the item.
	 *
	 * @return int
	 */
	public function getHidden()
	{
		$this->Load();

		return $this->hidden;
	}

	/**
	 * Returns the flag that indicates this should be available for the subnav bar.
	 *
	 * @return int
	 */
	public function getShowNav()
	{
		$this->Load();

		return $this->shownav;
	}

	/**
	 * Returns the page title- the HTML page header info
	 *
	 * @return int
	 */
	public function getHtmlTitle()
	{
		$this->Load();

		return $this->html_title;
	}


	/**
	 * Returns whether the content is published.
	 *
	 * @return bool
	 */
	public function isPublished()
	{
		$this->Load(self::INFO_PARENTS);

		return (!$this->pending_parent AND ($this->setpublish AND $this->publishdate AND ($this->publishdate <= TIMENOW)));
	}

	/**
	 * Returns the public preview flag of the content.
	 *
	 * @return int
	 */
	public function getPublicPreview()
	{
		$this->Load();

		return $this->publicpreview;
	}

	/**
	 * Returns the publish dateline of the content.
	 *
	 * @return int
	 */
	public function getViewcount()
	{
		$this->Load();

		return $this->viewcount;
	}


	/**
	 * Returns the id of the nearest unpublished parent.
	 *
	 * @return bool
	 */
	public function getPendingParentId()
	{
		$this->Load(self::INFO_PARENTS);

		return $this->pending_parent;
	}


	/**
	 * Returns the title of the nearest unpublished parent.
	 *
	 * @return string
	 */
	public function getPendingParentTitle()
	{
		$this->Load(self::INFO_PARENTS);

		if ($pending_id = $this->getPendingParentId())
		{
			return $this->parents[$pending_id]['title'];
		}

		return false;
	}


	/**
	 * Returns the navigation menu nodes.
	 *
	 * @return array int
	 */
	public function getNavigationNodes()
	{
		$this->Load(self::INFO_NAVIGATION);

		return $this->navigation_nodes;
	}


	/**
	 * Gets whether this node has it's own menu.
	 *
	 * @return bool
	 */
	public function hasNavigation()
	{
		$this->Load(self::INFO_NAVIGATION);

		return $this->navigation_ownmenu;
	}


	/**
	 * Returns the nodeid of the node that the navigation is loaded from.
	 *
	 * @return int
	 */
	public function getNavigationNode()
	{
		$this->Load(self::INFO_NAVIGATION);

		return $this->navigation_node;
	}


	/**
	 * Returns the title of the node that the navigation is loaded from.
	 *
	 * @return string
	 */
	public function getNavigationParentTitle()
	{
		$this->Load(self::INFO_NAVIGATION);

		if ($this->canusehtml == -1)
		{
			$this->canusehtml = vBCMS_Permissions::canUseHtml($this->nodeid, $this->contenttypeid, $this->userid);
		}

		if (!$this->canusehtml)
		{
			$this->navigation_parenttitle = strip_tags($this->navigation_parenttitle);
		}

		return $this->navigation_parenttitle;
	}


	/**
	 * Fetches the contentid.
	 * How this is interpreted is up to the content handler for the contenttype.
	 * Note that to make vB_Model work properly when instantiating a new item
	 * we need to return the nodeid if we don't have a content id. But we should
	 * be able to get only the contentid if we don't want the nodeid.
	 * @return int
	 */
	public function getContentId($contentonly = false)
	{
		//For sections, and other types in the future, we have no separate contentid, just a nodeid
		$this->Load();

		if ($contentonly)
		{
			return $this->contentid;
		}

		return ($this->contentid >0 ? $this->contentid : $this->nodeid) ;
	}


	/**
	 * Fetches the lastupdated timestamp.
	 *
	 * @return integer
	 ***/
	public function getLastUpdated()
	{
		$this->Load();

		return $this->lastupdated;

	}

	/**
	 * Fetches the Rating number
	 *
	 * @return integer
	 ***/
	public function getRatingNum()
	{
		$this->Load(self::INFO_NODE);

		return $this->ratingnum;

	}

	/**
	 * Fetches the rating total
	 *
	 * @return integer
	 ***/
	public function getRatingTotal()
	{
		$this->Load(self::INFO_NODE);

		return $this->ratingtotal;

	}

	/**
	 * Fetches the rating
	 *
	 * @return integer
	 ***/
	public function getRating()
	{
		$this->Load(self::INFO_NODE);

		return $this->rating;

	}

	/**
	 * Fetches the creationdate timestamp.
	 *
	 * @return int
	 */
	public function getCreationDate()
	{
		$this->Load();

		return $this->creationdate;
	}
	/**
	 * Fetches the id of the content.
	 *
	 * @return int
	 */
	public function getContentTypeID()
	{
		$this->Load();

		return $this->contenttypeid;
	}

	/**
	 * Fetches the depth of the content in the node tree.
	 *
	 * @return int
	 */
	public function getDepth()
	{
		$this->Load(self::INFO_DEPTH);

		return $this->depth;
	}


	/******
	* Can this user view this item?
	* @return boolean
	******/
	public function canView()
	{
		// This user can view if either they are the creator,
		// or they have view rights for this content and this is published,
		// or they have edit rights or publish rights;
		$this->loadInfo();
		if (!isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		// No one can bypass the main canview permission
		if (!in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canview']))
		{
			return false;
		}

		//Hidden flag applies to the section. An article is never hidden if we're going directly to it.
		if ($this->hidden AND ($this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Section") ))
		{
			return (in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canpublish']) );
		}

		//if any parent is not published, then the article should be treated (for permissions) as unpublished.

		$published = ($this->setpublish AND ($this->publishdate <= TIMENOW));

		if ($published AND !empty($this->parents))
		{
			foreach ($this->parents as $parent)
			{
				if (!$parent['setpublish'] OR $parent['publishdate'] > TIMENOW)
				{
					$published = false;
					break;
				}
			}
		}

		$viewown = (vB::$vbulletin->userinfo['userid'] AND vB::$vbulletin->userinfo['userid'] == $this->userid);
		if ($viewown
			OR $published
			OR in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canpublish'])
			OR in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canedit']))
		{
			return true;
		}
		else
		{
		   return false;
		}

	}

	/******
	 * Can a non-logged-in view this item?
	 * @return boolean
	 ******/
	public function publicCanView()
	{
		//The public, i.e. non-logged-in, group is usergroupid 1
		$this->loadInfo();

		if (!isset($this->publicCanView))
		{

			//We need to do a query here
			if ($record = vB::$vbulletin->db->query_first("SELECT permissionid FROM " .TABLE_PREFIX .
				"cms_permissions WHERE nodeid = " . $this->permissionsfrom . " AND usergroupid =1"));
			{
				$this->publicCanView = (isset($record) AND intval($record['permissionid']));
			}

		}

		return $this->publicCanView;
	}

	/******
	 * Can this user edit this item?
	 * @return boolean
	 ******/
	public function canEdit()
	{
		if (!vB::$vbulletin->userinfo)
		{
			return false;
		}

		//This user can edit if either they are the creator,
		// or they have edit rights for this content;
		$this->loadInfo();

		if (vB::$vbulletin->userinfo['userid'] AND (vB::$vbulletin->userinfo['userid'] == $this->userid))
		{
			return true;
		}

		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		if ($this->hidden)
		{
			return (in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canpublish']));
		}
		return (in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canedit']));
	}
	/******
	 * Can this user edit this item?
	 * return boolean
	 ******/
	public function canUseHtml($userid)
	{
		$this->loadInfo();

		return vBCMS_Permissions::canUseHtml($this->nodeid,
			$this->contenttypeid, $userid);

		}

	/******
	 * Can this user create an item here?
	 * @return boolean
	 ******/
	public function canCreate()
	{
		if (!vB::$vbulletin->userinfo['userid'])
		{
			return false;
		}

		//This user can create content if this is a section
		// and they have create rights here;
		$this->loadInfo();
		if (!vb_Types::instance()->getContentTypeID("vBCms_Section") == $this->contenttypeid)
		{
			return false;
		}
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		if ($this->hidden)
		{
			return (in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['canpublish']) );
		}

		return in_array($this->permissionsfrom, vB::$vbulletin->userinfo['permissions']['cms']['cancreate']);
	}

	/******
	 * Can this user publish in this Section?
	 * @return boolean
	 ******/
	public function canPublish()
	{
		if (!vB::$vbulletin->userinfo['userid'])
		{
			return false;
		}

		$this->loadInfo();
		//This user can view if they have view rights for this content;
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		$permissionsfrom = isset($this->permissionsfrom) ? $this->permissionsfrom : $this->parentpermissions;

		return (in_array($permissionsfrom,  vB::$vbulletin->userinfo['permissions']['cms']['canpublish'])) ;
	}

	/******
	 * Can this user download/view content in this Section?
	 * @return boolean
	 ******/
	public function canDownload()
	{
		$this->loadInfo();
		//This user can view if they have view rights for this content;
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		$permissionsfrom = isset($this->permissionsfrom) ? $this->permissionsfrom : $this->parentpermissions;

		return (in_array($permissionsfrom,  vB::$vbulletin->userinfo['permissions']['cms']['candownload'])) ;
	}



	/**
	 * Fetches all permissions as an array.
	 *
	 * @return array
	 */
	public function getPermissions()
	{
		return array(
			'view' => $this->canView(),
			'create' => $this->canCreate(),
			'publish' => $this->canPublish(),
			'edit' => $this->canEdit()
		);
	}

	/**
	 * Fetches the Permissionsfrom value
	 *
	 * @return integer
	 */
	public function getPermissionsFrom()
	{
		$this->loadInfo();
		return $this->permissionsfrom;
	}

	/**
	 * Returns the ShowTitle field
	 *
	 * @return integer
	 */
	public function getShowTitle()
	{
		$this->loadInfo();
		return $this->showtitle;
	}

	/**
	 * Returns the showuser field
	 *
	 * @return integer
	 */
	public function getShowUser()
	{
		$this->loadInfo();
		return $this->showuser;
	}

	/**
	 * Returns the showpreviewonly field
	 *
	 * @return integer
	 */
	public function getShowPreviewonly()
	{
		$this->loadInfo();
		return $this->showpreviewonly;
	}

	/**
	 * Returns the showupdated field
	 *
	 * @return integer
	 */
	public function getShowUpdated()
	{
		$this->loadInfo();
		return $this->showupdated;
	}

	/**
	 * Returns the showviewcount field
	 *
	 * @return integer
	 */
	public function getShowViewcount()
	{
		$this->loadInfo();
		return $this->showviewcount;
	}

	/**
	 * Returns the showpublishdate field
	 *
	 * @return integer
	 */
	public function getShowPublishdate()
	{
		$this->loadInfo();
		return $this->showpublishdate;
	}

	/**
	 * Returns the settingsforboth field
	 *
	 * @return integer
	 */
	public function getSettingsForboth()
	{
		$this->loadInfo();
		return $this->settingsforboth;
	}

	/**
	 * Returns the includechildren field
	 *
	 * @return integer
	 */
	public function getIncludeChildren()
	{
		$this->loadInfo();
		return $this->includechildren;
	}

	/**
	 * Returns the editshowchildren field
	 *
	 * @return integer
	 */
	public function getEditShowchildren()
	{
		$this->loadInfo();
		return $this->editshowchildren;
	}

	/**
	 * Returns the showall field
	 *
	 * @return integer
	 */
	public function getShowall()
	{
		$this->loadInfo();
		return $this->showall;
	}


	/**
	 * Fetches the class identifier of the contenttype.
	 * Note: This is only a segment of the class name.  It should be combined with
	 * the package class identifier and the required class type.
	 *
	 * Usually class names can be resolved with the vB_Content for a specific
	 * content type.
	 *
	 * @return string
	 */
	public function getClass()
	{
		$this->Load();

		if ($this->contenttypeid)
		{
			return vBCms_Types::instance()->getContentTypeClass($this->contenttypeid);
		}

		return $this->class;
	}


	/**
	 * Fetches the package class identifier of the contenttype.
	 * Note: This is only a segment of the class name.  It should be combined with
	 * the content class identifier and the required class type.
	 *
	 * Usually class names can be resolved with the vB_Content for a specific
	 * content type.
	 *
	 * @return string
	 */
	public function getPackage()
	{
		$this->Load();

		if ($this->contenttypeid)
		{
			return vBCms_Types::instance()->getContentTypePackage($this->contenttypeid);
		}

		return $this->package;
	}


	/**
	 * Fetches the node config of the content.
	 *
	 * @return array mixed
	 */
	public function getConfig($cvar = false)
	{
		$this->Load(self::INFO_CONFIG);

		if ($cvar)
		{
			return (isset($this->config[$cvar]) ? $this->config[$cvar] : null);
		}

		return $this->config;
	}


	/**
	 * Sets the config.
	 * TODO: Only allow items of same type to set the config?
	 *
	 * @param array mixed $config
	 */
	public function setConfig($config)
	{
		$this->config = $config;

		$this->loaded_info |= self::INFO_CONFIG;
	}


	/**
	 * Allows parents to be set by client code.
	 *
	 * @param $parentlist						- The list of parents
	 */
	public function setParents($parentlist)
	{
		$this->parents = $parentlist;

		$this->loaded_info |= self::INFO_PARENTS;
	}


	/**
	 * Fetches the node url title only.
	 * The nodeid prefix is not included.  This is useful for display purposes.
	 *
	 * @return string
	 */
	public function getUrlTitle()
	{
		$this->Load();

		return $this->url;
	}


	/**
	 * Returns the resolved url segment for the node.
	 *
	 * @return string
	 */
	public function getUrlSegment()
	{
		$this->Load();

		return self::buildUrlSegment($this->nodeid, $this->url);
	}


	/**
	 * Builds a node segment from a nodeid and url segment.
	 *
	 * @param $nodeid
	 * @param $url_segment
	 * @return string
	 */
	public static function buildUrlSegment($nodeid, $url_segment)
	{
		return $nodeid . ($url_segment ? '-' . $url_segment: '');
	}


	/**
	 * Returns the url segment defined for the node.
	 *
	 * @return string
	 */
	public function getUrl()
	{
		$this->Load();

		return $this->url;
	}


	/*** returns the current style
	 * @return string
	 * ******/
	public function getStyle()
	{
		$this->Load();
		return $this->getStyleId();
	}


	/*** returns the flag for comments enabled
	*
	 * @return int
	 * ******/
	public function getComments_Enabled()
	{
		$this->Load();

		return $this->comments_enabled;
	}

	/** Sets the Comments Enabled flag
	* @param boolean
	****/
	public function setComments_Enabled($enabled = true)
	{
		$this->comments_enabled = $enabled;
	}

	/** returns a list of user groups that can read this item
	*
	* @return array
	*
	****/
	public function getReaderGroups()
	{
		$groups = array();

		if ($rst = vB::$vbulletin->db->query_read($sql = "SELECT u.usergroupid, u.title FROM " .
			TABLE_PREFIX . "usergroup AS u INNER JOIN " . TABLE_PREFIX . "cms_permissions AS perm
			ON perm.usergroupid = u.usergroupid WHERE perm.nodeid = " . $this->permissionsfrom .
			" AND perm.permissions > 0	ORDER BY u.title"))
		{
			while($row = vB::$vbulletin->db->fetch_array($rst))
			{
				$groups[] = array('usergroupid' => $row['usergroupid'] , 'title' => $row['title']);
			}

		}
		return $groups;
	}

	/** returns a list of categories where this is used
	 *
	 * @return array
	 *
	 ****/
	public function getThisCategories()
	{
		$dupemap = array();
		$categories = array();

		if ($rst = vB::$vbulletin->db->query_read($sql = "
			SELECT cat.category, cat.categoryid, cat.catleft, cat.catright, nodec.nodeid, info.title AS section
			FROM " . TABLE_PREFIX . "cms_category AS cat
			LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON cat.parentnode = info.nodeid
			LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS nodec ON cat.categoryid = nodec.categoryid AND nodec.nodeid = " . $this->nodeid . "
			ORDER BY cat.catleft;
			")
		)
		{
			$array_index = 0;
			$ancestry = array('0' => array());
			while($row = vB::$vbulletin->db->fetch_array($rst))
			{
				//Let's get the ancestry tree;
				// Make sure we have the index to an array element that's a parent of the current node.
				while(($array_index > 0) AND (intval($row['catright']) > intval($ancestry[$array_index]['catright']) ) )
				{
					$array_index--;
				}
				$parents = array();
				if (0 < $array_index )
				{
					for($i = 1;$i <= $array_index; $i++)
					{
						$parents[] = $ancestry[$i]['category'];
					}
				}
				$parents[] = $row['category'];
				$row['duplicate'] = 0;
				$row['catlevel'] = sizeOf($parents);
				$row['text'] = implode('>', $parents);
				$row['checked'] = (isset($row['nodeid']) ? 'checked="checked"' : '');
				$array_index++;
				$ancestry[$array_index] = $row;
				$categories[] = $row;
				$dupemap[$row['category']][] = sizeOf($categories)-1;
			}
		}

		// Flag duplicate names.
		foreach($dupemap AS $data)
		{
			if (sizeOf($data) > 1)
			{
				foreach($data AS $cat)
				{
					$categories[$cat]['duplicate'] = 1;
					$categories[$cat]['text'] = $categories[$cat]['section'].': '.$categories[$cat]['text'];
				}					
			}
		}

		return $categories;
	}

	/** returns a string representing the hierarchy of parentage for this node
	 *
	 * @return string
	 *
	 ****/
	public function getParentage()
	{

		if (! $this->parentnode)
		{
			return '';
		}

		if ($rst = vB::$vbulletin->db->query_read("SELECT node.nodeleft, info.title FROM " .
			TABLE_PREFIX . "cms_node AS node INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info
			ON node.nodeid = info.nodeid INNER JOIN " . TABLE_PREFIX .
			"cms_node AS myself ON (myself.nodeleft
			 >= node.nodeleft AND myself.nodeleft <= node.noderight) WHERE myself.nodeid =" . $this->parentnode . "
			ORDER BY node.nodeleft"))
		{
			$parents = array();
			while($row = vB::$vbulletin->db->fetch_array($rst))
			{
				$parents[] = $row['title'];
			}

		}
		return implode('>' , $parents);

	}

	/** Creates the publish/metadata editor at the top right of the edit section
	 *
	 * @return mixed
	 *
	 ****/
	public function getPublishEditor($submit_url, $formid, $showpreview = true, $showcomments = true,
		$publicpreview = false, $comments_enabled = false, $pagination_links = 1)
	{
		if ($this->canPublish())
		{
			$pub_view = new vB_View('vbcms_edit_publisher');
			$pub_view->formid = $formid;
			$pub_view->setpublish = $this->setpublish;

			// if this is an unpublished article then we display publish to facebook
			if ($this->contenttypeid != vb_Types::instance()->getContentTypeID("vBCms_Section") 
				AND is_facebookenabled() AND vB::$vbulletin->options['fbfeednewarticle'] AND !$this->setpublish)
			{
				// only display box if user is connectected to facebook
				$pub_view->showfbpublishcheckbox = is_userfbconnected();
			}

			//Get date is a most annoying function for us. It takes a Unix time stamp
			// and converts it to server local time. We need to compensate for the difference between
			// server time (date('Z')) and usertime (vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo))
			$offset = vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo) - date('Z');

			if (intval($this->publishdate))
			{
				$pub_view->publishdate = $this->publishdate ;
			}
			else
			{
				// get the current date/time dependent on user locality
				$pub_view->publishdate = TIMENOW;
			}

			$then = getdate(intval($pub_view->publishdate) + $offset);

			$pub_view->hour = $then['hours'];
			$pub_view->minute = $then['minutes'];
			//we need to parse out the date and time

			//Are we using a 24 hour clock?
			if ((strpos(vB::$vbulletin->options['timeformat'], 'G') !== false) OR
				(strpos( vB::$vbulletin->options['timeformat'], 'H') !== false))
			{
				$pub_view->show24 = 1;

			}
			else
			{
				$pub_view->show24 = 0;
				$pub_view->offset = $pub_view->hour >= 12 ? 'PM' : 'AM';
				if ($pub_view->hour > 12)
				{
					$pub_view->hour -= 12;
				}
			}

			$pub_view->title = $this->title;
			$pub_view->html_title = $this->html_title;
			$pub_view->username = $this->username;
			$pub_view->dateformat = vB::$vbulletin->options['dateformat'];
			// get the appropriate date format string for the
			// publish date calendar based on user's locale
			$pub_view->calendardateformat = (!empty(vB::$vbulletin->userinfo['lang_locale']) ? '%Y/%m/%d' : 'Y/m/d');
			$pub_view->groups = $this->getReaderGroups();
			$pub_view->parents = $this->getParentage();
			$pub_view->submit_url = $submit_url;
			$pub_view->sectiontypeid = vb_Types::instance()->getContentTypeID("vBCms_Section");
			$pub_view->parents = $this->getParentage();
			$pub_view->showtitle = $this->getShowTitle();
			$pub_view->showuser = $this->getShowUser();
			$pub_view->showpreviewonly = $this->getShowPreviewonly();
			$pub_view->showupdated = $this->getShowUpdated();
			$pub_view->showviewcount = $this->getShowViewcount();
			$pub_view->showpublishdate = $this->getShowPublishdate();
			$pub_view->settingsforboth = $this->getSettingsForboth();
			$pub_view->showall = $this->getShowall();
			$pub_view->includechildren = $this->getIncludeChildren();
			$pub_view->showrating = $this->getShowRating();
			$pub_view->hidden = $this->getHidden();
			$pub_view->pagination_links = $pagination_links;
			$pub_view->show_pagination_link = ($this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Section") ) ? 1 : 0;
			$pub_view->shownav = $this->getShowNav();
			$pub_view->show_shownav = ($this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Section") ) ? 0 : 1;
			$pub_view->nosearch = $this->getNoSearch();

			$sectionid = (1 == $this->nodeid) ? 1 : $this->parentnode;

			$pub_view->hours24 = vB::$vbulletin->options['dateformat'];
			if ($this->contenttypeid == $pub_view->sectiontypeid)
			{
				$pub_view->show_categories = 0;
				$pub_view->is_section = 1;
				$pub_view->show_showsettings = 0;
			}
			else
			{
				$pub_view->show_categories = 1;
				$pub_view->categories = $this->getThisCategories();
				$pub_view->show_showsettings = 1;
				$pub_view->is_section = 0;
				$pub_view->sectionid = $this->parentnode;
			}

			if ($pub_view->show_htmloption = (
				$this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Article")	// this is limited here to article but could be moved to any contenttype
					AND
				$this->canusehtml	// this is set by some of the member functions above...
			))
			{
				$pub_view->htmloption = $this->htmlstate;
			}
			$pub_view->show_categories = ($this->contenttypeid == $pub_view->sectiontypeid ? 0 : 1);

			//get the nodes
			$nodelist = vBCms_ContentManager::getSections(false, false, false);

			if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
			{
				vBCMS_Permissions::getUserPerms();
			}

			foreach ($nodelist as $key => $node)
			{
				if (in_array(strval($node['permissionsfrom']), vB::$vbulletin->userinfo['permissions']['cms']['canpublish']))
				{
					$nodelist[$key]['selected'] = ($sectionid == $node['nodeid'] ? 'selected="selected"' : '');
				}
				else
				{
					unset($nodelist[$key]);
				}
			}

			$pub_view->nodelist = $nodelist;
			$pub_view->showpreview = $showpreview;
			$pub_view->showcomments = $showcomments;

			//if this is an article being promoted, set the keepthread and movethread values.

			if (!$this->getAssociatedThreadId() AND
				method_exists($this, 'getPostId') AND ($this->getPostId() > 0))
			{
				$pub_view->showMoveThread = true;
				$pub_view->keepthread = $this->getKeepThread();
				$pub_view->movethread = $this->getMoveThread();
			}
			else
			{
				$pub_view->showMoveThread = false;
				$pub_view->listnodes = false;
				$pub_view->movethread = false;
			}

			if (method_exists($this, 'getAllComments'))
			{
				$pub_view->showAllComments = (method_exists($this, 'getPostId') AND ($this->getPostId() > 0));
				$pub_view->allcomments = $this->getAllComments();
			}
			else
			{
				$pub_view->showAllComments = 0;
				$pub_view->allcomments = 0;
			}

			$pub_view->publicpreview = $publicpreview;
			$pub_view->hidden = $this->hidden;
			$pub_view->comments_enabled = $comments_enabled;
			$pub_view->show_sections = (1 != $this->nodeid);

			($hook = vBulletinHook::fetch_hook('vbcms_content_publish_editor')) ? eval($hook) : false;

			//Extra handling is needed if this was promoted from a post and is an article
			$pub_view->render_sharethread = 0;

			if (method_exists($this, 'getPostId') AND method_exists($this, 'getKeepThread')	AND $this->getPostId())
			{
				//If the thread hasn't been assigned we display the "Keep thread" option
				if (!$this->getAssociatedThreadId())
				{
					$pub_view->canset_forumid = 1;
				}
				else
				{
					$pub_view->canset_forumid = 0;
					$pub_view->associatedthreadid  = $this->getAssociatedThreadId();
				}

				$pub_view->allcomments = $this->getAllComments();
				$pub_view->keepthread = $this->getKeepThread();
				$pub_view->movethread = $this->getMoveThread();
			}

			if ($comments_enabled)
			{
				$pub_view->section_showcomments = 1;
			}

			return $pub_view;
		}
	}

	/** Creates the publish editor at the lower right of the edit section
	 *
	 * @return mixed
	 *
	 ****/
	public function getMetadataEditor()
	{
		require_once DIR . '/includes/functions_databuild.php';

		fetch_phrase_group('cpglobal');

		if ($this->canEdit() OR $this->canPublish())
		{
			$meta_view = new vB_View('vbcms_edit_metadataeditor');
			$meta_view->html_title = $this->html_title;
			$meta_view->description = $this->description;
			$meta_view->keywords = $this->keywords;
			return $meta_view;
		}
	}


	/** Creates the publish editor across the bottom of the edit section
	 *
	 * @return mixed
	 *
	 ****/
	public function getEditBar($submit_url, $view_url, $formid, $editorid = 0, $action = 'edit', $candelete = true)
	{
		global $vbphrase;

		if ($this->canEdit() OR $this->canPublish())
		{
			require_once DIR . '/includes/functions_databuild.php';

			fetch_phrase_group('cpcms');
			fetch_phrase_group('contenttypes');

			$new_view = new vB_View('vbcms_content_edit_editbar');
			$new_view->submit_url = $submit_url;
			//If this is a new node, then view url is the home page.

			if (intval($this->new))
			{
				$segments = array('node' => vBCms_Item_Content::buildUrlSegment($this->getParentId(), $this->getParentURLSegment()), 'action' =>'view');
				$view_url = vBCms_Route_Content::getURL($segments);
			}
			$new_view->view_url = $view_url;
			$new_view->formid = $formid;
			$new_view->editorid = $editorid;
			$new_view->header_phrase = $header_phrase;
			$new_view->adding = construct_phrase($vbphrase['addoredit_x'], $vbphrase[$action], $vbphrase[strtolower('contenttype_' . $this->package . '_' . $this->class)]);
			$new_view->confirm_message = $vbphrase['delete_page_confirmation_message'];
			$new_view->candelete = $candelete;
			$new_view->is_section = ($this->contenttypeid == vb_Types::instance()->getContentTypeID("vBCms_Section"));

			return $new_view;
		}
	}

	/**
	 * Returns associated thread used for comments on this content
	 *
	 * @return string
	 */
	public function getAssociatedThreadId()
	{
		$this->Load();

		return $this->associatedthreadid;
	}

	/**
	 * Sets the associated thread
	 *
	 * @param int $associatedthreadid, the associated thread id to populate the node info with
	 * @return bool, true is successful
	 */
	public function setAssociatedThread($associatedthreadid)
	{
		// update the node info record
		if (!vB::$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_nodeinfo
			SET associatedthreadid = $associatedthreadid
			WHERE nodeid = " . $this->nodeid)
		)
		{
			return false;
		}

		// if succesful, update the instance and return
		$this->associatedthreadid = $associatedthreadid;
		return true;
	}

	/**** for non-section nodes, we return the category list-
	* array (id => title)
	*
	*
	*
	* @return array
	*
	****/
	public function getCategories()
	{
		global $vbphrase;
		if (vb_Types::instance()->getContentTypeID("vBCms_Section") == $this->contenttypeid )
		{
			return array();
		}

		$clc = 0;
		$categories = array();
		if ($rst = vB::$vbulletin->db->query_read("SELECT category, cat.categoryid FROM "
			. TABLE_PREFIX . "cms_nodecategory nc INNER JOIN "
			. TABLE_PREFIX . "cms_category cat ON cat.categoryid = nc.categoryid
			WHERE nc.nodeid = " . $this->nodeid ))
		{
			while($record = vB::$vbulletin->db->fetch_array($rst))
			{
				$clc++;
				$record['comma'] = $vbphrase['comma_space'];
				$route_info = $record['categoryid'] . ($record['category'] != '' ? '-' . $record['category'] : '');
				$record['category_url'] = vB_Route::create('vBCms_Route_List', "category/$route_info/1")->getCurrentURL();
				$categories[$clc] = $record;
			}
		}

		// Last element
		if ($clc)
		{
			$categories[$clc]['comma'] = '';
		}

		return $categories;
	}


}
