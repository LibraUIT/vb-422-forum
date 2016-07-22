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

// Old versions of Forum Runner
if (isset($_REQUEST['forumid']) && $_REQUEST['forumid'] == -1) {
    unset($_REQUEST['forumid']);
}

require_once(MCWD . '/include/forumbits.php');

chdir('../');

define('THIS_SCRIPT', 'forumdisplay');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumdisplay.php');
require_once(DIR . '/includes/functions_prefix.php');
require_once(DIR . '/includes/functions_user.php');

$vbulletin->options['threadpreview'] = FR_PREVIEW_LEN;

function
do_get_forum ()
{
    global $vbulletin, $db, $show, $vbphrase, $foruminfo;

    $canpost = true;
    
    $vbulletin->input->clean_array_gpc('r', array(
	'fid' => TYPE_INT,
	'previewtype' => TYPE_INT,
    ));
    
    $previewtype = $vbulletin->GPC['previewtype'];
    if (!$previewtype) {
	$previewtype = 1;
    }

    if (empty($foruminfo['forumid'])) {
	$forumid = -1;
    } else {
	$vbulletin->input->clean_array_gpc('r', array(
	    'password' => TYPE_STR,
	));

	// Check the forum password
	if ($vbulletin->GPC['password'] && ($foruminfo['password'] == $vbulletin->GPC['password'])) {
	    
	    // Set a temp cookie for guests
	    if (!$vbulletin->userinfo['userid'])
	    {
		set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['password']));
	    }
	    else
	    {
		set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['password']), 1);
	    }
	}

	$perpage =  $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
	$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
	$daysprune = $vbulletin->input->clean_gpc('r', 'daysprune', TYPE_INT);
	$sortfield = $vbulletin->input->clean_gpc('r', 'sortfield', TYPE_STR);

	// get permission to view forum
	$_permsgetter_ = 'forumdisplay';
	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
	    json_error(ERR_NO_PERMISSION);
	}

	// Check for forum password!
	if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false)) {
	    json_error(ERR_NEED_PASSWORD, RV_NEED_FORUM_PASSWORD);
	}

	// Can we post in this forum?
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew'])) {
	    $canpost = false;
	}

	$forumid = $foruminfo['forumid'];
    }

    // Can forum contain threads?
    $announcements_out = array();

    // These $_REQUEST values will get used in the sort template so they are assigned to normal variables
    $perpage =  $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
    $pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
    $daysprune = $vbulletin->input->clean_gpc('r', 'daysprune', TYPE_INT);
    $sortfield = $vbulletin->input->clean_gpc('r', 'sortfield', TYPE_STR);
    
    // get permission to view forum
    $_permsgetter_ = 'forumdisplay';
    $forumperms = fetch_permissions($foruminfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
    {
	json_error(ERR_NO_PERMISSION);
    }
    
    // disable thread preview if we can't view threads
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
    	$vbulletin->options['threadpreview'] = 0;
    }
    
    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
    
    // verify that we are at the canonical SEO url
    // and redirect to this if not
    //verify_seo_url('forum', $foruminfo, array('pagenumber' => $_REQUEST['pagenumber']));
    
    // get vbulletin->iforumcache - for use by makeforumjump and forums list
    // fetch the forum even if they are invisible since its needed
    // for the title but we'll unset that further down
    // also fetch subscription info for $show['subscribed'] variable
    cache_ordered_forums(1, 1, $vbulletin->userinfo['userid']);
    
    $show['newthreadlink'] = iif(!$show['search_engine'] AND $foruminfo['allowposting'], true, false);
    $show['threadicons'] = iif ($foruminfo['allowicons'], true, false);
    $show['threadratings'] = iif ($foruminfo['allowratings'], true, false);
    $show['subscribed_to_forum'] = ($vbulletin->forumcache["$foruminfo[forumid]"]['subscribeforumid'] != '' ? true : false);
    
    if (!$daysprune)
    {
    	if ($vbulletin->userinfo['daysprune'])
    	{
    		$daysprune = $vbulletin->userinfo['daysprune'];
    	}
    	else
    	{
    		$daysprune = iif($foruminfo['daysprune'], $foruminfo['daysprune'], 30);
    	}
    }

    $daysprune = -1; // FRNR

    // ### GET FORUMS, PERMISSIONS, MODERATOR iCACHES ########################
    cache_moderators();
    
    // draw nav bar
    $navbits = array();
    $navbits[$vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q']] = $vbphrase['forum'];
    $parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
    foreach ($parentlist AS $forumID)
    {
    	$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
    	$navbits[fetch_seo_url('forum', array('forumid' => $forumID, 'title' => $forumTitle))] = $forumTitle;
    }
    
    // pop the last element off the end of the $nav array so that we can show it without a link
    array_pop($navbits);
    
    $navbits[''] = $foruminfo['title'];
    $navbits = construct_navbits($navbits);
    $navbar = render_navbar_template($navbits);
    
    $moderatorslist = '';
    $listexploded = explode(',', $foruminfo['parentlist']);
    $showmods = array();
    $show['moderators'] = false;
    $totalmods = 0;
    foreach ($listexploded AS $parentforumid)
    {
    	if (!$imodcache["$parentforumid"] OR $parentforumid == -1)
    	{
    		continue;
    	}
    	foreach ($imodcache["$parentforumid"] AS $moderator)
    	{
    		if ($showmods["$moderator[userid]"] === true)
    		{
    			continue;
    		}
    
    		$showmods["$moderator[userid]"] = true;
    
    		$show['comma_leader'] = ($moderatorslist != '');
    		$show['moderators'] = true;
    
    		$totalmods++;
    	}
    }
    
    // ### BUILD FORUMS LIST #################################################
    
    // get an array of child forum ids for this forum
    $foruminfo['childlist'] = explode(',', $foruminfo['childlist']);
    
    // define max depth for forums display based on $vbulletin->options[forumhomedepth]
    define('MAXFORUMDEPTH', $vbulletin->options['forumdisplaydepth']);
    
    if (($vbulletin->options['showforumusers'] == 1 OR $vbulletin->options['showforumusers'] == 2 OR ($vbulletin->options['showforumusers'] > 2 AND $vbulletin->userinfo['userid'])) AND !$show['search_engine'])
    {
    	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
    	$forumusers = $db->query_read_slave("
    		SELECT user.username, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible, user.usergroupid,
    			session.userid, session.inforum, session.lastactivity, session.badlocation,
    			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
    		FROM " . TABLE_PREFIX . "session AS session
    		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
    		WHERE session.lastactivity > $datecut
    		ORDER BY" . iif($vbulletin->options['showforumusers'] == 1 OR $vbulletin->options['showforumusers'] == 3, " username ASC,") . " lastactivity DESC
    	");
    
    	$numberregistered = 0;
    	$numberguest = 0;
    	$doneuser = array();
    
    	if ($vbulletin->userinfo['userid'])
    	{
    		// fakes the user being in this forum
    		$loggedin = array(
    			'userid'        => $vbulletin->userinfo['userid'],
    			'username'      => $vbulletin->userinfo['username'],
    			'invisible'     => $vbulletin->userinfo['invisible'],
    			'invisiblemark' => $vbulletin->userinfo['invisiblemark'],
    			'inforum'       => $foruminfo['forumid'],
    			'lastactivity'  => TIMENOW,
    			'musername'     => $vbulletin->userinfo['musername'],
    		);
    		$numberregistered = 1;
    		fetch_online_status($loggedin);
    
    		$show['comma_leader'] = false;
    		$doneuser["{$vbulletin->userinfo['userid']}"] = 1;
    	}
    
    	$inforum = array();
    
    	// this require the query to have lastactivity ordered by DESC so that the latest location will be the first encountered.
    	while ($loggedin = $db->fetch_array($forumusers))
    	{
    		if ($loggedin['badlocation'])
    		{
    			continue;
    		}
    
    		if (empty($doneuser["$loggedin[userid]"]))
    		{
    			if (in_array($loggedin['inforum'], $foruminfo['childlist']) AND $loggedin['inforum'] != -1)
    			{
    				if (!$loggedin['userid'])
    				{
    					// this is a guest
    					$numberguest++;
    					$inforum["$loggedin[inforum]"]++;
    				}
    				else
    				{
    					$numberregistered++;
    					$inforum["$loggedin[inforum]"]++;
    
    					if (fetch_online_status($loggedin))
    					{
    						fetch_musername($loggedin);
    
    						$show['comma_leader'] = ($activeusers != '');
    					}
    				}
    			}
    			if ($loggedin['userid'])
    			{
    				$doneuser["$loggedin[userid]"] = 1;
    			}
    		}
    	}
    
    	if (!$vbulletin->userinfo['userid'])
    	{
    		$numberguest = ($numberguest == 0) ? 1 : $numberguest;
    	}
    	$totalonline = $numberregistered + $numberguest;
    	unset($joingroupid, $key, $datecut, $invisibleuser, $userinfo, $userid, $loggedin, $index, $value, $forumusers, $parentarray );
    
    	$show['activeusers'] = true;
    }
    else
    {
    	$show['activeusers'] = false;
    }
    
    // #############################################################################
    // get read status for this forum and children
    $unreadchildforums = 0;
    foreach ($foruminfo['childlist'] AS $val)
    {
    	if ($val == -1 OR $val == $foruminfo['forumid'])
    	{
    		continue;
    	}
    
    	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
    	{
    		$lastread_child = max($vbulletin->forumcache["$val"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
    	}
    	else
    	{
    		$lastread_child = max(intval(fetch_bbarray_cookie('forum_view', $val)), $vbulletin->userinfo['lastvisit']);
    	}
    
    	if ($vbulletin->forumcache["$val"]['lastpost'] > $lastread_child)
    	{
    		$unreadchildforums = 1;
    		break;
    	}
    }
    
    $forumbits = fr_construct_forum_bit($forumid);
    
    // admin tools
    
    $show['post_queue'] = can_moderate($foruminfo['forumid'], 'canmoderateposts');
    $show['attachment_queue'] = can_moderate($foruminfo['forumid'], 'canmoderateattachments');
    $show['mass_move'] = can_moderate($foruminfo['forumid'], 'canmassmove');
    $show['mass_prune'] = can_moderate($foruminfo['forumid'], 'canmassprune');
    
    $show['post_new_announcement'] = can_moderate($foruminfo['forumid'], 'canannounce');
    $show['addmoderator'] = ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']);
    
    $show['adminoptions'] = ($show['post_queue'] OR $show['attachment_queue'] OR $show['mass_move'] OR $show['mass_prune'] OR $show['addmoderator'] OR $show['post_new_announcement']);
    
    $navpopup = array(
    	'id'    => 'forumdisplay_navpopup',
    	'title' => $foruminfo['title_clean'],
    	'link'  => fetch_seo_url('forum', $foruminfo)
    );
    construct_quick_nav($navpopup);
    
    
    /////////////////////////////////
    if ($foruminfo['cancontainthreads'])
    {
    	/////////////////////////////////
    	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
    	{
    		$foruminfo['forumread'] = $vbulletin->forumcache["$foruminfo[forumid]"]['forumread'];
    		$lastread = max($foruminfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
    	}
    	else
    	{
    		$bbforumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
    		$lastread = max($bbforumview, $vbulletin->userinfo['lastvisit']);
    	}
    
    	// Inline Moderation
    	$show['movethread'] = (can_moderate($forumid, 'canmanagethreads')) ? true : false;
    	$show['deletethread'] = (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts')) ? true : false;
    	$show['approvethread'] = (can_moderate($forumid, 'canmoderateposts')) ? true : false;
    	$show['openthread'] = (can_moderate($forumid, 'canopenclose')) ? true : false;
    	$show['inlinemod'] = ($show['movethread'] OR $show['deletethread'] OR $show['approvethread'] OR $show['openthread']) ? true : false;
    	$show['spamctrls'] = ($show['inlinemod'] AND $show['deletethread']);
    	$url = $show['inlinemod'] ? SCRIPTPATH : '';
    
    	// fetch popup menu
    	if ($show['popups'] AND $show['inlinemod'])
    	{
    	}
    	else
    	{
    		$threadadmin_imod_thread_menu = '';
    	}
    
    	// get announcements
    
    	$announcebits = '';
    	if ($show['threadicons'] AND $show['inlinemod'])
    	{
    		$announcecolspan = 6;
    	}
    	else if (!$show['threadicons'] AND !$show['inlinemod'])
    	{
    		$announcecolspan = 4;
    	}
    	else
    	{
    		$announcecolspan = 5;
    	}
    
    	$mindate = TIMENOW - 2592000; // 30 days
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    
    	$announcements = $db->query_read_slave("
    		SELECT
    			announcement.announcementid, startdate, title, announcement.views,
    			user.username, user.userid, user.usertitle, user.customtitle, user.usergroupid,
    			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
    			" . (($vbulletin->userinfo['userid']) ? ", NOT ISNULL(announcementread.announcementid) AS readannounce" : "") . "
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "announcement AS announcement
    		" . (($vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "announcementread AS announcementread ON (announcementread.announcementid = announcement.announcementid AND announcementread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
    		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = announcement.userid)
    		$hook_query_joins
    		WHERE startdate <= " . TIMENOW . "
    			AND enddate >= " . TIMENOW . "
    			AND " . fetch_forum_clause_sql($foruminfo['forumid'], 'forumid') . "
    			$hook_query_where
    		ORDER BY startdate DESC, announcement.announcementid DESC
    		" . iif($vbulletin->options['oneannounce'], "LIMIT 1")
    	);
    
    	while ($announcement = $db->fetch_array($announcements))
    	{
    		fetch_musername($announcement);
    		$announcement['title'] = fetch_censored_text($announcement['title']);
    		$announcement['postdate'] = vbdate($vbulletin->options['dateformat'], $announcement['startdate']);
    		if ($announcement['readannounce'] OR $announcement['startdate'] <= $mindate)
    		{
    			$announcement['statusicon'] = 'old';
    		}
    		else
    		{
    			$announcement['statusicon'] = 'new';
    		}
    		$announcement['views'] = vb_number_format($announcement['views']);
    		$announcementidlink = iif(!$vbulletin->options['oneannounce'] , "&amp;a=$announcement[announcementid]");

		// FRNR START
		if ($pagenumber == 1) {
		    
		    $avatarurl = '';
		    $userinfoavatar = fetch_userinfo($announcement['userid'], FETCH_USERINFO_AVATAR);
		    fetch_avatar_from_userinfo($userinfoavatar, true, false);
		    if ($userinfoavatar['avatarurl'] != '') {
			$avatarurl = process_avatarurl($userinfoavatar['avatarurl']);
		    }
		    unset($userinfoavatar);

		    $tmp = array(
			'thread_id' => $foruminfo['forumid'],
			'announcement' => 1,
			'new_posts' => $announcement['readannounce'] ? 0 : 1,
			'thread_title' => prepare_utf8_string(strip_tags($announcement['title'])),
			'thread_preview' => prepare_utf8_string(preview_chop(html_entity_decode($announcement['pagetext']), FR_PREVIEW_LEN)),
			'post_userid' => $announcement['userid'],
			'post_lastposttime' => prepare_utf8_string(date_trunc($announcement['postdate'])),
			'post_username' => prepare_utf8_string(strip_tags($announcement['username'])),
		    );
		    if ($avatarurl != '') {
			$tmp['avatarurl'] = $avatarurl;
		    }
		    $announcements_out[] = $tmp;
		}
		// FRNR END
  	}
  
  	// display threads

    	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
    	{
    		$limitothers = "AND postuserid = " . $vbulletin->userinfo['userid'] . " AND " . $vbulletin->userinfo['userid'] . " <> 0";
    	}
    	else
    	{
    		$limitothers = '';
    	}
    
    	if (can_moderate($foruminfo['forumid']))
    	{
    		$redirectjoin = "LEFT JOIN " . TABLE_PREFIX . "threadredirect AS threadredirect ON(thread.open = 10 AND thread.threadid = threadredirect.threadid)";
    	}
    	else
    	{
    		$redirectjoin = '';
    	}
    
    	// filter out deletion notices if can't be seen
    	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($foruminfo['forumid']))
    	{
    		$canseedelnotice = true;
    		$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND deletionlog.type = 'thread')";
    	}
    	else
    	{
    		$canseedelnotice = false;
    		$deljoin = '';
    	}
    
    	// remove threads from users on the global ignore list if user is not a moderator
    	if ($Coventry = fetch_coventry('string') AND !can_moderate($foruminfo['forumid']))
    	{
    		$globalignore = "AND postuserid NOT IN ($Coventry) ";
    	}
    	else
    	{
    		$globalignore = '';
    	}
    
    	// look at thread limiting options
    	$stickyids = '';
    	$stickycount = 0;
    	if ($daysprune != -1)
    	{
    		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
    		{
    			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
    				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")";
    			$datecut = " AND (thread.lastpost >= " . (TIMENOW - ($daysprune * 86400)) . " OR tachythreadpost.lastpost >= " . (TIMENOW - ($daysprune * 86400)) . ")";
    		}
    		else
    		{
    			$datecut = "AND lastpost >= " . (TIMENOW - ($daysprune * 86400));
    			$tachyjoin = "";
    		}
    		$show['noposts'] = false;
    	}
    	else
    	{
    		$tachyjoin = "";
    		$datecut = "";
    		$show['noposts'] = true;
    	}
    
    	// complete form fields on page
    	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
    	$daysprunesel = array($daysprunesel => 'selected="selected"');
    
    	$vbulletin->input->clean_array_gpc('r', array(
    		'sortorder' => TYPE_NOHTML,
    		'prefixid'  => TYPE_NOHTML,
    	));
    
    	// prefix options
    	$prefix_options = fetch_prefix_html($foruminfo['forumid'], $vbulletin->GPC['prefixid']);
    	$prefix_selected = array('anythread', 'anythread' => '', 'none' => '');
    	if ($vbulletin->GPC['prefixid'])
    	{
    		//no prefix id
    		if ($vbulletin->GPC['prefixid'] == '-1')
    		{
    			$prefix_filter = "AND thread.prefixid = ''";
    			$prefix_selected['none'] = ' selected="selected"';
    		}
    
    		//any prefix id
    		else if ($vbulletin->GPC['prefixid'] == '-2')
    		{
    			$prefix_filter = "AND thread.prefixid <> ''";
    			$prefix_selected['anyprefix'] = ' selected="selected"';
    		}
    
    		//specific prefix id
    		else
    		{
    			$prefix_filter = "AND thread.prefixid = '" . $db->escape_string($vbulletin->GPC['prefixid']) . "'";
    		}
    	}
    	else
    	{
    		$prefix_filter = '';
    		$prefix_selected['anythread'] = ' selected="selected"';
    	}
    
    	// default sorting methods
    	if (empty($sortfield))
    	{
    		$sortfield = $foruminfo['defaultsortfield'];
    	}
    	if (empty($vbulletin->GPC['sortorder']))
    	{
    		$vbulletin->GPC['sortorder'] = $foruminfo['defaultsortorder'];
    	}
    
    	// look at sorting options:
    	if ('asc' != ($sortorder = $vbulletin->GPC['sortorder']))
    	{
    		$sqlsortorder = 'DESC';
    		$order = array('desc' => 'checked="checked"');
    		$vbulletin->GPC['sortorder'] = 'desc';
    	}
    	else
    	{
    		$sqlsortorder = '';
    		$order = array('asc' => 'checked="checked"');
    	}
    
    	$sqlsortfield2 = '';
    
    	switch ($sortfield)
    	{
    		case 'title':
    			$sqlsortfield = 'thread.title';
    			break;
    		case 'lastpost':
    			$sqlsortfield = 'lastpost';
    			break;
    		case 'replycount':
    		case 'views':
    			$sqlsortfield = 'views';
    		case 'postusername':
    			$sqlsortfield = $sortfield;
    			break;
    		case 'voteavg':
    			if ($foruminfo['allowratings'])
    			{
    				$sqlsortfield = 'voteavg';
    				$sqlsortfield2 = 'votenum';
    				break;
    			}
    		case 'dateline':
    			$sqlsortfield = 'thread.dateline';
    			break;
    		// else, use last post
    		default:
    			$handled = false;
    			if (!$handled)
    			{
    				$sqlsortfield = 'lastpost';
    				$sortfield = 'lastpost';
    			}
    	}
    	$sort = array($sortfield => 'selected="selected"');

	$visiblethreads = " AND visible = 1";
    	/*if (!can_moderate($forumid, 'canmoderateposts'))
    	{
    		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice']))
    		{
    			$visiblethreads = " AND visible = 1 ";
    		}
    		else
    		{
    			$visiblethreads = " AND visible IN (1,2)";
    		}
    	}
    	else
    	{
    		$visiblethreads = " AND visible IN (0,1,2)";
	}*/
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    
    	# Include visible IN (0,1,2) in order to hit upon the 4 column index
    	$threadscount = $db->query_first_slave("
    		SELECT COUNT(*) AS threads, SUM(IF(thread.lastpost > $lastread AND open <> 10, 1, 0)) AS newthread
    		$hook_query_fields
    		FROM " . TABLE_PREFIX . "thread AS thread
    		$tachyjoin
    		$hook_query_joins
    		WHERE forumid = $foruminfo[forumid]
    			AND sticky = 0
    			$prefix_filter
    			$visiblethreads
    			$globalignore
    			$limitothers
    			$datecut
    			$hook_query_where
    	");
    	$totalthreads = $threadscount['threads'];
    	$newthreads = $threadscount['newthread'];
    
    	// set defaults
    	sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);
    
    	// get number of sticky threads for the first page
    	// on the first page there will be the sticky threads PLUS the $perpage other normal threads
    	// not quite a bug, but a deliberate feature!
    	if ($pagenumber == 1) // FRNR OR $vbulletin->options['showstickies'])
    	{
    		$stickies = $db->query_read_slave("
    			SELECT thread.threadid, lastpost, open
    			FROM " . TABLE_PREFIX . "thread AS thread
    			WHERE forumid = $foruminfo[forumid]
    				AND sticky = 1
    				$prefix_filter
    				$visiblethreads
    				$limitothers
    				$globalignore
    		");
    		while ($thissticky = $db->fetch_array($stickies))
    		{
    			$stickycount++;
    			if ($thissticky['lastpost'] >= $lastread AND $thissticky['open'] <> 10)
    			{
    				$newthreads++;
    			}
    			$stickyids .= ",$thissticky[threadid]";
    		}
    		$db->free_result($stickies);
    		unset($thissticky, $stickies);
    	}
    
    
    	$limitlower = ($pagenumber - 1) * $perpage;
    	$limitupper = ($pagenumber) * $perpage;
    
    	if ($limitupper > $totalthreads)
    	{
    		$limitupper = $totalthreads;
    		if ($limitlower > $totalthreads)
    		{
    			$limitlower = ($totalthreads - $perpage) - 1;
    		}
    	}
    	if ($limitlower < 0)
    	{
    		$limitlower = 0;
    	}
    
    	if ($foruminfo['allowratings'])
    	{
    		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);
    		$votequery = "
    			IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
    			IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg,
    		";
    	}
    	else
    	{
    		$votequery = '';
    	}
    
	if ($previewtype == 1) {
	    $previewfield = "post.pagetext AS preview, post.username AS lastpost_username, post.userid AS lastpost_userid,";
	    $previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
	} else {
	    $previewfield = "post.pagetext AS preview, post.username AS lastpost_username, post.userid AS lastpost_userid,";
	    $previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.lastpostid)";
	}
    
    	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
    	{
    		$tachyjoin = "
    			LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON
    				(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")
    			LEFT JOIN " . TABLE_PREFIX . "tachythreadcounter AS tachythreadcounter ON
    				(tachythreadcounter.threadid = thread.threadid AND tachythreadcounter.userid = " . $vbulletin->userinfo['userid'] . ")
    		";
    		$tachy_columns = "
    			IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost,
    			IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter,
    			IF(tachythreadpost.userid IS NULL, thread.lastposterid, tachythreadpost.lastposterid) AS lastposterid,
    			IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid,
    			IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount) AS replycount,
    			IF(thread.views<=IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount), IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount)+1, thread.views) AS views
    		";
    
    	}
    	else
    	{
    		$tachyjoin = '';
    		$tachy_columns = 'thread.lastpost, thread.lastposter, thread.lastposterid, thread.lastpostid, thread.replycount, IF(thread.views<=thread.replycount, thread.replycount+1, thread.views) AS views';
    	}
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    
    	$getthreadids = $db->query_read_slave("
    		SELECT " . iif($sortfield == 'voteavg', $votequery) . " thread.threadid,
    			$tachy_columns
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "thread AS thread
    		$tachyjoin
    		$hook_query_joins
    		WHERE forumid = $foruminfo[forumid]
    			AND sticky = 0
    			$prefix_filter
    			$visiblethreads
    			$globalignore
    			$limitothers
    			$datecut
    			$hook_query_where
    		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder" . (!empty($sqlsortfield2) ? ", $sqlsortfield2 $sqlsortorder" : '') . "
    		LIMIT $limitlower, $perpage
    	");
    
    	$ids = '';
    	while ($thread = $db->fetch_array($getthreadids))
    	{
    		$ids .= ',' . $thread['threadid'];
    	}
    
    	$ids .= $stickyids;
    
    	$db->free_result($getthreadids);
    	unset ($thread, $getthreadids);
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    
    	$threads = $db->query_read_slave("
    		SELECT $votequery $previewfield
    			thread.threadid, thread.title AS threadtitle, thread.forumid, pollid, open, postusername, postuserid, thread.iconid AS threadiconid,
    			thread.dateline, notes, thread.visible, sticky, votetotal, thread.attach, $tachy_columns,
    			thread.prefixid, thread.taglist, hiddencount, deletedcount,
    			user.usergroupid, user.homepage, user.options AS useroptions, IF(userlist.friend = 'yes', 1, 0) AS isfriend
    			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ? ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed" : "") . "
    			" . ($deljoin ? ", deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
    			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", threadread.readtime AS threadread" : "") . "
    			" . ($redirectjoin ? ", threadredirect.expires" : "") . "
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "thread AS thread
    			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = thread.lastposterid)
    			LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.relationid = user.userid AND userlist.type = 'buddy' AND userlist.userid = " . $vbulletin->userinfo['userid'] . ")
    			$deljoin
    			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ?  " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)" : "") . "
    			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
    			$previewjoin
    			$tachyjoin
    			$redirectjoin
    			$hook_query_joins
    		WHERE thread.threadid IN (0$ids) $hook_query_where
    		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder" . (!empty($sqlsortfield2) ? ", $sqlsortfield2 $sqlsortorder" : '') . "
    	");
    	unset($limitothers, $delthreadlimit, $deljoin, $datecut, $votequery, $sqlsortfield, $sqlsortorder, $threadids, $sqlsortfield2);
    
    	// Get Dot Threads
    	$dotthreads = fetch_dot_threads_array($ids);
    	if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
    	{
    		$show['dotthreads'] = true;
    	}
    	else
    	{
    		$show['dotthreads'] = false;
    	}
    
    	unset($ids);
    
    	$pageinfo = array();
    	if ($vbulletin->GPC['prefixid'])
    	{
    		$pageinfo['prefixid'] = $vbulletin->GPC['prefixid'];
    	}
    	if ($vbulletin->GPC['daysprune'])
    	{
    		$pageinfo['daysprune'] = $daysprune;
    	}
    
    	$show['fetchseo'] = true;
    	$oppositesort = $vbulletin->GPC['sortorder'] == 'asc' ? 'desc' : 'asc';
    
    	$pageinfo_voteavg = $pageinfo + array('sort' => 'voteavg', 'order' => ('voteavg' == $sortfield) ? $oppositesort : 'desc');
    	$pageinfo_title = $pageinfo + array('sort' => 'title', 'order' => ('title' == $sortfield) ? $oppositesort : 'asc');
    	$pageinfo_postusername = $pageinfo + array('sort' => 'postusername', 'order' => ('postusername' == $sortfield) ? $oppositesort : 'asc');
    	$pageinfo_flastpost = $pageinfo + array('sort' => 'lastpost', 'order' => ('lastpost' == $sortfield) ? $oppositesort : 'asc');
    	$pageinfo_replycount = $pageinfo + array('sort' => 'replycount', 'order' => ('replycount' == $sortfield) ? $oppositesort : 'desc');
    	$pageinfo_views = $pageinfo + array('sort' => 'views', 'order' => ('views' == $sortfield) ? $oppositesort : 'desc');
    
    	$pageinfo_sort = $pageinfo + array(sort => $sortfield, 'order' => $oppositesort, 'pp' => $perpage, 'page' => $pagenumber);
    
    
    	if ($totalthreads > 0 OR $stickyids)
    	{
    		if ($totalthreads > 0)
    		{
    			$limitlower++;
    		}
    		// check to see if there are any threads to display. If there are, do so, otherwise, show message
    
    		if ($vbulletin->options['threadpreview'] > 0)
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
    
    		$show['threads'] = true;
    		$threadbits = '';
    		$threadbits_sticky = '';
    
    		$counter = 0;
    		$toread = 0;
    
    		while ($thread = $db->fetch_array($threads))
    		{ // AND $counter++ < $perpage)
    
    			// build thread data
    			$thread = process_thread_array($thread, $lastread, $foruminfo['allowicons']);
    			$realthreadid = $thread['realthreadid'];
    
    			if ($thread['sticky'])
    			{
    				$threadbit =& $threadbits_sticky;
    			}
    			else
    			{
    				$threadbit =& $threadbits;
    			}
    
			// Soft Deleted Thread
    			if ($thread['visible'] == 2)
    			{
    				$thread['deletedcount']++;
    				$show['threadtitle'] = (can_moderate($forumid) OR ($vbulletin->userinfo['userid'] != 0 AND $vbulletin->userinfo['userid'] == $thread['postuserid'])) ? true : false;
    				$show['deletereason'] = (!empty($thread['del_reason'])) ?  true : false;
    				$show['viewthread'] = (can_moderate($forumid)) ? true : false;
    				$show['managethread'] = (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts')) ? true : false;
    				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($forumid, 'canmoderateposts')) ? true : false;
    				$show['deletedthread'] = $canseedelnotice;
    			}
    			else
    			{
    				if (!$thread['visible'])
    				{
    					$thread['hiddencount']++;
    				}
    				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($forumid, 'canmoderateposts')) ? true : false;
    				$show['deletedthread'] = ($thread['deletedcount'] > 0 AND $canseedelnotice) ? true : false;
    
    				$pageinfo_lastpage = array();
    				if ($show['pagenavmore'])
    				{
    					$pageinfo_lastpage['page'] = $thread['totalpages'];
    				}
    				$pageinfo_newpost = array('goto' => 'newpost');
    				$pageinfo_lastpost = array('p' => $thread['lastpostid']);
    				
    				// prepare the member action drop-down menu
    				$memberaction_dropdown = construct_memberaction_dropdown(fetch_lastposter_userinfo($thread));
			}

			// FRNR Start
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
			    'new_posts' => $show['gotonewpost'] ? 1 : 0,
			    'forum_id' => $thread['forumid'],
			    'total_posts' => $thread['totalposts'] ? $thread['totalposts'] : 0,
			    'thread_title' => prepare_utf8_string(strip_tags($thread['threadtitle'])),
			    'thread_preview' => prepare_utf8_string(preview_chop(html_entity_decode($thread['preview']), FR_PREVIEW_LEN)),
			    'post_userid' => $thread['lastpost_userid'],
			    'post_lastposttime' => prepare_utf8_string(date_trunc($thread['lastpostdate']) . ' ' . $thread['lastposttime']),
			    'post_username' => prepare_utf8_string(strip_tags($thread['lastpost_username'])),
			);
			if ($avatarurl != '') {
			    $tmp['avatarurl'] = $avatarurl;
			}
 			if ($thread['prefixid']) {
 			    $tmp['prefix'] = prepare_utf8_string(strip_tags($vbphrase["prefix_{$thread['prefixid']}_title_plain"]));
 			}
 			if ($thread['attach']) {
 			    $tmp['attach'] = true;
 			}
 			if ($thread['pollid']) {
 			    $tmp['poll'] = true;
 			}
			if ($thread['open'] == 10) {
			    // Special case for redirect threads
			    $tmp = array_merge($tmp, array(
				'post_userid' => $thread['postuserid'],
				'post_username' => prepare_utf8_string(strip_tags($thread['postusername'])),
				'poll' => false,
			    ));
			}
			if ($thread['sticky']) {
			    $thread_data_sticky[] = $tmp;
			} else {
			    $thread_data[] = $tmp;
			}
			// FRNR Stop
		}

    		$db->free_result($threads);
    		unset($thread, $counter);
    
    		$pageinfo_pagenav = array();
    		if (!empty($vbulletin->GPC['perpage']))
    		{
    			$pageinfo_pagenav['pp'] = $perpage;
    		}
    		if (!empty($vbulletin->GPC['prefixid']))
    		{
    			$pageinfo_pagenav['prefixid'] = $vbulletin->GPC['prefixid'];
    		}
    		if (!empty($vbulletin->GPC['sortfield']))
    		{
    			$pageinfo_pagenav['sort'] = $sortfield;
    		}
    		if (!empty($vbulletin->GPC['sortorder']))
    		{
    			$pageinfo_pagenav['order'] = $vbulletin->GPC['sortorder'];
    		}
    		if (!empty($vbulletin->GPC['daysprune']))
    		{
    			$pageinfo_pagenav['daysprune'] = $daysprune;
    		}
    
    		$pagenav = construct_page_nav(
    			$pagenumber,
    			$perpage,
    			$totalthreads,
    			'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]",
    			'',
    			'',
    			'forum',
    			$foruminfo,
    			$pageinfo_pagenav
    		);
    
    	}
    	unset($threads, $dotthreads);
    
    	// get colspan for bottom bar
    	$foruminfo['bottomcolspan'] = 5;
    	if ($foruminfo['allowicons'])
    	{
    		$foruminfo['bottomcolspan']++;
    	}
    	if ($show['inlinemod'])
    	{
    		$foruminfo['bottomcolspan']++;
    	}
    
    	$show['threadslist'] = true;
    
    	/////////////////////////////////
    } // end forum can contain threads
    else
    {
	$show['threadslist'] = false;
	$canpost = false; // FRNR
    }
    /////////////////////////////////
    
    if (!$vbulletin->GPC['prefixid'] AND $newthreads < 1 AND $unreadchildforums < 1)
    {
    	mark_forum_read($foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
    }
    

    // FNRN Below
    $out = array();
    if (is_array($thread_data) && count($thread_data) > 0) {
	$out['threads'] = $thread_data;
    } else {
	$out['threads'] = array();
    }
    if (is_array($thread_data_sticky) && count($thread_data_sticky) > 0) {
	$out['threads_sticky'] = $thread_data_sticky;
	$out['total_sticky_threads'] = count($thread_data_sticky);
    } else {
	$out['threads_sticky'] = array();
	$out['total_sticky_threads'] = 0;
    }

    // Announcements become #1 on the threads
    if (is_array($announcements_out) && count($announcements_out) == 1) {
	array_unshift($out['threads'], $announcements_out[0]);
	$totalthreads++;
    }

    $out['total_threads'] = $totalthreads ? $totalthreads : 0;

    if ($forumbits) {
	$out['forums'] = $forumbits;
    } else {
	$out['forums'] = array();
    }

    $out['canpost'] = $canpost ? 1 : 0;
    $out['canattach'] = ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid']);

    // Get thread prefixes for this forum (if any)
    $prefix_out = array();
    if ($prefixsets = fetch_prefix_array($forumid))
    {
	foreach ($prefixsets AS $prefixsetid => $prefixes)
	{
	    $optgroup_options = '';
	    foreach ($prefixes AS $prefixid => $prefix)
	    {
		if ($permcheck AND !can_use_prefix($prefixid, $prefix['restrictions']))
		{
		    continue;
		}

		$optionvalue = $prefixid;
		$optiontitle = htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]);

		$prefix_out[] = array(
		    'prefixid' => $prefixid,
		    'prefixcaption' => prepare_utf8_string($optiontitle),
		);
	    }
	}
    }
    if ($foruminfo['options'] & $vbulletin->bf_misc_forumoptions['prefixrequired']) {
	$out['prefixrequired'] = true;
    } else {
	$out['prefixrequired'] = false;
    }

    $out['prefixes'] = $prefix_out;

    return $out;
}

function
do_get_forum_data ()
{
    global $vbulletin, $db, $show, $vbphrase;

    $vbulletin->input->clean_array_gpc('r', array(
        'forumids' => TYPE_STR,
    ));

    if (!$vbulletin->GPC['forumids'] || strlen($vbulletin->GPC['forumids']) == 0) {
        return array('forums' => array());
    }

    cache_ordered_forums(1, 1);

    $forumids = split(',', $vbulletin->GPC['forumids']);

    $forum_data = array();

    foreach ($forumids AS $forumid) {
        $foruminfo = fetch_foruminfo($forumid);

        $type = 'old';

        if (is_array($foruminfo) AND !empty($foruminfo['link'])) { // see if it is a redirect
            $type = 'link';
        } else {
            if ($vbulletin->userinfo['lastvisitdate'] == -1) {
                $type = 'new';
            } else {
                if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) {
                    $userlastvisit = (!empty($foruminfo['forumread']) ? $foruminfo['forumread'] : (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
                } else {
                    $forumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));

                    //use which one produces the highest value, most likely cookie
                    $userlastvisit = ($forumview > $vbulletin->userinfo['lastvisit'] ? $forumview : $vbulletin->userinfo['lastvisit']);
                }

                if ($foruminfo['lastpost'] AND $userlastvisit < $foruminfo['lastpost']) {
                    $type = 'new';
                } else {
                    $type = 'old';
                }
            }
        }


        // If this forum has a password, check to see if we have
        // the proper cookie.  If so, don't prompt for one
        $password = false;
        if ($foruminfo['password']) {
            $pw_ok = verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false);
            if (!$pw_ok) {
                $password = true;
            }
        }

        $out = array(
            'id' => $foruminfo['forumid'],
            'new' => $type == 'new' ? true : false,
            'name' => prepare_utf8_string(strip_tags($foruminfo['title'])),
            'password' => $password,
        );
        $icon = fr_get_forum_icon($foruminfo['forumid'], $foruminfo == 'new');
        if ($icon) {
            $out['icon'] = $icon;
        }
        if ($foruminfo['link'] != '') {
            $link = fr_fix_url($foruminfo['link']);
            if (is_int($link)) {
                $out['id'] = $link;
            } else {
                $out['link'] = $link;
            }
            $linkicon = fr_get_forum_icon($foruminfo['forumid'], false, true);
            if ($linkicon) {
                $out['icon'] = $linkicon;
            }
        }
        if ($foruminfo['description'] != '') {
            $desc = prepare_utf8_string(strip_tags($foruminfo['description']));
            if (strlen($desc) > 0) {
                $out['desc'] = $desc;
            }
        }
        $forum_data[] = $out;
    }

    return array('forums' => $forum_data);
}

?>
