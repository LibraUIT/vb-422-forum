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

define('THIS_SCRIPT', 'login');
define('CSRF_PROTECTION', false);

$_REQUEST['do'] = $_REQUEST['cmd'];

$phrasegroups = array('timezone', 'user', 'register', 'cprofilefield');

require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');

vbsetcookie('skip_fr_detect', 'false');

function
do_login ()
{
    global $vbulletin, $fr_version, $fr_platform;

    $vbulletin->input->clean_array_gpc('r', array(
	'username' => TYPE_STR,
	'password' => TYPE_STR,
	'md5_password' => TYPE_STR,
	'fr_username' => TYPE_STR,
	'fr_b' => TYPE_BOOL,
    ));

    $navbg = null;
    if (strlen($vbulletin->options['forumrunner_branding_navbar_bg'])) {
	$navbg = $vbulletin->options['forumrunner_branding_navbar_bg'];
	if (is_iphone() && strlen($navbg) == 7) {
	    $r = hexdec(substr($navbg, 1, 2));
	    $g = hexdec(substr($navbg, 3, 2));
	    $b = hexdec(substr($navbg, 5, 2));
	    $navbg = "$r,$g,$b";
	}
    }

    $vbulletin->GPC['username'] = prepare_remote_utf8_string($vbulletin->GPC['username']);
    $vbulletin->GPC['password'] = prepare_remote_utf8_string($vbulletin->GPC['password']);

    $out = array(
	'v' => $fr_version,
	'p' => $fr_platform,
    );

    if ($navbg) {
	$out['navbg'] = $navbg;
    }

    if (is_iphone() && $vbulletin->options['forumrunner_admob_publisherid_iphone']) {
	$out['admob'] = $vbulletin->options['forumrunner_admob_publisherid_iphone'];
    } else if (is_android() && $vbulletin->options['forumrunner_admob_publisherid_android']) {
	$out['admob'] = $vbulletin->options['forumrunner_admob_publisherid_android'];
    }

    if ($vbulletin->options['forumrunner_google_analytics_id']) {
	$out['gan'] = $vbulletin->options['forumrunner_google_analytics_id'];
    }

    if ($vbulletin->options['forumrunner_facebook_application_id']) {
	$out['fb'] = $vbulletin->options['forumrunner_facebook_application_id'];
    }

    if ($vbulletin->options['forumrunner_cms_onoff']) {
	$out['cms'] = true;
	$out['cms_section'] = $vbulletin->options['forumrunner_cms_section'];
    }
    
    if ($vbulletin->options['forumrunner_enable_registration']) {
        $out['reg'] = true;
    }
    
    if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']) {
        $out['albums'] = true;
    }

    if (!$vbulletin->GPC['username'] || (!$vbulletin->GPC['password'] && !$vbulletin->GPC['md5_password'])) {
	// This could be an attempt to see if forums require login.  Check.
	$requires_authentication = false;
	if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])) {
	    $requires_authentication = true;
	}

	// If the forum is closed, require login!
	if (!$vbulletin->options['bbactive']) {
	    $requires_authentication = true;
	}

	$out += array(
	    'authenticated' => false,
	    'requires_authentication' => $requires_authentication,
	);
    } else {
	// can the user login?
	$strikes = verify_strike_status($vbulletin->GPC['username'], true);

	// make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
	$original_userinfo = $vbulletin->userinfo;

	if (!verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], $vbulletin->GPC['md5_password'], $vbulletin->GPC['md5_password'], true, true))
	{
	    exec_strike_user($vbulletin->GPC['username']);

	    if ($vbulletin->options['usestrikesystem']) {
		if ($strikes === false) {
		    $message = 'Incorrect login.  You have used up your login allowance.  Please wait 15 minutes before trying again.';
		} else {
		    $message = 'Incorrect login (' . ($strikes + 1) . ' of 5 tries allowed)';
		}
	    } else {
		$message = 'Incorrect login.';
	    }
	    json_error($message, RV_BAD_PASSWORD);
	}

	exec_unstrike_user($vbulletin->GPC['username']);

	// create new session
	process_new_login('', true, '');

	cache_permissions($vbulletin->userinfo, true);

	$vbulletin->session->save();

	// If the forum is closed, boot em
	if (!$vbulletin->options['bbactive'] &&
	    (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])))
	{
	    process_logout();

	    json_error(strip_tags($vbulletin->options['bbclosedreason']), RV_BAD_PASSWORD);
	}

	fr_update_push_user($vbulletin->GPC['fr_username'], $vbulletin->GPC['fr_b']);

	$out += array(
	    'authenticated' => true,
	    'username' => prepare_utf8_string($vbulletin->userinfo['username']),
	    'cookiepath' => $vbulletin->options['cookiepath'],
	);
    }

    return $out;
}

function
do_logout ()
{
    global $vbulletin;

    $vbulletin->userinfo['amemberid'] = false;

    $vbulletin->input->clean_array_gpc('r', array(
	'fr_username' => TYPE_STR,
    ));

    if (!$vbulletin->userinfo['userid']) {
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

    process_logout();

    $guestuser = array(
	'userid'      => 0,
	'usergroupid' => 0,
    );
    $permissions = cache_permissions($guestuser);

    $requires_authentication = false;
    if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])) {
	$requires_authentication = true;
    }
    // If the forum is closed, require login!
    if (!$vbulletin->options['bbactive']) {
	$requires_authentication = true;
    }
    return array(
	'success' => true,
	'requires_authentication' => $requires_authentication,
    );
}

function
do_register ()
{
    global $vbulletin, $vbphrase, $db;

    if ($vbulletin->userinfo['userid']) {
        json_error(ERR_NO_PERMISSION);
    }
    
    if (!$vbulletin->options['forumrunner_enable_registration']) {
        json_error(ERR_NO_PERMISSION);
    }

    $vbulletin->input->clean_array_gpc('r', array(
        'username'            => TYPE_STR,
        'email'               => TYPE_STR,
        'password'            => TYPE_STR,
        'password_md5'        => TYPE_STR,
        'birthday'            => TYPE_STR,
        'timezoneoffset'      => TYPE_NUM,
    ));

    // They are registering.  Lets find out what fields are required.
    if (!$vbulletin->options['allowregistration']) {
        standard_error(fetch_error('noregister'));
    }

    $out = array();

    if ($vbulletin->GPC['username']) {
        // Registering.

        $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);

        $vbulletin->GPC['coppauser'] = false;

        $userdata->set_info('coppauser', false);
	$userdata->set_info('coppapassword', $vbulletin->GPC['password']);
	$userdata->set_bitfield('options', 'coppauser', false);
	$userdata->set('parentemail', '');

        if (empty($vbulletin->GPC['username'])
            || empty($vbulletin->GPC['email'])
            || (empty($vbulletin->GPC['password']) && empty($vbulletin->GPC['password_md5'])))
        {
            standard_error(fetch_error('fieldmissing'));
        }

        $vbulletin->GPC['password_md5'] = strtolower($vbulletin->GPC['password_md5']);
        $vbulletin->GPC['passwordconfirm_md5'] = strtolower($vbulletin->GPC['password_md5']);

        $userdata->set('email', $vbulletin->GPC['email']);
        $userdata->set('username', $vbulletin->GPC['username']);
        $userdata->set('password', ($vbulletin->GPC['password_md5'] ? $vbulletin->GPC['password_md5'] : $vbulletin->GPC['password']));
        $userdata->set_bitfield('options', 'adminemail', 1);

        if ($vbulletin->options['verifyemail']) {
            $newusergroupid = 3;
        } else if ($vbulletin->options['moderatenewmembers'] || $vbulletin->GPC['coppauser']) {
            $newusergroupid = 4;
        } else {
            $newusergroupid = 2;
        }
        $userdata->set('usergroupid', $newusergroupid);

        $userdata->set('languageid', $vbulletin->userinfo['languageid']);

        $userdata->set_usertitle('', false, $vbulletin->usergroupcache["$newusergroupid"], false, false);

        $parts = preg_split('#/#', $vbulletin->GPC['birthday']);
        $day = $month = $year = '';
        if ($parts[1]) {
            $day = $parts[1];
        }
        if ($parts[0]) {
            $month = $parts[0];
        }
        if ($parts[2]) {
            $year = $parts[2];
        }
        $userdata->set('showbirthday', 0);
        $userdata->set('birthday', array(
            'day'   => $day,
            'month' => $month,
            'year'  => $year,
        ));

        $dst = 2;
        $userdata->set_dst($dst);
        $userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

        // register IP address
        $userdata->set('ipaddress', IPADDRESS);

        $userdata->pre_save();

        if (count($userdata->errors)) {
            // Just return one error for now.
            json_error(strip_tags($userdata->errors[0]));
        }

        $vbulletin->userinfo['userid'] = $userid = $userdata->save();

        if ($userid) {
            $userinfo = fetch_userinfo($userid);
            $userdata_rank =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
            $userdata_rank->set_existing($userinfo);
            $userdata_rank->set('posts', 0);
            $userdata_rank->save();

            require_once(DIR . '/includes/functions_login.php');
            $vbulletin->session->created = false;
            process_new_login('', false, '');

            // send new user email
            if ($vbulletin->options['newuseremail'] != '') {
                $username = $vbulletin->GPC['username'];
                $email = $vbulletin->GPC['email'];

                if ($birthday = $userdata->fetch_field('birthday')) {
                    $bday = explode('-', $birthday);
                    $year = vbdate('Y', TIMENOW, false, false);
                    $month = vbdate('n', TIMENOW, false, false);
                    $day = vbdate('j', TIMENOW, false, false);
                    if ($year > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000') {
                        require_once(DIR . '/includes/functions_misc.php');
                        $vbulletin->options['calformat1'] = mktimefix($vbulletin->options['calformat1'], $bday[2]);
                        if ($bday[2] >= 1970) {
                            $yearpass = $bday[2];
                        } else {
                            $yearpass = $bday[2] + 28 * ceil((1970 - $bday[2]) / 28);
                        }
                        $birthday = vbdate($vbulletin->options['calformat1'], mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
                    } else {
                        $birthday = vbdate($vbulletin->options['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
                    }

                    if ($birthday == '') {
                        if ($bday[2] == '0000') {
                            $birthday = "$bday[0]-$bday[1]";
                        } else {
                            $birthday = "$bday[0]-$bday[1]-$bday[2]";
                        }
                    }
                }

                if ($userdata->fetch_field('referrerid') AND $vbulletin->GPC['referrername']) {
                    $referrer = unhtmlspecialchars($vbulletin->GPC['referrername']);
                } else {
                    $referrer = $vbphrase['n_a'];
                }
                $ipaddress = IPADDRESS;

                eval(fetch_email_phrases('newuser', 0));

                $newemails = explode(' ', $vbulletin->options['newuseremail']);
                foreach ($newemails AS $toemail) {
                    if (trim($toemail)) {
                        vbmail($toemail, $subject, $message);
                    }
                }
            }

            $username = htmlspecialchars_uni($vbulletin->GPC['username']);
            $email = htmlspecialchars_uni($vbulletin->GPC['email']);

            // sort out emails and usergroups
            if ($vbulletin->options['verifyemail']) {
                $activateid = build_user_activation_id($userid, (($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

                eval(fetch_email_phrases('activateaccount'));

                vbmail($email, $subject, $message, true);
            } else if ($newusergroupid == 2) {
                if ($vbulletin->options['welcomemail']) {
                    eval(fetch_email_phrases('welcomemail'));
                    vbmail($email, $subject, $message);
                }
            }

            ($hook = vBulletinHook::fetch_hook('register_addmember_complete')) ? eval($hook) : false;

            // Let them log in again.
            process_logout();

            $out += array(
                'emailverify' => $vbulletin->options['verifyemail'] ? true : false,
            );
        }
    } else {
        $rules = preg_replace('/<a href=\"(.*?)\">(.*?)<\/a>/', "\\2", $vbphrase['fr_register_forum_rules']);

        $out += array(
            'rules' => prepare_utf8_string($rules),
            'birthday' => $vbulletin->options['reqbirthday'] ? true : false,
        );
    }

    return $out;
}

?>
