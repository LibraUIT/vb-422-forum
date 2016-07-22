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

define('THIS_SCRIPT', 'postings');
define('CSRF_PROTECTION', false);

$phrasegroups = array('threadmanage', 'prefix');

$specialtemplates = array();

require_once('./global.php');
require_once(DIR . '/includes/functions_threadmanage.php');
require_once(DIR . '/includes/functions_databuild.php');
require_once(DIR . '/includes/functions_log_error.php');

function
do_moderation ()
{
    global $vbulletin, $db, $foruminfo, $forumperms, $threadinfo, $postinfo, $vbphrase, $threadid;

    $postlimit = 400;
    $threadlimit = 200;
    $threadarray = array();
    $postarray = array();
    $postinfos = array();
    $forumlist = array();
    $threadlist = array();

    switch ($_REQUEST['do']) {
    case 'openclosethread':
    case 'dodeletethread':
    case 'domovethread':
    case 'updatethread':
    case 'domergethread':
    case 'stick':
    case 'removeredirect':
    case 'deletethread':
    case 'deleteposts':
    case 'movethread':
    case 'copythread':
    case 'editthread':
    case 'mergethread':
    case 'moderatethread':
	if (!$threadinfo['threadid']) {
	    standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']));
	}
    }

    if ($_REQUEST['do'] == 'getforums') {
	$forums = array();
	get_forums(-1, $forums);

	return array(
	    'forums' => $forums,
	);
    }

    if ($threadinfo['forumid']) {
	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (
	    (($threadinfo['postuserid'] != $vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
	    OR
	    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
	    OR
	    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
	)
	{
	    json_error(ERR_NO_PERMISSION);
	}
    }

    // Open/Close Thread
    if ($_POST['do'] == 'openclosethread') {
	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
	    if (can_moderate($threadinfo['forumid']))
	    {
		json_error(ERR_NO_PERMISSION);
	    }
	    else
	    {
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	    }
	}

	// permission check
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
	    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']))
	    {
		json_error(ERR_NO_PERMISSION);
	    }
	    else
	    {
		if (!is_first_poster($threadid))
		{
		    json_error(ERR_NO_PERMISSION);
		}
	    }
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// handles mod log
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('open', ($threadman->fetch_field('open') == 1 ? 0 : 1));

	($hook = vBulletinHook::fetch_hook('threadmanage_openclose')) ? eval($hook) : false;

	$threadman->save();
    }

    // Stick/Unstick Thread
    if ($_POST['do'] == 'stick') {
	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
	    if (can_moderate($threadinfo['forumid']))
	    {
		json_error(ERR_NO_PERMISSION);
	    }
	    else
	    {
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	    }
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
	    json_error(ERR_NO_PERMISSION);
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// handles mod log
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('sticky', ($threadman->fetch_field('sticky') == 1 ? 0 : 1));

	($hook = vBulletinHook::fetch_hook('threadmanage_stickunstick')) ? eval($hook) : false;
	$threadman->save();
    }

    // Delete Thread
    if ($_POST['do'] == 'dodeletethread') {
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'		=> TYPE_UINT, 	// 1=leave message; 2=removal
		'deletereason'		=> TYPE_STR,
		'keepattachments'	=> TYPE_BOOL,
	));

	$vbulletin->GPC['deletereason'] = prepare_remote_utf8_string($vbulletin->GPC['deletereason']);

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'canremoveposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
		    json_error(ERR_NO_PERMISSION);
		}
		else
		{
			standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
		}
	}

	$physicaldel = false;
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']))
		{
		    json_error(ERR_NO_PERMISSION);
		}
		else if ($threadinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
		{
		    json_error(ERR_NO_PERMISSION);
		}
		else
		{
			if (!$threadinfo['open'])
			{
			    json_error(ERR_NO_PERMISSION);
			}
			if (!is_first_poster($threadinfo['threadid']))
			{
			    json_error(ERR_NO_PERMISSION);
			}
		}
	}
	else
	{
		if (!can_moderate($threadinfo['forumid'], 'canremoveposts'))
		{
			$physicaldel = false;
		}
		else if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
		{
			$physicaldel = true;
		}
		else
		{
			$physicaldel = iif($vbulletin->GPC['deletetype'] == 1, false, true);
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$delinfo = array(
		'userid'          => $vbulletin->userinfo['userid'],
		'username'        => $vbulletin->userinfo['username'],
		'reason'          => $vbulletin->GPC['deletereason'],
		'keepattachments' => $vbulletin->GPC['keepattachments']
	);

	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->delete($foruminfo['countposts'], $physicaldel, $delinfo);
	unset($threadman);

	build_forum_counters($threadinfo['forumid']);
    }

    // Delete Posts
    if ($_POST['do'] == 'dodeleteposts') {
	$vbulletin->input->clean_array_gpc('p', array(
	    'postids' => TYPE_STR,
	));

	$postids = explode(',', $vbulletin->GPC['postids']);
	foreach ($postids AS $index => $postid) {
	    if (intval($postid) == 0) {
		unset($postids["$index"]);
	    } else {
		$postids["$index"] = intval($postid);
	    }
	}

	if (empty($postids)) {
	    standard_error(fetch_error('no_applicable_posts_selected'));
	}

	if (count($postids) > 400) {
	    standard_error(fetch_error('you_are_limited_to_working_with_x_posts', $postlimit));
	}
	
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'      => TYPE_UINT,	// 1 = soft delete post, 2 = physically remove.
		'keepattachments' => TYPE_BOOL,
		'deletereason'    => TYPE_STR
	));
	
	$vbulletin->GPC['deletereason'] = prepare_remote_utf8_string($vbulletin->GPC['deletereason']);

	$physicaldel = iif($vbulletin->GPC['deletetype'] == 1, false, true);

	// Validate posts
	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.parentid, post.visible, post.title, post.userid AS posteruserid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN (" . implode(',', $postids) . ")
		ORDER BY postid
	");

	$deletethreads = array();
	$firstpost = array();
	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
				OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
		    json_error(ERR_NO_PERMISSION);
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}
		else if (!can_moderate($post['forumid'], 'canremoveposts') AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}

		if (!can_moderate($post['forumid'], 'canremoveposts') AND $physicaldel)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}
		else if (
			!physicaldel
			AND (
				!can_moderate($post['forumid'], 'candeleteposts')
				AND (
					$post['posteruserid'] != $vbulletin->userinfo['userid']
					OR !($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['candeletepost'])
				)

			)
		)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;

		if ($post['firstpostid'] == $post['postid'])
		{	// deleting a thread so do not decremement the counters of any other posts in this thread
			$firstpost["$post[threadid]"] = true;
		}
		else if (!empty($firstpost["$post[threadid]"]))
		{
			$postarray["$post[postid]"]['skippostcount'] = true;
		}
	}

	if (empty($postarray))
	{
		standard_error(fetch_error('no_applicable_posts_selected'));
	}
	
	$firstpost = false;
	$gotothread = true;
	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($post);
		$postman->delete(($foruminfo['countposts'] AND !$post['skippostcount']), $post['threadid'], $physicaldel, array(
			'userid'          => $vbulletin->userinfo['userid'],
			'username'        => $vbulletin->userinfo['username'],
			'reason'          => $vbulletin->GPC['deletereason'],
			'keepattachments' => $vbulletin->GPC['keepattachments']
		));
		unset($postman);
	}

	foreach(array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	foreach(array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	($hook = vBulletinHook::fetch_hook('inlinemod_dodeleteposts')) ? eval($hook) : false;
    }

    // Move Thread
    if ($_POST['do'] == 'domovethread') {
	$vbulletin->input->clean_array_gpc('p', array(
		'destforumid'      => TYPE_UINT,
		'redirect'         => TYPE_STR,
		'title'            => TYPE_NOHTML,
		'redirectprefixid' => TYPE_NOHTML,
		'redirecttitle'    => TYPE_NOHTML,
		'period'           => TYPE_UINT,
		'frame'            => TYPE_STR,
	));
	
	$vbulletin->GPC['title'] = prepare_remote_utf8_string($vbulletin->GPC['title']);
	$vbulletin->GPC['redirecttitle'] = prepare_remote_utf8_string($vbulletin->GPC['redirecttitle']);
	$vbulletin->GPC['redirectprefixid'] = prepare_remote_utf8_string($vbulletin->GPC['redirectprefixid']);

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
		    json_error(ERR_NO_PERMISSION);
		}
		else
		{
			standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
		}
	}

	// check whether dest can contain posts
	$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
	$destforuminfo = fetch_foruminfo($destforumid);
	if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
	{
		standard_error(fetch_error('moveillegalforum'));
	}

	if (($threadinfo['isdeleted'] AND !can_moderate($destforuminfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($destforuminfo['forumid'], 'canmoderateposts')))
	{
		## Insert proper phrase about not being able to move a hidden thread to a forum you can't moderateposts in or a deleted thread to a forum you can't deletethreads in
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}

	// check source forum permissions
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove']))
		{
			json_error(ERR_NO_PERMISSION);
		}
		else
		{
			if (!$threadinfo['open'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']))
			{
				json_error(ERR_NO_PERMISSION);
			}
			if (!is_first_poster($threadid))
			{
				json_error(ERR_NO_PERMISSION);
			}
		}
	}

	// check destination forum permissions
	$destforumperms = fetch_permissions($destforuminfo['forumid']);
	if (!($destforumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		json_error(ERR_NO_PERMISSION);
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
	verify_forum_password($destforuminfo['forumid'], $destforuminfo['password']);

	// check to see if this thread is being returned to a forum it's already been in
	// if a redirect exists already in the destination forum, remove it
	if ($checkprevious = $db->query_first_slave("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = $destforuminfo[forumid] AND open = 10 AND pollid = $threadid"))
	{
		$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$old_redirect->set_existing($checkprevious);
		$old_redirect->delete(false, true, NULL, false);
		unset($old_redirect);
	}

	// check to see if this thread is being moved to the same forum it's already in but allow copying to the same forum
	if ($destforuminfo['forumid'] == $threadinfo['forumid'] AND $vbulletin->GPC['redirect'])
	{
		standard_error(fetch_error('movesameforum'));
	}

	($hook = vBulletinHook::fetch_hook('threadmanage_move_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['title'] != '' AND $vbulletin->GPC['title'] != $threadinfo['title'])
	{
		$oldtitle = $threadinfo['title'];
		$threadinfo['title'] = unhtmlspecialchars($vbulletin->GPC['title']);
		$updatetitle = true;
	}
	else
	{
		$oldtitle = $threadinfo['title'];
		$updatetitle = false;
	}

	if ($vbulletin->GPC['redirect'] == 'none')
	{
		$method = 'move';
	}
	else
	{
		$method = 'movered';
	}

	switch($method)
	{
		// ***************************************************************
		// move the thread wholesale into the destination forum
		case 'move':
			// update forumid/notes and unstick to prevent abuse
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_info('skip_moderator_log', true);
			$threadman->set_existing($threadinfo);
			if ($updatetitle)
			{
				$threadman->set('title', $threadinfo['title']);
				if ($vbulletin->options['similarthreadsearch'])
				{
					require_once(DIR . '/includes/functions_search.php');
					$threadman->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
				}
			}
			else
			{	// Bypass check since title wasn't modified
				$threadman->set('title', $threadinfo['title'], true, false);
			}
			$threadman->set('forumid', $destforuminfo['forumid']);

			// If mod can not manage threads in destination forum then unstick thread
			if (!can_moderate($destforuminfo['forumid'], 'canmanagethreads'))
			{
				$threadman->set('sticky', 0);
			}

			($hook = vBulletinHook::fetch_hook('threadmanage_move_simple')) ? eval($hook) : false;

			$threadman->save();

			log_moderator_action($threadinfo, 'thread_moved_to_x', $destforuminfo['title']);

			break;
		// ***************************************************************


		// ***************************************************************
		// move the thread into the destination forum and leave a redirect
		case 'movered':

			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_info('skip_moderator_log', true);
			$threadman->set_existing($threadinfo);
			if ($updatetitle)
			{
				$threadman->set('title', $threadinfo['title']);
				if ($vbulletin->options['similarthreadsearch'])
				{
					require_once(DIR . '/includes/functions_search.php');
					$threadman->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
				}
			}
			else
			{	// Bypass check since title wasn't modified
				$threadman->set('title', $threadinfo['title'], true, false);
			}
			$threadman->set('forumid', $destforuminfo['forumid']);

			// If mod can not manage threads in destination forum then unstick thread
			if (!can_moderate($destforuminfo['forumid'], 'canmanagethreads'))
			{
				$threadman->set('sticky', 0);
			}

			($hook = vBulletinHook::fetch_hook('threadmanage_move_redirect_orig')) ? eval($hook) : false;

			$threadman->save();
			unset($threadman);

			if ($threadinfo['visible'] == 1)
			{	// Insert redirect for visible thread
				log_moderator_action($threadinfo, 'thread_moved_with_redirect_to_a', $destforuminfo['title']);

				$redirdata = array(
					'lastpost'     => intval($threadinfo['lastpost']),
					'forumid'      => intval($threadinfo['forumid']),
					'pollid'       => intval($threadinfo['threadid']),
					'open'         => 10,
					'replycount'   => intval($threadinfo['replycount']),
					'postusername' => $threadinfo['postusername'],
					'postuserid'   => intval($threadinfo['postuserid']),
					'lastposter'   => $threadinfo['lastposter'],
					'dateline'     => intval($threadinfo['dateline']),
					'views'        => intval($threadinfo['views']),
					'iconid'       => intval($threadinfo['iconid']),
					'visible'      => 1
				);

				$redir =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
				foreach (array_keys($redirdata) AS $field)
				{
					// bypassing the verify_* calls; this data should be valid as is
					$redir->setr($field, $redirdata["$field"], true, false);
				}

				if ($updatetitle)
				{
					if (empty($vbulletin->GPC['redirecttitle']))
					{
						$redir->set('title', $threadinfo['title']);
					}
					else
					{
						$redir->set('title', unhtmlspecialchars($vbulletin->GPC['redirecttitle']));
					}
				}
				else
				{	// Bypass check since title wasn't modified
					if (empty($vbulletin->GPC['redirecttitle']))
					{
						$redir->set('title', $threadinfo['title'], true, false);
					}
					else
					{
						$redir->set('title', unhtmlspecialchars($vbulletin->GPC['redirecttitle']));
					}
				}

				require_once(DIR . '/includes/functions_prefix.php');
				if (can_use_prefix($vbulletin->GPC['redirectprefixid']))
				{
					$redir->set('prefixid', $vbulletin->GPC['redirectprefixid']);
				}
				($hook = vBulletinHook::fetch_hook('threadmanage_move_redirect_notice')) ? eval($hook) : false;

				if ($redirthreadid = $redir->save() AND $vbulletin->GPC['redirect'] == 'expires')
				{
					switch($vbulletin->GPC['frame'])
					{
						case 'h':
							$expires = mktime(date('H') + $vbulletin->GPC['period'], date('i'), date('s'), date('m'), date('d'), date('y'));
							break;
						case 'd':
							$expires = mktime(date('H'), date('i'), date('s'), date('m'), date('d') + $vbulletin->GPC['period'], date('y'));
							break;
						case 'w':
							$expires = $vbulletin->GPC['period'] * 60 * 60 * 24 * 7 + TIMENOW;
							break;
						case 'y':
							$expires =  mktime(date('H'), date('i'), date('s'), date('m'), date('d'), date('y') + $vbulletin->GPC['period']);
							break;
						case 'm':
							default:
							$expires =  mktime(date('H'), date('i'), date('s'), date('m') + $vbulletin->GPC['period'], date('d'), date('y'));
					}
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "threadredirect
							(threadid, expires)
						VALUES
							($redirthreadid, $expires)
					");
				}
				unset($redir);
			}
			else
			{	// leave no redirect for hidden or deleted threads
				log_moderator_action($threadinfo, 'thread_moved_to_x', $destforuminfo['title']);
			}

			break;
		// ***************************************************************

	} // end switch($method)

	// kill the cache for the old thread
	delete_post_cache_threads(array($threadinfo['threadid']));

	// Update Post Count if we move from a counting forum to a non counting or vice-versa..
	// Source Dest  Visible Thread    Hidden Thread
	// Yes    Yes   ~           	  ~
	// Yes    No    -visible          ~
	// No     Yes   +visible          ~
	// No     No    ~                 ~
	if ($threadinfo['visible'] AND ($method == 'move' OR $method == 'movered') AND (($foruminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$foruminfo['countposts'] AND $destforuminfo['countposts'])))
	{
		$posts = $db->query_read_slave("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid]
				AND	userid > 0
				AND visible = 1
		");
		$userbyuserid = array();
		while ($post = $db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach ($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
			}

			$operator = ($destforuminfo['countposts'] ? '+' : '-');

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = CAST(posts AS SIGNED) $operator
					CASE
						$casesql
						ELSE 0
					END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	build_forum_counters($threadinfo['forumid']);
	if ($threadinfo['forumid'] != $destforuminfo['forumid'])
	{
		build_forum_counters($destforuminfo['forumid']);
	}

	// Update canview status of thread subscriptions
	update_subscriptions(array('threadids' => array($threadid)));
    }

    // Undelete Posts
    if ($_POST['do'] == 'undeleteposts') {
	$vbulletin->input->clean_array_gpc('p', array(
	    'postids' => TYPE_STR,
	));

	$postids = explode(',', $vbulletin->GPC['postids']);
	foreach ($postids AS $index => $postid) {
	    if (intval($postid) == 0) {
		unset($postids["$index"]);
	    } else {
		$postids["$index"] = intval($postid);
	    }
	}

	if (empty($postids)) {
	    standard_error(fetch_error('no_applicable_posts_selected'));
	}

	if (count($postids) > 400) {
	    standard_error(fetch_error('you_are_limited_to_working_with_x_posts', $postlimit));
	}

	$postids = implode(',', $postids);
	
	// Validate posts
	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.parentid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible,
			forum.options AS forum_options
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid)
		WHERE postid IN ($postids)
			AND (post.visible = 2 OR (post.visible = 1 AND thread.visible = 2 AND post.postid = thread.firstpostid))
		ORDER BY postid
	");

	$deletethreads = array();

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
				OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
		    json_error(ERR_NO_PERMISSION);
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;

		if ($post['firstpostid'] == $post['postid'])
		{	// undeleting a thread so need to update the $tinfo for any other posts in this thread
			$firstpost["$post[threadid]"] = true;
		}
		else if (!empty($firstpost["$post[threadid]"]))
		{
			$postarray["$post[postid]"]['thread_visible'] = 1;
		}
	}

	if (is_array($postarray)) {
	    foreach ($postarray AS $postid => $post)
	    {
		$tinfo = array(
		    'threadid'    => $post['threadid'],
		    'forumid'     => $post['forumid'],
		    'visible'     => $post['thread_visible'],
		    'firstpostid' => $post['firstpostid']
		);
		undelete_post($post['postid'], $post['forum_options'] & $vbulletin->bf_misc_forumoptions['countposts'], $post, $tinfo, false);
	    }
	}

	if (is_array($threadlist)) {
	    foreach (array_keys($threadlist) AS $threadid)
	    {
		build_thread_counters($threadid);
	    }
	}

	if (is_array($forumlist)) {
	    foreach (array_keys($forumlist) AS $forumid)
	    {
		build_forum_counters($forumid);
	    }
	}
    }

    // Delete As Spam
    if ($_REQUEST['do'] == 'dodeletespam') {
	$vbulletin->input->clean_array_gpc('p', array(
	    'type' => TYPE_STR,
	));
	if ($vbulletin->GPC['type'] == 'post')
	{
	    $vbulletin->input->clean_array_gpc('p', array(
		'postids' => TYPE_STR,
	    ));

	    $postids = explode(',', $vbulletin->GPC['postids']);
	    foreach ($postids AS $index => $postid)
	    {
		if (intval($postid) == 0)
		{
		    unset($postids["$index"]);
		}
		else
		{
		    $postids["$index"] = intval($postid);
		}
	    }

	    if (empty($postids))
	    {
		standard_error(fetch_error('no_applicable_posts_selected'));
	    }

	    if (count($postids) > $postlimit)
	    {
		standard_error(fetch_error('you_are_limited_to_working_with_x_posts', $postlimit));
	    }
	}
	else
	{
	    $vbulletin->input->clean_array_gpc('p', array(
		'threadid' => TYPE_STR,
	    ));

	    $threadids = explode(',', $vbulletin->GPC['threadid']);
	    foreach ($threadids AS $index => $threadid)
	    {
		if (intval($threadid) == 0)
		{
		    unset($threadids["$index"]);
		}
		else
		{
		    $threadids["$index"] = intval($threadid);
		}

	    }

	    if (empty($threadids))
	    {
		standard_error(fetch_error('you_did_not_select_any_valid_threads'));
	    }

	    if (count($threadids) > $threadlimit)
	    {
		standard_error(fetch_error('you_are_limited_to_working_with_x_threads', $threadlimit));
	    }
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'banusers' => TYPE_BOOL,
		'userids' => TYPE_STR,
	));

	$banusers = false;
	if ($vbulletin->GPC['banusers']) {
	    $banusers = true;
	}

	$vbulletin->GPC['userid'] = split(',', $vbulletin->GPC['userids']);
	$vbulletin->GPC_exists['userid'] = true;

	$userids = array();

	if ($vbulletin->GPC['type'] == 'thread')
	{ // threads
		$threadarray = array();
		$threads = $db->query_read_slave("
			SELECT threadid, open, visible, forumid, title, prefixid, postuserid
			FROM " . TABLE_PREFIX . "thread
			WHERE threadid IN (" . implode(',', $threadids) . ")
		");
		while ($thread = $db->fetch_array($threads))
		{
			$forumperms = fetch_permissions($thread['forumid']);
			if 	(
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
					OR
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
					OR
				(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
				)
			{
			    json_error(ERR_NO_PERMISSION);
			}

			$thread['prefix_plain_html'] = ($thread['prefixid'] ? htmlspecialchars_uni($vbphrase["prefix_$thread[prefixid]_title_plain"]) . ' ' : '');

			if ($thread['open'] == 10)
			{
				if (!can_moderate($thread['forumid'], 'canmanagethreads'))
				{ // No permission to remove redirects.
					standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
				}
			}
			else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
			}
			else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if (!can_moderate($thread['forumid'], 'canremoveposts'))
			{
				if (!can_moderate($thread['forumid'], 'candeleteposts'))
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
				}
			}
			else if (!can_moderate($thread['forumid'], 'candeleteposts'))
			{
				if (!can_moderate($thread['forumid'], 'canremoveposts'))
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
				}
			}
			$threadarray["$thread[threadid]"] = $thread;
			$userids["$thread[postuserid]"] = true;
		}

		if (empty($threadarray))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_threads'));
		}
	}
	else
	{ // posts
		// Validate posts
		$postarray = array();
		$posts = $db->query_read_slave("
			SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
				thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible, thread.firstpostid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			WHERE postid IN (" . implode(',', $postids) . ")
		");
		while ($post = $db->fetch_array($posts))
		{
			$forumperms = fetch_permissions($post['forumid']);
			if 	(
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
					OR
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
					OR
				(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
				)
			{
			    json_error(ERR_NO_PERMISSION);
			}

			if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
			}
			else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
			}
			else if (!can_moderate($post['forumid'], 'canremoveposts'))
			{
				if (!can_moderate($post['forumid'], 'candeleteposts'))
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
				}
			}
			else if (!can_moderate($post['forumid'], 'candeleteposts'))
			{
				if (!can_moderate($post['forumid'], 'canremoveposts'))
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
				}
			}
			$postarray["$post[postid]"] = $post;
			$userids["$post[userid]"] = true;
		}

		if (empty($postarray))
		{
			standard_error(fetch_error('no_applicable_posts_selected'));
		}
	}

	$user_cache = array();

	foreach ($vbulletin->GPC['userid'] AS $userid)
	{
		// check that userid appears somewhere in either posts / threads, if they don't then you're doing something naughty
		if (!isset($userids["$userid"]))
		{
		    json_error(ERR_NO_PERMISSION);
		}
		$user_cache["$userid"] = fetch_userinfo($userid);
		cache_permissions($user_cache["$userid"]);
		$user_cache["$userid"]['joindate_string'] = vbdate($vbulletin->options['dateformat'], $user_cache["$userid"]['joindate']);
	}

	if ($banusers)
	{
		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/functions_banning.php');
		if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
		{
		    json_error(ERR_NO_PERMISSION);
		}

		// check that user has permission to ban the person they want to ban
		if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			foreach ($user_cache AS $userid => $userinfo)
			{
				if (can_moderate(0, '', $userinfo['userid'], $userinfo['usergroupid'] . (trim($userinfo['membergroupids']) ? ",$userinfo[membergroupids]" : ''))
					OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
					OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
					OR is_unalterable_user($userinfo['userid']))
				{
					standard_error(fetch_error('no_permission_ban_non_registered_users'));
				}
			}
		}
		else
		{
			foreach ($user_cache AS $userid => $userinfo)
			{
				if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
					OR is_unalterable_user($userinfo['userid']))
				{
					standard_error(fetch_error('no_permission_ban_non_registered_users'));
				}
			}
		}
	}
	
	$vbulletin->input->clean_array_gpc('p', array(
		'deleteother'     => TYPE_BOOL,
		'type'            => TYPE_STR,
		'deletetype'      => TYPE_UINT, // 1 = soft, 2 = hard
		'deletereason'    => TYPE_STR,
		'keepattachments' => TYPE_BOOL,
	));
	
	$vbulletin->GPC['deletereason'] = prepare_remote_utf8_string($vbulletin->GPC['deletereason']);

	// Check if we have users to punish
	if (!empty($user_cache))
	{
	    if ($banusers) {
				$vbulletin->input->clean_array_gpc('p', array(
					'usergroupid'       => TYPE_UINT,
					'period'            => TYPE_STR,
					'reason'            => TYPE_STR,
				));
				
				$vbulletin->GPC['reason'] = prepare_remote_utf8_string($vbulletin->GPC['reason']);

				if (!isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]) OR ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
				{
					standard_error(fetch_error('invalid_usergroup_specified'));
				}

				// check that the number of days is valid
				if ($vbulletin->GPC['period'] != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $vbulletin->GPC['period']))
				{
					standard_error(fetch_error('invalid_ban_period_specified'));
				}

				if ($vbulletin->GPC['period'] == 'PERMANENT')
				{
					// make this ban permanent
					$liftdate = 0;
				}
				else
				{
					// get the unixtime for when this ban will be lifted
					$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
				}

				$user_dms = array();

				$current_bans = $db->query_read("
					SELECT user.userid, userban.liftdate, userban.bandate
					FROM " . TABLE_PREFIX . "user AS user
					LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
					WHERE user.userid IN (" . implode(',', array_keys($user_cache)) . ")
				");
				while ($current_ban = $db->fetch_array($current_bans))
				{
					$userinfo = $user_cache["$current_ban[userid]"];
					$userid = $userinfo['userid'];

					if ($current_ban['bandate'])
					{ // they already have a ban, check if the current one is being made permanent, continue if its not
						if ($liftdate AND $liftdate < $current_ban['liftdate'])
						{
							continue;
						}

						// there is already a record - just update this record
						$db->query_write("
							UPDATE " . TABLE_PREFIX . "userban SET
							bandate = " . TIMENOW . ",
							liftdate = $liftdate,
							adminid = " . $vbulletin->userinfo['userid'] . ",
							reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
							WHERE userid = $userinfo[userid]
						");
					}
					else
					{
						// insert a record into the userban table
						/*insert query*/
						$db->query_write("
							INSERT INTO " . TABLE_PREFIX . "userban
							(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate, reason)
							VALUES
							($userinfo[userid], $userinfo[usergroupid], $userinfo[displaygroupid], $userinfo[customtitle], '" . $db->escape_string($userinfo['usertitle']) . "', " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ", $liftdate, '" . $db->escape_string($vbulletin->GPC['reason']) . "')
						");
					}

					// update the user record
					$user_dms[$userid] =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
					$user_dms[$userid]->set_existing($userinfo);
					$user_dms[$userid]->set('usergroupid', $vbulletin->GPC['usergroupid']);
					$user_dms[$userid]->set('displaygroupid', 0);

					// update the user's title if they've specified a special user title for the banned group
					if ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle'] != '')
					{
						$user_dms[$userid]->set('usertitle', $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle']);
						$user_dms[$userid]->set('customtitle', 0);
					}
					$user_dms[$userid]->pre_save();
				}

				foreach ($user_dms AS $userdm)
				{
					$userdm->save();
				}
		}
	}

	// delete threads that are defined explicitly as spam by being ticked
	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;
	$skipped_user_prune = array();

	if ($vbulletin->GPC['deleteother'] AND !empty($user_cache) AND can_moderate(-1, 'canmassprune'))
	{
		$remove_all_posts = array();
		$user_checks = $db->query_read_slave("SELECT COUNT(*) AS total, userid AS userid FROM " . TABLE_PREFIX . "post WHERE userid IN (". implode(', ', array_keys($user_cache)) . ") GROUP BY userid");
		while ($user_check = $db->fetch_array($user_checks))
		{
			if (intval($user_check['total']) <= 50)
			{
				$remove_all_posts[] = $user_check['userid'];
			}
			else
			{
				$skipped_user_prune[] = $user_check['userid'];
			}
		}

		if (!empty($remove_all_posts))
		{
			$threads = $db->query_read_slave("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE postuserid IN (". implode(', ', $remove_all_posts) . ")");
			while ($thread = $db->fetch_array($threads))
			{
				$threadids[] = $thread['threadid'];
			}

			// Yes this can pick up firstposts of threads but we check later on when fetching info, so it won't matter if its already deleted
			$posts = $db->query_read_slave("SELECT postid FROM " . TABLE_PREFIX . "post WHERE userid IN (". implode(', ', $remove_all_posts) . ")");
			while ($post = $db->fetch_array($posts))
			{
				$postids[] = $post['postid'];
			}
		}
	}

	if (!empty($threadids))
	{
		// Validate threads
		$threads = $db->query_read_slave("
			SELECT threadid, open, visible, forumid, title, postuserid
			FROM " . TABLE_PREFIX . "thread
			WHERE threadid IN (" . implode(',', $threadids) . ")
		");
		while ($thread = $db->fetch_array($threads))
		{
			$forumperms = fetch_permissions($thread['forumid']);
			if 	(
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
					OR
				!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
					OR
				(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
				)
			{
			    json_error(ERR_NO_PERMISSION);
			}

			if ($thread['open'] == 10 AND !can_moderate($thread['forumid'], 'canmanagethreads'))
			{
				// No permission to remove redirects.
				standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
			}
			else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if ($thread['open'] != 10)
			{
				if (!can_moderate($thread['forumid'], 'canremoveposts') AND $physicaldel)
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
				}
				else if (!can_moderate($thread['forumid'], 'candeleteposts') AND !$physicaldel)
				{
					standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
				}
			}

			$threadarray["$thread[threadid]"] = $thread;
			$forumlist["$thread[forumid]"] = true;
		}
	}

	$delinfo = array(
			'userid'          => $vbulletin->userinfo['userid'],
			'username'        => $vbulletin->userinfo['username'],
			'reason'          => $vbulletin->GPC['deletereason'],
			'keepattachments' => $vbulletin->GPC['keepattachments'],
	);
	foreach ($threadarray AS $threadid => $thread)
	{
		$countposts = $vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
		if (!$physicaldel AND $thread['visible'] == 2)
		{
			# Thread is already soft deleted
			continue;
		}

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($thread);

		// Redirect
		if ($thread['open'] == 10)
		{
			$threadman->delete(false, true, $delinfo);
		}
		else
		{
			$threadman->delete($countposts, $physicaldel, $delinfo);
		}
		unset($threadman);
	}

	if (!empty($postids))
	{
		// Validate Posts
		$posts = $db->query_read_slave("
			SELECT post.postid, post.threadid, post.parentid, post.visible, post.title,
				thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			WHERE postid IN (" . implode(',', $postids) . ")
			ORDER BY postid
		");
		while ($post = $db->fetch_array($posts))
		{
			$postarray["$post[postid]"] = $post;
			$threadlist["$post[threadid]"] = true;
			$forumlist["$post[forumid]"] = true;
			if ($post['firstpostid'] == $post['postid'])
			{	// deleting a thread so do not decremement the counters of any other posts in this thread
				$firstpost["$post[threadid]"] = true;
			}
			else if (!empty($firstpost["$post[threadid]"]))
			{
				$postarray["$post[postid]"]['skippostcount'] = true;
			}
		}
	}

	$gotothread = true;
	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($post);
		$postman->delete(($foruminfo['countposts'] AND !$post['skippostcount']), $post['threadid'], $physicaldel, $delinfo);
		unset($postman);

		if ($vbulletin->GPC['threadid'] == $post['threadid'] AND $post['postid'] == $post['firstpostid'])
		{	// we've deleted the thread that we activated this action from so we can only return to the forum
			$gotothread = false;
		}
		else if ($post['postid'] == $postinfo['postid'] AND $physicaldel)
		{	// we came in via a post, which we have deleted so we have to go back to the thread
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 't=' . $vbulletin->GPC['threadid'];
		}
	}

	foreach(array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}
	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	if ($vbulletin->GPC['type'] == 'thread')
	{
		setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');
	}
	else
	{
		setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');
	}
    }

    return array('success' => true);
}

function
get_forums ($parentid = -1, &$forums)
{
    global $vbulletin, $db, $foruminfo, $forumperms, $threadinfo, $postinfo, $vbphrase;
    
    if (empty($vbulletin->iforumcache))
    {
	// get the vbulletin->iforumcache, as we use it all over the place, not just for forumjump
	cache_ordered_forums(0, 1);
    }

    if (is_array($vbulletin->iforumcache[$parentid])) {
	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
	    $forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
	    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	    {
		continue;
	    }
	    else
	    {
		// set $forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];

		$optionvalue = $forumid;
		$optiontitle = $forum[title];

		$ok = true;
		if ($forum['link'])
		{
		    $ok = false;
		}
		else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
		{
		    $ok = false;
		}
		else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']))
		{
		    $ok = false;
		}

		if ($ok) { 
		    $forums[] = array(
			'id' => $forumid,
			'title' => prepare_utf8_string($optiontitle),
		    );
		}

		get_forums($forumid, $forums);
	    } // if can view
	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)
    }
}

function
do_get_spam_data ()
{
    global $vbulletin, $db, $vbphrase;

    $vbulletin->input->clean_array_gpc('r', array(
	'threadid' => TYPE_STRING,
	'postids' => TYPE_STRING,
    ));

    $show['removethreads'] = true;
    $show['deletethreads'] = true;
    $show['deleteoption'] = true;

    if ($vbulletin->GPC['threadid'] != '') {
	$threadids = $vbulletin->GPC['threadid'];

	// Validate threads
	$threads = $db->query_read_slave("
		SELECT threadid, open, visible, forumid, title, prefixid, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
	");

	$redirectcount = 0;
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
				OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
		    json_error(ERR_NO_PERMISSION);
		}

		if ($thread['open'] == 10)
		{
			if (!can_moderate($thread['forumid'], 'canmanagethreads'))
			{
				// No permission to remove redirects.
				standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else
			{
				$redirectcount++;
			}
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
		}
		else if ($thread['visible'] == 2)
		{
			if (!can_moderate($thread['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if (!can_moderate($thread['forumid'], 'canremoveposts'))
			{
				continue;
			}
		}
		else if (!can_moderate($thread['forumid'], 'canremoveposts'))
		{
			if (!can_moderate($thread['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if (!$show['deletethreads'])
			{
				standard_error(fetch_error('you_do_not_share_delete_permission'));
			}
			else
			{
				$show['removethreads'] = false;
				$show['deleteoption'] = false;
			}
		}
		else if (!can_moderate($thread['forumid'], 'candeleteposts'))
		{
			if (!can_moderate($thread['forumid'], 'canremoveposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['prefix_plain_html'] . $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title']));
			}
			else if (!$show['removethreads'])
			{
				standard_error(fetch_error('you_do_not_share_delete_permission'));
			}
			else
			{
				$checked = array('remove' => 'checked="checked"');
				$show['deletethreads'] = false;
				$show['deleteoption'] = false;
			}
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;
	}

	if (empty($threadarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_threads'));
	}

	$threadcount = count($threadarray);
	$forumcount = count($forumlist);

			$users_result = $db->query_read("
				SELECT user.userid, user.username, user.joindate, user.posts, post.ipaddress, post.postid, thread.forumid
				FROM " . TABLE_PREFIX . "thread AS thread
				INNER JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)
				INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
				WHERE thread.threadid IN($threadids)
				ORDER BY user.username
			");
			$user_cache = array();
			$ip_cache = array();
			$user_list = array();
			$userid_list = array();
			while ($user = $db->fetch_array($users_result))
			{
			    $user_list[$user['userid']] = prepare_utf8_string($user['username']);
			    $userid_list[$user['userid']] = $user['userid'];

				$user_cache["$user[userid]"] = $user;
				if ($vbulletin->options['logip'] == 2 OR ($vbulletin->options['logip'] == 1 AND can_moderate($user['forumid'], 'canviewips')))
				{
					$ip_cache["$user[ipaddress]"] = $user['postid'];
				}
			}
			$db->free_result($users_result);

			$users = '';
			$usercount = count($user_cache);

			$ip_list = array();

			// IP addresses can be blank, double check this
			$ips = '';
			if ($vbulletin->options['logip'])	// already checked forum permission above
			{
				ksort($ip_cache);
				foreach ($ip_cache AS $ip => $postid)
				{
					if (empty($ip))
					{
						continue;
					}
					$ip_list[] = $ip;
				}
			}

			$show['ips'] = !empty($ips);
			$show['users'] = ($usercount !== 0);

			// make a list of usergroups into which to move this user
			$havebanned = false;
			foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
			{
				if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
				{
					$havebangroup = true;
					break;
				}
			}

			$show['punitive_action'] = ($havebangroup AND (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate(0, 'canbanusers'))) ? true : false;
			$show['akismet_option'] = !empty($vbulletin->options['vb_antispam_key']);
			$show['delete_others_option'] = can_moderate(-1, 'canmassprune');

			$show['deleteitems'] = $show['deletethreads'];
			$show['removeitems'] = $show['removethreads'];

			$out = array(
			    'users' => array_values($user_list),
			    'userids' => array_values($userid_list),
			    'ips' => $ip_list,
			    'punitive' => $show['punitive_action'],
			);
    } else if ($vbulletin->GPC['postids'] != '') {
	$postids = $vbulletin->GPC['postids'];

	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible, thread.firstpostid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
				OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
		    json_error(ERR_NO_PERMISSION);
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts'));
		}
		else if ($post['thread_visible'] == 2 AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
		}
		else if ($post['visible'] == 2)
		{
			if (!can_moderate($post['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
			}
			else if (!can_moderate($post['forumid'], 'canremoveposts'))
			{
				continue;
			}
		}
		else if (!can_moderate($post['forumid'], 'canremoveposts'))
		{
			if (!can_moderate($post['forumid'], 'candeleteposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
			}
			else if (!$show['deleteposts'])
			{
				standard_error(fetch_error('you_do_not_share_delete_permission'));
			}
			else
			{
				$show['removeposts'] = false;
				$show['deleteoption'] = false;
			}
		}
		else if (
			!can_moderate($post['forumid'], 'candeleteposts')
			AND (
				$post['userid'] != $vbulletin->userinfo['userid']
				OR !($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['candeletepost'])
			)
		)
		{
			if (!can_moderate($post['forumid'], 'canremoveposts'))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title']));
			}
			else if (!$show['removeposts'])
			{
				standard_error(fetch_error('you_do_not_share_delete_permission'));
			}
			else
			{
				$checked = array('remove' => 'checked="checked"');
				$show['deleteposts'] = false;
				$show['deleteoption'] = false;
			}
		}

		$postarray["$post[postid]"] = true;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
		$iplist["$post[ipaddress]"] = true;

		if ($post['postid'] == $post['firstpostid'])
		{
			$show['firstpost'] = true;
		}
	}

	if (empty($postarray))
	{
		standard_error(fetch_error('no_applicable_posts_selected'));
	}

	$postcount = count($postarray);
	$threadcount = count($threadlist);
	$forumcount = count($forumlist);

		$users_result = $db->query_read("
			SELECT user.userid, user.username, user.joindate, user.posts, post.ipaddress, post.postid, thread.forumid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			WHERE post.postid IN($postids)
			ORDER BY user.username
		");
		$user_cache = array();
		$ip_cache = array();
		$user_list = array();
		$userid_list = array();
		while ($user = $db->fetch_array($users_result))
		{
		    $user_list[$user['userid']] = prepare_utf8_string($user['username']);
		    $userid_list[$user['userid']] = $user['userid'];

			$user_cache["$user[userid]"] = $user;
			if ($vbulletin->options['logip'] == 2 OR ($vbulletin->options['logip'] == 1 AND can_moderate($user['forumid'], 'canviewips')))
			{
				$ip_cache["$user[ipaddress]"] = $user['postid'];
			}
		}
		$db->free_result($users_result);

		$users = '';
		$usercount = count($user_cache);
		$ip_list = array();

		$ips = '';
		if ($vbulletin->options['logip'])	// already checked forum permission above
		{
			ksort($ip_cache);
			foreach ($ip_cache AS $ip => $postid)
			{
				if (empty($ip))
				{
					continue;
				}
				$ip_list[] = $ip;
			}
		}

		// make a list of usergroups into which to move this user
		$havebanned = false;
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
			{
				$havebangroup = true;
				break;
			}
		}

		$show['ips'] = !empty($ips);
		$show['users'] = ($usercount !== 0);
		$show['punitive_action'] = ($havebangroup AND (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate(0, 'canbanusers'))) ? true : false;
		$show['akismet_option'] = !empty($vbulletin->options['vb_antispam_key']);
		$show['delete_others_option'] = can_moderate(-1, 'canmassprune');
		$show['removeitems'] = $show['removeposts'];
		$show['deleteitems'] = $show['deleteposts'];

			$out = array(
			    'users' => array_values($user_list),
			    'userids' => array_values($userid_list),
			    'ips' => $ip_list,
			    'punitive' => $show['punitive_action'],
			);
    }

    if ($show['punitive_action']) {
	$ban_usergroups = array();
	// make a list of usergroups into which to move this user
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
	    if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	    {
		$ban_usergroups[$usergroupid] = prepare_utf8_string($usergroup['title']);
	    }
	}
	$out['ban_usergroups'] = $ban_usergroups;
    }

    return $out;
}

function
do_get_ban_data ()
{
    global $vbulletin, $db, $vbphrase;

    $ban_usergroups = $out = array();
    
    // make a list of usergroups into which to move this user
    foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
    {
	if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	{
	    $ban_usergroups[$usergroupid] = prepare_utf8_string($usergroup['title']);
	}
    }
    $out['ban_usergroups'] = $ban_usergroups;

    return $out;
}

function
do_ban_user ()
{
    global $vbulletin, $db, $vbphrase;

    require_once(DIR . '/includes/functions_banning.php');
    require_once(DIR . '/includes/adminfunctions.php');

    $canbanuser = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')) ? true : false;
    $canunbanuser = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canunbanusers')) ? true : false;

    // check banning permissions
    if (!$canbanuser AND !$canunbanuser)
    {
	standard_error(fetch_error('no_permission_ban_users'));
    }

	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_INT,
		'period'      => TYPE_STR,
		'reason'      => TYPE_NOHTML,
		'userid'      => TYPE_INT
	));
    
        $vbulletin->GPC['reason'] = prepare_remote_utf8_string($vbulletin->GPC['reason']);

	if (!$canbanuser)
	{
		standard_error(fetch_error('no_permission_ban_users'));
	}

	/*$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	echo "
	<p>Period: {$vbulletin->GPC['period']}</p>
	<p>Banning <b>{$vbulletin->GPC['username']}</b> into usergroup <i>" . $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['title'] . "</i></p>
	<table>
	<tr><td>Time now:</td><td>" . vbdate('g:ia l jS F Y', TIMENOW, false, false) . "</td></tr>
	<tr><td>Lift date:</td><td>" . vbdate('g:ia l jS F Y', $liftdate, false, false) . "</td></tr>
	</table>";
	exit;*/

	// check that the target usergroup is valid
	if (!isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]) OR ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	{
		standard_error(fetch_error('invalid_usergroup_specified'));
	}

	// check that the user exists
	$user = $db->query_first("
		SELECT user.*,
			IF(moderator.moderatorid IS NULL, 0, 1) AS ismoderator
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid AND moderator.forumid <> -1)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");
	if (!$user OR $user['userid'] == $vbulletin->userinfo['userid'])
	{
		standard_error(fetch_error('invalid_user_specified'));
	}

	if (is_unalterable_user($user['userid']))
	{
		standard_error(fetch_error('user_is_protected_from_alteration_by_undeletableusers_var'));
	}

	cache_permissions($user);

	// Non-admins can't ban administrators, supermods or moderators
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if ($user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR $user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'] OR $user['ismoderator'])
		{
			standard_error(fetch_error('no_permission_ban_non_registered_users'));
		}
	}
	else if ($user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		standard_error(fetch_error('no_permission_ban_non_registered_users'));
	}

	// check that the number of days is valid
	if ($vbulletin->GPC['period'] != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $vbulletin->GPC['period']))
	{
		standard_error(fetch_error('invalid_ban_period_specified'));
	}

	// if we've got this far all the incoming data is good
	if ($vbulletin->GPC['period'] == 'PERMANENT')
	{
		// make this ban permanent
		$liftdate = 0;
	}
	else
	{
		// get the unixtime for when this ban will be lifted
		$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	}


	// check to see if there is already a ban record for this user in the userban table
	if ($check = $db->query_first("SELECT userid, liftdate FROM " . TABLE_PREFIX . "userban WHERE userid = $user[userid]"))
	{
		if ($liftdate AND $liftdate < $check['liftdate'])
		{
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate(0, 'canunbanusers'))
			{
				standard_error(fetch_error('no_permission_un_ban_users'));
			}
		}

		// there is already a record - just update this record
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "userban SET
			bandate = " . TIMENOW . ",
			liftdate = $liftdate,
			adminid = " . $vbulletin->userinfo['userid'] . ",
			reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
			WHERE userid = $user[userid]
		");
	}
	else
	{
		// insert a record into the userban table
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "userban
			(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate, reason)
			VALUES
			($user[userid], $user[usergroupid], $user[displaygroupid], $user[customtitle], '" . $db->escape_string($user['usertitle']) . "', " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ", $liftdate, '" . $db->escape_string($vbulletin->GPC['reason']) . "')
		");
	}

	// update the user record
	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
	$userdm->set_existing($user);
	$userdm->set('usergroupid', $vbulletin->GPC['usergroupid']);
	$userdm->set('displaygroupid', 0);

	// update the user's title if they've specified a special user title for the banned group
	if ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle'] != '')
	{
		$userdm->set('usertitle', $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle']);
		$userdm->set('customtitle', 0);
	}

	$userdm->save();
	unset($userdm);

    return array('success' => true);
}

?>
