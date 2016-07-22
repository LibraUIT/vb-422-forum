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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

require_once(DIR . '/includes/blog_functions.php');

$blogman =& datamanager_init('Blog_Firstpost', $vbulletin, ERRTYPE_SILENT, 'blog');

$blogids = array();
$pendingposts = $vbulletin->db->query_read_slave("
	SELECT blog.*, blog_text.pagetext, blog_user.bloguserid
	FROM " . TABLE_PREFIX . "blog AS blog
	INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
	LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.firstblogtextid = blog_text.blogtextid)
	WHERE blog.pending = 1
		AND blog.dateline <= " . TIMENOW . "
");
while ($blog = $vbulletin->db->fetch_array($pendingposts))
{
	$blogman->set_existing($blog);

	// This sets bloguserid for the post_save_each_blogtext() function
	$blogman->set_info('user', $blog);
	$blogman->set_info('send_notification', true);
	$blogman->set_info('skip_build_category_counters', true);
	$blogman->save();

	if ($blog['state'] == 'visible')
	{
		$blogids[] = $blog['blogid'];
		$userids["$blog[userid]"] = $blog['userid'];
	}
}

if (!empty($blogids))
{
	// Update Counters
	foreach ($userids AS $userid)
	{
		build_blog_user_counters($userid);
	}
}

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>