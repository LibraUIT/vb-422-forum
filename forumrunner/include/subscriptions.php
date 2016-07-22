<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

chdir(MCWD);

chdir('../');

define('THIS_SCRIPT', 'forumrunner');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function
do_get_subscriptions ()
{
    global $vbulletin, $db, $show, $vbphrase, $permissions, $subscribecounters;

    $vbulletin->options['threadpreview'] = FR_PREVIEW_LEN;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_NO_PERMISSION);
    }

    if ((!$vbulletin->userinfo['userid'] AND $_REQUEST['do'] != 'removesubscription')
	OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	OR $vbulletin->userinfo['usergroupid'] == 4
	OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
	json_error(ERR_NO_PERMISSION);
    }
    
    $thread_data = array();
    $unread_subs = 0;

    // vbulletin expects folderid, but we will just get them all
    $vbulletin->input->clean_array_gpc('r', array(
	'folderid'   => TYPE_NOHTML,
	'perpage'    => TYPE_UINT,
	'pagenumber' => TYPE_UINT,
	'sortfield'  => TYPE_NOHTML,
	'sortorder'  => TYPE_NOHTML,
	'previewtype' => TYPE_INT,
    ));
    
    $previewtype = $vbulletin->GPC['previewtype'];
    if (!$previewtype) {
	$previewtype = 1;
    }
    
    $vbulletin->GPC['folderid'] = 'all';

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$folderid   =& $vbulletin->GPC['folderid'];

	if ($folderid == 'all')
	{
		$getallfolders = true;
		$show['allfolders'] = true;
	}
	else
	{
		$folderid = intval($folderid);
	}

	$folderselect["$folderid"] = 'selected="selected"';

	// Build folder jump
	require_once(DIR . '/includes/functions_misc.php');
	$folders = construct_folder_jump(1, $folderid, false, '', true);

	$templater = vB_Template::create('subscribe_folder_jump');
		$templater->register('folders', $folders);
	$folderjump = $templater->render();

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'title':
		case 'lastpost':
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = 'thread.' . $sortfield;
			break;
		default:
			$handled = false;
			if (!$handled)
			{
				$sqlsortfield = 'thread.lastpost';
				$sortfield = 'lastpost';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	if ($getallfolders)
	{
		$totalallthreads = array_sum($subscribecounters);
	}
	else
	{
		$totalallthreads = $subscribecounters["$folderid"];
	}

	// set defaults
	sanitize_pageresults($totalallthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

	// display threads
	$limitlower = ($pagenumber - 1) * $perpage + 1;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalallthreads)
	{
		$limitupper = $totalallthreads;
		if ($limitlower > $totalallthreads)
		{
			$limitlower = $totalallthreads - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';

	$getthreads = $db->query_read_slave("
		SELECT thread.threadid, emailupdate, subscribethreadid, thread.forumid, thread.postuserid
			$hook_query_fields
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		$hook_query_joins
		WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
			AND thread.visible = 1
			AND canview = 1
		" . iif(!$getallfolders, "	AND folderid = $folderid") . "
			$hook_query_where
		ORDER BY $sqlsortfield $sqlsortorder
		LIMIT " . ($limitlower - 1) . ", $perpage
	");

	if ($totalthreads = $db->num_rows($getthreads))
	{
		$forumids = array();
		$threadids = array();
		$emailupdate = array();
		$killthreads = array();
		while ($getthread = $db->fetch_array($getthreads))
		{
			$forumperms = fetch_permissions($getthread['forumid']);

			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
			{
				$killthreads["$getthread[subscribethreadid]"] = $getthread['subscribethreadid'];
				$totalallthreads--;
				continue;
			}
			$forumids["$getthread[forumid]"] = true;
			$threadids[] = $getthread['threadid'];
			$emailupdate["$getthread[threadid]"] = $getthread['emailupdate'];
			$subscribethread["$getthread[threadid]"] = $getthread['subscribethreadid'];
		}
		$threadids = implode(',', $threadids);
	}
	unset($getthread);
	$db->free_result($getthreads);

	if (!empty($killthreads))
	{  // Update thread subscriptions
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "subscribethread
			SET canview = 0
			WHERE subscribethreadid IN (" . implode(', ', $killthreads) . ")
		");
	}

	if (!empty($threadids))
	{
		cache_ordered_forums(1);
		$colspan = 5;
		$show['threadicons'] = false;

		// get last read info for each thread
		$lastread = array();
		foreach (array_keys($forumids) AS $forumid)
		{
			if ($vbulletin->options['threadmarking'])
			{
				$lastread["$forumid"] = max($vbulletin->forumcache["$forumid"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
			}
			else
			{
				$lastread["$forumid"] = max(intval(fetch_bbarray_cookie('forum_view', $forumid)), $vbulletin->userinfo['lastvisit']);
			}
			if ($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
			{
				$show['threadicons'] = true;
				$colspan = 6;
			}
		}

		if ($previewtype == 1) {
		    $previewfield = "post.pagetext AS preview, post.username AS lastpost_username, post.userid AS lastpost_userid,";
		    $previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		} else {
		    $previewfield = "post.pagetext AS preview, post.username AS lastpost_username, post.userid AS lastpost_userid,";
		    $previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.lastpostid)";
		}

		$hasthreads = true;
		$threadbits = '';
		$pagenav = '';
		$counter = 0;
		$toread = 0;

		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
				"IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
				"IF(tachythreadpost.userid IS NULL, thread.lastposterid, tachythreadpost.lastposterid) AS lastposterid, " .
				"IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";

			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
		}
		else
		{
			$lastpost_info = 'thread.lastpost, thread.lastposter, thread.lastposterid, thread.lastpostid';
			$tachyjoin = '';
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';

		$threads = $db->query_read_slave("
			SELECT
				IF(thread.votenum >= " . $vbulletin->options['showvotes'] . ", thread.votenum, 0) AS votenum,
				IF(thread.votenum >= " . $vbulletin->options['showvotes'] . " AND thread.votenum > 0, thread.votetotal / thread.votenum, 0) AS voteavg,
				thread.votetotal,
				$previewfield thread.threadid, thread.title AS threadtitle, thread.forumid, thread.pollid,
				thread.open, thread.replycount, thread.postusername, thread.prefixid,
				$lastpost_info, thread.postuserid, thread.dateline, thread.views, thread.iconid AS threadiconid,
				thread.notes, thread.visible, thread.attach, thread.taglist
				" . ($vbulletin->options['threadmarking'] ? ", threadread.readtime AS threadread" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			$previewjoin
			" . ($vbulletin->options['threadmarking'] ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
			$tachyjoin
			$hook_query_joins
			WHERE thread.threadid IN ($threadids)
			ORDER BY $sqlsortfield $sqlsortorder
		");
		unset($sqlsortfield, $sqlsortorder);

		require_once(DIR . '/includes/functions_forumdisplay.php');

		// Get Dot Threads
		$dotthreads = fetch_dot_threads_array($threadids);
		if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
		{
			$show['dotthreads'] = true;
		}
		else
		{
			$show['dotthreads'] = false;
		}

		if ($vbulletin->options['threadpreview'] AND $vbulletin->userinfo['ignorelist'])
		{
			// Get Buddy List
			$buddy = array();
			if (trim($vbulletin->userinfo['buddylist']))
			{
				$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
					foreach ($buddylist AS $buddyuserid)
				{
					$buddy["$buddyuserid"] = 1;
				}
			}
			DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
			// Get Ignore Users
			$ignore = array();
			if (trim($vbulletin->userinfo['ignorelist']))
			{
				$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($ignorelist AS $ignoreuserid)
				{
					if (!$buddy["$ignoreuserid"])
					{
						$ignore["$ignoreuserid"] = 1;
					}
				}
			}
			DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
		}

		$foruminfo['allowratings'] = true;
		$show['notificationtype'] = true;
		$show['threadratings'] = true;
		$show['threadrating'] = true;

		while ($thread = $db->fetch_array($threads))
		{
			$threadid = $thread['threadid'];
			// build thread data
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

			switch ($emailupdate["$thread[threadid]"])
			{
				case 0:
					$thread['notification'] = $vbphrase['none'];
					break;
				case 1:
					$thread['notification'] = $vbphrase['instant'];
					break;
				case 2:
					$thread['notification'] = $vbphrase['daily'];
					break;
				case 3:
					$thread['notification'] = $vbphrase['weekly'];
					break;
				default:
					$thread['notification'] = $vbphrase['n_a'];
			}

			$avatarurl = '';
			if ($thread['lastpost_userid'] > 0) {
			    $userinfoavatar = fetch_userinfo($thread['lastpost_userid'], FETCH_USERINFO_AVATAR);
			    fetch_avatar_from_userinfo($userinfoavatar, true, false);
			    if ($userinfoavatar['avatarurl'] != '') {
				$avatarurl = process_avatarurl($userinfoavatar['avatarurl']);
			    }
			    unset($userinfoavatar);
			}

			$tmp = array(
			    'thread_id' => $thread['threadid'],
			    'new_posts' => $show['gotonewpost'] ? true : false,
			    'forum_id' => $thread['forumid'],
			    'total_posts' => $thread['totalposts'] ? $thread['totalposts'] : 0,
			    'forum_title' => prepare_utf8_string($thread['forumtitle']),
			    'thread_title' => prepare_utf8_string($thread['threadtitle']),
			    'thread_preview' => prepare_utf8_string(preview_chop(html_entity_decode($thread['preview']), FR_PREVIEW_LEN)),
			    'post_userid' => $thread['lastpost_userid'],
			    'post_lastposttime' => prepare_utf8_string(date_trunc($thread['lastpostdate']) . ' ' . $thread['lastposttime']),
			    'post_username' => prepare_utf8_string(strip_tags($thread['lastpost_username'])),
			);
			if ($avatarurl != '') {
			    $tmp['avatarurl'] = $avatarurl;
			}
			if ($thread['attach']) {
			    $tmp['attach'] = true;
			}
			if ($thread['pollid']) {
			    $tmp['poll'] = true;
			}
			$thread_data[] = $tmp;
		}

		$db->free_result($threads);
		unset($threadids);
	}
	else
	{
		$totalallthreads = 0;
	}

	$out = array(
	    'threads' => $thread_data,
	    'total_threads' => $totalallthreads,
	);

	return $out;
}

function
do_unsubscribe_thread ()
{
    global $vbulletin, $db;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

    if (!$vbulletin->GPC['threadid']) {
	json_error(ERR_INVALID_SUB);
    }

    $db->query_write("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = " . $vbulletin->GPC['threadid'] . " AND userid = " . $vbulletin->userinfo['userid']);

    return array(
	'success' => true,
    );
}

function
do_subscribe_thread ()
{
    global $vbulletin, $db, $foruminfo, $threadinfo;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

	$vbulletin->input->clean_array_gpc('r', array(
		'emailupdate' => TYPE_UINT,
		'folderid'    => TYPE_INT
	));

	$vbulletin->GPC['folderid'] = 0;

	if (!$foruminfo['forumid'])
	{
	    json_error(ERR_INVALID_THREAD);
	}

	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
	    json_error(ERR_INVALID_THREAD);
	}

	if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
	{
	    json_error(ERR_CANNOT_SUB_FORUM_CLOSED);
	}

	// check if there is a forum password and if so, ensure the user has it set
	if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false)) {
	    json_error(ERR_CANNOT_SUB_PASSWORD);
	}

	if ($threadinfo['threadid'])
	{
		if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')))
		{
		    json_error(ERR_INVALID_THREAD);
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
		    json_error(ERR_INVALID_THREAD);
		}

		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
			VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], " . $vbulletin->GPC['emailupdate'] . ", " . $vbulletin->GPC['folderid'] . ", 1)
		");
	}
	else if ($foruminfo['forumid'])
	{
		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "subscribeforum (userid, emailupdate, forumid)
			VALUES (" . $vbulletin->userinfo['userid'] . ", " . $vbulletin->GPC['emailupdate'] . ", " . $vbulletin->GPC['forumid'] . ")
		");
	}

    return array(
	'success' => true,
    );
}

?>
