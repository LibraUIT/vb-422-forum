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

define('THIS_SCRIPT', 'profile');
define('CSRF_PROTECTION', false);

$phrasegroups = array(
    'wol',
    'user',
    'messaging',
    'cprofilefield',
    'reputationlevel',
    'infractionlevel',
    'posting',
);

require_once('./global.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumlist.php');
if (file_exists(DIR . '/itrader_global.php')) {
    require_once(DIR . '/itrader_global.php');
}
require_once(DIR . '/includes/class_userprofile.php');
require_once(DIR . '/includes/class_profileblock.php');

function
do_get_profile ()
{
    global $vbulletin, $db, $show, $vbphrase, $permissions, $imodcache;

    $vbulletin->input->clean_array_gpc('r', array(
	'userid' => TYPE_UINT,
    ));

    if (!$vbulletin->userinfo['userid'] && !$vbulletin->GPC['userid']) {
	json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
    }

    if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']))
    {
	json_error(ERR_NO_PERMISSION);
    }

    if (!$vbulletin->GPC['userid']) {
	$vbulletin->GPC['userid'] = $vbulletin->userinfo['userid'];
    }

    $fetch_userinfo_options = (
	FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
	FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
	FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
    );

    $userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, $fetch_userinfo_options);

    if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
    {
	json_error(ERR_NO_PERMISSION);
    }

    $posts = $userinfo['posts'];
    $joindate = vbdate($vbulletin->options['dateformat'], $userinfo['joindate']);

    $out = array(
        'username' => html_entity_decode($userinfo['username']),
        'online' => fetch_online_status($userinfo, false),
        'avatar_upload' => ($vbulletin->options['avatarenabled'] && ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'])),
        'posts' => $posts,
        'joindate' => $joindate,
    );

    $avatarurl_info = fetch_avatar_url($userinfo['userid']);
    if ($avatarurl_info) {
	$out['avatarurl'] = process_avatarurl($avatarurl_info[0]);
    }

    cache_moderators();
    $canbanuser = (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate(0, 'canbanusers'));

    if ($canbanuser) {
	$out['ban'] = true;
    }

    $groups = array();

    // About
    $out_group = array(
        'name' => 'about',
        'values' => array(
            array(
                'name' => prepare_utf8_string($vbphrase['posts']),
                'value' => strval(vb_number_format($userinfo['posts'])),
            ),
            array(
                'name' => prepare_utf8_string($vbphrase['join_date']),
                'value' => vbdate($vbulletin->options['dateformat'], $userinfo['joindate']),
            )
        ),
    );

    if (function_exists('itrader_user')) {
        itrader_user($userinfo);
        $out_group['values'][] = array(
            'name' => 'iTrader',
            'value' => vb_number_format($userinfo['tradescore']) . ', ' . $userinfo['tradepcnt'] . '%',
        );
        $out += array(
            'itrader_score' => vb_number_format($userinfo['tradescore']),
            'itrader_percent' => $userinfo['tradepcnt'] . '%',
        );
    }

    $groups[] = $out_group;

    $profileobj = new vB_UserProfile($vbulletin, $userinfo);
    $blockfactory = new vB_ProfileBlockFactory($vbulletin, $profileobj);
    $profileblock =& $blockfactory->fetch('ProfileFields');
    $profileblock->build_field_data(false);
    $profile = $profileblock->categories[0];

    // Additional information
    if (count($profile)) {
        $out_group = array(
            'name' => 'additional',
        );

        foreach ($profile as $profilefield) {
            $field_value = $userinfo["field$profilefield[profilefieldid]"];
            fetch_profilefield_display($profilefield, $field_value);
            if (!strlen(trim($field_value))) {
                continue;
            }
            $out_group['values'][] = array(
                'name' => prepare_utf8_string($profilefield['title']),
                'value' => prepare_utf8_string($profilefield['value']),
            );
        }

        if (count($out_group['values'])) {
            $groups[] = $out_group;
        }
    }

    $out['groups'] = $groups;

    return $out;
}

function
do_upload_avatar ()
{
    global $vbulletin, $db, $show, $vbphrase, $permissions;

    if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmodifyprofile']))
    {
        print_no_permission();
    }

    if (!$vbulletin->options['avatarenabled'])
    {
        standard_error(fetch_error('avatardisabled'));
    }

    if (($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'])) {
        $vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

        // begin custom avatar code
        require_once(DIR . '/includes/class_upload.php');
        require_once(DIR . '/includes/class_image.php');

        $upload = new vB_Upload_Userpic($vbulletin);

        $upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
        $upload->image =& vB_Image::fetch_library($vbulletin);
        $upload->maxwidth = $vbulletin->userinfo['permissions']['avatarmaxwidth'];
        $upload->maxheight = $vbulletin->userinfo['permissions']['avatarmaxheight'];
        $upload->maxuploadsize = $vbulletin->userinfo['permissions']['avatarmaxsize'];
        $upload->allowanimation = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;

        if (!$upload->process_upload($vbulletin->GPC['avatarurl'])) {
            standard_error($upload->fetch_error());
        }
    }

    // init user data manager
    $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
    $userdata->set_existing($vbulletin->userinfo);

    $userdata->set('avatarid', 0);

    ($hook = vBulletinHook::fetch_hook('profile_updateavatar_complete')) ? eval($hook) : false;

    $userdata->save();

    return array('success' => true);
}

