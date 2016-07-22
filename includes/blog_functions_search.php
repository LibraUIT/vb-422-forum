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

/**
* Build Blog permission query for search
*
* @param	array	Userinfo array that must at least contain permissions
*
* @return	array	An array containing the 'joins' and 'where' conditions to enforce permissions correctly
*/
function build_blog_permissions_query($user)
{
	require_once DIR . '/includes/blog_functions.php';
	global $vbulletin;
	$permissions =& $user['permissions'];
	$joins = array();

	$state = array('visible');

	/* this is for the current user, do we expect this to come from another user? */
	if (can_moderate_blog('canmoderateentries'))
	{
		$state[] = 'moderation';
	}
	if (can_moderate_blog('candeleteentries'))
	{
		$state[] = 'deleted';
	}

	$wheresql = array(
		"blog.state IN ('" . implode("', '", $state) . "')",
		"blog.pending = 0",
		"blog.dateline <= " . TIMENOW,
	);
	if (!($permissions['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$wheresql[] = "blog.userid = $user[userid]";
	}

	if (!($permissions['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $user['userid'])
	{
		$wheresql[] = "blog.userid <> $user[userid]";
	}

	if (!can_moderate_blog())
	{
		$joins[] = "LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)";

		if ($user['userid'])
		{
			if (!$user['memberblogids'])
			{
				$mb = $vbulletin->db->query_first("
					SELECT
						memberblogids, memberids
					FROM " . TABLE_PREFIX . "blog_user
					WHERE
						bloguserid = $user[userid]
				");
				$user['memberblogids'] = $mb['memberblogids'] ? $mb['memberblogids'] : $user['userid'];
				$user['memberids'] = $mb ? $mb['memberids'] : $user['userid'];
			}

			$userlist_sql = array();
			$userlist_sql[] = "blog.userid IN (" . $user['memberblogids'] . ")";
			$userlist_sql[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_member & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
			$wheresql[] = "(" . implode(" OR ", $userlist_sql) . ")";

			$joins[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $user['userid'] . " AND buddy.type = 'buddy')";
			$joins[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $user['userid'] . " AND ignored.type = 'ignore')";

			$wheresql[] = "
				(blog.userid IN ($user[memberblogids])
					OR
				~blog.options & " . $vbulletin->bf_misc_vbblogoptions['private'] . "
					OR
				(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL))";
		}
		else
		{
			$wheresql[] = "options_guest & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$wheresql[] = "~blog.options & " . $vbulletin->bf_misc_vbblogoptions['private'];
		}
	}

	if (!empty($vbulletin->userinfo['blogcategorypermissions']['cantview']))
	{
		$joins[] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $vbulletin->userinfo['blogcategorypermissions']['cantview']) . "))";
		$wheresql[] = "cu.blogcategoryid IS NULL";
	}

	$return = array();
	$return['join'] = implode("\n", $joins);
	$return['where'] = implode("\nAND ", $wheresql);

	return $return;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 27303 $
|| ####################################################################
\*======================================================================*/