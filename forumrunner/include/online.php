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

define('THIS_SCRIPT', 'online');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('wol');

// get special data templates from the datastore
$specialtemplates = array(
	'maxloggedin',
	'wol_spiders',
);

require_once('./global.php');
require_once(DIR . '/includes/functions_online.php');
require_once(DIR . '/includes/functions_user.php');

function
do_online ()
{
    global $vbulletin, $db;

    $showmembers = true;
    $showguests = true;
    $showspiders = true;

    $datecut = TIMENOW - $vbulletin->options['cookietimeout'];
    $wol_event = array();
    $wol_pm = array();
    $wol_calendar = array();
    $wol_user = array();
    $wol_forum = array();
    $wol_link = array();
    $wol_thread = array();
    $wol_post = array();

    $sqlsort = 'user.username';
    $sortfield = 'username';

    $hook_query_fields = $hook_query_joins = $hook_query_where = '';
    ($hook = vBulletinHook::fetch_hook('online_query')) ? eval($hook) : false;

    $allusers = $db->query_read_slave("
	SELECT
	    user.username, session.useragent, session.location, session.lastactivity, user.userid, user.options, session.host, session.badlocation, session.incalendar, user.aim, user.icq, user.msn, user.yahoo, user.skype,
	    IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
	$hook_query_fields
	FROM " . TABLE_PREFIX . "session AS session
	". iif($vbulletin->options['WOLguests'], " LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid) ", ", " . TABLE_PREFIX . "user AS user") ."
	$hook_query_joins
	WHERE session.lastactivity > $datecut
	". iif(!$vbulletin->options['WOLguests'], " AND session.userid = user.userid", "") ."
	$hook_query_where
	ORDER BY $sqlsort $sortorder
    ");


    require_once(DIR . '/includes/class_postbit.php');
    while ($users = $db->fetch_array($allusers))
    {
	if ($users['userid'])
	{ // Reg'd Member
	    if (!$showmembers)
	    {
		continue;
	    }

	    $users = array_merge($users, convert_bits_to_array($users['options'] , $vbulletin->bf_misc_useroptions));

	    $key = $users['userid'];
	    if ($key == $vbulletin->userinfo['userid'])
	    { // in case this is the first view for the user, fake it that show up to themself
		$foundviewer = true;
	    }
	    if (empty($userinfo["$key"]['lastactivity']) OR ($userinfo["$key"]['lastactivity'] < $users['lastactivity']))
	    {
		unset($userinfo["$key"]); // need this to sort by lastactivity
		$userinfo["$key"] = $users;
		fetch_musername($users);
		$userinfo["$key"]['musername'] = $users['musername'];
		$userinfo["$key"]['useragent'] = htmlspecialchars_uni($users['useragent']);

		$userinfoavatar = fetch_userinfo($key, FETCH_USERINFO_AVATAR);
		fetch_avatar_from_userinfo($userinfoavatar, true, false);
		if ($userinfoavatar['avatarurl'] != '') {
		    $userinfo["$key"]['avatarurl'] = process_avatarurl($userinfoavatar['avatarurl']);
		}
		unset($userinfoavatar);

		if ($users['invisible'])
		{
		    if (($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $key == $vbulletin->userinfo['userid'])
		    {
			$userinfo["$key"]['hidden'] = '*';
			$userinfo["$key"]['invisible'] = 0;
		    }
		}
		if ($vbulletin->options['WOLresolve'] AND ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
		{
		    $userinfo["$key"]['host'] = @gethostbyaddr($users['host']);
		}
		$userinfo["$key"]['buddy'] = $buddy["$key"];
	    }
	}
	else
	{ // Guest or Spider..
	    $spider = '';

	    if ($vbulletin->options['enablespiders'] AND !empty($vbulletin->wol_spiders))
	    {
		if (preg_match('#(' . $vbulletin->wol_spiders['spiderstring'] . ')#si', $users['useragent'], $agent))
		{
		    $agent = strtolower($agent[1]);

		    // Check ip address
		    if (!empty($vbulletin->wol_spiders['agents']["$agent"]['lookup']))
		    {
			$ourip = ip2long($users['host']);
			foreach ($vbulletin->wol_spiders['agents']["$agent"]['lookup'] AS $key => $ip)
			{
			    if ($ip['startip'] AND $ip['endip']) // Range or CIDR
			    {
				if ($ourip >= $ip['startip'] AND $ourip <= $ip['endip'])
				{
				    $spider = $vbulletin->wol_spiders['agents']["$agent"];
				    break;
				}
			    }
			    else if ($ip['startip'] == $ourip) // Single IP
			    {
				$spider = $vbulletin->wol_spiders['agents']["$agent"];
				break;
			    }
			}
		    }
		    else
		    {
			$spider = $vbulletin->wol_spiders['agents']["$agent"];
		    }
		}
	    }

	    if ($spider)
	    {
		if (!$showspiders)
		{
		    continue;
		}
		$guests["$count"] = $users;
		$guests["$count"]['spider'] = $spider['name'];
		$guests["$count"]['spidertype'] = $spider['type'];
	    }
	    else
	    {
		if (!$showguests)
		{
		    continue;
		}
		$guests["$count"] = $users;
	    }

	    $guests["$count"]['username'] = $vbphrase['guest'];
	    $guests["$count"]['invisible'] = 0;
	    $guests["$count"]['displaygroupid'] = 1;
	    fetch_musername($guests["$count"]);
	    if ($vbulletin->options['WOLresolve'] AND ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
	    {
		$guests["$count"]['host'] = @gethostbyaddr($users['host']);
	    }
	    $guests["$count"]['count'] = $count + 1;
	    $guests["$count"]['useragent'] = htmlspecialchars_uni($users['useragent']);
	    $count++;

	    ($hook = vBulletinHook::fetch_hook('online_user')) ? eval($hook) : false;
	}
    }

    $online_users = array();

    if (is_array($userinfo)) {
	foreach ($userinfo as $userid => $user) {
	    if ($user['invisible']) {
		continue;
	    }
	    $tmp = array(
		'userid' => $userid,
		'username' => prepare_utf8_string(strip_tags($user['username'])),
	    );
	    if ($user['userid'] == $vbulletin->userinfo['userid']) {
		$tmp['me'] = true;
	    }
	    if ($user['avatarurl'] != '') {
		$tmp['avatarurl'] = $user['avatarurl'];
	    }
	    $online_users[] = $tmp;
	}
    }

    $numguests = 0;
    if (is_array($guests)) {
	$numguests = count($guests);
    }

    return array(
	'users' => $online_users,
	'num_guests' => $numguests,
    );
}

?>
