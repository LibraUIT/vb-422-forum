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

define('THIS_SCRIPT', 'newreply');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_log_error.php');
require_once(DIR . '/includes/functions_prefix.php');

$vbulletin->options['cybfrules_enable_global'] = false;

function fr_build_new_post($type = 'thread', $foruminfo, $threadinfo, $postinfo, &$post, &$errors)
{
	//NOTE: permissions are not checked in this function

	// $post is passed by reference, so that any changes (wordwrap, censor, etc) here are reflected on the copy outside the function
	// $post[] includes:
	// title, iconid, message, parseurl, email, signature, preview, disablesmilies, rating
	// $errors will become any error messages that come from the checks before preview kicks in
	global $vbulletin, $vbphrase, $forumperms;

	// ### PREPARE OPTIONS AND CHECK VALID INPUT ###
	$post['disablesmilies'] = intval($post['disablesmilies']);
	$post['enablesmilies'] = ($post['disablesmilies'] ?  0 : 1);
	$post['folderid'] = intval($post['folderid']);
	$post['emailupdate'] = intval($post['emailupdate']);
	$post['rating'] = intval($post['rating']);
	$post['podcastsize'] = intval($post['podcastsize']);
	/*$post['parseurl'] = intval($post['parseurl']);
	$post['email'] = intval($post['email']);
	$post['signature'] = intval($post['signature']);
	$post['preview'] = iif($post['preview'], 1, 0);
	$post['iconid'] = intval($post['iconid']);
	$post['message'] = trim($post['message']);
	$post['title'] = trim(preg_replace('/&#0*32;/', ' ', $post['title']));
	$post['username'] = trim($post['username']);
	$post['posthash'] = trim($post['posthash']);
	$post['poststarttime'] = trim($post['poststarttime']);*/

	// Make sure the posthash is valid
	if (md5($post['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']) != $post['posthash'])
	{
		$post['posthash'] = 'invalid posthash'; // don't phrase me
	}

	// OTHER SANITY CHECKS
	$threadinfo['threadid'] = intval($threadinfo['threadid']);

	// create data manager
	if ($type == 'thread')
	{
		$dataman =& datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$dataman->set('prefixid', $post['prefixid']);
	}
	else
	{
		$dataman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	}

	// set info
	$dataman->set_info('preview', $post['preview']);
	$dataman->set_info('parseurl', $post['parseurl']);
	$dataman->set_info('posthash', $post['posthash']);
	$dataman->set_info('forum', $foruminfo);
	$dataman->set_info('thread', $threadinfo);
	if (!$vbulletin->GPC['fromquickreply'])
	{
		$dataman->set_info('show_title_error', true);
	}
	if ($foruminfo['podcast'] AND (!empty($post['podcasturl']) OR !empty($post['podcastexplicit']) OR !empty($post['podcastauthor']) OR !empty($post['podcastsubtitle']) OR !empty($post['podcastkeywords'])))
	{
		$dataman->set_info('podcastexplicit', $post['podcastexplicit']);
		$dataman->set_info('podcastauthor', $post['podcastauthor']);
		$dataman->set_info('podcastkeywords', $post['podcastkeywords']);
		$dataman->set_info('podcastsubtitle', $post['podcastsubtitle']);
		$dataman->set_info('podcasturl', $post['podcasturl']);
		if ($post['podcastsize'])
		{
			$dataman->set_info('podcastsize', $post['podcastsize']);
		}
	}

	// set options
	$dataman->setr('showsignature', $post['signature']);
	$dataman->setr('allowsmilie', $post['enablesmilies']);

	// set data
	$dataman->setr('userid', $vbulletin->userinfo['userid']);
	if ($vbulletin->userinfo['userid'] == 0)
	{
		$dataman->setr('username', $post['username']);
	}
	$dataman->setr('title', $post['title']);
	$dataman->setr('pagetext', $post['message']);
	$dataman->setr('iconid', $post['iconid']);

	// see if post has to be moderated or if poster in a mod
	if (
		((
			(
				($foruminfo['moderatenewthread'] AND $type == 'thread') OR ($foruminfo['moderatenewpost'] AND $type == 'reply')
			)
			OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['followforummoderation'])
		)
		AND !can_moderate($foruminfo['forumid']))
		OR
		($type == 'reply' AND (($postinfo['postid'] AND !$postinfo['visible'] AND !empty($postinfo['specifiedpost'])) OR !$threadinfo['visible']))
	)
	{
		// note: specified post comes from a variable passed into newreply.php
		$dataman->set('visible', 0);
		$post['visible'] = 0;
	}
	else
	{
		$dataman->set('visible', 1);
		$post['visible'] = 1;
	}

	if ($type != 'thread')
	{
		if ($postinfo['postid'] == 0)
		{
			// get parentid of the new post
			// we're not posting a new thread, so make this post a child of the first post in the thread
                        $getfirstpost = $vbulletin->db->query_first("SELECT firstpostid AS postid FROM " . TABLE_PREFIX . "thread WHERE threadid = $threadinfo[threadid]");
                        $parentid = $getfirstpost['postid'];
		}
		else
		{
			$parentid = $postinfo['postid'];
		}
		$dataman->setr('parentid', $parentid);
		$dataman->setr('threadid', $threadinfo['threadid']);
	}
	else
	{
		$dataman->setr('forumid', $foruminfo['forumid']);
	}

	$errors = array();

	// done!
	($hook = vBulletinHook::fetch_hook('newpost_process')) ? eval($hook) : false;

	if ($vbulletin->GPC['fromquickreply'] AND $post['preview'])
	{
		$errors = array();
		return;
	}

	if ($dataman->info['podcastsize'])
	{
		$post['podcastsize'] = $dataman->info['podcastsize'];
	}

	// check if this forum requires a prefix
	if ($type == 'thread' AND !$dataman->fetch_field('prefixid') AND ($foruminfo['options'] & $vbulletin->bf_misc_forumoptions['prefixrequired']))
	{
		// only require a prefix if we actually have options for this forum
		require_once(DIR . '/includes/functions_prefix.php');
		if (fetch_prefix_array($foruminfo['forumid']))
		{
			$dataman->error('thread_prefix_required');
		}
	}

	if ($type == 'thread' AND $post['taglist'])
	{
		$threadinfo['postuserid'] = $vbulletin->userinfo['userid'];

		require_once(DIR . '/includes/class_taggablecontent.php');
		$content = vB_Taggable_Content_Item::create($vbulletin, "vBForum_Thread",
			$dataman->thread['threadid'], $threadinfo);

		$limits = $content->fetch_tag_limits();
		$content->filter_tag_list_content_limits($post['taglist'], $limits, $tag_errors, true, false);

		if ($tag_errors)
		{
			foreach ($tag_errors AS $error)
			{
				$dataman->error($error);
			}
		}

		$dataman->setr('taglist', $post['taglist']);
	}

	$dataman->pre_save();
	$errors = array_merge($errors, $dataman->errors);

	if ($post['preview'])
	{
		return;
	}

	// ### DUPE CHECK ###
	$dupehash = md5($foruminfo['forumid'] . $post['title'] . $post['message'] . $vbulletin->userinfo['userid'] . $type);
	$prevpostfound = false;
	$prevpostthreadid = 0;

	if ($prevpost = $vbulletin->db->query_first("
		SELECT posthash.threadid, thread.title
		FROM " . TABLE_PREFIX . "posthash AS posthash
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = posthash.threadid)
		WHERE posthash.userid = " . $vbulletin->userinfo['userid'] . " AND
			posthash.dupehash = '" . $vbulletin->db->escape_string($dupehash) . "' AND
			posthash.dateline > " . (TIMENOW - 300) . "
	"))
	{
		if (($type == 'thread' AND $prevpost['threadid'] == 0) OR ($type == 'reply' AND $prevpost['threadid'] == $threadinfo['threadid']))
		{
			$prevpostfound = true;
			$prevpostthreadid = $prevpost['threadid'];
		}
	}

	// Redirect user to forumdisplay since this is a duplicate post
	if ($prevpostfound)
	{
		if ($type == 'thread')
		{
		    json_error(ERR_DUPE_THREAD, RV_POST_ERROR);
		}
		else
		{

		    json_error(ERR_DUPE_POST, RV_POST_ERROR);
		}
	}

	if (sizeof($errors) > 0)
	{
		return;
	}

	$id = $dataman->save();
	if ($type == 'thread')
	{
		$post['threadid'] = $id;
		$threadinfo =& $dataman->thread;
		$post['postid'] = $dataman->fetch_field('firstpostid');
	}
	else
	{
		$post['postid'] = $id;
	}
	$post['visible'] = $dataman->fetch_field('visible');

	$set_open_status = false;
	$set_sticky_status = false;
	if ($vbulletin->GPC['openclose'] AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
	{
		$set_open_status = true;
	}
	if ($vbulletin->GPC['stickunstick'] AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$set_sticky_status = true;
	}

	if ($set_open_status OR $set_sticky_status)
	{
		$thread =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		if ($type == 'thread')
		{
			$thread->set_existing($dataman->thread);
			if ($set_open_status)
			{
				$post['postpoll'] = false;
			}
		}
		else
		{
			$thread->set_existing($threadinfo);
		}

		if ($set_open_status)
		{
			$thread->set('open', ($thread->fetch_field('open') == 1 ? 0 : 1));
		}
		if ($set_sticky_status)
		{
			$thread->set('sticky', ($thread->fetch_field('sticky') == 1 ? 0 : 1));
		}

		$thread->save();
	}

	if ($type == 'thread')
	{
		require_once(DIR . '/includes/class_taggablecontent.php');
		$content = vB_Taggable_Content_Item::create($vbulletin, "vBForum_Thread",
			$dataman->thread['threadid'], $threadinfo);

		$limits = $content->fetch_tag_limits();
		$content->add_tags_to_content($post['taglist'], $limits);
	}

	// ### DO THREAD RATING ###
	build_thread_rating($post['rating'], $foruminfo, $threadinfo);

	// ### DO EMAIL NOTIFICATION ###
	if ($post['visible'] AND $type != 'thread' AND !in_coventry($vbulletin->userinfo['userid'], true)) // AND !$prevpostfound (removed as redundant - bug #22935)
	{
		exec_send_notification($threadinfo['threadid'], $vbulletin->userinfo['userid'], $post['postid']);
	}

	// ### DO THREAD SUBSCRIPTION ###
	if ($vbulletin->userinfo['userid'] != 0)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$post['emailupdate'] = verify_subscription_choice($post['emailupdate'], $vbulletin->userinfo, 9999);

		($hook = vBulletinHook::fetch_hook('newpost_subscribe')) ? eval($hook) : false;

		if (!$threadinfo['issubscribed'] AND $post['emailupdate'] != 9999)
		{ // user is not subscribed to this thread so insert it
			/*insert query*/
			$vbulletin->db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], $post[emailupdate], $post[folderid], 1)");
		}
		else
		{ // User is subscribed, see if they changed the settings for this thread
			if ($post['emailupdate'] == 9999)
			{	// Remove this subscription, user chose 'No Subscription'
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadinfo[threadid] AND userid = " . $vbulletin->userinfo['userid']);
			}
			else if ($threadinfo['emailupdate'] != $post['emailupdate'] OR $threadinfo['folderid'] != $post['folderid'])
			{
				// User changed the settings so update the current record
				/*insert query*/
				$vbulletin->db->query_write("REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], $post[emailupdate], $post[folderid], 1)");
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('newpost_complete')) ? eval($hook) : false;
}

function
do_post_message ()
{
    global $vbulletin, $db, $foruminfo, $forumperms, $threadinfo, $postinfo, $vbphrase, $forumid;
    global $permissions;

    if (!$foruminfo['forumid'])
    {
	json_error(ERR_INVALID_FORUM, RV_POST_ERROR);
    }

    $forumid = $foruminfo['forumid'];

    if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
	eval(standard_error(fetch_error('forumclosed')));
    }

    $forumperms = fetch_permissions($foruminfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']))
    {
	json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
    }

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    ($hook = vBulletinHook::fetch_hook('newthread_start')) ? eval($hook) : false;

	// Variables reused in templates
	$poststarttime =& $vbulletin->input->clean_gpc('r', poststarttime, TYPE_UINT);
        $posthash =  md5($vbulletin->GPC['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

	$vbulletin->input->clean_array_gpc('r', array(
		'wysiwyg'         => TYPE_BOOL,
		'preview'         => TYPE_STR,
		'message'         => TYPE_STR,
		'subject'         => TYPE_STR,
		'iconid'          => TYPE_UINT,
		'rating'          => TYPE_UINT,
		'prefixid'        => TYPE_NOHTML,
		'taglist'         => TYPE_NOHTML,

		'postpoll'        => TYPE_BOOL,
		'polloptions'     => TYPE_UINT,

		'signature'       => TYPE_BOOL,
		'disablesmilies'  => TYPE_BOOL,
		'parseurl'        => TYPE_BOOL,
		'folderid'        => TYPE_UINT,
		'subscribe'       => TYPE_BOOL,
		'emailupdate'     => TYPE_UINT,
		'stickunstick'    => TYPE_BOOL,
		'openclose'       => TYPE_BOOL,

		'username'        => TYPE_STR,
		'loggedinuser'    => TYPE_INT,

		'humanverify'     => TYPE_ARRAY,

		'podcasturl'      => TYPE_STR,
		'podcastsize'     => TYPE_UINT,
		'podcastexplicit' => TYPE_BOOL,
		'podcastkeywords' => TYPE_STR,
		'podcastsubtitle' => TYPE_STR,
		'podcastauthor'   => TYPE_STR,

		'sig' => TYPE_STR,
	));

	if ($vbulletin->GPC['message']) {
	    $vbulletin->GPC['message'] = prepare_remote_utf8_string($vbulletin->GPC['message']);
	}
	if ($vbulletin->GPC['subject']) {
	    $vbulletin->GPC['subject'] = prepare_remote_utf8_string($vbulletin->GPC['subject']);
	}
	
	if ($vbulletin->options['forumrunner_signature'] && $vbulletin->GPC['sig']) {
	    $vbulletin->GPC['message'] .= "\n\n" . prepare_remote_utf8_string($vbulletin->GPC['sig']);
	}

	$vbulletin->GPC['signature'] = $vbulletin->GPC_exists['signature'] = true;

	if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
	{
		// User was logged in when writing post but isn't now. If we got this
		// far, guest posts are allowed, but they didn't enter a username so
		// they'll get an error. Force them to log back in.
	    json_error(ERR_LOGGED_OUT, RV_POST_ERROR);
	}

	($hook = vBulletinHook::fetch_hook('newthread_post_start')) ? eval($hook) : false;

	$newpost['message'] =& $vbulletin->GPC['message'];

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']))
	{
		$vbulletin->GPC['postpoll'] = false;
	}

	if ($vbulletin->userinfo['autosubscribe'] != -1 AND !$threadinfo['issubscribed']) {
	    $vbulletin->GPC['folderid'] = 0;
	    $vbulletin->GPC_exists['folderid'] = true;
	    $vbulletin->GPC['emailupdate'] = $vbulletin->userinfo['autosubscribe'];
	    $vbulletin->GPC_exists['emailupdate'] = true;
	} else if ($threadinfo['issubscribed']) { // Don't alter current settings
	    $vbulletin->GPC['folderid'] = $threadinfo['folderid'];
	    $vbulletin->GPC_exists['folderid'] = true;
	    $vbulletin->GPC['emailupdate'] = $threadinfo['emailupdate'];
	    $vbulletin->GPC_exists['emailupdate'] = true;
	} else { // Don't don't add!
	    $vbulletin->GPC['emailupdate'] = 9999;
	    $vbulletin->GPC_exists['emailupdate'] = true;
	}

	$newpost['title'] =& $vbulletin->GPC['subject'];
	$newpost['iconid'] =& $vbulletin->GPC['iconid'];

	require_once(DIR . '/includes/functions_prefix.php');

	if (can_use_prefix($vbulletin->GPC['prefixid']))
	{
		$newpost['prefixid'] =& $vbulletin->GPC['prefixid'];
	}

	if ($show['tag_option'])
	{
		$newpost['taglist'] =& $vbulletin->GPC['taglist'];
	}
	$newpost['parseurl']        = (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode']);
	$newpost['signature']       =& $vbulletin->GPC['signature'];
	$newpost['preview']         =& $vbulletin->GPC['preview'];
	$newpost['disablesmilies']  =& $vbulletin->GPC['disablesmilies'];
	$newpost['rating']          =& $vbulletin->GPC['rating'];
	$newpost['username']        =& $vbulletin->GPC['username'];
	$newpost['postpoll']        =& $vbulletin->GPC['postpoll'];
	$newpost['polloptions']     =& $vbulletin->GPC['polloptions'];
	$newpost['folderid']        =& $vbulletin->GPC['folderid'];
	$newpost['humanverify']     =& $vbulletin->GPC['humanverify'];
	$newpost['poststarttime']   = $poststarttime;
	$newpost['posthash']        = $posthash;
	// moderation options
	$newpost['stickunstick']    =& $vbulletin->GPC['stickunstick'];
	$newpost['openclose']       =& $vbulletin->GPC['openclose'];
	$newpost['podcasturl']      =& $vbulletin->GPC['podcasturl'];
	$newpost['podcastsize']     =& $vbulletin->GPC['podcastsize'];
	$newpost['podcastexplicit'] =& $vbulletin->GPC['podcastexplicit'];
	$newpost['podcastkeywords'] =& $vbulletin->GPC['podcastkeywords'];
	$newpost['podcastsubtitle'] =& $vbulletin->GPC['podcastsubtitle'];
	$newpost['podcastauthor']   =& $vbulletin->GPC['podcastauthor'];
	$newpost['subscribe']       =& $vbulletin->GPC['subscribe'];

	if ($vbulletin->GPC_exists['emailupdate'])
	{
		$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
	}
	else
	{
		$newpost['emailupdate'] = array_pop($array = array_keys(fetch_emailchecked(array(), $vbulletin->userinfo)));
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		$newpost['emailupdate'] = 0;
	}

	fr_build_new_post('thread', $foruminfo, array(), array(), $newpost, $errors);

	if (sizeof($errors) > 0)
	{
	    fr_standard_error($errors[0]);
	}

    return array('success' => true);
}

function
do_post_reply ()
{
    global $vbulletin, $db, $foruminfo, $forumperms, $threadinfo, $postinfo, $vbphrase;
    global $permissions;

    if (!$threadinfo && !$postinfo) {
	json_error(ERR_INVALID_TOP, RV_POST_ERROR);
    }

    if (!$foruminfo['forumid'])
    {
	json_error(ERR_INVALID_FORUM, RV_POST_ERROR);
    }

    // ### CHECK IF ALLOWED TO POST ###
    if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
	json_error(strip_tags(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])), RV_POST_ERROR);
    }

    if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
	eval(standard_error(fetch_error('forumclosed')));
    }

    if (!$threadinfo['open'])
    {
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
	    eval(standard_error(fetch_error('threadclosed')));
	}
    }

    $forumperms = fetch_permissions($foruminfo['forumid']);
    if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
    {
	json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid']))
    {
	json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
    }

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    // *********************************************************************************
    // Tachy goes to coventry
    if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
    {
	json_error(strip_tags(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])), RV_POST_ERROR);
    }

    ($hook = vBulletinHook::fetch_hook('newreply_start')) ? eval($hook) : false;

	// Variables reused in templates
	$poststarttime =& $vbulletin->input->clean_gpc('r', poststarttime, TYPE_UINT);
        $posthash =  md5($vbulletin->GPC['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'        => TYPE_BOOL,
		'message'        => TYPE_STR,
		'quickreply'     => TYPE_BOOL,
		'fromquickreply' => TYPE_BOOL,
		'ajaxqrfailed'   => TYPE_BOOL,
		'folderid'       => TYPE_UINT,
		'emailupdate'    => TYPE_UINT,
		'subscribe'      => TYPE_BOOL,
		'title'          => TYPE_STR,
		'iconid'         => TYPE_UINT,
		'parseurl'       => TYPE_BOOL,
		'signature'      => TYPE_BOOL,
		'preview'        => TYPE_STR,
		'disablesmilies' => TYPE_BOOL,
		'username'       => TYPE_STR,
		'rate'           => TYPE_BOOL,
		'rating'         => TYPE_UINT,
		'stickunstick'   => TYPE_BOOL,
		'openclose'      => TYPE_BOOL,
		'ajax'           => TYPE_BOOL,
		'ajax_lastpost'  => TYPE_INT,
		'loggedinuser'   => TYPE_INT,
		'humanverify'    => TYPE_ARRAY,
		'multiquoteempty'=> TYPE_NOHTML,
		'specifiedpost'  => TYPE_BOOL,
		'return_node'    => TYPE_INT,
		
		'sig' => TYPE_STR,
	));
	
	if ($vbulletin->GPC['message']) {
	    $vbulletin->GPC['message'] = prepare_remote_utf8_string($vbulletin->GPC['message']);
	}
	if ($vbulletin->GPC['subject']) {
	    $vbulletin->GPC['subject'] = prepare_remote_utf8_string($vbulletin->GPC['subject']);
	}
	
	if ($vbulletin->userinfo['autosubscribe'] != -1 AND !$threadinfo['issubscribed']) {
	    $vbulletin->GPC['folderid'] = 0;
	    $vbulletin->GPC_exists['folderid'] = true;
	    $vbulletin->GPC['emailupdate'] = $vbulletin->userinfo['autosubscribe'];
	    $vbulletin->GPC_exists['emailupdate'] = true;
	} else if ($threadinfo['issubscribed']) { // Don't alter current settings
	    $vbulletin->GPC['folderid'] = $threadinfo['folderid'];
	    $vbulletin->GPC_exists['folderid'] = true;
	    $vbulletin->GPC['emailupdate'] = $threadinfo['emailupdate'];
	    $vbulletin->GPC_exists['emailupdate'] = true;
	} else { // Don't don't add!
	    $vbulletin->GPC['emailupdate'] = 9999;
	    $vbulletin->GPC_exists['emailupdate'] = true;
	}

	if ($vbulletin->options['forumrunner_signature'] && $vbulletin->GPC['sig']) {
	    $vbulletin->GPC['message'] .= "\n\n" . prepare_remote_utf8_string($vbulletin->GPC['sig']);
	}

	$vbulletin->GPC['signature'] = $vbulletin->GPC_exists['signature'] = true;

	if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
	{
		// User was logged in when writing post but isn't now. If we got this
		// far, guest posts are allowed, but they didn't enter a username so
		// they'll get an error. Force them to log back in.
	    json_error(ERR_LOGGED_OUT, RV_POST_ERROR);
	}

	($hook = vBulletinHook::fetch_hook('newreply_post_start')) ? eval($hook) : false;

	// ### PREP INPUT ###
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$newpost['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $foruminfo['allowhtml']);
	}
	else
	{
		$newpost['message'] = $vbulletin->GPC['message'];
	}

	if ($vbulletin->GPC['ajax'])
	{
		// posting via ajax so we need to handle those %u0000 entries
		$newpost['message'] = convert_urlencoded_unicode($newpost['message']);
	}

	if ($vbulletin->GPC['quickreply'])
	{
		$originalposter = fetch_quote_username($postinfo['username'] . ";$postinfo[postid]");
		$pagetext = trim(strip_quotes($postinfo['pagetext']));

		($hook = vBulletinHook::fetch_hook('newreply_post_quote')) ? eval($hook) : false;

		$templater = vB_Template::create('newpost_quote');
			$templater->register('originalposter', $originalposter);
			$templater->register('pagetext', $pagetext);
		$quotemessage = $templater->render(true);

		$newpost['message'] = trim($quotemessage) . "\n$newpost[message]";
	}


	if (isset($vbulletin->options['vbcmsforumid']) AND $foruminfo['forumid'] == $vbulletin->options['vbcmsforumid'])
	{
		$expire_cache = array('cms_comments_change');

		if ($threadinfo['threadid'])
		{
			$expire_cache[] = 'cms_comments_thread_' . intval($threadinfo['threadid']);
		}

		vB_Cache::instance()->event($expire_cache);
		vB_Cache::instance()->event('cms_comments_change_' . $threadinfo['threadid']);
		vB_Cache::instance()->cleanNow();
	}

	$newpost['title']          =& $vbulletin->GPC['title'];
	$newpost['iconid']         =& $vbulletin->GPC['iconid'];
	$newpost['parseurl']       = (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode']);
	$newpost['signature']      =& $vbulletin->GPC['signature'];
	$newpost['preview']        =& $vbulletin->GPC['preview'];
	$newpost['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$newpost['rating']         =& $vbulletin->GPC['rating'];
	$newpost['rate']           =& $newpost['rating'];
	$newpost['username']       =& $vbulletin->GPC['username'];
	$newpost['folderid']       =& $vbulletin->GPC['folderid'];
	$newpost['quickreply']     =& $vbulletin->GPC['quickreply'];
	$newpost['poststarttime']  =& $poststarttime;
	$newpost['posthash']       =& $posthash;
	$newpost['humanverify']    =& $vbulletin->GPC['humanverify'];
	// moderation options
	$newpost['stickunstick']   =& $vbulletin->GPC['stickunstick'];
	$newpost['openclose']      =& $vbulletin->GPC['openclose'];
	$newpost['subscribe']      =& $vbulletin->GPC['subscribe'];
	$newpost['ajaxqrfailed']   = $vbulletin->GPC['ajaxqrfailed'];

	if ($vbulletin->GPC['ajax'] AND $newpost['username'])
	{
		if ($newpost['username'])
		{
			$newpost['username'] = convert_urlencoded_unicode($newpost['username']);
		}
	}


	if ($vbulletin->GPC_exists['emailupdate'])
	{
		$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
	}
	else
	{
		$newpost['emailupdate'] = array_pop($array = array_keys(fetch_emailchecked($threadinfo, $vbulletin->userinfo)));
	}

	if ($vbulletin->GPC['specifiedpost'] AND $postinfo)
	{
		$postinfo['specifiedpost'] = true;
	}

	fr_build_new_post('reply', $foruminfo, $threadinfo, $postinfo, $newpost, $errors);
	
	if (sizeof($errors) > 0)
	{
	    fr_standard_error($errors[0]);
	}

    return array('success' => true);
}

function
do_post_edit ()
{
    global $vbulletin, $db, $foruminfo, $forumperms, $threadinfo;
    global $postinfo, $vbphrase, $stylevar, $permissions;

    $checked = array();
    $edit = array();
    $postattach = array();
    $contenttype = 'vBForum_Post';

    if (!$postinfo['postid'] OR $postinfo['isdeleted'] OR (!$postinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
	json_error(ERR_INVALID_TOP, RV_POST_ERROR);
    }

    if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
	json_error(ERR_INVALID_TOP, RV_POST_ERROR);
    }

    if ($vbulletin->options['wordwrap'])
    {
	$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
    }

    // get permissions info
    $_permsgetter_ = 'edit post';
    $forumperms = fetch_permissions($threadinfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)))
    {
	json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
    }

    $foruminfo = fetch_foruminfo($threadinfo['forumid'], false);

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    // need to get last post-type information
    cache_ordered_forums(1);

    // determine if we are allowed to be updating the thread's info
    $can_update_thread = (
	$threadinfo['firstpostid'] == $postinfo['postid']
	AND (can_moderate($threadinfo['forumid'], 'caneditthreads')
	OR ($postinfo['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW
    ));

	// otherwise, post is being edited
	if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
	{ // check for moderator
		if (!$threadinfo['open'])
		{
                    $vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
                    json_error(fetch_error('threadclosed'), RV_POST_ERROR);
		}
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']))
		{
                    json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
		}
		else
		{
			if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post
                            json_error(ERR_NO_PERMISSION, RV_POST_ERROR);
			}
			else
			{
				// check for time limits
				if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
				{
					json_error(fetch_error('edittimelimit', $vbulletin->options['edittimelimit'], $vbulletin->options['contactuslink']), RV_POST_ERROR);
				}
			}
		}
	}

	// Variables reused in templates
	$poststarttime =& $vbulletin->input->clean_gpc('r', poststarttime, TYPE_UINT);
        $posthash =  md5($vbulletin->GPC['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

	$vbulletin->input->clean_array_gpc('p', array(
		'stickunstick'    => TYPE_BOOL,
		'openclose'       => TYPE_BOOL,
		'wysiwyg'         => TYPE_BOOL,
		'message'         => TYPE_STR,
		'title'           => TYPE_STR,
		'prefixid'        => TYPE_NOHTML,
		'iconid'          => TYPE_UINT,
		'parseurl'        => TYPE_BOOL,
		'signature'	      => TYPE_BOOL,
		'disablesmilies'  => TYPE_BOOL,
		'reason'          => TYPE_NOHTML,
		'preview'         => TYPE_STR,
		'folderid'        => TYPE_UINT,
		'emailupdate'     => TYPE_UINT,
		'ajax'            => TYPE_BOOL,
		'advanced'        => TYPE_BOOL,
		'postcount'       => TYPE_UINT,
		'podcasturl'      => TYPE_STR,
		'podcastsize'     => TYPE_UINT,
		'podcastexplicit' => TYPE_BOOL,
		'podcastkeywords' => TYPE_STR,
		'podcastsubtitle' => TYPE_STR,
		'podcastauthor'   => TYPE_STR,

		'quickeditnoajax' => TYPE_BOOL, // true when going from an AJAX edit but not using AJAX
	));
	
	if ($vbulletin->GPC['message']) {
	    $vbulletin->GPC['message'] = prepare_remote_utf8_string($vbulletin->GPC['message']);
	}
	
	$vbulletin->GPC['signature'] = $vbulletin->GPC_exists['signature'] = true;
	
	// Make sure the posthash is valid

	($hook = vBulletinHook::fetch_hook('editpost_update_start')) ? eval($hook) : false;

	if (md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']) != $posthash)
	{
		$posthash = 'invalid posthash'; // don't phrase me
	}

	// ### PREP INPUT ###
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$edit['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $foruminfo['allowhtml']);
	}
	else
	{
		$edit['message'] =& $vbulletin->GPC['message'];
	}

	$cansubscribe = true;
	// Are we editing someone else's post? If so load that users subscription info for this thread.
	if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
	{
		if ($postinfo['userid'])
		{
			$userinfo = fetch_userinfo($postinfo['userid']);
			cache_permissions($userinfo);
		}

		$cansubscribe = (
			$userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview'] AND
			$userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads'] AND
			($threadinfo['postuserid'] == $userinfo['userid'] OR $userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers'])
		);

		if ($cansubscribe AND $otherthreadinfo = $db->query_first_slave("
			SELECT emailupdate, folderid
			FROM " . TABLE_PREFIX . "subscribethread
			WHERE threadid = $threadinfo[threadid] AND
				userid = $postinfo[userid] AND
				canview = 1"))
		{
			$threadinfo['issubscribed'] = true;
			$threadinfo['emailupdate'] = $otherthreadinfo['emailupdate'];
			$threadinfo['folderid'] = $otherthreadinfo['folderid'];
		}
		else
		{
			$threadinfo['issubscribed'] = false;
			// use whatever emailupdate setting came through
		}
	}

	if ($vbulletin->GPC['ajax'] OR $vbulletin->GPC['quickeditnoajax'])
	{
		// quick edit
		$tmpmessage = ($vbulletin->GPC['ajax'] ? convert_urlencoded_unicode($edit['message']) : $edit['message']);

		$edit = $postinfo;
		$edit['message'] =& $tmpmessage;
		$edit['title'] = unhtmlspecialchars($edit['title']);
		$edit['signature'] =& $edit['showsignature'];
		$edit['enablesmilies'] =& $edit['allowsmilie'];
		$edit['disablesmilies'] = $edit['enablesmilies'] ? 0 : 1;
		$edit['parseurl'] = true;
		$edit['prefixid'] = $threadinfo['prefixid'];

		$edit['reason'] = fetch_censored_text(
			$vbulletin->GPC['ajax'] ? convert_urlencoded_unicode($vbulletin->GPC['reason']) : $vbulletin->GPC['reason']
		);
	}
	else
	{
		$edit['iconid'] =& $vbulletin->GPC['iconid'];
		$edit['title'] =& $vbulletin->GPC['title'];
		$edit['prefixid'] = (($vbulletin->GPC_exists['prefixid'] AND can_use_prefix($vbulletin->GPC['prefixid'])) ? $vbulletin->GPC['prefixid'] : $threadinfo['prefixid']);

		$edit['podcasturl'] =& $vbulletin->GPC['podcasturl'];
		$edit['podcastsize'] =& $vbulletin->GPC['podcastsize'];
		$edit['podcastexplicit'] =& $vbulletin->GPC['podcastexplicit'];
		$edit['podcastkeywords'] =& $vbulletin->GPC['podcastkeywords'];
		$edit['podcastsubtitle'] =& $vbulletin->GPC['podcastsubtitle'];
		$edit['podcastauthor'] =& $vbulletin->GPC['podcastauthor'];

		// Leave this off for quickedit->advanced so that a post with unparsed links doesn't get parsed just by going to Advanced Edit
		$edit['parseurl'] = true;
		$edit['signature'] =& $vbulletin->GPC['signature'];
		$edit['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
		$edit['enablesmilies'] = $edit['allowsmilie'] = ($edit['disablesmilies']) ? 0 : 1;
		$edit['stickunstick'] =& $vbulletin->GPC['stickunstick'];
		$edit['openclose'] =& $vbulletin->GPC['openclose'];

		$edit['reason'] = fetch_censored_text($vbulletin->GPC['reason']);
		$edit['preview'] =& $vbulletin->GPC['preview'];
		$edit['folderid'] =& $vbulletin->GPC['folderid'];
		if (!$vbulletin->GPC['advanced'])
		{
			if ($vbulletin->GPC_exists['emailupdate'])
			{
				$edit['emailupdate'] =& $vbulletin->GPC['emailupdate'];
			}
			else
			{
				$edit['emailupdate'] = array_pop($array = array_keys(fetch_emailchecked($threadinfo)));
			}
		}
	}

	$dataman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$dataman->set_existing($postinfo);

	($hook = vBulletinHook::fetch_hook('editpost_update_process')) ? eval($hook) : false;

	// set info
	$dataman->set_info('parseurl', (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode'] AND $edit['parseurl']));
	$dataman->set_info('posthash', $posthash);
	$dataman->set_info('forum', $foruminfo);
	$dataman->set_info('thread', $threadinfo);
	$dataman->set_info('show_title_error', true);
	$dataman->set_info('podcasturl', $edit['podcasturl']);
	$dataman->set_info('podcastsize', $edit['podcastsize']);
	$dataman->set_info('podcastexplicit', $edit['podcastexplicit']);
	$dataman->set_info('podcastkeywords', $edit['podcastkeywords']);
	$dataman->set_info('podcastsubtitle', $edit['podcastsubtitle']);
	$dataman->set_info('podcastauthor', $edit['podcastauthor']);
	if ($postinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$dataman->set_info('user', $vbulletin->userinfo);
	}

	// set options
	$dataman->setr('showsignature', $edit['signature']);
	$dataman->setr('allowsmilie', $edit['enablesmilies']);

	// set data
	/*$dataman->setr('userid', $vbulletin->userinfo['userid']);
	if ($vbulletin->userinfo['userid'] == 0)
	{
		$dataman->setr('username', $post['username']);
	}*/
	$dataman->setr('title', $edit['title']);
	$dataman->setr('pagetext', $edit['message']);
	if ($postinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		$dataman->setr('iconid', $edit['iconid'], true, false);
	}
	else
	{
		$dataman->setr('iconid', $edit['iconid']);
	}

	$postusername = $vbulletin->userinfo['username'];

	$dataman->pre_save();
	if ($dataman->errors)
	{
		$errors = $dataman->errors;
	}
	if ($dataman->info['podcastsize'])
	{
		$edit['podcastsize'] = $dataman->info['podcastsize'];
	}

	if (sizeof($errors) > 0)
	{
	    fr_standard_error($errors[0]);
	}
	else if ($edit['preview'])
	{
		require_once(DIR . '/packages/vbattach/attach.php');
		$attach = new vB_Attach_Display_Content($vbulletin, 'vBForum_Post');
		$postattach = $attach->fetch_postattach($posthash, $postinfo['postid']);

		// ### PREVIEW POST ###
		$postpreview = process_post_preview($edit, $postinfo['userid'], $postattach);
		$previewpost = true;
		$_REQUEST['do'] = 'editpost';
	}
	else if ($vbulletin->GPC['advanced'])
	{
		// Don't display preview on QuickEdit->Advanced as parseurl is turned off and so the preview won't be correct unless the post originally had checked to not parse links
		// If you turn on parseurl then the opposite happens and you have to go unparse your links if that is what you want. Compromise
		$_REQUEST['do'] = 'editpost';
	}
	else
	{
		// ### POST HAS NO ERRORS ###

		$dataman->save();

		$update_edit_log = true;

		// don't show edited by AND reason unchanged - don't update edit log
		if (!($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']) AND $edit['reason'] == $postinfo['edit_reason'])
		{
			$update_edit_log = false;
		}

		if ($update_edit_log)
		{
			// ug perm: show edited by
			if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['noeditedbytime'] * 60)) OR !empty($edit['reason']))
			{
				// save the postedithistory
				if ($vbulletin->options['postedithistory'])
				{
					// insert original post on first edit
					if (!$db->query_first("SELECT postedithistoryid FROM " . TABLE_PREFIX . "postedithistory WHERE original = 1 AND postid = " . $postinfo['postid']))
					{
						$db->query_write("
							INSERT INTO " . TABLE_PREFIX . "postedithistory
								(postid, userid, username, title, iconid, dateline, reason, original, pagetext)
							VALUES
								($postinfo[postid],
								" . $postinfo['userid'] . ",
								'" . $db->escape_string($postinfo['username']) . "',
								'" . $db->escape_string($postinfo['title']) . "',
								$postinfo[iconid],
								" . $postinfo['dateline'] . ",
								'',
								1,
								'" . $db->escape_string($postinfo['pagetext']) . "')
						");
					}
					// insert the new version
					$db->query_write("
						INSERT INTO " . TABLE_PREFIX . "postedithistory
							(postid, userid, username, title, iconid, dateline, reason, pagetext)
						VALUES
							($postinfo[postid],
							" . $vbulletin->userinfo['userid'] . ",
							'" . $db->escape_string($vbulletin->userinfo['username']) . "',
							'" . $db->escape_string($edit['title']) . "',
							$edit[iconid],
							" . TIMENOW . ",
							'" . $db->escape_string($edit['reason']) . "',
							'" . $db->escape_string($edit['message']) . "')
					");
				}
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "editlog
						(postid, userid, username, dateline, reason, hashistory)
					VALUES
						($postinfo[postid],
						" . $vbulletin->userinfo['userid'] . ",
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($edit['reason']) . "',
						" . ($vbulletin->options['postedithistory'] ? 1 : 0) . ")
				");
			}
		}

		$date = vbdate($vbulletin->options['dateformat'], TIMENOW);
		$time = vbdate($vbulletin->options['timeformat'], TIMENOW);

		// initialize thread / forum update clauses
		$forumupdate = false;

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($threadinfo);
		$threadman->set_info('pagetext', $edit['message']);

		if ($can_update_thread AND $edit['title'] != '')
		{
			// need to update thread title and iconid
			if (!can_moderate($threadinfo['forumid']))
			{
				$threadman->set_info('skip_moderator_log', true);
			}

			$threadman->set_info('skip_first_post_update', true);

			if ($edit['title'] != $postinfo['title'])
			{
				$threadman->set('title', unhtmlspecialchars($edit['title']));
			}

			if ($edit['iconid'] != $postinfo['iconid'])
			{
				$threadman->set('iconid', $edit['iconid']);
			}

			if ($vbulletin->GPC_exists['prefixid'] AND can_use_prefix($vbulletin->GPC['prefixid']))
			{
				$threadman->set('prefixid', $vbulletin->GPC['prefixid']);
				if ($threadman->thread['prefixid'] === '' AND ($foruminfo['options'] & $vbulletin->bf_misc_forumoptions['prefixrequired']))
				{
					// the prefix wasn't valid or was set to an empty one, but that's not allowed
					$threadman->do_unset('prefixid');
				}
			}

			// do we need to update the forum counters?
			$forumupdate = ($foruminfo['lastthreadid'] == $threadinfo['threadid']) ? true : false;
		}

		// can this user open/close this thread if they want to?
		if ($vbulletin->GPC['openclose'] AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
		{
			$threadman->set('open', ($threadman->fetch_field('open') == 1 ? 0 : 1));
		}
		if ($vbulletin->GPC['stickunstick'] AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
		{
			$threadman->set('sticky', ($threadman->fetch_field('sticky') == 1 ? 0 : 1));
		}

		($hook = vBulletinHook::fetch_hook('editpost_update_thread')) ? eval($hook) : false;

		$threadman->save();

		// if this is a mod edit, then log it
		if ($vbulletin->userinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
		{
			$modlog = array(
				'threadid' => $threadinfo['threadid'],
				'forumid'  => $threadinfo['forumid'],
				'postid'   => $postinfo['postid']
			);
			log_moderator_action($modlog, 'post_x_edited', $postinfo['title']);
		}

		require_once(DIR . '/includes/functions_databuild.php');

		// do forum update if necessary
		if ($forumupdate)
		{
			build_forum_counters($threadinfo['forumid']);
		}

		// don't do thread subscriptions if we are doing quick edit
		if (!$vbulletin->GPC['ajax'] AND !$vbulletin->GPC['quickeditnoajax'])
		{
			// ### DO THREAD SUBSCRIPTION ###
			// We use $postinfo[userid] so that we update the user who posted this, not the user who is editing this
			if (!$threadinfo['issubscribed'] AND $edit['emailupdate'] != 9999)
			{
				// user is not subscribed to this thread so insert it
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid], 1)
				");
			}
			else
			{ // User is subscribed, see if they changed the settings for this thread
				if ($edit['emailupdate'] == 9999)
				{
					// Remove this subscription, user chose 'No Subscription'
					/*insert query*/
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "subscribethread
						WHERE threadid = $threadinfo[threadid]
							AND userid = $postinfo[userid]
					");
				}
				else if ($threadinfo['emailupdate'] != $edit['emailupdate'] OR $threadinfo['folderid'] != $edit['folderid'])
				{
					// User changed the settings so update the current record
					/*insert query*/
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
						VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid], 1)
					");
				}
			}
		}

		($hook = vBulletinHook::fetch_hook('editpost_update_complete')) ? eval($hook) : false;
	}

    return array('success' => true);
}


?>
