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
 * vBCms Navbar
 * Builds the vBCms links for display in the navbar.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 31871 $
 * @since $Date: 2009-08-25 16:54:54 +0100 (Tue, 25 Aug 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_NavBar
{
	/*Constants=====================================================================*/

	/**
	 * Global cache event.
	 * Use when a section is deleted or published.
	 */
	const GLOBAL_CACHE_EVENT = 'vbcms_nav_global';
	const GLOBAL_SECTION_CACHE_EVENT = 'sections_updated';

	/*Properties====================================================================*/

	private static $cache_ttl = 10;

	/**
	 * The navbar link list
	 *
	 * @var array
	 */
	public static $linklist = array();

	/**
	 * A prefix for all cache references.
	 *
	 * @var string
	 */
	public static $cache_prefix = 'vbcms_nav_';


	/*View==========================================================================*/

	/**
	 * Prepares the navbar view so that it can be fetched and rendered.
	 * Note: Forcing the cache to be ignored is useful if the subnav has just been
	 * updated.
	 *
	 * @param vBCms_Item_Content $node			- The current node
	 * @param bool $refresh						- Forces the cache to be ignored and the view to be rebuilt.
	 */
	public static function prepareNavBar($node = false, $refresh = false)
	{
		// Normalize node
		$node = ($node ? $node : 1);

		if (!$node instanceof vBCms_Item_Content)
		{
			$node = new vBCms_Item_Content($node, vBCms_Item_Content::INFO_NAVIGATION);
		}

		$cache_key = self::getHash($node);

		if ($refresh OR !($navnodes = vB_Cache::instance()->read($cache_key, false, true)))
		{

			//The query to pull the navigation requires that the
			//parent information be available
			$node->requireInfo(vBCms_Item_Content::INFO_PARENTS);
			$node->isValid();
			$node->requireInfo(vBCms_Item_Content::INFO_NAVIGATION);

			if ($navnodes = $node->getNavigationNodes())
			{
				// get collection
				$collection = new vBCms_Collection_Content($navnodes, vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_PARENTS);
				$collection->filterVisible(false);

				// check count
				if (!$collection->isValid())
				{
					return false;
				}

				// set original ids as keys
				$navnodes = array_flip($navnodes);
				// remap order
				foreach ($collection AS $navnode)
				{
					$navnodes[$navnode->getNodeId()] = $navnode;
				}
				unset($collection);

				// remove unfound entries
				foreach ($navnodes AS $id => $navnode)
				{
					if (!$navnode instanceof vBCms_Item_Content)
					{
						unset($navnodes[$id]);
					}
				}
				// write cache
				vB_Cache::instance()->write(
					$cache_key,
					$navnodes,
					self::$cache_ttl,
					array(
						self::getCacheEventId($node->getNavigationNode()),
						self::GLOBAL_CACHE_EVENT,
						self::GLOBAL_SECTION_CACHE_EVENT
					)
				);
			}
		}

		if (is_array($navnodes) AND !empty($navnodes))
		{
			$perms_load = array();
			foreach($navnodes as $navnode)
			{
				$perms_load[] = $navnode->getNodeId();
			}

			vBCMS_Permissions::loadPermissionsfrom(array_keys($perms_load));
		}

		// create navlinks for published nodes
		$links = array();
		$route = new vBCms_Route_Content();

		foreach ((array)$navnodes AS $navnode)
		{
			if ($navnode->isPublished() AND $navnode->canView())
			{
				$route->node = $navnode->getUrlSegment();
				$links[] = array(
					'type' => 'link',
					'title' => $navnode->getTitle(),
					'url' => $route->getCurrentUrl()
				);
			}
		}

		if (!self::$linklist OR $refresh)
		{
			self::$linklist = $links;
		}
	}


	/**
	 * Fetches the prepared nabar list.
	 *
	 * @return array	- The navbar links
	*/
	public static function getLinks()
	{
		if (self::$linklist)
		{
			return self::$linklist;
		}
		else
		{
			return array();
		}
	}

	/********* Get a hash so we can cache the data
	 *
	 ********/
	protected static function getHash($node)
	{
		$context = new vB_Context(self::$cache_prefix, array($node, vB::$vbulletin->userinfo['usergroupid'], vB::$vbulletin->userinfo['membergroupids']));
		return strval($context);
	}

	/**
	 * Fetches a consistent event id for a given node's navbar.
	 *
	 * @param int $nodeid						- The nodeid being cached.
	 * @return string
	 */
	public static function getCacheEventId($nodeid)
	{
		return array(self::$cache_prefix . $nodeid);

	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 31871 $
|| ####################################################################
\*======================================================================*/
