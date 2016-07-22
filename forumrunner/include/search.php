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
do_showresults ($searchid, $pagenumber = 1, $perpage = 25)
{
    global $vbulletin, $db, $show, $vbphrase, $current_user, $show;

    $vbulletin->options['threadpreview'] = FR_PREVIEW_LEN;
    
    $vbulletin->input->clean_array_gpc('r', array(
	'previewtype' => TYPE_INT,
    ));
    $previewtype = $vbulletin->GPC['previewtype'];
    if (!$previewtype) {
	$previewtype = 1;
    }

    $bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

    // Get exclude IDs
    $exclude_ids = @explode(',', $vbulletin->options['forumrunner_exclude']);
    if (in_array('-1', $exclude_ids)) {
	$exclude_ids = array();
    }

    if ($results = vB_Search_Results::create_from_searchid($current_user, $searchid)) {
        $pages = $results->get_page($pagenumber, $perpage, 10000);
    } else {
        $pages = array();
    }

    if (count($pages) == 0) {
	$threads[]['error'] = strip_tags(fetch_error('searchnoresults', ''));
	return array(
	    'threads' => $threads,
	    'total_threads' => count($threads),
	);
    }

    $thread_data = array();

    $skipped = 0;
    foreach ($pages as $item) {
	switch (get_class($item)) {
	case 'vBForum_Search_Result_Thread':
	    $thread = $item->get_thread();

	    $foruminfo = fetch_foruminfo($thread->get_field('forumid'));

	    $parentlist = explode(',', substr($foruminfo['parentlist'], 0, -3));
	    $skip = false;
	    foreach ($parentlist as $parent_id) {
		if (in_array($parent_id, $exclude_ids)) {
		    $skip = true;
		}
	    }
	    if ($thread->get_field('visible') == 2) {
		$skip = true;
	    }
	    if ($skip) {
		$skipped++;
		continue;
	    }

	    $lastread = $thread->get_forum()->get_last_read_by_current_user($current_user);
	    $legacy_thread = process_thread_array($thread->get_record(), $lastread);

	    $date = vbdate($vbulletin->options['dateformat'], $thread->get_field('lastpost'));
	    $time = vbdate($vbulletin->options['timeformat'], $thread->get_field('lastpost'));

	    $previewinfo = $db->query_first_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "post
		WHERE postid = " . $thread->get_field(($previewtype == 1 ? 'firstpostid' : 'lastpostid')) . "
	    ");

	    $preview = '';
	    if (method_exists($bbcode_parser, 'get_preview')) {
		$preview = $bbcode_parser->get_preview(fetch_censored_text($previewinfo['pagetext']), 200);
	    } else {
		// vB4 prior to vB4.0.4 did not have get_preview()
		list ($text, $nuked_quotes, $images) = parse_post($previewinfo['pagetext'], true, array());
		$preview = preview_chop(fetch_censored_text($nuked_quotes), 200);
	    }
	    
	    $avatarurl = '';
	    if ($previewinfo['userid'] > 0) {
		$userinfoavatar = fetch_userinfo($previewinfo['userid'], FETCH_USERINFO_AVATAR);
		fetch_avatar_from_userinfo($userinfoavatar, true, false);
		if ($userinfoavatar['avatarurl'] != '') {
		    $avatarurl = process_avatarurl($userinfoavatar['avatarurl']);
		}
		unset($userinfoavatar);
	    }

	    $tmp = array(
		'thread_id' => $thread->get_field('threadid'),
		'new_posts' => $show['gotonewpost'],
		'forum_id' => $thread->get_field('forumid'),
		'total_posts' => $thread->get_field('replycount'),
		'forum_title' => prepare_utf8_string(strip_tags($foruminfo['title'])),
		'thread_title' => prepare_utf8_string(strip_tags($thread->get_field('title'))),
		'thread_preview' => prepare_utf8_string(preview_chop(strip_tags(strip_bbcode(html_entity_decode($preview))), FR_PREVIEW_LEN)),
		'post_userid' => $previewinfo['userid'],
		'post_lastposttime' => prepare_utf8_string(date_trunc($date) . ' ' . $time),
		'post_username' => prepare_utf8_string(strip_tags($previewinfo['username'])),
	    );
	    if ($avatarurl != '') {
		$tmp['avatarurl'] = $avatarurl;
	    }
	    if ($thread->get_field('prefixid')) {
		$prefixid = $thread->get_field('prefixid');
		$tmp['prefix'] = prepare_utf8_string(strip_tags($vbphrase["prefix_{$prefixid}_title_plain"]));
	    }
	    if ($thread->get_field('attach')) {
		$tmp['attach'] = true;
	    }
	    if ($thread->get_field('pollid')) {
		$tmp['poll'] = true;
	    }
	    $thread_data[] = $tmp;

	    break;
	case 'vBForum_Search_Result_Post':
	    $post = $item->get_post();
	    $thread = $post->get_thread();

	    $foruminfo = fetch_foruminfo($thread->get_field('forumid'));
	    
	    $parentlist = explode(',', substr($foruminfo['parentlist'], 0, -3));
	    $skip = false;
	    foreach ($parentlist as $parent_id) {
		if (in_array($parent_id, $exclude_ids)) {
		    $skip = true;
		}
	    }
	    if ($post->get_field('visible') == 2) {
		$skip = true;
	    }
	    if ($skip) {
		$skipped++;
		continue;
	    }
	    
	    $date = vbdate($vbulletin->options['dateformat'], $post->get_field('dateline'));
	    $time = vbdate($vbulletin->options['timeformat'], $post->get_field('dateline'));

	    $avatarurl = '';
	    if ($post->get_field('userid') > 0) {
		$userinfoavatar = fetch_userinfo($post->get_field('userid'), FETCH_USERINFO_AVATAR);
		fetch_avatar_from_userinfo($userinfoavatar, true, false);
		if ($userinfoavatar['avatarurl'] != '') {
		    $avatarurl = process_avatarurl($userinfoavatar['avatarurl']);
		}
		unset($userinfoavatar);
	    }

	    $tmp = array(
		'thread_id' => $post->get_field('threadid'),
		'post_id' => $post->get_field('postid'),
		'jump_to_post' => 1,
		//'new_posts' => $show['gotonewpost'] ? 1 : 0, RKJ
		'forum_id' => $thread->get_field('forumid'),
		'forum_title' => prepare_utf8_string(strip_tags($foruminfo['title'])),
		'thread_title' => prepare_utf8_string(strip_tags($thread->get_field('title'))),
		'thread_preview' => prepare_utf8_string(preview_chop(htmlspecialchars_uni(fetch_censored_text(strip_bbcode(strip_quotes(html_entity_decode($post->get_field('pagetext'))), false, true))), FR_PREVIEW_LEN)),
		'post_userid' => $post->get_field('userid'),
		'post_lastposttime' => prepare_utf8_string(date_trunc($date) . ' ' . $time),
		'post_username' => prepare_utf8_string(strip_tags($post->get_field('username'))),
	    );
	    if ($avatarurl != '') {
		$tmp['avatarurl'] = $avatarurl;
	    }
	    if ($thread->get_field('prefixid')) {
		$prefixid = $thread->get_field('prefixid');
		$tmp['prefix'] = prepare_utf8_string(strip_tags($vbphrase["prefix_{$prefixid}_title_plain"]));
	    }
	    if ($post->get_field('attach')) {
		$tmp['attach'] = true;
	    }
	    $thread_data[] = $tmp;

	    break;
	}
    }

    $out = array();
    if (is_array($thread_data) && count($thread_data) > 0) {
	$out['threads'] = $thread_data;
	$out['total_threads'] = max($results->get_confirmed_count() - $skipped, 0);
    } else {
	$out['threads'] = array();
	$out['total_threads'] = 0;
    }
    $out['searchid'] = $searchid;

    return $out;
}

function 
do_new_posts_search (&$searchid, &$reterrors)
{
    global $vbulletin, $db, $vbphrase, $search_core, $current_user;

    $errors = array();

	//f is an auto variable that is already registered.  We include it here for
	//clarity and to guard against the day that we don't automatically process
	//the forum/thread/post variables on init
	$vbulletin->input->clean_array_gpc('r', array(
		'f'					 => TYPE_UINT,
		'days'       => TYPE_UINT,
		'exclude'    => TYPE_NOHTML,
		'include'    => TYPE_NOHTML,
		'showposts'  => TYPE_BOOL,
		'oldmethod'  => TYPE_BOOL,
		'sortby'     => TYPE_NOHTML,
		'noannounce' => TYPE_BOOL,
		'contenttype' => TYPE_NOHTML,
		'type' => TYPE_STR
	));

	$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_NEW);

	//default to post to preserve existing links
	if (!$vbulletin->GPC_exists['contenttypeid'])
	{
		$type = $vbulletin->GPC['contenttypeid'];
	}

	if (!$vbulletin->GPC_exists['contenttype'])
	{
		$type = 'vBForum_Post';
	}
	else
	{
		$type = $vbulletin->GPC['contenttype'];
	}

	$type = vB_Types::instance()->getContentTypeId($type);
	if (!$type)
	{
	    //todo, do we need a seperate error for this?
	    $errors[] = 'searchnoresults';
	}

	//hack, we have a getnew controller for events, but they are not actually
	//indexed.  For now we need to skip the search backlink for events because
	//there isn't anywhere for them to go.
	if ($type <> vB_Types::instance()->getContentTypeId('vBForum_Event'))
	{
		$searchterms['searchdate'] = $_REQUEST['do'] == 'getnew' ? 'lastvisit' : 1;
		$searchterms['contenttypeid'] = $type;
		$searchterms['search_type'] = 1;
		$searchterms['showposts'] = $vbulletin->GPC['showposts'];

		$criteria->set_search_terms($searchterms);
	}

	$criteria->add_contenttype_filter($type);
	$criteria->set_grouped($vbulletin->GPC['showposts'] ?
		vB_Search_Core::GROUP_NO : vB_Search_Core::GROUP_YES);

	//set critieria and sort
	set_newitem_forums($criteria);
	set_newitem_date($criteria, $current_user, $_REQUEST['do']);
	set_getnew_sort($criteria, $vbulletin->GPC['sortby']);

	//check for any errors
	$errors = array_merge($errors, $criteria->get_errors());
	if (count($errors) > 0)
	{
	    $reterrors = $errors;
	    return;
	}

	try
	{
		$search_controller = $search_core->get_newitem_search_controller_by_id($type);
	}
	catch (Exception $e)
	{
	    $errors[] = 'searchnoresults';
	    $reterrors = $errors;
	    return;
	}

	$results = vB_Search_Results::create_from_criteria($current_user, $criteria, $search_controller);

	$searchid = $results->get_searchid();
}

function
do_process_search (&$searchid, &$reterrors)
{
    global $vbulletin, $db, $vbphrase, $search_core, $search_type, $globals, $current_user;

    if (!$vbulletin->options['enablesearches']) {
	$searchid = -1;
	$reterrors[] = 'searchdisabled';
	return;
    }

    $errors = array();

    ($hook = vBulletinHook::fetch_hook('search_before_process')) ? eval($hook) : false;

	($hook = vBulletinHook::fetch_hook('search_process_start')) ? eval($hook) : false;

	if (!$vbulletin->options['threadtagging'])
	{
		//  tagging disabled, don't let them search on it
		$vbulletin->GPC['tag'] = '';
	}

	if ($vbulletin->GPC['userid'] AND $userinfo = fetch_userinfo($vbulletin->GPC['userid']))
	{
		$vbulletin->GPC_exists['searchuser'] = true;
		$vbulletin->GPC['searchuser'] = unhtmlspecialchars($userinfo['username']);
	}

	if ($vbulletin->GPC['searchthreadid'])
	{
		$vbulletin->GPC['sortby'] = 'dateline';
		$vbulletin->GPC['sortorder'] = 'ASC';
		$vbulletin->GPC['showposts'] = true;

		$vbulletin->GPC['starteronly'] = false;
		$vbulletin->GPC['titleonly'] = false;

	}

	// if searching for only a tag, we must show results as threads
	if ($vbulletin->GPC['tag'] AND empty($vbulletin->GPC['query']) AND empty($vbulletin->GPC['searchuser']))
	{
		$vbulletin->GPC['showposts'] = false;
	}

	//do this even if the hv check fails to make sure that the user sees any errors
	//nothing worse then typing in a capcha five times only to get a message saying
	//fix something and type it in again.

	if ($vbulletin->GPC['contenttypeid'] and !is_array($vbulletin->GPC['contenttypeid']))
	{
		$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_ADVANCED);
		$criteria->set_advanced_typeid($vbulletin->GPC['contenttypeid']);
	}
	else
	{
		$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_COMMON);
	}
	set_criteria_from_vbform($current_user, $criteria);
	$search_type->add_advanced_search_filters($criteria, $vbulletin);


	//caputure the search form values for backreferencing
	$searchterms = array();
	foreach ($globals AS $varname => $value)
	{
		if (
			!$vbulletin->GPC_exists[$varname] OR
			(in_array($value, array(TYPE_ARRAY, TYPE_ARRAY_NOHTML)) AND
			!is_array($vbulletin->GPC[$varname]))
		)
		{
			continue;
		}
		$searchterms[$varname] = $vbulletin->GPC[$varname];
	}
	$criteria->set_search_terms($searchterms);

	$errors = array_merge($errors, $criteria->get_errors());
	if ($errors)
	{
	    return;
	}
	$results = null;

	if (!($vbulletin->debug AND $vbulletin->GPC_exists['nocache'] AND $vbulletin->GPC['nocache']))
	{
		$results = vB_Search_Results::create_from_cache($current_user, $criteria);
	}

	if (!$results)
	{
		$results = vB_Search_Results::create_from_criteria($current_user, $criteria);
	}

	$searchid = $results->get_searchid();
}

function set_newitem_forums($criteria)
{
	global $vbulletin;

	//figure out forums
	//This follows the logic of the original search.  If a forum is specified then use it and its
	//children.  If an include list is specified, then use it without its children.
	//Do not honor the exclude list if we are using the provided forumid
	if ($vbulletin->GPC['f'])
	{
		$criteria->add_forumid_filter($vbulletin->GPC['f'], true);
	}
	else
	{
		if ($vbulletin->GPC['include'])
		{
			$list = explode(',', $vbulletin->GPC['include']);

			if (is_array($list))
			{
				$list = array_map('intval', $list);
				$criteria->add_forumid_filter($list, false);
			}
		}

		if ($vbulletin->GPC['exclude'])
		{
			$list = explode(',', $vbulletin->GPC['exclude']);

			if (is_array($list))
			{
				$list = array_map('intval', $list);
				$criteria->add_excludeforumid_filter($list);
			}
		}
	}
	$reterrors = $errors;
}

function set_newitem_date($criteria, $user, $action)
{
	global $vbulletin;

	//if we don't have a last visit date, then can't do getnew
	if (!$user->get_field('lastvisit'))
	{
		$action = 'getdaily';
	}


	$markinglimit = false;

	if ($action == 'getnew')
	{
		//if we are using marking logic, then get
		if (!$user->isGuest() AND $vbulletin->options['threadmarking'] AND !$vbulletin->GPC['oldmethod'])
		{
			$markinglimit = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);
		}
		$datecut = $vbulletin->userinfo['lastvisit'];
	}
	//get daily
	else
	{
		if ($vbulletin->GPC['days'] < 1)
		{
			$vbulletin->GPC['days'] = 1;
		}
		$datecut = TIMENOW - (24 * 60 * 60 * $vbulletin->GPC['days']);
	}

	$criteria->add_newitem_filter($datecut, $markinglimit, $action);
}

function set_getnew_sort($criteria, $sort)
{

	if (!$sort)
	{
		$sort = 'dateline';
	}

	//handle rename to standard sort fields.
	$sort_map = array (
		'postusername' => 'user',
		'lastpost' => 'dateline'
	);

	$descending_sorts = array (
		'dateline', 'threadstart'
	);

	$sortorder = in_array($sort, $descending_sorts) ? 'desc' : 'asc';

	if ($sort == 'dateline' OR $sort == 'user')
	{
		//todo -- figure this out, because its spreading
		$sort = $criteria->switch_field($sort);
	}

	$criteria->set_sort($sort, $sortorder);
}

function set_criteria_from_vbform($user, $criteria)
{
	global $vbulletin;

	if ($vbulletin->GPC_exists['contenttypeid'])
	{
		$criteria->add_contenttype_filter( $vbulletin->GPC['contenttypeid']);
	}
	else if ($vbulletin->GPC_exists['type'])
	{
		$criteria->add_contenttype_filter( $vbulletin->GPC['type']);
	}

	$grouped =  vB_Search_Core::GROUP_DEFAULT;
 	if ($vbulletin->GPC_exists['showposts'])
	{
		$grouped = $vbulletin->GPC['showposts'] ? vB_Search_Core::GROUP_NO : vB_Search_Core::GROUP_YES;
		$criteria->set_grouped($grouped);
	}

	if ($vbulletin->GPC_exists['starteronly'])
	{
		$groupuser = $vbulletin->GPC['starteronly'] ? vB_Search_Core::GROUP_YES : vB_Search_Core::GROUP_NO;
	}
	else
	{
		//if not specified assume that we want the starter when showing groups and the item user for items
		$groupuser = $grouped;
	}

	if ($vbulletin->GPC_exists['query'])
	{
		$criteria->add_keyword_filter($vbulletin->GPC['query'], $vbulletin->GPC['titleonly']);
	}

	if ($vbulletin->GPC['searchuser'] )
	{
		$criteria->add_user_filter($vbulletin->GPC['searchuser'],
			$vbulletin->GPC['exactname'], $groupuser);
	}

	if ($vbulletin->GPC['userid'] )
	{
		$criteria->add_userid_filter(array($vbulletin->GPC['userid']), $groupuser);
	}

	if ($vbulletin->GPC['tag'])
	{
		$criteria->add_tag_filter(htmlspecialchars_uni($vbulletin->GPC['tag']));
	}

	if ($vbulletin->GPC['searchdate'])
	{
		if (is_numeric($vbulletin->GPC['searchdate']))
		{
			$dateline = TIMENOW - ($vbulletin->GPC['searchdate'] * 86400);
		}
		else
		{
			$dateline = $user->get_field('lastvisit');
		}

		$criteria->add_date_filter($vbulletin->GPC['beforeafter'] == 'after' ? vB_Search_Core::OP_GT : vB_Search_Core::OP_LT,
		 	$dateline);
	}

	// allow both sortby rank or relevance to denote natural search
	if ($vbulletin->GPC_exists['sortby'] AND ($vbulletin->GPC['sortby'] == 'relevance' OR $vbulletin->GPC['sortby'] == 'rank') AND $vbulletin->GPC_exists['query'])
	{
		$vbulletin->GPC_exists['sortorder'] = true;
		$vbulletin->GPC['sortorder'] = 'desc';

	}
	else if (!$vbulletin->GPC_exists['sortby'] OR $vbulletin->GPC['sortby'] == 'relevance' OR $vbulletin->GPC['sortby'] == 'rank')
	{
		$vbulletin->GPC['sortby'] = 'dateline';
	}

	if (!$vbulletin->GPC_exists['sortorder'])
	{
		$vbulletin->GPC['sortorder'] = 'desc';
	}

	//natural mode search defaults to false. Only set if we are passed
	// true or 1
	if ($vbulletin->GPC_exists['natural'] AND $vbulletin->GPC['natural'])
	{
		$vbulletin->GPC['sortorder'] = 'desc';
	}

	$field = $vbulletin->GPC['sortby'];
	//fix user or dateline fields.
	$field = $criteria->switch_field($field);

	$criteria->set_sort($field, $vbulletin->GPC['sortorder']);
}

?>
