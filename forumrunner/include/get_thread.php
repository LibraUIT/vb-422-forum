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

define('THIS_SCRIPT', 'showthread');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
	'postbit',
	'showthread',
	'inlinemod',
	'reputationlevel'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'mailqueue',
	'bookmarksitecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'ad_showthread_beforeqr',
	'ad_showthread_firstpost',
	'ad_showthread_firstpost_start',
	'ad_showthread_firstpost_sig',
	'ad_thread_first_post_content',
	'ad_thread_last_post_content',
	'forumdisplay_loggedinuser',
	'forumrules',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'im_skype',
	'postbit',
	'postbit_wrapper',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
	'postbit_attachmentmoderated',
	'postbit_deleted',
	'postbit_ignore',
	'postbit_ignore_global',
	'postbit_ip',
	'postbit_onlinestatus',
	'postbit_reputation',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'bbcode_video',
	'SHOWTHREAD',
	'showthread_list',
	'showthread_similarthreadbit',
	'showthread_similarthreads',
	'showthread_quickreply',
	'showthread_bookmarksite',
	'tagbit',
	'tagbit_wrapper',
	'polloptions_table',
	'polloption',
	'polloption_multiple',
	'pollresults_table',
	'pollresult',
	'threadadmin_imod_menu_post',
	'editor_clientscript',
	'editor_jsoptions_font',
	'editor_jsoptions_size',
	'editor_toolbar_colors',
	'editor_toolbar_fontname',
	'editor_toolbar_fontsize',
);

$specialtemplates = array(
    'smiliecache',
    'bbcodecache',
);

require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/class_postbit.php');

// pre-cache templates used by specific actions
$actiontemplates = array();

$vbulletin->options['awc_enable'] = false;

vbsetcookie('skip_fr_detect', 'false');

function
do_get_thread ()
{
    global $vbulletin, $db, $foruminfo, $threadinfo, $postid, $vault, $vbphrase;

    $vbulletin->input->clean_array_gpc('r', array(
	'pagenumber' => TYPE_UINT,
	'perpage'    => TYPE_UINT,
	'password'   => TYPE_STR,
	'signature'  => TYPE_BOOL,
    ));

    if (empty($threadinfo['threadid'])) {
	json_error(ERR_INVALID_THREAD);
    }

    $threadedmode = 0;    
    $threadid = $vbulletin->GPC['threadid'];

    // Goto first unread post?
    if ($vbulletin->GPC['pagenumber'] == FR_LAST_POST) {
	$threadinfo = verify_id('thread', $threadid, 1, 1);

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) {
	    $vbulletin->userinfo['lastvisit'] = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
	} else if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadid))) > $vbulletin->userinfo['lastvisit']) {
	    $vbulletin->userinfo['lastvisit'] = $tview;
	}

	$coventry = fetch_coventry('string');
	$posts = $db->query_first("
	    SELECT MIN(postid) AS postid
	    FROM " . TABLE_PREFIX . "post
	    WHERE threadid = $threadinfo[threadid]
	    AND visible = 1
	    AND dateline > " . intval($vbulletin->userinfo['lastvisit']) . "
	    ". ($coventry ? "AND userid NOT IN ($coventry)" : "") . "
	    LIMIT 1
	");
	if ($posts['postid']) {
	    $postid = $posts['postid'];
	} else {
	    $postid = $threadinfo['lastpostid'];
	}
    }

    // *********************************************************************************
    // workaround for header redirect issue from forms with enctype in IE
    // (use a scrollIntoView javascript call in the <body> onload event)
    $onload = '';
    
    // *********************************************************************************
    // set $perpage

    $perpage = max(FR_MIN_PERPAGE, min($vbulletin->GPC['perpage'], FR_MAX_PERPAGE)); // FRNR
    //$perpage = sanitize_maxposts($vbulletin->GPC['perpage']);
    
    // *********************************************************************************
    // set post order
    if ($vbulletin->userinfo['postorder'] == 0)
    {
    	$postorder = '';
    }
    else
    {
    	$postorder = 'DESC';
    }
    
    // *********************************************************************************
    // get thread info
    $thread = verify_id('thread', $threadid, 1, 1);
    $threadinfo =& $thread;
    
    ($hook = vBulletinHook::fetch_hook('showthread_getinfo')) ? eval($hook) : false;
    
    // *********************************************************************************
    // check for visible / deleted thread
    if (((!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))) OR ($thread['isdeleted'] AND !can_moderate($thread['forumid'])))
    {
	json_error(ERR_INVALID_THREAD);
    }
    
    // *********************************************************************************
    // Tachy goes to coventry
    if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
    {
	json_error(ERR_INVALID_THREAD);
    }

    // FRNR Start

    // Check the forum password (set necessary cookies)
    if ($vbulletin->GPC['password'] && ($foruminfo['password'] == $vbulletin->GPC['password']))
    {
	// set a temp cookie for guests
	if (!$vbulletin->userinfo['userid'])
	{
	    set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['password']));
	}
	else
	{
	    set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['password']), 1);
	}
    }

    // FRNR End

    // *********************************************************************************
    // do word wrapping for the thread title
    if ($vbulletin->options['wordwrap'] != 0)
    {
    	$thread['title'] = fetch_word_wrapped_string($thread['title']);
    }
    
    $thread['title'] = fetch_censored_text($thread['title']);
    
    $thread['meta_description'] = strip_bbcode(strip_quotes($thread['description']), false, true);
    $thread['meta_description'] = htmlspecialchars_uni(fetch_censored_text(fetch_trimmed_title($thread['meta_description'], 500, false)));
    
    // *********************************************************************************
    // words to highlight from the search engine
    if (!empty($vbulletin->GPC['highlight']))
    {
    	$highlight = preg_replace('#\*+#s', '*', $vbulletin->GPC['highlight']);
    	if ($highlight != '*')
    	{
    		$regexfind = array('\*', '\<', '\>');
    		$regexreplace = array('[\w.:@*/?=]*?', '<', '>');
    		$highlight = preg_quote(strtolower($highlight), '#');
    		$highlight = explode(' ', $highlight);
    		$highlight = str_replace($regexfind, $regexreplace, $highlight);
    		foreach ($highlight AS $val)
    		{
    			if ($val = trim($val))
    			{
    				$replacewords[] = htmlspecialchars_uni($val);
    			}
    		}
    	}
    }
    
    // *********************************************************************************
    // make the forum jump in order to fill the forum caches
    $navpopup = array(
    	'id'    => 'showthread_navpopup',
    	'title' => $foruminfo['title_clean'],
    	'link'  => fetch_seo_url('thread', $threadinfo)
    );
    construct_quick_nav($navpopup);
    
    
    // *********************************************************************************
    // get forum info
    $forum = fetch_foruminfo($thread['forumid']);
    $foruminfo =& $forum;
    
    // *********************************************************************************
    // check forum permissions
    $forumperms = fetch_permissions($thread['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
	json_error(ERR_NO_PERMISSION);
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    {
	json_error(ERR_NO_PERMISSION);
    }
    
    // *********************************************************************************
    // check if there is a forum password and if so, ensure the user has it set
    if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'])) {
	// FRNR
	json_error(ERR_NEED_PASSWORD, RV_NEED_FORUM_PASSWORD);
    }
    
    // verify that we are at the canonical SEO url
    // and redirect to this if not
    //verify_seo_url('thread|js', $threadinfo, array('pagenumber' => $_REQUEST['pagenumber']));
    
    // *********************************************************************************
    // jump page if thread is actually a redirect
    if ($thread['open'] == 10)
    {
    	$destthreadinfo = fetch_threadinfo($threadinfo['pollid']);
    	exec_header_redirect(fetch_seo_url('thread|js', $destthreadinfo, $pageinfo));
    }
    
    // *********************************************************************************
    // get ignored users
    $ignore = array();
    if (trim($vbulletin->userinfo['ignorelist']))
    {
    	$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
    	foreach ($ignorelist AS $ignoreuserid)
    	{
    		$ignore["$ignoreuserid"] = 1;
    	}
    }
    DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
    
    // *********************************************************************************
    // filter out deletion notices if can't be seen
    if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($threadinfo['forumid']))
    {
	$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND deletionlog.type = 'post')";
    }
    else
    {
    	$deljoin = '';
    }

    $show['viewpost'] = (can_moderate($threadinfo['forumid'])) ? true : false;
    $show['managepost'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
    $show['approvepost'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts')) ? true : false;
    $show['managethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads')) ? true : false;
    $show['approveattachment'] = (can_moderate($threadinfo['forumid'], 'canmoderateattachments')) ? true : false;
    $show['inlinemod'] = (!$show['threadedmode'] AND ($show['managethread'] OR $show['managepost'] OR $show['approvepost'])) ? true : false;
    $show['spamctrls'] = ($show['inlinemod'] AND $show['managepost']);
    $url = $show['inlinemod'] ? SCRIPTPATH : '';
    
    // build inline moderation popup
    if ($show['popups'] AND $show['inlinemod'])
    {
    	$threadadmin_imod_menu_post = vB_Template::create('threadadmin_imod_menu_post')->render();
    }
    else
    {
    	$threadadmin_imod_menu_post = '';
    }
    
    // *********************************************************************************
    // find the page that we should be on to display this post
    if (!empty($postid) AND $threadedmode == 0)
    {
    	$postinfo = verify_id('post', $postid, 1, 1);
    	$threadid = $postinfo['threadid'];
    
    	$getpagenum = $db->query_first("
    		SELECT COUNT(*) AS posts
    		FROM " . TABLE_PREFIX . "post AS post
    		WHERE threadid = $threadid AND visible = 1
    		AND dateline " . iif(!$postorder, '<=', '>=') . " $postinfo[dateline]
    	");
    	$vbulletin->GPC['pagenumber'] = ceil($getpagenum['posts'] / $perpage);
    }
    
    // *********************************************************************************
    // update views counter
    if ($vbulletin->options['threadviewslive'])
    {
    	// doing it as they happen; for optimization purposes, this cannot use a DM!
    	$db->shutdown_query("
    		UPDATE " . TABLE_PREFIX . "thread
    		SET views = views + 1
    		WHERE threadid = " . intval($threadinfo['threadid'])
    	);
    }
    else
    {
    	// or doing it once an hour
    	$db->shutdown_query("
    		INSERT INTO " . TABLE_PREFIX . "threadviews (threadid)
    		VALUES (" . intval($threadinfo['threadid']) . ')'
    	);
    }
    
    // *********************************************************************************
    // display ratings if enabled
    $show['rating'] = false;
    if ($forum['allowratings'] == 1)
    {
    	if ($thread['votenum'] > 0)
    	{
    		$thread['voteavg'] = vb_number_format($thread['votetotal'] / $thread['votenum'], 2);
    		$thread['rating'] = intval(round($thread['votetotal'] / $thread['votenum']));
    
    		if ($thread['votenum'] >= $vbulletin->options['showvotes'])
    		{
    			$show['rating'] = true;
    		}
    	}
    
    	devdebug("threadinfo[vote] = $threadinfo[vote]");
    
    	if ($threadinfo['vote'])
    	{
    		$voteselected["$threadinfo[vote]"] = 'selected="selected"';
    		$votechecked["$threadinfo[vote]"] = 'checked="checked"';
    	}
    	else
    	{
    		$voteselected[0] = 'selected="selected"';
    		$votechecked[0] = 'checked="checked"';
    	}
    }
    
    // *********************************************************************************
    // set page number
    if ($vbulletin->GPC['pagenumber'] < 1)
    {
    	$vbulletin->GPC['pagenumber'] = 1;
    }
    else if ($vbulletin->GPC['pagenumber'] > ceil(($thread['replycount'] + 1) / $perpage))
    {
    	$vbulletin->GPC['pagenumber'] = ceil(($thread['replycount'] + 1) / $perpage);
    }
    // *********************************************************************************
    // initialise some stuff...
    $limitlower = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
    $limitupper = ($vbulletin->GPC['pagenumber']) * $perpage;
    $counter = 0;
    if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
    {
    	$threadview = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
    }
    else
    {
    	$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
    	if (!$threadview)
    	{
    		$threadview = $vbulletin->userinfo['lastvisit'];
    	}
    }
    $threadinfo['threadview'] = intval($threadview);
    $displayed_dateline = 0;
    
    ################################################################################
    ############################### SHOW POLL ######################################
    ################################################################################
    $poll = '';
    if ($thread['pollid'])
    {
    	$pollbits = '';
    	$counter = 1;
    	$pollid = $thread['pollid'];
    
    	$show['editpoll'] = iif(can_moderate($threadinfo['forumid'], 'caneditpoll'), true, false);
    
    	// get poll info
    	$pollinfo = $db->query_first_slave("
    		SELECT *
    		FROM " . TABLE_PREFIX . "poll
    		WHERE pollid = $pollid
    	");
    
    	require_once(DIR . '/includes/class_bbcode.php');
    	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
    
    	$pollinfo['question'] = $bbcode_parser->parse(unhtmlspecialchars($pollinfo['question']), $forum['forumid'], true);
    
    	$splitoptions = explode('|||', $pollinfo['options']);
    	$splitoptions = array_map('rtrim', $splitoptions);
    
    	$splitvotes = explode('|||', $pollinfo['votes']);
    
    	$showresults = 0;
    	$uservoted = 0;
    	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
    	{
    		$nopermission = 1;
    	}
    
    	if (!$pollinfo['active'] OR !$thread['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0) OR $nopermission)
    	{
    		//thread/poll is closed, ie show results no matter what
    		$showresults = 1;
    	}
    	else
    	{
    		//get userid, check if user already voted
    		$voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
    		if ($voted)
    		{
    			$uservoted = 1;
    		}
    	}
    
    	($hook = vBulletinHook::fetch_hook('showthread_poll_start')) ? eval($hook) : false;
    
    	if ($pollinfo['timeout'] AND !$showresults)
    	{
    		$pollendtime = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
    		$pollenddate = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
    		$show['pollenddate'] = true;
    	}
    	else
    	{
    		$show['pollenddate'] = false;
    	}
    
    	foreach ($splitvotes AS $index => $value)
    	{
    		$pollinfo['numbervotes'] += $value;
    	}
    
    	if ($vbulletin->userinfo['userid'] > 0)
    	{
    		$pollvotes = $db->query_read_slave("
    			SELECT voteoption
    			FROM " . TABLE_PREFIX . "pollvote
    			WHERE userid = " . $vbulletin->userinfo['userid'] . " AND pollid = $pollid
    		");
    		if ($db->num_rows($pollvotes) > 0)
    		{
    			$uservoted = 1;
    		}
    	}
    
    	if ($showresults OR $uservoted)
    	{
    		if ($uservoted)
    		{
    			$uservote = array();
    			while ($pollvote = $db->fetch_array($pollvotes))
    			{
    				$uservote["$pollvote[voteoption]"] = 1;
    			}
    		}
    	}
    
    	$left = vB_Template_Runtime::fetchStyleVar('left');
    	$right = vB_Template_Runtime::fetchStyleVar('right');
    	$option['open'] = $left[0];
    	$option['close'] = $right[0];
    
    	foreach ($splitvotes AS $index => $value)
    	{
    		$arrayindex = $index + 1;
    		$option['uservote'] = iif($uservote["$arrayindex"], true, false);
    		$option['question'] = $bbcode_parser->parse($splitoptions["$index"], $forum['forumid'], true);
    
    		// public link
    		if ($pollinfo['public'] AND $value)
    		{
    			$option['votes'] = '<a href="poll.php?' . $vbulletin->session->vars['sessionurl'] . 'do=showresults&amp;pollid=' . $pollinfo['pollid'] . '">' . vb_number_format($value) . '</a>';
    		}
    		else
    		{
    			$option['votes'] = vb_number_format($value);   //get the vote count for the option
    		}
    
    		$option['number'] = $counter;  //number of the option
    
    		//Now we check if the user has voted or not
    		if ($showresults OR $uservoted)
    		{ // user did vote or poll is closed
    
    			if ($value <= 0)
    			{
    				$option['percentraw'] = 0;
    			}
    			else if ($pollinfo['multiple'])
    			{
    				$option['percentraw'] = ($value < $pollinfo['voters']) ? $value / $pollinfo['voters'] * 100 : 100;
    			}
    			else
    			{
    				$option['percentraw'] = ($value < $pollinfo['numbervotes']) ? $value / $pollinfo['numbervotes'] * 100 : 100;
    			}
    			$option['percent'] = vb_number_format($option['percentraw'], 2);
    
    			$option['graphicnumber'] = $option['number'] % 6 + 1;
    			$option['barnumber'] = round($option['percent']) * 2;
    			$option['remainder'] = 201 - $option['barnumber'];
    
    			// Phrase parts below
    			if ($nopermission)
    			{
    				$pollstatus = $vbphrase['you_may_not_vote_on_this_poll'];
    			}
    			else if ($showresults)
    			{
    				$pollstatus = $vbphrase['this_poll_is_closed'];
    			}
    			else if ($uservoted)
    			{
    				$pollstatus = $vbphrase['you_have_already_voted_on_this_poll'];
    			}
    
    			($hook = vBulletinHook::fetch_hook('showthread_polloption')) ? eval($hook) : false;
    
    			$templater = vB_Template::create('pollresult');
    				$templater->register('names', $names);
    				$templater->register('option', $option);
    			$pollbits .= $templater->render();
    		}
    		else
    		{
    			($hook = vBulletinHook::fetch_hook('showthread_polloption')) ? eval($hook) : false;
    
    			if ($pollinfo['multiple'])
    			{
    				$templater = vB_Template::create('polloption_multiple');
    					$templater->register('option', $option);
    				$pollbits .= $templater->render();
    			}
    			else
    			{
    				$templater = vB_Template::create('polloption');
    					$templater->register('option', $option);
    				$pollbits .= $templater->render();
    			}
    		}
    		$counter++;
    	}
    
    	if ($pollinfo['multiple'])
    	{
    		$pollinfo['numbervotes'] = $pollinfo['voters'];
    		$show['multiple'] = true;
    	}
    
    	if ($pollinfo['public'])
    	{
    		$show['publicwarning'] = true;
    	}
    	else
    	{
    		$show['publicwarning'] = false;
    	}
    
    	$displayed_dateline = $threadinfo['lastpost'];
    
    	($hook = vBulletinHook::fetch_hook('showthread_poll_complete')) ? eval($hook) : false;
    
    	if ($showresults OR $uservoted)
    	{
    		$templater = vB_Template::create('pollresults_table');
    			$templater->register('pollbits', $pollbits);
    			$templater->register('pollenddate', $pollenddate);
    			$templater->register('pollendtime', $pollendtime);
    			$templater->register('pollinfo', $pollinfo);
    			$templater->register('pollstatus', $pollstatus);
    		$poll = $templater->render();
    	}
    	else
    	{
    		$templater = vB_Template::create('polloptions_table');
    			$templater->register('pollbits', $pollbits);
    			$templater->register('pollenddate', $pollenddate);
    			$templater->register('pollendtime', $pollendtime);
    			$templater->register('pollinfo', $pollinfo);
    		$poll = $templater->render();
    	}
    
    }
    
    // work out if quickreply should be shown or not
    if (
    	$vbulletin->options['quickreply']
    	AND
    	!$thread['isdeleted'] AND !is_browser('netscape') AND $vbulletin->userinfo['userid']
    	AND (
    		($vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown'])
    		OR
    		($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])
    	)
    	AND ($thread['open'] OR can_moderate($threadinfo['forumid'], 'canopenclose'))
    	AND (!fetch_require_hvcheck('post'))
    )
    {
    	$show['quickreply'] = true;
    }
    else
    {
    	$show['quickreply'] = false;
    	$show['wysiwyg'] = 0;
    	$quickreply = '';
    }
    $show['largereplybutton'] = (!$thread['isdeleted'] AND !$show['threadedmode'] AND $forum['allowposting'] AND !$show['search_engine']);
    if (!$forum['allowposting'])
    {
    	$show['quickreply'] = false;
    }
    
    $show['multiquote_global'] = ($vbulletin->options['multiquote'] AND $vbulletin->userinfo['userid']);
    if ($show['multiquote_global'])
    {
    	$vbulletin->input->clean_array_gpc('c', array(
    		'vbulletin_multiquote' => TYPE_STR
    	));
    	$vbulletin->GPC['vbulletin_multiquote'] = explode(',', $vbulletin->GPC['vbulletin_multiquote']);
    }
    
    // post is cachable if option is enabled, last post is newer than max age, and this user
    // isn't showing a sessionhash
    $post_cachable = (
    	$vbulletin->options['cachemaxage'] > 0 AND
    	(TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $thread['lastpost'] AND
    	$vbulletin->session->vars['sessionurl'] == ''
    );
    $saveparsed = '';
    $save_parsed_sigs = '';
    
    ($hook = vBulletinHook::fetch_hook('showthread_post_start')) ? eval($hook) : false;
    
    ################################################################################
    ####################### SHOW THREAD IN LINEAR MODE #############################
    ################################################################################
    if ($threadedmode == 0)
    {
    	// allow deleted posts to not be counted in number of posts displayed on the page;
    	// prevents issue with page count on forum display being incorrect
    	$ids = array();
    	$lastpostid = 0;
    
    	$hook_query_joins = $hook_query_where = '';
    	($hook = vBulletinHook::fetch_hook('showthread_query_postids')) ? eval($hook) : false;
    
    	if (empty($deljoin) AND !$show['approvepost'])
    	{
    		$totalposts = $threadinfo['replycount'] + 1;
    
    		if (can_moderate($thread['forumid']))
    		{
    			$coventry = '';
    		}
    		else
    		{
    			$coventry = fetch_coventry('string');
    		}
    
    		$getpostids = $db->query_read("
    			SELECT post.postid
    			FROM " . TABLE_PREFIX . "post AS post
    			$hook_query_joins
    			WHERE post.threadid = $threadid
    				AND post.visible = 1
    				" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
    				$hook_query_where
    			ORDER BY post.dateline $postorder
    			LIMIT $limitlower, $perpage
    		");
    		while ($post = $db->fetch_array($getpostids))
    		{
    			if (!isset($qrfirstpostid))
    			{
    				$qrfirstpostid = $post['postid'];
    			}
    			$qrlastpostid = $post['postid'];
    			$ids[] = $post['postid'];
    		}
    		$db->free_result($getpostids);
    
    		$lastpostid = $qrlastpostid;
    	}
    	else
    	{
    
    		$getpostids = $db->query_read("
    			SELECT post.postid, post.visible, post.userid
    			FROM " . TABLE_PREFIX . "post AS post
    			$hook_query_joins
    			WHERE post.threadid = $threadid
    				AND post.visible IN (1
    				" . (!empty($deljoin) ? ",2" : "") . "
    				" . ($show['approvepost'] ? ",0" : "") . "
    				)
    				$hook_query_where
    			ORDER BY post.dateline $postorder
    		");
    		$totalposts = 0;
    		if ($limitlower != 0)
    		{
    			$limitlower++;
    		}
    		while ($post = $db->fetch_array($getpostids))
    		{
    			if (!isset($qrfirstpostid))
    			{
    				$qrfirstpostid = $post['postid'];
    			}
    			$qrlastpostid = $post['postid'];
    			if ($post['visible'] == 1 AND !in_coventry($post['userid']) AND !$ignore[$post['userid']])
    			{
    				$totalposts++;
    			}
    			if ($totalposts < $limitlower OR $totalposts > $limitupper)
    			{
    				continue;
    			}
    
    			// remember, these are only added if they're going to be displayed
    			$ids[] = $post['postid'];
    			$lastpostid = $post['postid'];
    		}
    		$db->free_result($getpostids);
    	}
    
    	// '0' inside parenthesis in unlikely case we have no ids for this page
    	// (this could happen if the replycount is wrong in the db)
    	$postids = "post.postid IN (0" . implode(',', $ids) . ")";
    
    	// load attachments
    	if ($thread['attach'])
    	{
    		require_once(DIR . '/packages/vbattach/attach.php');
    		$attach = new vB_Attach_Display_Content($vbulletin, 'vBForum_Post');
    		$postattach = $attach->fetch_postattach(0, $ids);
    	}
    
    	$hook_query_fields = $hook_query_joins = '';
    	($hook = vBulletinHook::fetch_hook('showthread_query')) ? eval($hook) : false;
    
    	$posts = $db->query_read("
    		SELECT
    			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
    			user.*, userfield.*, usertextfield.*,
    			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
    			" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
    			" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
    			" . iif($deljoin, 'deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,') . "
    			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
    			editlog.reason AS edit_reason, editlog.hashistory,
    			postparsed.pagetext_html, postparsed.hasimages,
    			sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
    			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
    			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
    			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "post AS post
    		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
    		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
    		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
    		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
    		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
    		" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
    			$deljoin
    		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
    		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
    		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
    		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
    			$hook_query_joins
    		WHERE $postids
    		ORDER BY post.dateline $postorder
    	");
    
    	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canseethumbnails']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    	{
    		$vbulletin->options['attachthumbs'] = 0;
    	}
    
    	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    	{
    		$vbulletin->options['viewattachedimages'] = 0;
    	}
    
    	$postcount = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
    	if ($postorder)
    	{
    		// Newest first
    		$postcount = $totalposts - $postcount + 1;
    	}
    
    	$counter = 0;
    	$postbits = '';
    
    	$postbit_factory = new vB_Postbit_Factory();
    	$postbit_factory->registry =& $vbulletin;
    	$postbit_factory->forum =& $foruminfo;
    	$postbit_factory->thread =& $thread;
    	$postbit_factory->cache = array();
    	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
    
    	while ($post = $db->fetch_array($posts))
	{
    		if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
    		{
    			continue;
    		}
    
    		if ($post['visible'] == 1 AND !$tachyuser)
    		{
    			++$counter;
    			if ($postorder)
    			{
    				$post['postcount'] = --$postcount;
    			}
    			else
    			{
    				$post['postcount'] = ++$postcount;
    			}
    		}
    
    		if ($tachyuser)
    		{
    			$fetchtype = 'post_global_ignore';
    		}
    		else if ($ignore["$post[userid]"])
    		{
    			$fetchtype = 'post_ignore';
    		}
    		else if ($post['visible'] == 2)
    		{
    			$fetchtype = 'post_deleted';
    		}
    		else
    		{
    			$fetchtype = 'post';
    		}
    
    		if (
    			($vbulletin->GPC['viewfull'] AND $post['postid'] == $postinfo['postid'] AND $fetchtype != 'post')
    				AND
    			(can_moderate($threadinfo['forumid']) OR !$post['isdeleted'])
    		)
    		{
    			$fetchtype = 'post';
    		}

		if ($fetchtype != 'post' && $fetchtype != 'post_deleted') {
		    continue;
		}

    		($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;
    
    		$postbit_obj =& $postbit_factory->fetch_postbit($fetchtype);
    		if ($fetchtype == 'post')
    		{
    			$postbit_obj->highlight =& $replacewords;
    		}
    		$postbit_obj->cachable = $post_cachable;
    
    		$post['islastshown'] = ($post['postid'] == $lastpostid);
    		$post['isfirstshown'] = ($counter == 1 AND $fetchtype == 'post' AND $post['visible'] == 1);
    		$post['islastshown'] = ($post['postid'] == $lastpostid);
    		$post['attachments'] = $postattach["$post[postid]"];
    
    		$parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);
    
    		$postbits .= $postbit_obj->construct_postbit($post);
    
    		// Only show after the first post, counter isn't incremented for deleted/moderated posts
    		if ($post['isfirstshown'])
    		{
    			$postbits .= vB_Template::create('ad_showthread_firstpost')->render();
    		}
    
    		if ($post_cachable AND $post['pagetext_html'] == '')
    		{
    			if (!empty($saveparsed))
    			{
    				$saveparsed .= ',';
    			}
    			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . intval($postbit_obj->post_cache['has_images']) . ", '" . $db->escape_string($postbit_obj->post_cache['text']) . "', " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
    		}
    
    		if (!empty($postbit_obj->sig_cache) AND $post['userid'])
    		{
    			if (!empty($save_parsed_sigs))
    			{
    				$save_parsed_sigs .= ',';
    			}
    			$save_parsed_sigs .= "($post[userid], " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ", '" . $db->escape_string($postbit_obj->sig_cache['text']) . "', " . intval($postbit_obj->sig_cache['has_images']) . ")";
    		}
    
    		// get first and last post ids for this page (for big reply buttons)
    		if (!isset($FIRSTPOSTID))
    		{
    			$FIRSTPOSTID = $post['postid'];
    		}
    		$LASTPOSTID = $post['postid'];
    
    		if ($post['dateline'] > $displayed_dateline)
    		{
    			$displayed_dateline = $post['dateline'];
    			if ($displayed_dateline <= $threadview)
    			{
    				$updatethreadcookie = true;
    			}
		}

		// FRNR Start

                // find out if first post
                $getpost = $db->query_first("
                    SELECT firstpostid
                    FROM " . TABLE_PREFIX . "thread
                    WHERE threadid = $threadinfo[threadid]
                ");
                $isfirstpost = $getpost['firstpostid'] == $post['postid'];

                $candelete = false;
                if ($isfirstpost AND can_moderate($threadinfo['forumid'], 'canmanagethreads')) {
                    $candelete = true;
                } else if (!$isfirstpost AND can_moderate($threadinfo['forumid'], 'candeleteposts')) {
                    $candelete = true;
                } else if (((($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) AND !$isfirstpost) OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']) AND $isfirstpost)) AND $vbulletin->userinfo['userid'] == $post['userid']) {
                    $candelete = true;
                }

		// Get post date/time
		$postdate = vbdate($vbulletin->options['dateformat'], $post['dateline'], 1);
		$posttime = vbdate($vbulletin->options['timeformat'], $post['dateline']);

		$fr_images = array();
		$docattach = array();

		// Attachments (images).
		if (is_array($post['attachments']) && count($post['attachments']) > 0) {
		    foreach ($post['attachments'] as $attachment) {
			$lfilename = strtolower($attachment['filename']);
			if (strpos($lfilename, '.jpe') !== false ||
			    strpos($lfilename, '.png') !== false ||
			    strpos($lfilename, '.gif') !== false ||
			    strpos($lfilename, '.jpg') !== false ||
			    strpos($lfilename, '.jpeg') !== false) {
                                $tmp = array(
                                    'img' => $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'],
                                );
                                if ($vbulletin->options['attachthumbs']) {
                                    $tmp['tmb'] = $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'] . '&stc=1&thumb=1';
                                }
                                $fr_images[] = $tmp;
			}
			if (strpos($lfilename, '.pdf') !== false) {
			    $docattach[] = $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'];
			}
		    }
		}

		// Parse the post for quotes and inline images
		list ($text, $nuked_quotes, $images) = parse_post($post['pagetext'], $post['allowsmilie'] && $usesmilies);
		if (count($fr_images) > 0) {
		    $text .= "<br/>";
		    foreach ($fr_images as $attachment) {
			$text .= "<img src=\"{$attachment['img']}\"/>";
		    }
		}
		foreach ($images as $image) {
		    $fr_images[] = array(
			'img' => $image,
		    );
		}

		$avatarurl = '';

		// Avatar work
		if ($post['avatarurl']) {
		    $avatarurl = process_avatarurl($post['avatarurl']);
		}

        $userinfo = fetch_userinfo($post['userid']);
		$tmp = array(
		    'post_id' => $post['postid'],
		    'thread_id' => $post['threadid'],
		    'forum_id' => $foruminfo['forumid'],
		    'forum_title' => prepare_utf8_string($foruminfo['title_clean']),
		    'username' => prepare_utf8_string(strip_tags($post['username'])),
		    'joindate' => prepare_utf8_string($post['joindate']),
		    'usertitle' => prepare_utf8_string(strip_tags($post['usertitle'])),
		    'numposts' => $post['posts'] ? (string)$post['posts'] : '0',
		    'userid' => $post['userid'],
		    'title' => prepare_utf8_string($post['title']),
		    'online' => fetch_online_status($userinfo, false),
		    'post_timestamp' => prepare_utf8_string(date_trunc($postdate) . ' ' . $posttime),
		    'fr_images' => $fr_images,
                );
                if ($candelete) {
                    $tmp['candelete'] = true;
                }

		// Soft Deleted
		if ($post['visible'] == 2) {
		    $tmp['deleted'] = true;
		    $tmp['del_username'] = prepare_utf8_string($post['del_username']);
		    if ($post['del_reason']) {
			$tmp['del_reason'] = prepare_utf8_string($post['del_reason']);
		    }
		} else {
		    $tmp['text'] = $text;
		    $tmp['quotable'] = $nuked_quotes;
		    if ($post['editlink']) {
			$tmp['canedit'] = true;
			$tmp['edittext'] = prepare_utf8_string($post['pagetext']);
		    }
		}
		if ($avatarurl != '') {
		    $tmp['avatarurl'] = $avatarurl;
		}
		if (count($docattach) > 0) {
		    $tmp['docattach'] = $docattach;
		}
		if ($vbulletin->GPC['signature']) {
		    $sig = trim(remove_bbcode(strip_tags($post['signatureparsed']), true, true), '<a>');
		    $sig = str_replace(array("\t", "\r"), array('', ''), $sig);
		    $sig = str_replace("\n\n", "\n", $sig);
		    $tmp['sig'] = prepare_utf8_string($sig);
                }

                // Begin Support for Post Thanks Hack - http://www.vbulletin.org/forum/showthread.php?t=122944
                if ($vbulletin->userinfo['userid'] && function_exists('post_thanks_off') && function_exists('can_thank_this_post') && function_exists('thanked_already') && function_exists('fetch_thanks')) {
                    if (!post_thanks_off($thread['forumid'], $post, $thread['firstpostid'], THIS_SCRIPT)) {
                        global $ids;

                        if (can_thank_this_post($post, $thread['isdeleted'])) {
                            $tmp['canlike'] = true;
                        }
                        if (thanked_already($post, 0, true)) {
                            $tmp['likes'] = true;
                            if (!$vbulletin->options['post_thanks_delete_own']) {
                                $tmp['canlike'] = $tmp['likes'] = false;
                            }
                        }
                        $thanks = fetch_thanks($post['postid']);
                        $thank_users = array();
                        if (is_array($thanks)) {
                            foreach ($thanks as $thank) {
                                $thank_users[] = $thank['username'];
                            }
                        }
                        if (count($thank_users)) {
                            $tmp['likestext'] = prepare_utf8_string($vbphrase['fr_thanked_by'] . ': ' . join(', ', $thank_users));
                            $tmp['likesusers'] = join(', ', $thank_users);
                        }
                    }
                }
                // End Support for Post Thanks Hack

		$posts_out[] = $tmp;

		// FRNR End
    	}
    	$db->free_result($posts);
    	unset($post);
    
    	if ($postbits == '' AND $vbulletin->GPC['pagenumber'] > 1)
    	{
    		$pageinfo = array(
    			'page'     => $vbulletin->GPC['pagenumber'] - 1,
    		);
    		if (!empty($vbulletin->GPC['perpage']))
    		{
    			$pageinfo['pp'] = $perpage;
    		}
    		if (!empty($vbulletin->GPC['highlight']))
    		{
    			$pageinfo['highlight'] = urlencode($vbulletin->GPC['highlight']);
    		}
    
    		exec_header_redirect(fetch_seo_url('thread|js', $threadinfo, $pageinfo));
    	}
    
    	DEVDEBUG("First Post: $FIRSTPOSTID; Last Post: $LASTPOSTID");
    
    	$pageinfo = array();
    	if ($vbulletin->GPC['highlight'])
    	{
    		$pageinfo['highlight'] = urlencode($vbulletin->GPC['highlight']);
    	}
    	if (!empty($vbulletin->GPC['perpage']))
    	{
    		$pageinfo['pp'] = $perpage;
    	}
    
    	$pagenav = construct_page_nav(
    		$vbulletin->GPC['pagenumber'],
    		$perpage,
    		$totalposts,
    		'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]",
    		'',
    		'',
    		'thread',
    		$threadinfo,
    		$pageinfo
    	);
    
    	if ($thread['lastpost'] > $threadview)
    	{
    		if ($firstnew)
    		{
    			$firstunread = fetch_seo_url('thread', $threadinfo, array('page' => $vbulletin->GPC['pagenumber'])) . '#post' . $firstnew;
    			$show['firstunreadlink'] = true;
    		}
    		else
    		{
    			$firstunread = fetch_seo_url('thread', $threadinfo, array('goto' => 'newpost'));
    			$show['firstunreadlink'] = true;
    		}
    	}
    	else
    	{
    		$firstunread = '';
    		$show['firstunreadlink'] = false;
    	}
    
    	if ($vbulletin->userinfo['postorder'])
    	{
    		// disable ajax qr when displaying linear newest first
    		$show['allow_ajax_qr'] = 0;
    	}
    	else
    	{
    		// only allow ajax on the last page of a thread when viewing oldest first
    		$show['allow_ajax_qr'] = (($vbulletin->GPC['pagenumber'] == ceil($totalposts / $perpage)) ? 1 : 0);
    	}
    
    ################################################################################
    ################ SHOW THREAD IN THREADED OR HYBRID MODE ########################
    ################################################################################
    }
    else
    {
    	// ajax qr doesn't work with threaded controls
    	$show['allow_ajax_qr'] = 0;
    
    	require_once(DIR . '/includes/functions_threadedmode.php');
    
    	// save data
    	$ipostarray = array();
    	$postarray = array();
    	$userarray = array();
    	$postparent = array();
    	$postorder = array();
    	$hybridposts = array();
    	$deletedparents = array();
    	$totalposts = 0;
    	$links = '';
    	$cache_postids = '';
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    	($hook = vBulletinHook::fetch_hook('showthread_query_postids_threaded')) ? eval($hook) : false;
    
    	// get all posts
    	$listposts = $db->query_read("
    		SELECT
    			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
    			user.*, userfield.*
    			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "post AS post
    		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
    		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
    		$hook_query_joins
    		WHERE threadid = $threadid
    			$hook_query_where
    		ORDER BY postid
    	");
    
    	// $toppostid is the first post in the thread
    	// $curpostid is the postid passed from the URL, or if not specified, the first post in the thread
    	$ids = array();
    	while ($post = $db->fetch_array($listposts))
    	{
    		if (($post['visible'] == 2 AND !$deljoin) OR ($post['visible'] == 0 AND !$show['approvepost']) OR (in_coventry($post['userid']) AND !can_moderate($thread['forumid'])))
    		{
    			$deletedparents["$post[postid]"] = iif(isset($deletedparents["$post[parentid]"]), $deletedparents["$post[parentid]"], $post['parentid']);
    			continue;
    		}
    
    		if (empty($toppostid))
    		{
    			$toppostid = $post['postid'];
    		}
    		if (empty($postid))
    		{
    			if (empty($curpostid))
    			{
    				$curpostid = $post['postid'];
    				if ($threadedmode == 2 AND empty($vbulletin->GPC['postid']))
    				{
    					$vbulletin->GPC['postid'] = $curpostid;
    				}
    				$curpostparent = $post['parentid'];
    			}
    		}
    		else
    		{
    			if ($post['postid'] == $postid)
    			{
    				$curpostid = $post['postid'];
    				$curpostparent = $post['parentid'];
    			}
    		}
    
    		$postparent["$post[postid]"] = $post['parentid'];
    		$ipostarray["$post[parentid]"][] = $post['postid'];
    		$postarray["$post[postid]"] = $post;
    		$userarray["$post[userid]"] = $db->escape_string($post['username']);
    
    		$totalposts++;
    		$ids[] = $post['postid'];
    	}
    	$db->free_result($listposts);
    
    	// hooks child posts up to new parent if actual parent has been deleted or hidden
    	if (count($deletedparents) > 0)
    	{
    		foreach ($deletedparents AS $dpostid => $dparentid)
    		{
    
    			if (is_array($ipostarray[$dpostid]))
    			{
    				foreach ($ipostarray[$dpostid] AS $temppostid)
    				{
    					$postparent[$temppostid] = $dparentid;
    					$ipostarray[$dparentid][] = $temppostid;
    					$postarray[$temppostid]['parentid'] = $dparentid;
    				}
    				unset($ipostarray[$dpostid]);
    			}
    
    			if ($curpostparent == $dpostid)
    			{
    				$curpostparent = $dparentid;
    			}
    		}
    	}
    
    	unset($post, $listposts, $deletedparents);
    
    	if ($thread['attach'])
    	{
    		require_once(DIR . '/packages/vbattach/attach.php');
    		$attach = new vB_Attach_Display_Content($vbulletin, 'vBForum_Post');
    		$postattach = $attach->fetch_postattach(0, $ids);
    	}
    
    	// get list of usernames from post list
    	$userjs = '';
    	foreach ($userarray AS $userid => $username)
    	{
    		if ($userid)
    		{
    			$userjs .= "pu[$userid] = \"" . addslashes_js($username) . "\";\n";
    		}
    	}
    	unset($userarray, $userid, $username);
    
    	$parent_postids = fetch_post_parentlist($curpostid);
    	if (!$parent_postids)
    	{
    		$currentdepth = 0;
    	}
    	else
    	{
    		$currentdepth = sizeof(explode(',', $parent_postids));
    	}
    
    	sort_threaded_posts();
    
    	if (empty($curpostid))
    	{
    		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
    	}
    
    	if ($threadedmode == 2) // hybrid display mode
    	{
    		$numhybrids = sizeof($hybridposts);
    
    		if ($vbulletin->GPC['pagenumber'] < 1)
    		{
    			$vbulletin->GPC['pagenumber'] = 1;
    		}
    		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
    		if ($startat > $numhybrids)
    		{
    			$vbulletin->GPC['pagenumber'] = 1;
    			$startat = 0;
    		}
    		$endat = $startat + $perpage;
    		for ($i = $startat; $i < $endat; $i++)
    		{
    			if (isset($hybridposts["$i"]))
    			{
    				if (!isset($FIRSTPOSTID))
    				{
    					$FIRSTPOSTID = $hybridposts["$i"];
    				}
    				$cache_postids .= ",$hybridposts[$i]";
    				$LASTPOSTID = $hybridposts["$i"];
    			}
    		}
    
    		$pageinfo = array('p' => $vbulletin->GPC['postid']);
    		if ($vbulletin->GPC['highlight'])
    		{
    			$pageinfo['highlight'] = urlencode($vbulletin->GPC['highlight']);
    		}
    		if (!empty($vbulletin->GPC['perpage']))
    		{
    			$pageinfo['pp'] = $perpage;
    		}
    
    		$pagenav = construct_page_nav(
    			$vbulletin->GPC['pagenumber'],
    			$perpage,
    			$numhybrids,
    			'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]",
    			'',
    			'',
    			'thread',
    			$threadinfo,
    			$pageinfo
    		);
    	}
    	else // threaded display mode
    	{
    		$FIRSTPOSTID = $curpostid;
    		$LASTPOSTID = $curpostid;
    
    		// sort out which posts to cache:
    		if (!$vbulletin->options['threaded_maxcache'])
    		{
    			$vbulletin->options['threaded_maxcache'] = 999999;
    		}
    
    		// cache $vbulletin->options['threaded_maxcache'] posts
    		// take 0.25 from above $curpostid
    		// and take 0.75 below
    		if (sizeof($postorder) <= $vbulletin->options['threaded_maxcache']) // cache all, thread is too small!
    		{
    			$startat = 0;
    		}
    		else
    		{
    			if (($curpostidkey + ($vbulletin->options['threaded_maxcache'] * 0.75)) > sizeof($postorder))
    			{
    				$startat = sizeof($postorder) - $vbulletin->options['threaded_maxcache'];
    			}
    			else if (($curpostidkey - ($vbulletin->options['threaded_maxcache'] * 0.25)) < 0)
    			{
    				$startat = 0;
    			}
    			else
    			{
    				$startat = intval($curpostidkey - ($vbulletin->options['threaded_maxcache'] * 0.25));
    			}
    		}
    		unset($curpostidkey);
    
    		foreach ($postorder AS $postkey => $pid)
    		{
    			if ($postkey > ($startat + $vbulletin->options['threaded_maxcache'])) // got enough entries now
    			{
    				break;
    			}
    			if ($postkey >= $startat AND empty($morereplies["$pid"]))
    			{
    				$cache_postids .= ',' . $pid;
    			}
    		}
    
    		// get next/previous posts for each post in the list
    		// key: NAVJS[postid][0] = prev post, [1] = next post
    		$NAVJS = array();
    		$prevpostid = 0;
    		foreach ($postorder AS $pid)
    		{
    			$NAVJS["$pid"][0] = $prevpostid;
    			$NAVJS["$prevpostid"][1] = $pid;
    			$prevpostid = $pid;
    		}
    		$NAVJS["$toppostid"][0] = $pid; //prev button for first post
    		$NAVJS["$pid"][1] = $toppostid; //next button for last post
    
    		$navjs = '';
    		foreach ($NAVJS AS $pid => $info)
    		{
    			$navjs .= "pn[$pid] = \"$info[0],$info[1]\";\n";
    		}
    	}
    
    	unset($ipostarray, $postparent, $postorder, $NAVJS, $postid, $info, $prevpostid, $postkey);
    
    	$cache_postids = substr($cache_postids, 1);
    	if (empty($cache_postids))
    	{
    		// umm... something weird happened. Just prevent an error.
    		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
    	}
    
    	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
    	($hook = vBulletinHook::fetch_hook('showthread_query')) ? eval($hook) : false;
    
    	$cacheposts = $db->query_read("
    		SELECT
    			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
    			user.*, userfield.*, usertextfield.*,
    			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
    			" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,') . "
    			" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
    			" . iif($deljoin, "deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,") . "
    			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
    			editlog.reason AS edit_reason, editlog.hashistory,
    			postparsed.pagetext_html, postparsed.hasimages,
    			sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
    			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
    			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
    			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
    			$hook_query_fields
    		FROM " . TABLE_PREFIX . "post AS post
    		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
    		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
    		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
    		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
    		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
    		" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
    			$deljoin
    		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
    		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
    		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
    		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
    			$hook_query_joins
    		WHERE post.postid IN (" . $cache_postids . ") $hook_query_where
    	");
    
    	// re-initialise the $postarray variable
    	$postarray = array();
    	while ($post = $db->fetch_array($cacheposts))
    	{
    		$postarray["$post[postid]"] = $post;
    	}
    
    	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    	{
    		$vbulletin->options['viewattachedimages'] = 0;
    		$vbulletin->options['attachthumbs'] = 0;
    	}
    
    	// init
    	$postcount = 0;
    	$postbits = '';
    	$saveparsed = '';
    	$jspostbits = '';
    
    	$postbit_factory = new vB_Postbit_Factory();
    	$postbit_factory->registry =& $vbulletin;
    	$postbit_factory->forum =& $foruminfo;
    	$postbit_factory->thread =& $thread;
    	$postbit_factory->cache = array();
    	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
    
    	foreach (explode(',', $cache_postids) AS $id)
    	{
    		// get the post from the post array
    		if (!isset($postarray["$id"]))
    		{
    			continue;
    		}
    		$post = $postarray["$id"];
    
    		if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
    		{
    			continue;
    		}
    		if ($tachyuser)
    		{
    			$fetchtype = 'post_global_ignore';
    		}
    		else if ($ignore["$post[userid]"])
    		{
    			$fetchtype = 'post_ignore';
    		}
    		else if ($post['visible'] == 2)
    		{
    			$fetchtype = 'post_deleted';
    		}
    		else
    		{
    			$fetchtype = 'post';
    		}
    
    		if (
    			($vbulletin->GPC['viewfull'] AND $post['postid'] == $postinfo['postid'] AND $fetchtype != 'post')
    				AND
    			(can_moderate($threadinfo['forumid']) OR !$post['isdeleted'])
    		)
    		{
    			$fetchtype = 'post';
    		}
    
    		($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;
    
    		$postbit_obj =& $postbit_factory->fetch_postbit($fetchtype);
    		if ($fetchtype == 'post')
    		{
    			$postbit_obj->highlight =& $replacewords;
    		}
    		$postbit_obj->cachable = $post_cachable;
    
    		$post['postcount'] = ++$postcount;
    		$post['attachments'] =& $postattach["$post[postid]"];
    
    		$parsed_postcache = array('text' => '', 'images' => 1);
    
    		$bgclass = 'alt2';
    		if ($threadedmode == 2) // hybrid display mode
    		{
    			$postbits .= $postbit_obj->construct_postbit($post);
    		}
    		else // threaded display mode
    		{
    			$postbit = $postbit_obj->construct_postbit($post);
    
    			if ($curpostid == $post['postid'])
    			{
    				$curpostdateline = $post['dateline'];
    				$curpostbit = $postbit;
    			}
    			$postbit = preg_replace('#</script>#i', "<\\/scr' + 'ipt>", addslashes_js($postbit));
    			$jspostbits .= "pd[$post[postid]] = '$postbit';\n";
    
    		} // end threaded mode
    
    		if ($post_cachable AND $post['pagetext_html'] == '')
    		{
    			if (!empty($saveparsed))
    			{
    				$saveparsed .= ',';
    			}
    			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . intval($postbit_obj->post_cache['has_images']) . ", '" . $db->escape_string($postbit_obj->post_cache['text']) . "'," . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
    		}
    
    		if (!empty($postbit_obj->sig_cache) AND $post['userid'])
    		{
    			if (!empty($save_parsed_sigs))
    			{
    				$save_parsed_sigs .= ',';
    			}
    			$save_parsed_sigs .= "($post[userid], " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ", '" . $db->escape_string($postbit_obj->sig_cache['text']) . "', " . intval($postbit_obj->sig_cache['has_images']) . ")";
    		}
    
    		if ($post['dateline'] > $displayed_dateline)
    		{
    			$displayed_dateline = $post['dateline'];
    			if ($displayed_dateline <= $threadview)
    			{
    				$updatethreadcookie = true;
    			}
    		}
    
    	} // end while ($post)
    	$db->free_result($cacheposts);
    
    	if ($threadedmode == 1)
    	{
    		$postbits = $curpostbit;
    	}
    
    	$templater = vB_Template::create('showthread_list');
    		$templater->register('curpostid', $curpostid);
    		$templater->register('highlightwords', $highlightwords);
    		$templater->register('jspostbits', $jspostbits);
    		$templater->register('links', $links);
    		$templater->register('navjs', $navjs);
    		$templater->register('threadedmode', $threadedmode);
    		$templater->register('userjs', $userjs);
    	$threadlist = $templater->render();
    	unset($curpostbit, $post, $cacheposts, $parsed_postcache, $postbit);
    }
    
    ################################################################################
    ########################## END LINEAR / THREADED ###############################
    ################################################################################
    
    $effective_lastpost = max($displayed_dateline, $thread['lastpost']);
    
    
    // *********************************************************************************
    //set thread last view
    if ($thread['pollid'] AND $vbulletin->options['updatelastpost'] AND ($displayed_dateline == $thread['lastpost'] OR $threadview == $thread['lastpost']) AND $pollinfo['lastvote'] > $thread['lastpost'])
    {
    	$displayed_dateline = $pollinfo['lastvote'];
    }
    
    if ((!$vbulletin->GPC['posted'] OR $updatethreadcookie) AND $displayed_dateline AND $displayed_dateline > $threadview)
    {
    	mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], $displayed_dateline);
    }
    
    // FRNR Below

    fr_update_subsent($threadinfo['threadid'], $displayed_dateline);

    if (!is_array($posts_out)) {
	$posts_out = array();
    }


    // Figure out if we can post
    $canpost = true;

    if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
	$canpost = false;
    }

    if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
	$canpost = false;
    }

    if (!$threadinfo['open'])
    {
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
	    $canpost = false;
	}
    }

    if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
    {
	$canpost = false;
    }

    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid']))
    {
	$canpost = false;
    }

    $mod = 0;
    if (can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts')) {
	$mod |= MOD_DELETEPOST;
    }
    if (can_moderate($threadinfo['forumid'], 'canmanagethreads')) {
	if ($threadinfo['sticky']) {
	    $mod |= MOD_UNSTICK;
	} else {
	    $mod |= MOD_STICK;
	}
    }
    if (($threadinfo['visible'] != 2 AND can_moderate($threadinfo['forumid'], 'candeleteposts')) OR can_moderate($threadinfo['forumid'], 'canremoveposts') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread'] AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND ($vbulletin->options['edittimelimit'] == 0 OR $threadinfo['dateline'] > (TIMENOW - ($vbulletin->options['edittimelimit'] * 60))))) {
	$mod |= MOD_DELETETHREAD;
    }
    if (can_moderate($threadinfo['forumid'], 'canopenclose') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) {
	if ($threadinfo['open']) {
	    $mod |= MOD_CLOSE;
	} else {
	    $mod |= MOD_OPEN;
	}
    }
    if (can_moderate($threadinfo['forumid'], 'canmanagethreads') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) {
	$mod |= MOD_MOVETHREAD;
    }
    if ($show['spamctrls']) {
	$mod |= MOD_SPAM_CONTROLS;
    }

    $out = array(
	'posts' => $posts_out,
	'total_posts' => $totalposts,
	'page' => $vbulletin->GPC['pagenumber'],
	'canpost' => $canpost ? 1 : 0,
	'mod' => $mod,
	'pollid' => $thread['pollid'],
	'subscribed' => $threadinfo['issubscribed'] ? 1 : 0,
	'title' => prepare_utf8_string($thread['title']),
	'canattach' => ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid']),
    );
    if ($postid) {
	$out['gotopostid'] = $postid;
    }

    return $out;
}

function
do_get_poll ()
{
    global $vbulletin, $db, $foruminfo, $threadinfo, $postid, $vbphrase;

    if (empty($threadinfo['threadid'])) {
	json_error(ERR_INVALID_THREAD);
    }

    $threadid = $vbulletin->GPC['threadid'];

    $counter = 1;
    $pollid = $threadinfo['pollid'];

    if (!$pollid) {
	json_error(ERR_INVALID_THREAD);
    }

    $forumperms = fetch_permissions($threadinfo['forumid']);

    // get poll info
    $pollinfo = $db->query_first_slave("
	SELECT *
	FROM " . TABLE_PREFIX . "poll
	WHERE pollid = $pollid
    ");

    require_once(DIR . '/includes/class_bbcode.php');
    $bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

    $pollinfo['question'] = prepare_utf8_string(strip_tags(remove_bbcode(unhtmlspecialchars($pollinfo['question']), true, true)));

    $splitoptions = explode('|||', $pollinfo['options']);
    $splitoptions = array_map('rtrim', $splitoptions);

    $splitvotes = explode('|||', $pollinfo['votes']);

    $showresults = 0;
    $uservoted = 0;
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
    {
	$nopermission = 1;
    }

    if (!$pollinfo['active'] OR !$threadinfo['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0) OR $nopermission)
    {
	//thread/poll is closed, ie show results no matter what
	$showresults = 1;
    }
    else
    {
	//get userid, check if user already voted
	$voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
	if ($voted)
	{
	    $uservoted = 1;
	}
    }

    if ($pollinfo['timeout'] AND !$showresults)
    {
	$pollendtime = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
	$pollenddate = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
	$show['pollenddate'] = true;
    }
    else
    {
	$show['pollenddate'] = false;
    }

    foreach ($splitvotes AS $index => $value)
    {
	$pollinfo['numbervotes'] += $value;
    }

    if ($vbulletin->userinfo['userid'] > 0)
    {
	$pollvotes = $db->query_read_slave("
	    SELECT voteoption
	    FROM " . TABLE_PREFIX . "pollvote
	    WHERE userid = " . $vbulletin->userinfo['userid'] . " AND pollid = $pollid
	");
	if ($db->num_rows($pollvotes) > 0)
	{
	    $uservoted = 1;
	}
    }

    if ($showresults OR $uservoted)
    {
	if ($uservoted)
	{
	    $uservote = array();
	    while ($pollvote = $db->fetch_array($pollvotes))
	    {
		$uservote["$pollvote[voteoption]"] = 1;
	    }
	}
    }

    $options = array();

    foreach ($splitvotes AS $index => $value)
    {
	$arrayindex = $index + 1;
	if ($value <= 0)
	{
	    $percent = 0;
	}
	else
	{
	    $percent = vb_number_format(($value < $pollinfo['numbervotes']) ? $value / $pollinfo['numbervotes'] * 100 : 100, 0);
	}

	$options[] = array(
	    'voted' => iif($uservote["$arrayindex"], true, false),
	    'percent' => $percent,
	    'title' => prepare_utf8_string(strip_tags(remove_bbcode(unhtmlspecialchars($splitoptions["$index"]), true, true)) . $titleadd),
	    'votes' => $value,
	);
    }

    // Phrase parts below
    if ($nopermission)
    {
	$pollstatus = $vbphrase['you_may_not_vote_on_this_poll'];
    }
    else if ($showresults)
    {
	$pollstatus = $vbphrase['this_poll_is_closed'];
    }
    else if ($uservoted)
    {
	$pollstatus = $vbphrase['you_have_already_voted_on_this_poll'];
    }

    $out = array(
	'title' => prepare_utf8_string($pollinfo['question']),
	'pollstatus' => prepare_utf8_string($pollstatus),
	'options' => $options,
	'total' => $pollinfo['numbervotes'],
	'canvote' => (!$nopermission && !$uservoted),
    );
    if ($pollinfo['multiple']) {
	$out['multiple'] = true;
    }

    return $out;
}

function
do_vote_poll ()
{
    global $vbulletin, $db, $foruminfo, $threadinfo, $postid, $vbphrase;

    if (empty($threadinfo['threadid'])) {
	json_error(ERR_INVALID_THREAD);
    }

    $threadid = $vbulletin->GPC['threadid'];

    $counter = 1;
    $pollid = $threadinfo['pollid'];

    if (!$pollid) {
	json_error(ERR_INVALID_THREAD);
    }

    $forumperms = fetch_permissions($threadinfo['forumid']);

    // Get Poll info
    $pollinfo = verify_id('poll', $pollid, 0, 1);

	if (!$pollinfo['pollid'])
	{
		json_error(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
	}

	$vbulletin->input->clean_array_gpc('r', array(
	    'options' => TYPE_STR,
	));
	$options = split(',', $vbulletin->GPC['options']);

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
	{
		print_no_permission();
	}

	//check if poll is closed
	if (!$pollinfo['active'] OR !$threadinfo['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0))
	{ //poll closed
		 json_error(standard_error(fetch_error('pollclosed')));
	}

	//check if an option was selected
	if (true) 
	{
		// Query master to reduce the chance of multiple poll votes
		if ($uservoteinfo = $db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND pollid = $pollid
		"))
		{
			//the user has voted before
			json_error(standard_error(fetch_error('useralreadyvote')));
		}

		$totaloptions = substr_count($pollinfo['options'], '|||') + 1;

		//Error checking complete, lets get the options
		if ($pollinfo['multiple'])
		{
			$insertsql = '';
			$skip_voters = false;
			foreach ($options AS $val)
			{
				$val = intval($val);
				if ($val > 0 AND $val <= $totaloptions)
				{
					$pollvote =& datamanager_init('PollVote', $vbulletin, ERRTYPE_STANDARD);
					$pollvote->set_info('skip_voters', $skip_voters);
					$pollvote->set('pollid',     $pollid);
					$pollvote->set('votedate',   TIMENOW);
					$pollvote->set('voteoption', $val);
					$pollvote->set('userid', $vbulletin->userinfo['userid']);
					$pollvote->set('votetype', $val);
					if (!$pollvote->save(true, false, false, false, true))
					{
					    json_error(standard_error(fetch_error('useralreadyvote')));
					}

					$skip_voters = true;
				}
			}
		}
		else if ($options[0] > 0 AND $options[0] <= $totaloptions)
		{
				$pollvote =& datamanager_init('PollVote', $vbulletin, ERRTYPE_STANDARD);
				$pollvote->set('pollid',     $pollid);
				$pollvote->set('votedate',   TIMENOW);
				$pollvote->set('voteoption', $options[0]);
				$pollvote->set('userid', $vbulletin->userinfo['userid']);
				$pollvote->set('votetype',   0);
				if (!$pollvote->save(true, false, false, false, true))
				{
				    json_error(standard_error(fetch_error('useralreadyvote')));
				}
		}

		// make last reply date == last vote date
		if ($vbulletin->options['updatelastpost'])
		{
			// option selected in CP
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->set('lastpost', TIMENOW);
			$threadman->save();
		}

		($hook = vBulletinHook::fetch_hook('poll_vote_complete')) ? eval($hook) : false;
	}

	return array('success' => true);
}

function
do_get_post ()
{
    global $vbulletin, $db, $foruminfo, $threadinfo, $postid, $postinfo;
    
    $vbulletin->input->clean_array_gpc('r', array(
	'type' => TYPE_STR,
    ));

    $type = 'html';
    if ($vbulletin->GPC['type']) {
	$type = $vbulletin->GPC['type'];
    }

    if (!$postinfo['postid'])
    {
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
    }

    if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
    {
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
    }

    if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
    {
	standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']));
    }

    $forumperms = fetch_permissions($threadinfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
	json_error(ERR_NO_PERMISSION);
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    {
	json_error(ERR_NO_PERMISSION);
    }

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    $postbit_factory = new vB_Postbit_Factory();
    $postbit_factory->registry =& $vbulletin;
    $postbit_factory->forum =& $foruminfo;
    $postbit_factory->cache = array();
    $postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

    $post = $db->query_first_slave("
	SELECT
	post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
	    user.*, userfield.*, usertextfield.*,
	    " . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
	    IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid,
		" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
		" . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
		editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason, editlog.hashistory,
		postparsed.pagetext_html, postparsed.hasimages,
		sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
		sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
		" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
		$hook_query_fields
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
		" . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
		$hook_query_joins
		WHERE post.postid = $postid
    ");
    
    $types = vB_Types::instance();
    $contenttypeid = $types->getContentTypeID('vBForum_Post');

    $attachments = $db->query_read_slave("
		SELECT
			fd.thumbnail_dateline, fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_filesize,
			a.dateline, a.state, a.attachmentid, a.counter, a.contentid AS postid, a.filename,
			type.contenttypes
		FROM " . TABLE_PREFIX . "attachment AS a
		INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
		LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS type ON (fd.extension = type.extension)
		WHERE
			a.contentid = $postid
				AND
			a.contenttypeid = $contenttypeid
		ORDER BY a.attachmentid
	");

    $fr_images = array();
    while ($attachment = $db->fetch_array($attachments)) {
	$lfilename = strtolower($attachment['filename']);
	if (strpos($lfilename, '.jpe') !== false ||
	    strpos($lfilename, '.png') !== false ||
	    strpos($lfilename, '.gif') !== false ||
	    strpos($lfilename, '.jpg') !== false ||
	    strpos($lfilename, '.jpeg') !== false) {
            $tmp = array(
                'img' => $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'],
            );
            if ($vbulletin->options['attachthumbs']) {
                $tmp['tmb'] = $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'] . '&stc=1&thumb=1';
            }
            $fr_images[] = $tmp;
	}
    }
	
    $postbits = ''; 
    $postbit_obj =& $postbit_factory->fetch_postbit('post');
    $postbit_obj->cachable = $post_cachable;
    $postbits .= $postbit_obj->construct_postbit($post);

    if ($type == 'html') {
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$vbulletin->templatecache['bbcode_quote'] = '
<div style=\"margin:0px; margin-top:0px;\">
	<table cellpadding=\"$stylevar[cellpadding]\" cellspacing=\"0\" border=\"0\" width=\"100%\">
	<tr>
		<td class=\"alt2\" style=\"border:1px solid #777777;\">
			".(($show[\'username\']) ? ("
				<div>
					" . construct_phrase("$vbphrase[originally_posted_by_x]", "$username") . "
				</div>
				<div style=\"font-style:italic\">$message</div>
			") : ("
				$message
			"))."
		</td>
	</tr>
	</table>
</div>
	';

	$css = <<<EOF
<style type="text/css">
body {
  margin: 0;
  padding: 3;
  font: 13px Arial, Helvetica, sans-serif;
}
.alt2 {
  background-color: #e6edf5;
  font: 13px Arial, Helvetica, sans-serif;
}
html {
    -webkit-text-size-adjust: none;
}
</style>
EOF;

	$html = $css . $bbcode_parser->parse($post['pagetext']);
	$image = '';
    } else if ($type == 'facebook') {
	$html = fetch_censored_text(strip_bbcode(strip_quotes($post['pagetext']), false, true));
	if (count($fr_images)) {
	    $image = $fr_images[0]['img'];
	}
    }

    // Figure out if we can post
    $canpost = true;

    if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
	$canpost = false;
    }

    if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
	$canpost = false;
    }

    if (!$threadinfo['open'])
    {
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
	    $canpost = false;
	}
    }

    if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
    {
	$canpost = false;
    }

    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid']))
    {
	$canpost = false;
    }

    // Avatar work
    $avatarurl = '';
    if ($post['avatarurl']) {
	$avatarurl = process_avatarurl($post['avatarurl']);
    }

    // Get post date/time
    $postdate = vbdate($vbulletin->options['dateformat'], $post['dateline'], 1);
    $posttime = vbdate($vbulletin->options['timeformat'], $post['dateline']);
    
    // Parse the post for quotes and inline images
    list ($text, $nuked_quotes, $images) = parse_post($post['pagetext'], $post['allowsmilie'] && $usesmilies);

    $out = array(
	'html' => prepare_utf8_string($html),
	'post_id' => $post['postid'],
	'thread_id' => $post['threadid'],
	'forum_id' => $foruminfo['forumid'],
	'forum_title' => prepare_utf8_string($foruminfo['title_clean']),
	'username' => prepare_utf8_string(strip_tags($post['username'])),
	'joindate' => prepare_utf8_string($post['joindate']),
	'usertitle' => prepare_utf8_string(strip_tags($post['usertitle'])),
	'numposts' => $post['posts'] ? (string)$post['posts'] : '0',
	'userid' => $post['userid'],
	'title' => prepare_utf8_string($post['title']),
	'post_timestamp' => prepare_utf8_string(date_trunc($postdate) . ' ' . $posttime),
	'canpost' => $canpost,
	'quotable' => $nuked_quotes,
	'canattach' => ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid']),
	'edittext' => prepare_utf8_string($post['pagetext']),
    );
    if ($avatarurl != '') {
	$out['avatarurl'] = $avatarurl;
    }
    if ($post['editlink']) {
	$out['canedit'] = true;
    }
    if ($image != '') {
	$out['image'] = $image;
    }

    return $out;
}

?>
