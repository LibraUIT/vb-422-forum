<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Determine moderator ability
*
* @param string		Permissions
* @param interger	Userid
* @param	string	Comma separated list of usergroups to which the user belongs
*
* @return	boolean
*/
function can_moderate_blog($do = '', $userinfo = null)
{
	global $vbulletin;

	$issupermod = false;
	$superpermissions = 0;

	if ($userinfo === null)
	{
		$modinfo =& $vbulletin->userinfo;
	}
	else
	{
		$modinfo =& $userinfo;
	}

	if ($modinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
	{
		DEVDEBUG('  USER IS A SUPER MODERATOR');
		$issupermod = true;
	}

	if (empty($do))
	{
		if ($issupermod)
		{
			return true;
		}
		else if (isset($modinfo['isblogmoderator']))
		{
			if ($modinfo['isblogmoderator'])
			{
				DEVDEBUG('	USER HAS ISBLOGMODERATOR SET');
				return true;
			}
			else
			{
				DEVDEBUG('	USER DOES NOT HAVE ISBLOGMODERATOR SET');
				return false;
			}
		}
	}

	cache_blog_moderators();
	$permissions = intval($vbulletin->vbblog['modcache']["$modinfo[userid]"]['normal']['permissions']);
	if ($issupermod)
	{
		if (isset($vbulletin->vbblog['modcache']["$modinfo[userid]"]['super']))
		{
			$permissions |= $vbulletin->vbblog['modcache']["$modinfo[userid]"]['super']['permissions'];
		}
		else
		{
			$permissions |= array_sum($vbulletin->bf_misc_vbblogmoderatorpermissions);
		}
	}

	if (empty($do) AND $permissions)
	{
		return true;
	}
	else if ($permissions & $vbulletin->bf_misc_vbblogmoderatorpermissions["$do"])
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
* Cache blog moderators into $vbulletin->blog
*
* @return	void
*/
function cache_blog_moderators()
{
	global $vbulletin;

	if (!is_array($vbulletin->vbblog['modcache']))
	{
		$vbulletin->vbblog['modcache'] = array();
		$blogmoderators = $vbulletin->db->query_read_slave("
			SELECT bm.userid, bm.permissions, bm.type, user.username
			FROM " . TABLE_PREFIX . "blog_moderator AS bm
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		");
		while ($moderator = $vbulletin->db->fetch_array($blogmoderators))
		{
			$vbulletin->vbblog['modcache']["$moderator[userid]"]["$moderator[type]"] = $moderator;
		}
	}
}

/**
* Prepares the blog category permissions for a user, taking into account primary and
* secondary groups.
*
* @param	array	(In/Out) User information
*
* @return	array	Category permissions (also in $user['blogcategorypermissions'])
*/
function prepare_blog_category_permissions(&$user, $loadcache = false)
{
	global $vbulletin;

	$membergroupids = fetch_membergroupids_array($user);

	if (sizeof($membergroupids) == 1 OR !($vbulletin->usergroupcache["$user[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['allowmembergroups']))
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($user['usergroupid']);
	}

	$user['blogcategorypermissions'] = array(
		'cantview' => array(),
		'cantpost' => array(),
	);

	if ($vbulletin->blogcategorycache === NULL AND $loadcache)
	{
		// Load the cache
		$vbulletin->datastore->fetch(array('blogcategorycache'));
		if ($vbulletin->blogcategorycache === NULL)
		{
			$vbulletin->blogcategorycache = array();
		}
	}

	if (is_array($vbulletin->blogcategorycache))
	{
		foreach (array_keys($vbulletin->blogcategorycache) AS $blogcategoryid)
		{
			if (!isset($user['blogcategorypermissions']["$blogcategoryid"]))
			{
				$user['blogcategorypermissions']["$blogcategoryid"] = 0;
			}
			foreach ($membergroupids AS $usergroupid)
			{
				$user['blogcategorypermissions']["$blogcategoryid"] |= $vbulletin->blogcategorycache["$blogcategoryid"]['permissions']["$usergroupid"];
			}
			foreach (explode(',', str_replace(' ', '', $user['infractiongroupids'])) AS $usergroupid)
			{
				if ($usergroupid)
				{
					$user['blogcategorypermissions']["$blogcategoryid"] &= $vbulletin->blogcategorycache["$blogcategoryid"]['permissions']["$usergroupid"];
				}
			}
			if (!($user['blogcategorypermissions']["$blogcategoryid"] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewcategory']))
			{
				$user['blogcategorypermissions']['cantview'][] = $blogcategoryid;
			}
			if (!($user['blogcategorypermissions']["$blogcategoryid"] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canpostcategory']))
			{
				$user['blogcategorypermissions']['cantpost'][] = $blogcategoryid;
			}
		}
	}

	return $user['blogcategorypermissions'];
}

/**
* Fetches the latest entry that the viewing user has permission to view
*
* @param	array	(In/Out) User information
*
* @return	array	Latest entry information
*/
function fetch_latest_entry(&$user)
{
	global $vbulletin;

	$joinsql = array();
	$wheresql = array();
	if (!empty($user['blogcategorypermissions']['cantview']))
	{
		$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $user['blogcategorypermissions']['cantview']) . "))";
		if ($user['userid'])
		{
			$wheresql[] = "(cu.blogcategoryid IS NULL OR blog.userid = " . $user['userid'] . ")";
		}
		else
		{
			$wheresql[] = "cu.blogcategoryid IS NULL";
		}
	}

	if ($vbulletin->userinfo['userid'] == $user['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		$wheresql[] = "blog.userid <> $user[userid]";
	}

	require_once(DIR . '/includes/functions_bigthree.php');
	if ($coventry = fetch_coventry('string'))
	{
		$wheresql[] = "blog.userid NOT IN ($coventry)";
	}

	$latestentry = $vbulletin->db->query_first_slave("
		SELECT user.username, blog.userid, blog.title, blog.blogid, blog.postedby_userid, blog.postedby_username, bu.title AS blogtitle
		FROM " . TABLE_PREFIX . "blog AS blog
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (blog.userid = bu.bloguserid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
		" . (!empty($joinsql) ? implode("\r\n", $joinsql) : "") . "
		WHERE
			state = 'visible' AND
			dateline <= " . TIMENOW . " AND
			blog.pending = 0 AND
			~blog.options & " . $vbulletin->bf_misc_vbblogoptions['private'] . " AND
			bu.options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND
			bu.options_member & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . "
			" . (!empty($wheresql) ? " AND " . implode(" AND ", $wheresql) : "") . "
		ORDER BY dateline DESC
		LIMIT 1
	");

	return $latestentry;
}

/**
* Verify that user is member of this blog
*
* @param	int		Userinfo to verify
* @param	array	Bloginfo
*
* @return	bool
*/
function is_member_of_blog($userinfo, &$bloginfo)
{
	global $vbulletin;
	static $blogcache;

	if (!is_array($userinfo))
	{
		trigger_error('is_member_of_blog(): $userinfo not an array', E_USER_ERROR);
	}
	if (!is_array($bloginfo))
	{
		trigger_error('is_member_of_blog(): $bloginfo not an array', E_USER_ERROR);
	}
	if (empty($userinfo['permissions']))
	{
		trigger_error('is_member_of_blog(): $userinfo[\'permissions\'] not defined.', E_USER_ERROR);
	}
	if (empty($bloginfo['permissions']) AND $bloginfo['userid'])
	{
		trigger_error('is_member_of_blog(): $bloginfo[\'permissions\'] not defined.', E_USER_ERROR);
	}

	if (isset($blogcache["$bloginfo[userid]"]))
	{
		return $blogcache["$bloginfo[userid]"];
	}

	if ($userinfo['userid'] == $bloginfo['userid'])
	{
		$blogcache["$bloginfo[userid]"] = true;
	}
	else if (
		$bloginfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canhavegroupblog']
			AND
		$bloginfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']
			AND
		$bloginfo['memberids']
	)
	{
		$members = explode(',', str_replace(' ', '', $bloginfo['memberids']));
		if (in_array($userinfo['userid'], $members) AND $userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canjoingroupblog'])
		{
			$blogcache["$bloginfo[userid]"] = true;
		}
		else
		{
			$blogcache["$bloginfo[userid]"] = false;
		}
	}
	else
	{
		$blogcache["$bloginfo[userid]"] = false;
	}

	return $blogcache["$bloginfo[userid]"];
}

/**
* Fetch the user's ability to post a comment
*
* @param	array	$bloginfo from fetch_bloginfo or equivalent
* @param	array $userinfo from fetch_userinfo or equivalent
*
* @return	bool
*/
function fetch_can_comment($bloginfo, $userinfo)
{
	global $vbulletin;

	return (
			$bloginfo['cancommentmyblog']
			AND
			($bloginfo['allowcomments'] OR is_member_of_blog($userinfo, $bloginfo) OR can_moderate_blog('', $userinfo))
			AND
			(
				(($userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentown']) AND $bloginfo['userid'] == $userinfo['userid'])
				OR
				(($userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentothers']) AND $bloginfo['userid'] != $userinfo['userid'])
			)
			AND
			(
				(
					$bloginfo['state'] == 'moderation'
						AND
					(
						can_moderate_blog('canmoderateentries', $userinfo)
							OR
						(
							$userinfo['userid']
								AND
							$bloginfo['userid'] == $userinfo['userid']
								AND
							$bloginfo['postedby_userid'] != $userinfo['userid']
								AND
							$bloginfo['membermoderate']
						)
					)
				)
					OR
				$bloginfo['state'] == 'visible'
			)
			AND !$bloginfo['pending']
		);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 63620 $
|| ####################################################################
\*======================================================================*/