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
* Rebuild the pending blog group req count
*
* @param	int			Userid
*
*/
function build_blog_pending_count($userid)
{
	global $vbulletin;

	$pending = $vbulletin->db->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "blog_groupmembership
		WHERE userid = $userid
			AND state = 'pending'
	");

	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userinfo = array('userid' => $userid);
	$userdata->set_existing($userinfo);
	$userdata->set('bloggroupreqcount', $pending['count']);
	$userdata->save();
}

/**
* Fetches information about the selected custom block
*
* @param	integer	The custom block that we want info about
* @param	integer	Force block to be owned by userid
* @param	mixed		Should a permission check be performed as well
* @param	boolean	If we want to use a cached copy
*
* @return	array	Array of information about the blog or prints an error if it doesn't exist / permission problems
*/
function fetch_customblock_info($customblockid, $userid = 0, $alert = true, $usecache = true)
{
	global $vbulletin, $vbphrase;
	static $sidebarcache;

	$sqland = array(
		"customblockid = " . intval($customblockid),
	);
	if ($userid)
	{
		$sqland[] = "userid = $userid";
	}

	if (!isset($sidebarcache["$customblockid"]) OR !$usecache)
	{
		$sidebar = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "blog_custom_block
			WHERE
				" . implode(" AND ", $sqland) . "
		");

		if ($sidebar)
		{
			$sidebarcache["$customblockid"] = $sidebar;
		}
	}

	if (!$sidebarcache["$customblockid"])
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', 'customblock', $vbulletin->options['contactuslink']));
		}
		else
		{
			return array();
		}
	}

	($hook = vBulletinHook::fetch_hook('blog_fetch_customblockinfo')) ? eval($hook) : false;

	return $sidebarcache["$customblockid"];
}

/**
* Fetches information about the selected custompage with permission checks
*
* @param	integer	The custompage we want info about
* @param	string	The type of customblock that we are working with (page or block)
* @param	bool		Should an error be displayed when block is not found
* @param	bool		Should a permission check be performed as well
*
* @return	array	Array of information about the custom page or prints an error if it doesn't exist / permission problems
*/
function verify_blog_customblock($customblockid, $type = null, $alert = true, $perm_check = true)
{
	global $vbulletin, $vbphrase;

	if (!($blockinfo = fetch_customblock_info($customblockid)))
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['custom_block'], $vbulletin->options['contactuslink']));
		}
		else
		{
			return 0;
		}
	}
	else if ($type AND $blockinfo['type'] != $type)
	{
		standard_error(fetch_error('invalidid', $vbphrase['custom_block'], $vbulletin->options['contactuslink']));
	}

	$blockinfo['userinfo'] = verify_id('user', $blockinfo['userid'], 1, 1, 10);

	if ($perm_check)
	{
		if ($vbulletin->userinfo['userid'] != $blockinfo['userinfo']['userid'] AND empty($blockinfo['userinfo']['bloguserid']))
		{
			standard_error(fetch_error('blog_noblog', $blockinfo['userinfo']['username']));
		}

		if (!$blockinfo['userinfo']['canviewmyblog'])
		{
			print_no_permission();
		}
		if (in_coventry($blockinfo['userinfo']['userid']) AND !can_moderate_blog())
		{
			standard_error(fetch_error('invalidid', $vbphrase['custom_block'], $vbulletin->options['contactuslink']));
		}

		if ($vbulletin->userinfo['userid'] == $blockinfo['userinfo']['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{
			print_no_permission();
		}

		if ($vbulletin->userinfo['userid'] != $blockinfo['userinfo']['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			// Can't view other's entries so off you go to your own blog.
			exec_header_redirect(fetch_seo_url('blog', $vbulletin->userinfo));
		}
	}

	return $blockinfo;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 27303 $
|| ####################################################################
\*======================================================================*/
?>
