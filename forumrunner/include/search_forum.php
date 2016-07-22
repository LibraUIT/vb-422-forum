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

require_once(MCWD . '/include/search.php');

chdir('../');

define('THIS_SCRIPT', 'search');
define('CSRF_PROTECTION', false);

// get special phrase groups
$phrasegroups = array('search', 'inlinemod', 'prefix', 'socialgroups', 'prefix', 'user');

$globaltemplates = array(
    'bbcode_code',
    'bbcode_html',
    'bbcode_php',
    'bbcode_quote',
    'bbcode_video',
);

require_once('./global.php');
//old stuff,  we should start getting rid of this
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_misc.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumdisplay.php');
require_once(DIR . '/includes/functions_search.php');

//new search stuff.
require_once(DIR . "/vb/search/core.php");
require_once(DIR . "/vb/legacy/currentuser.php");
require_once(DIR . "/vb/search/resultsview.php");
require_once(DIR . "/vb/search/searchtools.php");

require_once(DIR . '/includes/class_bbcode.php');

$vbulletin->options['hv_type'] = false;

$search_core = vB_Search_Core::get_instance();
$current_user = new vB_Legacy_CurrentUser();
$search_type =  vB_Search_Core::get_instance()->get_search_type('vBForum', 'Common');

$globals = $search_type->listSearchGlobals();
$vbulletin->input->clean_array_gpc('r', $globals);

if ($vbulletin->GPC['query']) {
    $vbulletin->GPC['query'] = prepare_remote_utf8_string($vbulletin->GPC['query']);
}
if ($vbulletin->GPC['searchuser']) {
    $vbulletin->GPC['searchuser'] = prepare_remote_utf8_string($vbulletin->GPC['searchuser']);
}

if ($vbulletin->GPC['sortby'] == 'lastpost') {
    $vbulletin->GPC['sortby'] = 'dateline';
}
$vbulletin->GPC['contenttypeid'] = 1; // vbforum
$vbulletin->GPC_exists['contenttypeid'] = true;
$vbulletin->GPC['searchfromtype'] = 'vBForum:Post';
$vbulletin->GPC_exists['searchfromtype'] = true;

function
do_search_getnew ()
{
    global $vbulletin, $search_core, $current_user;

    $args = process_input(
	array(
	    'do' => STRING,
	)
    );

    $vbulletin->input->clean_array_gpc('r', array(
	'days'       => TYPE_UINT,
	'exclude'    => TYPE_NOHTML,
	'include'    => TYPE_NOHTML,
	'showposts'  => TYPE_BOOL,
	'oldmethod'  => TYPE_BOOL,
	'sortby'     => TYPE_NOHTML,
	'noannounce' => TYPE_BOOL,
	'pagenumber' => TYPE_UINT,
	'perpage'    => TYPE_UINT,
    ));
    
    if (!$current_user->hasPermission('forumpermissions', 'cansearch')) {
	$threads[]['error'] = strip_tags(fetch_error('fr_no_permission_current'));
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    if (!$vbulletin->options['enablesearches']) {
	$threads[]['error'] = strip_tags(fetch_error('searchdisabled'));
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    $searchid = -1;
    $errors = array();
    do_new_posts_search($searchid, $errors);

    if (is_array($errors) && count($errors)) {
	// Errors  Print them out as non-clickable rows.
	$threads = array();
	foreach (array_map('fetch_error', $errors) AS $error) {
	    $threads[]['error'] = prepare_utf8_string(strip_tags($error));
	}

	return array(
	    'threads' => $threads,
	    'total_threads' => count($threads),
	);
    } else if ($searchid == -1) {
	return array(
	    'threads' => array(),
	    'total_threads' => 0,
	);
    }

    return do_showresults($searchid, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage']);
}

/*
 * Granddaddy search mechanism. Try to emulate vBulletin's GPC args as much as
 * possible
 */
function
do_search ()
{
    global $vbulletin, $db, $search_type, $globals, $current_user;

    $args = process_input(
	array(
	    'forumid' => INTEGER,
	)
    );

    $vbulletin->input->clean_array_gpc('r', array(
	'pagenumber'     => TYPE_UINT,
	'perpage'        => TYPE_UINT,
    ));
    
    if (!$current_user->hasPermission('forumpermissions', 'cansearch')) {
	$threads[]['error'] = ERR_NO_PERMISSION;
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    if (!$vbulletin->options['enablesearches']) {
	$threads[]['error'] = strip_tags(fetch_error('searchdisabled'));
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    if ($args['forumid']) {
	$vbulletin->GPC['forumchoice'][] = $args['forumid'];
    }

    $vbulletin->GPC['childforums'] = true;
    $vbulletin->GPC_exists['childforums'] = true;
    $vbulletin->GPC['prefixchoice'] = array();
    $vbulletin->GPC_exists['prefixchoice'] = true;

    $searchid = -1;
    $errors = array();
    // Disable NoSpam!
    $vbulletin->options['nospam_onoff'] = false;
    do_process_search($searchid, $errors);

    if (is_array($errors)) {
	// Detect and use Sphinx if its installed
	if ($errors['sphinx']) {
	    $forumrunner = true;

	    ($hook = vBulletinHook::fetch_hook('search_start')) ? eval($hook) : false;
	}

	if (count($errors) > 0) {
	    // Errors  Print them out as non-clickable rows.
	    foreach (array_map('fetch_error', $errors) AS $error) {
		$threads[]['error'] = prepare_utf8_string(strip_tags($error));
	    }

	    return array(
		'threads' => $threads,
		'total_threads' => count($threads),
	    );
	}
    }

    return do_showresults($searchid, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage']);
}

function
do_search_finduser ()
{
    global $vbulletin, $search_core, $search_type, $current_user;

	$vbulletin->input->clean_array_gpc('r', array(
		'type'			  => TYPE_ARRAY_NOHTML,
		'userid'	     	  => TYPE_UINT,
		'starteronly'    => TYPE_BOOL,
		'forumchoice'    => TYPE_ARRAY,
		'childforums'    => TYPE_BOOL,
		'postuserid' 	  => TYPE_UINT,
		'searchthreadid' => TYPE_UINT,
		'pagenumber' => TYPE_INT,
		'perpage'    => TYPE_INT,
	));

    $vbulletin->GPC['prefixchoice'] = array();
    $vbulletin->GPC_exists['prefixchoice'] = true;
    
    if (!$current_user->hasPermission('forumpermissions', 'cansearch')) {
	$threads[]['error'] = ERR_NO_PERMISSION;
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    if (!$vbulletin->options['enablesearches']) {
	$threads[]['error'] = strip_tags(fetch_error('searchdisabled'));
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    // valid user id?
    if (!$vbulletin->GPC['userid']) {
	if (!$vbulletin->userinfo['userid'])
	{
	    json_error(ERR_INVALID_USER);
	}
	$vbulletin->GPC['userid'] = $vbulletin->userinfo['userid'];
	$vbulletin->GPC_exists['userid'] = true;
    }

	// valid user id?
	if (!$vbulletin->GPC['userid'] and !$vbulletin->GPC['postuserid'])
	{
	    json_error(ERR_INVALID_USER);
	}

	//default to posts
	if (! $vbulletin->GPC_exists['contenttypeid'])
	{
	    $vbulletin->GPC['contenttypeid'] = vB_Types::instance()->getContentTypeID('vBForum_Post');
	    $vbulletin->GPC_exists['contenttypeid'] = true;
	}

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
	$criteria->set_sort($criteria->switch_field('dateline'), 'desc');

	$errors = $criteria->get_errors();

	if ($errors)
	{
	    json_error($errors[0]);
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

    if ($searchid != -1) {
	return do_showresults($searchid, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage']);
    } else {
	return array(
	    'threads' => array(),
	    'total_threads' => 0,
	);
    }
}

function
do_search_searchid ()
{
    global $vbulletin, $db, $current_user;

    $vbulletin->input->clean_array_gpc('r',  array(
	'pagenumber' => TYPE_INT,
	'perpage'    => TYPE_INT,
	'searchid'   => TYPE_INT,
    ));
    
    if (!$current_user->hasPermission('forumpermissions', 'cansearch')) {
	$threads[]['error'] = ERR_NO_PERMISSION;
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    if (!$vbulletin->options['enablesearches']) {
	$threads[]['error'] = strip_tags(fetch_error('searchdisabled'));
	return array(
	    'threads' => $threads,
	    'total_threads' => 1,
	);
    }

    return do_showresults($vbulletin->GPC['searchid'], $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage']);
}

?>
