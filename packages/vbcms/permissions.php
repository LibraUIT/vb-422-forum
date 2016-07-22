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
 * CMS Content Controller
 * Base content controller for CMS specific content types.
 *
 * @package vBulletin
 * @author Ed Brown, vBulletin Development Team
 * @version $Revision: 77113 $
 * @since $Date: 2013-08-27 08:06:24 -0700 (Tue, 27 Aug 2013) $
 * @copyright vBulletin Solutions Inc.
 */

 /**
  * Class to return permissions
  *
  */

 class vBCMS_Permissions
{
	/**** Caching time in minutes for permissions info ********/
 	protected static $cache_ttl = 5;

 	//Permissions are:
 	//1: canview
 	//2: cancreate
 	//4: canedit
 	//8: canpublish
 	//16: canUseHtml
 	//32: canDownload
 	protected static $permissionsfrom = array();

 	/*** returns a string suitable for use in a "where" clause to limit results
 	* to those visible to this user.
 	* ***/
 	protected static $permission_string = false;

 	//We may have to check "canUseHtml" for several articles.
 	//Might as well cache the usergroups info.

 	private static $known_users = array();



 	 /**** This queries for a user's permissions. It is normally called once early
 	 * on a CMS page to read this user's permissions.
 	 *
 	 * @param int
 	 *
 	 ****/
 	public static function getUserPerms($userid = false)
	{
		// TODO: If we fetch another user's permissions they're going to overwrite the current user...
		vB::$vbulletin->userinfo['permissions']['cms'] = self::getPerms($userid);
	}



 	/**** This function generates and if appropriate caches CMS permissions for a user
 	 *
 	 * @param int
 	 * @param string
 	 *
 	 * @return array
 	 ****/
 	public static function getPerms($userid = false, $usergroups = false)
 	{
 		if (false === $userid)
		{
			$userid = vB::$vbulletin->userinfo['userid'];
		}

		if ($userid == vB::$vbulletin->userinfo['userid'] AND isset(vB::$vbulletin->userinfo['permissions']['cms']) )
 		{
 			return vB::$vbulletin->userinfo['permissions']['cms'];
 		}

 		//See if we have a cached version
 		$hash = self::getHash($userid);

 		//See if we already have this user;
 		if (array_key_exists($userid, self::$known_users))
 		{
 			return self::$known_users[$userid];
 		}

 		if ($cmsperms = vB_Cache::instance()->read($hash, true, true))
 		{
 			if ($userid == vB::$vbulletin->userinfo['userid'] )
 			{
 				vB::$vbulletin->userinfo['permissions']['cms'] = $cmsperms;
 			}
 			else
 			{
 				self::$known_users[$userid] = $cmsperms;
 			}
 			return $cmsperms;
 		}

 		if (!$usergroups)
 		{
 			if (!$userid OR (vB::$vbulletin->userinfo['userid'] == $userid))
 			{
				$usergroups = vB::$vbulletin->userinfo['usergroupid'] .
					(vB::$vbulletin->userinfo['membergroupids'] ? ',' . vB::$vbulletin->userinfo['membergroupids'] : '');
 			}
 			else
 			{
 				$record = vB::$vbulletin->db->query_first("SELECT usergroupid, membergroupids FROM "
 					. TABLE_PREFIX . "user WHERE userid = $userid");
 				$usergroups = $record['usergroupid']
 				. (strlen($record['membergroupids']) ? ',' . $record['membergroupids'] : '');
 			}
 		}

 		$cmsperms = array();
 		//We need to create four arrays
 		$cmsperms['canview'] = array();
 		$cmsperms['cancreate'] = array();
 		$cmsperms['canedit'] = array();
 		$cmsperms['canpublish'] = array();
 		$cmsperms['canusehtml'] = array();

		//The admin settings are all done by hooks, so we need to parse out the information
 		//ourselves manually.
 		$sql ="SELECT vbcmspermissions FROM " . TABLE_PREFIX .
 			"administrator WHERE userid = $userid";
			$record = vB::$vbulletin->db->query_first($sql);
 		if ($record AND $record['vbcmspermissions'])
 		{
 			$cmsperms['admin'] = $record['vbcmspermissions'];

 		}
 		else
 		{
 			$cmsperms['admin'] = 0;
 		}


 		if ($usergroups != '')
 		{
			$rst = vB::$vbulletin->db->query_read($sql = "SELECT nodeid,
			MAX(permissions & 1) AS canview, MAX(permissions & 2) AS cancreate , MAX(permissions & 4) AS canedit,
			MAX(permissions & 8) AS canpublish, MAX(permissions & 16) AS canusehtml, MAX(permissions & 32) AS candownload
			FROM " . TABLE_PREFIX . "cms_permissions p
			WHERE usergroupid IN (" . $usergroups . ")
			GROUP BY nodeid; ");

			while($rst AND $result = vB::$vbulletin->db->fetch_array($rst))
	 		{
				$nodeid = $result['nodeid'];
				unset($result['nodeid']);
				foreach ($result AS $key => $value)
				{
					if ($value)
					{
						$cmsperms["$key"][] = $nodeid;
					}
				}
			}
 		}

 		//when we use these in "where" clauses we'd better have at least one value.
 		if (empty($cmsperms['canview']))
 		{
 			$cmsperms['canview'][] = -1;
 		}

 		if (empty($cmsperms['cancreate']))
 		{
 			$cmsperms['cancreate'][] = -1;
 		}

 		if (empty($cmsperms['canedit']))
 		{
 			$cmsperms['canedit'][] = -1;
 		}

 		if (empty($cmsperms['canpublish']))
 		{
 			$cmsperms['canpublish'][] = -1;
 		}

 		if (empty($cmsperms['canusehtml']))
 		{
 			$cmsperms['canusehtml'][] = -1;
 		}

 		if (empty($cmsperms['candownload']))
 		{
 			$cmsperms['candownload'][] = -1;
 		}

		$cmsperms['alledit'] = $cmsperms['canedit'];

		$cmsperms['viewonly'] = array_diff($cmsperms['canview'], $cmsperms['alledit']);

 		$cmsperms['allview'] = $cmsperms['canview'];

 		if ($userid == vB::$vbulletin->userinfo['userid'] )
 		{
 			vB::$vbulletin->userinfo['permissions']['cms'] = $cmsperms;
 		}
		else
		{
			self::$known_users[$userid] = $cmsperms;
		}

  		vB_Cache::instance()->write($hash, $cmsperms, self::$cache_ttl,
			array('cms_permissions_change', "permissions_$userid"));

 		return $cmsperms;
 	}


 	/** This pulls the permission data from the database for a node,
 	*  if we don't already have it.
 	***/
 	private static function getPermissionsFrom($nodeid)
 	{
 		if (!$record = vB::$vbulletin->db->query_first("
 			SELECT
 				permissionsfrom, hidden, setpublish, publishdate, userid
 			FROM " . TABLE_PREFIX . "cms_node
 			WHERE
 				nodeid = $nodeid
 		"))
 		{
 			return false;
 		}
 		if (intval($record['permissionsfrom']))
 		{
 			self::$permissionsfrom[$nodeid] = $record;
 			return $record;
 		}
 		return false;
 	}
	/****
	 * This resets permissions for a node, and it's surrounding nodes, should they be unassigned.
	 *
	 ****/
	private static function repairPermissions($nodeid)
	{
		//we start by generating a list of this node's parents. We go up the tree until
		// either we find a node with assigned permissions, or we hit the top.
		//If we hit the top, we use that node.
		$parents = array();
		$rst = vB::$vbulletin->db->query_read("SELECT parent.nodeid,
		parent.permissionsfrom FROM " . TABLE_PREFIX . "cms_node AS parent INNER JOIN
		" . TABLE_PREFIX . "cms_node AS node ON (node.nodeleft >=  parent.nodeleft AND node.nodeleft <= parent.noderight)
			AND parent.nodeid <> node.nodeid
		WHERE node.nodeid = $nodeid ORDER BY node.nodeleft DESC");
		$permissionsfrom = 1;

		while($record = vB::$vbulletin->db->fetch_array($rst))
		{
			$parents[] = $record;

			if (intval($record['permissionsfrom']))
			{
				$permissionsfrom = $record['permissionsfrom'];
				break;
			}
		}
		//Now we go back down the list. Assign the node to the children at each level;
		foreach ($parents as $parent)
		{
			vB::$vbulletin->db->query_write("UPDATE ". TABLE_PREFIX . "cms_node SET permissionsfrom = $permissionsfrom
				WHERE permissionsfrom IS NULL AND parentnode = " . $parent['nodeid']);
		}

	}


	/****
	* This determines if the user can view a node
	*
	* @param int
	*
	* @return boolean
	* ****/
 	public static function canView($nodeid)
	{
 		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
 		{
 			self::getUserPerms();
 		}

 		if (array_key_exists ($nodeid, self::$permissionsfrom))
 		{
 			$permfrom = self::$permissionsfrom[$nodeid];
 		}
 		else
 		{
 			if (!$permfrom = self::getPermissionsFrom($nodeid))
 			{
 				return false;
 			}
 		}

		// No one can bypass the main canview permission
		if (!in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canview']))
		{
			return false;
		}

		/*
		// Having Edit doesn't infer View, admin decides this
 		if (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canedit']))
 		{
 			return true;
 		}
		*/

		$viewown = (vB::$vbulletin->userinfo['userid'] AND $permfrom['userid'] == vB::$vbulletin->userinfo['userid']);
		if (!$viewown AND !in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canpublish']) AND (!$permfrom['setpublish'] OR $permfrom['publishdate'] > TIMENOW))
		{
			return false;
		}

		if (intval($permfrom['hidden']) AND !in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canpublish']))
		{
			return false;
		}
		else
		{
			return true;
		}
	}
 	/****
 	 * This determines if the user can view a node
 	 *
 	 * @param int
 	 *
 	 * @return boolean
 	 * ****/
 	public static function canDownload($nodeid)
 	{
 		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
 		{
 			self::getUserPerms();
 		}

 		if (array_key_exists ($nodeid, self::$permissionsfrom))
 		{
 			$permfrom = self::$permissionsfrom[$nodeid];
 		}
 		else
 		{
 			if (!$permfrom = self::getPermissionsFrom($nodeid))
 			{
 				return false;
 			}
 		}

 		if ($permfrom['userid'] == vB::$vbulletin->userinfo['userid'])
 		{
 			return true;
 		}

 		if (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canedit'])
 			OR in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canpublish'])
 			)
 		{
 			return true;
 		}

 		return (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['candownload'])
 			AND intval($permfrom['setpublish']) AND ($permfrom['publishdate'] < TIMENOW));
 	}


 	/*** In some cases we have a node, then in a few lines need to get the permissions from. If we have
 	* a value, let's keep it***/
 	public static function setPermissionsfrom($nodeid, $permissionsfrom, $hidden = 0,
 		$setpublish = false, $publishdate = false, $userid = false)
 	{
 		if (intval($permissionsfrom) AND intval($nodeid) AND intval($userid) )
 		{
 			self::$permissionsfrom[$nodeid] = array('permissionsfrom' => intval($permissionsfrom),
 				'hidden' => intval($hidden), 'setpublish' =>$setpublish,
 				'publishdate' => $publishdate, 'userid' => $userid);
 		}
 	}

 	/*** If we have a bunch of nodes for which we need to check permissions, let's load
 	* the permissions in one query.
 	*
 	* @param	array	list of nodeids
 	***/
 	public static function loadPermissionsfrom($nodes)
 	{
 		if (empty($nodes))
 		{
 			return;
 		}

		foreach($nodes as $key =>$nodeid)
 		{
 			if (array_key_exists($nodeid, self::$permissionsfrom))
 			{
 				unset($nodes[$key]);
 			}
 		}

 		if (empty($nodes))
 		{
 			return;
 		}
 		$sql = "SELECT nodeid, permissionsfrom, hidden, setpublish, publishdate, userid FROM " .
 			TABLE_PREFIX . "cms_node where nodeid in (" . implode(array_unique($nodes), ',') .
 			")" ;
		$qry_permissions = vB::$db->query_read($sql);

 		if ($qry_permissions)
 		{
 			while($permission = vB::$db->fetch_array($qry_permissions))
 			{
 				self::setPermissionsfrom($permission['nodeid'], $permission['permissionsfrom'],
 					$permission['hidden'], $permission['setpublish'] , $permission['publishdate'],
 					$permission['userid']);
 			}

 		}
 	}


 	/** This function tells whether we can create a content node.
 	 * The rules are: if we have publish rights we can create any type of content
 	 * If we have create or edit we can create non-section types.
 	 * @param int
 	 *
 	 * @return boolean
 	 ***/
 	public static function canEdit($nodeid)
	{
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
		{
			self::getUserPerms();
		}
			if (array_key_exists ($nodeid, self::$permissionsfrom))
		{
			$permfrom = self::$permissionsfrom[$nodeid];
		}
		else
		{
			if (!$permfrom = self::getPermissionsFrom($nodeid))
			{
				return false;
			}
		}

		if ($permfrom['userid'] == vB::$vbulletin->userinfo['userid'])
		{
			return true;
		}

		if (intval($permfrom['hidden']))
		{
			return (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canpublish']));
		}

		return (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canedit']));
	}

 	/**** This gives us a string suitable for using in a "where" clause that
 	* limits results from the node table to those records this user can see
 	* That means either: They have canedit or canpublish and edit_view is allowed
	* or it's theirs, or they have canview and it's published
	* @param int : userid, false = current user.
	* @param boolean : Use cached result, generally override this if using the setting below
	* @param boolean : Allow viewing if they have canedit or canpublish, regardless of canview.
	*
	* @return string
	* ***/
 	public static function getPermissionString($userid = false, $cache = true, $edit_view = false)
 	{
 		if (($userid === false) AND ($userid !== 0))
 		{
 			$userid = vB::$vbulletin->userinfo['userid'];
 		}

 		if (($userid == vB::$vbulletin->userinfo['userid']) AND self::$permission_string AND $cache)
 		{
 			return self::$permission_string;
 		}

 		$can_view = array();
 		$blocked = array();
 		$perms = self::getPerms($userid);

 		//We need to block out unpublished sections.
		$sections = vBCms_ContentManager::getSections();

		foreach($sections as $section)
		{
			$can_view_this = (intval($section['setpublish']) > 0) AND ($section['publishdate'] < TIMENOW);

			if (!$edit_view)
			{
				$can_view_this = ($can_view_this AND in_array($section['permissionsfrom'],$perms['canview']));
			}

 			if (!$can_view_this)
 			{
				$blocked[$section['nodeid']] = 1;
				if (isset($can_view[$section['nodeid']]))
				{
					unset($can_view[$section['nodeid']]);
				}
			}
			else if (!isset($can_view[$section['nodeid']]) AND !isset($blocked[$section['nodeid']]))
			{
				$can_view[$section['nodeid']] = $section['nodeid'];
			}
		}

 		if (empty($can_view))
 		{
			$can_view[] = -1;
 		}

 		$can_edit = array_unique(array_merge($perms['canedit'], $perms['canpublish']));

		if (!$edit_view)
		{
			$can_edit = array_intersect($can_edit, $perms['canview']);
		}

		if (empty($can_edit))
		{
			$can_edit[] = -1;
		}

 		self::$permission_string = "( (node.permissionsfrom IN (" . implode(',', $can_edit) . "))";

 		if (intval($userid))
 		{
 			self::$permission_string .= " OR (node.userid =" . vB::$vbulletin->userinfo['userid'] . ") ";
 		}

 		if (!empty($can_view))
 		{
 			self::$permission_string .= " OR ( node.permissionsfrom in (" .
				implode(',', $perms['canview']) . ") AND (node.parentnode IN (" .
				implode(',', $can_view) . ")" .
				(isset($can_view[1]) ? " OR node.nodeid = 1" : "") . ") AND
				node.setpublish > 0 AND node.publishdate < " . TIMENOW . " )";
 		}

 		self::$permission_string .= ")";

 		return self::$permission_string;
 	}

 	/** This function tells whether we can create a content node.
 	* The rules are: if we have publish rights we can create any type of content
 	* If we have create or edit we can create non-section types.
 	*
 	* @param int
 	* @param int
 	*
 	* @return boolean
 	***/
 	public static function canCreate($nodeid, $contenttype)
 	{

 		if (array_key_exists ($nodeid, self::$permissionsfrom))
 		{
 			$permfrom = self::$permissionsfrom[$nodeid];
 		}
 		else
 		{
 			if (!$permfrom = self::getPermissionsFrom($nodeid))
 			{
 				return false;
 			}
 		}

 		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']) )
 		{
 			self::getUserPerms();
 		}

		if (intval($permfrom['hidden']))
		{
			return (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canpublish']));
		}

		return (in_array($permfrom['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['cancreate']));


 		return false;
 	}

 	/** This function tells whether this user can create a content node.
 	 * The rules are: if we have publish rights we can create any type of content
 	 * If we have create or edit we can create non-section types.
 	 * @param int
 	 * @param int
 	 * @param int
 	 *
 	 * @return boolean
 	 ***/
 	public static function canUseHtml($nodeid, $contenttype, $userid)
 	{
 		if (! intval($userid))
 		{
 			return false;
 		}

		if (array_key_exists ($nodeid, self::$permissionsfrom))
 		{
 			$permfrom = self::$permissionsfrom[$nodeid];
 		}
 		else
 		{
 			if (!$permfrom = self::getPermissionsFrom($nodeid))
 			{
 				return false;
 			}
 		}

 		$perms = self::getPerms($userid);
 		$result = in_array($permfrom['permissionsfrom'], $perms['canusehtml']) ?  1 : 0;

 		return $result;
 	}

	/********* Get a hash so we can cache the data
	 * @param int
	 *
	 *@return string
	 ********/
	protected static function getHash($userid = null)
	{
		if ($userid == null)
		{
			$userid = vB::$vbulletin->userinfo['userid'];
		}
		/* Since its purely userid based, its visually 
		   easier to track cache entries with this key. */
		$context = "cms_priv_user_$userid"; 
		return strval($context);

	}
 }
