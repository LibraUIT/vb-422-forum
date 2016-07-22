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

function
fr_exec_shut_down ($closedb = false)
{
    global $vbulletin;

    if (!$closedb) {
	$vbulletin->db->unlock_tables();

	if (is_array($vbulletin->db->shutdownqueries))
	{
	    $vbulletin->db->hide_errors();
	    foreach($vbulletin->db->shutdownqueries AS $name => $query)
	    {
		if (!empty($query) AND ($name !== 'pmpopup' OR !defined('NOPMPOPUP')))
		{
		    $vbulletin->db->query_write($query);
		}
	    }
	    $vbulletin->db->show_errors();
	}
    } else {
	exec_mail_queue();
	if (defined('NOSHUTDOWNFUNC'))
	{
		$vbulletin->db->close();
	}
	$vbulletin->db->shutdownqueries = array();
    }
}

function
get_sub_thread_updates ()
{
    global $vbulletin, $db;

    $total = 0;

    if (!$vbulletin->options['threadmarking']) {
	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true)) {
	    $lastpost_info = ", IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastposts";
	    $tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
		"(tachythreadpost.threadid = subscribethread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	    $lastpost_having = "HAVING lastposts > " . $vbulletin->userinfo['lastvisit'];
	} else {
	    $lastpost_info = '';
	    $tachyjoin = '';
	    $lastpost_having = "AND lastpost > " . $vbulletin->userinfo['lastvisit'];
	}

	$getthreads = $db->query_read_slave("
	    SELECT thread.threadid, thread.forumid, thread.postuserid, subscribethread.subscribethreadid
	    $lastpost_info
	    FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
	    INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
	    $tachyjoin
	    WHERE subscribethread.threadid = thread.threadid
	    AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
	    AND thread.visible = 1
	    AND subscribethread.canview = 1
	    $lastpost_having
	");
    } else {
	$readtimeout = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true)) {
	    $lastpost_info = ", IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastposts";
	    $tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
		"(tachythreadpost.threadid = subscribethread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	} else {
	    $lastpost_info = ', thread.lastpost AS lastposts';
	    $tachyjoin = '';
	}

	$getthreads = $db->query_read_slave("
	    SELECT thread.threadid, thread.forumid, thread.postuserid,
	    IF(threadread.readtime IS NULL, $readtimeout, IF(threadread.readtime < $readtimeout, $readtimeout, threadread.readtime)) AS threadread,
	    IF(forumread.readtime IS NULL, $readtimeout, IF(forumread.readtime < $readtimeout, $readtimeout, forumread.readtime)) AS forumread,
	    subscribethread.subscribethreadid
	    $lastpost_info
	    FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
	    INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (subscribethread.threadid = thread.threadid)
	    LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
	    LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
	    $tachyjoin
	    WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
	    AND thread.visible = 1
	    AND subscribethread.canview = 1
	    HAVING lastposts > IF(threadread > forumread, threadread, forumread)
	");
    }

    if ($totalthreads = $db->num_rows($getthreads)) {
	$forumids = array();
	$threadids = array();
	$killthreads = array();
	while ($getthread = $db->fetch_array($getthreads)) {
	    $forumperms = fetch_permissions($getthread['forumid']);
	    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))) {
		$killthreads[] = $getthread['subscribethreadid'];
		continue;
	    }
	    $forumids["$getthread[forumid]"] = true;
	    $threadids[] = $getthread['threadid'];
	}
	$threadids = implode(',', $threadids);
    }
    unset($getthread);
    $db->free_result($getthreads);

    // if there are some results to show, query the data
    if (!empty($threadids)) {
	// get last read info for each thread
	$lastread = array();
	foreach (array_keys($forumids) AS $forumid) {
	    if ($vbulletin->options['threadmarking']) {
		$lastread["$forumid"] = max($vbulletin->forumcache["$forumid"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
	    } else {
		$lastread["$forumid"] = max(intval(fetch_bbarray_cookie('forum_view', $forumid)), $vbulletin->userinfo['lastvisit']);
	    }
	}

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true)) {
	    $lastpost_info = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
		"IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
		"IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";

	    $tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
		"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	} else {
	    $lastpost_info = 'thread.lastpost, thread.lastposter, thread.lastpostid';
	    $tachyjoin = '';
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';

	$getthreads = $db->query_read_slave("
	    SELECT $previewfield
	    thread.threadid, thread.title AS threadtitle, thread.forumid, thread.pollid,
	    thread.open, thread.replycount, thread.postusername, thread.postuserid,
	    thread.dateline, thread.views, thread.iconid AS threadiconid,
	    thread.notes, thread.visible,
	    $lastpost_info
	    " . ($vbulletin->options['threadmarking'] ? ", threadread.readtime AS threadread" : '') . "
	    $hook_query_fields
	    FROM " . TABLE_PREFIX . "thread AS thread
	    " . ($vbulletin->options['threadmarking'] ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
	    $previewjoin
	    $tachyjoin
	    $hook_query_joins
	    WHERE thread.threadid IN($threadids)
	    $hook_query_where
	    ORDER BY lastpost DESC
	");

	// check to see if there are any threads to display. If there are, do so, otherwise, show message
	if ($totalthreads = $db->num_rows($getthreads)) {
	    $threads = array();
	    while ($getthread = $db->fetch_array($getthreads)) {
		// unset the thread preview if it can't be seen
		$forumperms = fetch_permissions($getthread['forumid']);
		if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])) {
		    $getthread['preview'] = '';
		}
		if ($vbulletin->forumcache[$getthread['forumid']]['options'] & $vbulletin->bf_misc_forumoptions['allowicons']) {
		    $show['threadicons'] = true;
		    $subscribedthreadscolspan = 6;
		}
		$threads["$getthread[threadid]"] = $getthread;
	    }
	}
	unset($getthread);
	$db->free_result($getthreads);

	if ($totalthreads) {
	    foreach ($threads AS $threadid => $thread) {
		$last = $lastread["$thread[forumid]"];

		if ($last == -1) {
		    $last = $vbulletin->userinfo['lastvisit'];
		}

		if ($thread['lastpost'] > $last) {
		    if ($vbulletin->options['threadmarking'] AND $thread['threadread']) {
			$threadview = $thread['threadread'];
		    } else {
			$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
		    }

		    if ($thread['lastpost'] > $threadview) {
			$total++;
		    } 
		} 
	    }
	}
    }

    return $total;
}

function
get_pm_unread ()
{
    global $vbulletin, $db;

    $pmurc = $db->query_first_slave("
	SELECT SUM(IF(messageread = 0 AND folderid >= 0, 1, 0)) AS pmunread
	FROM " . TABLE_PREFIX . "pm AS pm
	WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
    ");
    $total = 0;
    if ($pmurc['pmunread']) {
	$total = intval($pmurc['pmunread']);
    }
    return $total;
}

function
fr_update_push_user ($username, $fr_b = false)
{
    global $vbulletin;
    
    $tableinfo = $vbulletin->db->query_first("
	SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_users'
    ");
    if ($tableinfo && $vbulletin->userinfo['userid']) {
	if ($username) {
	    // There can be only one FR user associated with this vb_userid and fr_username
	    $vb_user = $vbulletin->db->query_read_slave("
		SELECT id FROM " . TABLE_PREFIX . "forumrunner_push_users
		WHERE vb_userid = {$vbulletin->userinfo['userid']}
	    ");
	    if ($vbulletin->db->num_rows($vb_user) > 1) {
		// Multiple vb_userids.  Nuke em.
		$vbulletin->db->query_write("
		    DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
		    WHERE vb_userid = {$vbulletin->userinfo['userid']}
		");
	    }
	    $fr_user = $vbulletin->db->query_first("
		SELECT id FROM " . TABLE_PREFIX . "forumrunner_push_users
		WHERE fr_username = '" . $vbulletin->db->escape_string($username) . "'
	    ");
	    if ($fr_user) {
		$vbulletin->db->query_write("
		    UPDATE " . TABLE_PREFIX . "forumrunner_push_users
		    SET vb_userid = {$vbulletin->userinfo['userid']}, last_login = NOW(), b = " . ($fr_b ? 1 : 0) . "
		    WHERE id = {$fr_user['id']}
		");
	    } else {
		$vbulletin->db->query_write("
		    INSERT INTO " . TABLE_PREFIX . "forumrunner_push_users
		    (vb_userid, fr_username, b, last_login)
		    VALUES ({$vbulletin->userinfo['userid']}, '" . $vbulletin->db->escape_string($username) . "', " . ($fr_b ? 1 : 0) . ", NOW())
		");
	    }
	} else {
	    // Nuke any old entries of them being logged in
	    $vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
		WHERE vb_userid = {$vbulletin->userinfo['userid']}
	    ");
	}
    }
}

function 
fr_update_subsent ($threadid, $threadread)
{
    global $vbulletin;
    
    $tableinfo = $vbulletin->db->query_first("
	SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_data'
    ");
    if ($tableinfo) {
	$vbulletin->db->query_write("
	    UPDATE " . TABLE_PREFIX . "forumrunner_push_data
	    SET vb_subsent = 0, vb_threadread = $threadread
	    WHERE vb_userid = {$vbulletin->userinfo['userid']} AND vb_threadid = $threadid
	");
    }
}

function
fr_show_ad ()
{
    global $vbulletin;

    if (!$vbulletin ||
	($vbulletin->options['forumrunner_googleads_onoff'] == 0) ||
	(trim($vbulletin->options['forumrunner_googleads_usergroups'] == '')) ||
	(trim($vbulletin->options['forumrunner_admob_publisherid_iphone']) == '' && 
	 trim($vbulletin->options['forumrunner_admob_publisherid_android']) == '' && 
	 trim($vbulletin->options['forumrunner_googleads_javascript'] == '')))
    {
	return 0;
    }

    $adgids = explode(',', $vbulletin->options['forumrunner_googleads_usergroups']);
    $exclude_adgids = explode(',', $vbulletin->options['forumrunner_googleads_exclude_usergroups']);

    $mgids[] = $vbulletin->userinfo['usergroupid'];
    if ($vbulletin->userinfo['membergroupids'] && $vbulletin->userinfo['membergroupids'] != '') {
	$mgids = array_merge($mgids, explode(',', $vbulletin->userinfo['membergroupids']));
    }

    if (is_array($adgids)) {
	for ($i = 0; $i < count($adgids); $i++) {
	    $adgids[$i] = trim($adgids[$i]);
	}
    }
    
    if (is_array($exclude_adgids)) {
	for ($i = 0; $i < count($exclude_adgids); $i++) {
	    $exclude_adgids[$i] = trim($exclude_adgids[$i]);
	}
    }

    // See if they are included
    if (count(array_intersect($adgids, $mgids)) == 0) {
	return 0;
    }

    // See if they are excluded
    if (count(array_intersect($exclude_adgids, $mgids))) {
	return 0;
    }
   
    $ad = 0;
    if ($vbulletin->options['forumrunner_googleads_threadlist']) {
	$ad += FR_AD_THREADLIST;
    }
    if ($vbulletin->options['forumrunner_googleads_topthread']) {
	$ad += FR_AD_TOPTHREAD;
    }
    if ($vbulletin->options['forumrunner_googleads_bottomthread']) {
	$ad += FR_AD_BOTTOMTHREAD;
    }

    return $ad;
}

function
fr_standard_error ($error = '')
{
    json_error(prepare_utf8_string(strip_tags($error)));
}

?>
