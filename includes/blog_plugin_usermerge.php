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

require_once(DIR . '/includes/blog_functions.php');

$blogs = array();

define('VBBLOG_PERMS', true);
unset($usercache["$sourceinfo[userid]"], $usercache["$destinfo[userid]"]);
$sourceinfo = fetch_userinfo($sourceinfo['userid']);
$destinfo = fetch_userinfo($destinfo['userid']);

if ($sourceinfo['bloguserid'])
{
	// ###################### Subscribed Blogs #######################
	// Update Subscribed Blogs - Move source's blogs to dest, skipping any that both have
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeuser AS su1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su2 ON (su2.bloguserid = su1.bloguserid AND su2.userid = $destinfo[userid])
		SET su1.userid = $destinfo[userid]
		WHERE su1.userid = $sourceinfo[userid]
			AND su1.bloguserid <> $destinfo[userid]
			AND su2.blogsubscribeuserid IS NULL
	");

	// Update Subscribed Blogs - Update everyone else who was subscribed to source to be subscribed to dest
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeuser AS su1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su2 ON (su2.bloguserid = $destinfo[userid] AND su2.userid = su1.userid)
		SET su1.bloguserid = $destinfo[userid]
		WHERE su1.bloguserid = $sourceinfo[userid]
			AND su1.userid <> $destinfo[userid]
			AND su2.blogsubscribeuserid IS NULL
	");

	// Update Subscribed Blogs - Remove the blogs that source and dest both have - hit index
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
		WHERE bloguserid = $sourceinfo[userid]
	");

	// Update Subscribed Blogs - Remove the blogs that source and dest both have - hit index
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Subscribed Entries #######################
	// Update Subscribed Entries - Move source's entries to dest, skipping any that both have
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeentry AS se1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeentry AS se2 ON (se1.blogid = se2.blogid AND se2.userid = $destinfo[userid])
		SET se1.userid = $destinfo[userid]
		WHERE se1.userid = $sourceinfo[userid]
			AND se2.blogsubscribeentryid IS NULL
	");

	// Update Subscribed Entries - Remove the entries that source and dest both have
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Comments #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET bloguserid = $destinfo[userid]
		WHERE bloguserid = $sourceinfo[userid]
	");

	// ###################### Entries #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Groups ##########################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog
		SET postedby_userid = $destinfo[userid],
			postedby_username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE postedby_userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "blog_groupmembership
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "blog_groupmembership
		SET bloguserid = $destinfo[userid]
		WHERE bloguserid = $sourceinfo[userid]
	");

	// make sure that we didn't just join our own blog
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_groupmembership
		WHERE
			userid = $destinfo[userid]
				AND
			bloguserid = $destinfo[userid]
	");

	// ###################### Deletion Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_deletionlog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Deletion Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_editlog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Entry Ratings #######################
	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "blog_rate SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
	$blogratings = $db->query_read("SELECT blogid FROM " . TABLE_PREFIX . "blog_rate WHERE userid = $sourceinfo[userid]");
	while ($blograting = $db->fetch_array($blogratings))
	{
		$blogs["$blograting[blogid]"] = true;
	}
	if (!empty($blogs))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_rate WHERE userid = $sourceinfo[userid]");
	}

	// ###################### Read Blogs #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_read AS br1
		LEFT JOIN " . TABLE_PREFIX . "blog_read AS br2 ON (br2.userid = $destinfo[userid] AND br2.blogid = br1.blogid)
		SET br1.userid = $destinfo[userid]
		WHERE br1.userid = $sourceinfo[userid]
			AND br2.userid IS NULL
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_userread AS bu1
		LEFT JOIN " . TABLE_PREFIX . "blog_userread AS bu2 ON (bu2.userid = $destinfo[userid] AND bu2.bloguserid = bu2.bloguserid)
		SET bu1.userid = $destinfo[userid]
		WHERE bu1.userid = $sourceinfo[userid]
			AND bu2.userid IS NULL
	");
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_read
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_userread
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Blog Moderator #######################
	$destmod = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $destinfo[userid]");
	$sourcemod = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $sourceinfo[userid]");

	if ($destmod)
	{
		if ($sourcemod)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_moderator
				SET permissions = permissions | $sourceinfo[permissions]
				WHERE userid = $destinfo[userid]
			");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $sourceinfo[userid]");
		}
	}
	else if ($sourcemod)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_moderator
			SET userid = $destinfo[userid]
			WHERE userid = $sourceinfo[userid]
		");
	}

	// ###################### Tachy Entry #######################
	if (!defined('MYSQL_VERSION'))
	{
		$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
		define('MYSQL_VERSION', $mysqlversion['version']);
	}

	$db->query_write("
		DELETE te2
		FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
		WHERE te1.userid = $sourceinfo[userid] AND te1.lastcomment > te2.lastcomment AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid]
	");
	$db->query_write("
		DELETE te1
		FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
		WHERE te1.userid = $sourceinfo[userid] AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid] AND te1.lastcomment <= te2.lastcomment
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_tachyentry
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Trackbacks #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_trackback
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Trackback Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_trackbacklog
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Search #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_search
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Categories #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_category
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_categoryuser
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ##################### Custom Blocks ##########################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_custom_block
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	if ($sourceinfo['customblocks'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET customblocks = customblocks + " . $sourceinfo['customblocks'] . "
			WHERE userid = $destinfo[userid]
		");
	}

	// ##################### User CSS ##########################
	// If source user dest user hasn't customized then copy over source user (which may or may not exist)
	if (!$db->query_first_slave("
		SELECT userid
		FROM " . TABLE_PREFIX . "blog_usercss
		WHERE userid = $destinfo[userid]
	"))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_usercss
			SET userid = $destinfo[userid]
			WHERE userid = $sourceinfo[userid]
		");
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_usercsscache
			SET userid = $destinfo[userid]
			WHERE userid = $sourceinfo[userid]
		");
	}
	else
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_usercss
			WHERE userid = $sourceinfo[userid]
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_usercsscache
			WHERE userid = $sourceinfo[userid]
		");
	}

	if ($destinfo['bloguserid'])
	{
		$userdm =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_STANDARD);
		$userdm->set_existing($destinfo);
		$userdm->set('entries', "entries + $sourceinfo[entries]", false);
		if ($sourceinfo['blog_akismet_key'] AND !$destinfo['blog_akismet_key'])
		{
			$userdm->set('akismet_key', $sourceinfo['akismet_key'], false);
		}
		if ($sourceinfo['isblogmoderator'] AND !$destinfo['isblogmoderator'])
		{
			$userdm->set('isblogmoderator', 1, false);
		}
		//Empty tag cloud
		$userdm->set('tagcloud', array());
		$userdm->save();
		unset($userdm);
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_user
			SET bloguserid = $destinfo[userid]
			WHERE bloguserid = $sourceinfo[userid]
		");
	}

	// Update required blog entries
	foreach (array_keys($blogs) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	// Update counters for destination user
	build_blog_user_counters($destinfo['userid']);
	build_blog_memberblogids($destinfo['userid']);
	build_blog_memberids($destinfo['userid']);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>
