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

// Fake this so global.php doesn't boot us on forums requiring a login
$v = process_input(array('cmd' => STRING));
if ($v['cmd'] == 'get_new_updates' || $v['cmd'] == 'version') {
    $_REQUEST['do'] = 'login';
    define('SESSION_BYPASS', 1);
}
unset($v);

define('THIS_SCRIPT', 'login');
define('CSRF_PROTECTION', false);

$globaltemplates = array(
    'reportitem',
);

$specialtemplates = array(
    'userstats',
    'maxloggedin',
);

$phrasegroups = array('banning', 'threadmanage', 'posting', 'inlinemod');

require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_reportitem.php');

function
do_mark_read ()
{
    global $vbulletin, $foruminfo;

    mark_forums_read($foruminfo['forumid']);

    $tableinfo = $vbulletin->db->query_first("
	SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_data'
    ");
    if ($tableinfo) {
	if ($foruminfo['forumid'] > 0) {
	    require_once(DIR . '/includes/functions_misc.php');
	    $childforums = fetch_child_forums($foruminfo['forumid'], 'ARRAY');
	    $return_forumids = $childforums;
	    $return_forumids[] = $foruminfo['forumid'];
	    $vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "forumrunner_push_data AS forumrunner_push_data
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread
		    ON thread.threadid = forumrunner_push_data.vb_threadid
		SET forumrunner_push_data.vb_subsent = 0, forumrunner_push_data.vb_threadread = " . TIMENOW . "
		WHERE forumrunner_push_data.vb_userid = {$vbulletin->userinfo['userid']} AND thread.forumid IN (" . join(',', $return_forumids) . ")
	    ");
	} else {
	    $vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "forumrunner_push_data
		SET vb_subsent = 0, vb_threadread = " . TIMENOW . "
		WHERE vb_userid = {$vbulletin->userinfo['userid']} AND vb_threadid > 0
	    ");
	}
    }

    return array(
	'success' => 1,
    );
}

function
do_get_new_updates ()
{
    global $vbulletin;

    require_once(DIR . '/includes/functions_login.php');

    $vbulletin->input->clean_array_gpc('r', array(
	'username' => TYPE_STR,
	'password' => TYPE_STR,
	'md5_password' => TYPE_STR,
	'fr_username' => TYPE_STR,
	'fr_b' => TYPE_BOOL,
    ));

    if (!$vbulletin->GPC['username'] || (!$vbulletin->GPC['password'] && !$vbulletin->GPC['md5_password'])) {
	json_error(ERR_NO_PERMISSION);
    }
    
    $vbulletin->GPC['username'] = prepare_remote_utf8_string($vbulletin->GPC['username']);
    $vbulletin->GPC['password'] = prepare_remote_utf8_string($vbulletin->GPC['password']);

    if (!verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], $vbulletin->GPC['md5_password'], $vbulletin->GPC['md5_password'], $vbulletin->GPC['cookieuser'], true)) {
	json_error(ERR_NO_PERMISSION);
    }

    // Don't save the session, we just want pm & marked thread info
    process_new_login('', false, '');

    // Since we are not saving the session, fetch our userinfo
    $vbulletin->userinfo = fetch_userinfo($vbulletin->userinfo['userid']);

    cache_permissions($vbulletin->userinfo, true);

    $sub_notices  = get_sub_thread_updates();

    fr_update_push_user($vbulletin->GPC['fr_username'], $vbulletin->GPC['fr_b']);

    return array(
	'pm_notices' => $vbulletin->userinfo['pmunread'],
	'sub_notices' => $sub_notices,
    );
}

function
do_remove_fr_user ()
{
    global $vbulletin;

    $vbulletin->input->clean_array_gpc('r', array(
	'fr_username' => TYPE_STR,
    ));

    if (!$vbulletin->GPC['fr_username'] || !$vbulletin->userinfo['userid']) {
	json_error(ERR_NO_PERMISSION);
    }

    $tableinfo = $vbulletin->db->query_first("
	SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_users'
    ");
    if ($tableinfo) {
	$vbulletin->db->query_write("
	    DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
	    WHERE fr_username = '" . $vbulletin->db->escape_string($vbulletin->GPC['fr_username']) . "' AND vb_userid = {$vbulletin->userinfo['userid']}
	");
    }
}

function
do_version ()
{
    global $fr_version, $fr_platform, $vbulletin;

    if (file_exists(MCWD . '/sitekey.php')) {
        require_once(MCWD . '/sitekey.php');
    } else if (file_exists(MCWD . '/vb_sitekey.php')) {
        require_once(MCWD . '/vb_sitekey.php');
    }

    // See if they have the appropriate push entries
    $push = $vbulletin->db->query_first_slave("
	SELECT cronid
	FROM " . TABLE_PREFIX . "cron
	WHERE product = 'forumrunner' AND varname = 'forumrunnerpush' AND active=1
    ");
    
    return array(
	'version' => $fr_version,
	'platform' => $fr_platform,
	'push_enabled' => ($push) ? true : false,
	'charset' => get_local_charset(),
	'sitekey_setup' => (!$mykey || $mykey == '') ? false : true,
    );
}

function
do_stats ()
{
    global $vbulletin, $db;

    $activeusers = '';
    if (($vbulletin->options['displayloggedin'] == 1 OR $vbulletin->options['displayloggedin'] == 2 OR ($vbulletin->options['displayloggedin'] > 2 AND $vbulletin->userinfo['userid'])) AND !$show['search_engine'])
    {
	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
	$numbervisible = 0;
	$numberregistered = 0;
	$numberguest = 0;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('forumhome_loggedinuser_query')) ? eval($hook) : false;

	$forumusers = $db->query_read_slave("
	    SELECT
	    user.username, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible, user.usergroupid, user.lastvisit,
	    session.userid, session.inforum, session.lastactivity, session.badlocation,
	    IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
	    $hook_query_fields
	    FROM " . TABLE_PREFIX . "session AS session
	    LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
	    $hook_query_joins
	    WHERE session.lastactivity > $datecut
	    $hook_query_where
	    " . iif($vbulletin->options['displayloggedin'] == 1 OR $vbulletin->options['displayloggedin'] == 3, "ORDER BY username ASC") . "
	    ");

	if ($vbulletin->userinfo['userid'])
	{
	    // fakes the user being online for an initial page view of index.php
	    $vbulletin->userinfo['joingroupid'] = iif($vbulletin->userinfo['displaygroupid'], $vbulletin->userinfo['displaygroupid'], $vbulletin->userinfo['usergroupid']);
	    $userinfos = array
		(
		    $vbulletin->userinfo['userid'] => array
		    (
			'userid'            =>& $vbulletin->userinfo['userid'],
			'username'          =>& $vbulletin->userinfo['username'],
			'invisible'         =>& $vbulletin->userinfo['invisible'],
			'inforum'           => 0,
			'lastactivity'      => TIMENOW,
			'lastvisit'         =>& $vbulletin->userinfo['lastvisit'],
			'usergroupid'       =>& $vbulletin->userinfo['usergroupid'],
			'displaygroupid'    =>& $vbulletin->userinfo['displaygroupid'],
			'infractiongroupid' =>& $vbulletin->userinfo['infractiongroupid'],
		    )
		);
	}
	else
	{
	    $userinfos = array();
	}
	$inforum = array();

	while ($loggedin = $db->fetch_array($forumusers))
	{
	    $userid = $loggedin['userid'];
	    if (!$userid)
	    {	// Guest
		$numberguest++;
		if (!isset($inforum["$loggedin[inforum]"]))
		{
		    $inforum["$loggedin[inforum]"] = 0;
		}
		if (!$loggedin['badlocation'])
		{
		    $inforum["$loggedin[inforum]"]++;
		}
	    }
	    else if (empty($userinfos["$userid"]) OR ($userinfos["$userid"]['lastactivity'] < $loggedin['lastactivity']))
	    {
		$userinfos["$userid"] = $loggedin;
	    }
	}

	if (!$vbulletin->userinfo['userid'] AND $numberguest == 0)
	{
	    $numberguest++;
	}

	foreach ($userinfos AS $userid => $loggedin)
	{
	    $numberregistered++;
	    if ($userid != $vbulletin->userinfo['userid'] AND !$loggedin['badlocation'])
	    {
		if (!isset($inforum["$loggedin[inforum]"]))
		{
		    $inforum["$loggedin[inforum]"] = 0;
		}
		$inforum["$loggedin[inforum]"]++;
	    }
	    fetch_musername($loggedin);

	    ($hook = vBulletinHook::fetch_hook('forumhome_loggedinuser')) ? eval($hook) : false;

	    if (fetch_online_status($loggedin))
	    {
		$numbervisible++;
	    }
	}

	// memory saving
	unset($userinfos, $loggedin);

	$db->free_result($forumusers);

	$totalonline = $numberregistered + $numberguest;
	$numberinvisible = $numberregistered - $numbervisible;

	// ### MAX LOGGEDIN USERS ################################
	if (intval($vbulletin->maxloggedin['maxonline']) <= $totalonline)
	{
	    $vbulletin->maxloggedin['maxonline'] = $totalonline;
	    $vbulletin->maxloggedin['maxonlinedate'] = TIMENOW;
	    build_datastore('maxloggedin', serialize($vbulletin->maxloggedin), 1);
	}

	$recordusers = vb_number_format($vbulletin->maxloggedin['maxonline']);
	$recorddate = vbdate($vbulletin->options['dateformat'], $vbulletin->maxloggedin['maxonlinedate'], true);
	$recordtime = vbdate($vbulletin->options['timeformat'], $vbulletin->maxloggedin['maxonlinedate']);

	$showloggedinusers = true;
    }
    else
    {
	$showloggedinusers = false;
    }

    cache_ordered_forums(1, 1);

    // get total threads & posts from the forumcache
    $totalthreads = 0;
    $totalposts = 0;
    if (is_array($vbulletin->forumcache)) {
	foreach ($vbulletin->forumcache AS $forum) {
	    $totalthreads += $forum['threadcount'];
	    $totalposts += $forum['replycount'];
	}
    }
    $totalthreads = vb_number_format($totalthreads);
    $totalposts = vb_number_format($totalposts);

    // get total members and newest member from template
    $numbermembers = vb_number_format($vbulletin->userstats['numbermembers']);
    $newuserinfo = array(
	'userid'   => $vbulletin->userstats['newuserid'],
	'username' => $vbulletin->userstats['newusername']
    );
    $activemembers = vb_number_format($vbulletin->userstats['activemembers']);
    $showactivemembers = ($vbulletin->options['activememberdays'] > 0 AND ($vbulletin->options['activememberoptions'] & 2)) ? true : false;

    $out = array(
	'threads' => $totalthreads,
	'posts' => $totalposts,
	'members' => $numbermembers,
	'newuser' => $newuserinfo['username'],
    );

    $out = array_merge($out, array(
        'record_users' => $recordusers,
        'record_date' => $recorddate . ' ' . $recordtime,
        'online_members' => $numberregistered,
        'online_guests' => $numberguest,
    ));

    $top = $db->query_first_slave("SELECT username FROM " . TABLE_PREFIX . "user ORDER BY posts DESC LIMIT 1");
    if ($top['username']) {
	$out['top_poster'] = $top['username'];
    } else {
	$out['top_poster'] = 'N/A';
    }

    if ($showactivemembers) {
	$out['active_members'] = $activemembers;
    } else {
	$out['active_members'] = 'N/A';
    }

    return $out;

    return array(
	'top_poster' => '',
    );
}

function
do_report ()
{
    global $vbulletin, $postinfo, $threadinfo, $foruminfo;

    if (!$vbulletin->userinfo['userid']) {
	json_error(ERR_NO_PERMISSION);
    }

    $reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
    $reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

    if (!$reportthread AND !$reportemail) {
	standard_error(fetch_error('emaildisabled'));
    }

    $reportobj = new vB_ReportItem_Post($vbulletin);
    $reportobj->set_extrainfo('forum', $foruminfo);
    $reportobj->set_extrainfo('thread', $threadinfo);
    $perform_floodcheck = $reportobj->need_floodcheck();

    if ($perform_floodcheck) {
	$reportobj->perform_floodcheck_precommit();
    }

    $forumperms = fetch_permissions($threadinfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR
	!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR
	(($threadinfo['postuserid'] != $vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
    ) {
	json_error(ERR_NO_PERMISSION);
    }

    if (!$postinfo['postid']) {
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
    }

    if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid'])) {
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
    }

    if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid'])) {
	standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']));
    }

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    ($hook = vBulletinHook::fetch_hook('report_start')) ? eval($hook) : false;

    $vbulletin->input->clean_array_gpc('p', array(
	'reason' => TYPE_STR,
    ));

    if ($vbulletin->GPC['reason'] == '') {
	standard_error(fetch_error('noreason'));
    }

    if ($perform_floodcheck) {
	$reportobj->perform_floodcheck_commit();
    }

    $reportobj->do_report(prepare_remote_utf8_string($vbulletin->GPC['reason']), $postinfo);

    return array('success' => true);
}

// Begin Support for Post Thanks Hack - http://www.vbulletin.org/forum/showthread.php?t=122944
function
do_like ()
{
    global $vbulletin, $postinfo, $threadinfo, $forumid;

    @require_once(DIR . '/includes/functions_post_thanks.php');

    $vbulletin->input->clean_array_gpc('r', array(
        'postid' => TYPE_INT,
    ));

    if (!function_exists('fetch_thanks') || !$vbulletin->userinfo['userid']) {
        return array('success' => true);
    }

    $thanks = fetch_thanks($vbulletin->GPC['postid']);

    // Figure out if we've thanked this post
    $thanked = false;
    if (is_array($thanks)) {
        foreach ($thanks as $thank) {
            if ($thank['userid'] == $vbulletin->userinfo['userid']) {
                $thanked = true;
                break;
            }
        }
    }

    $threadinfo = verify_id('thread', $postinfo['threadid'], 1, 1);

    if ($thanked) {
        delete_thanks($postinfo, $vbulletin->userinfo['userid']);
    } else {
        $postinfo = array_merge($postinfo, fetch_userinfo($postinfo['userid']));
        if (post_thanks_off($threadinfo['forumid'], $postinfo, $threadinfo['firstpostid']) || !can_thank_this_post($postinfo, $threadinfo['isdeleted']) || thanked_already($postinfo))
        {
            return array('success' => true);
        }

        add_thanks($postinfo);
    }

    return array('success' => true);
}
// End Support for Post Thanks Hack

?>
