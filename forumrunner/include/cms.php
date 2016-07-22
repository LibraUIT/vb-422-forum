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

define('VB_PRODUCT', 'vbcms');
define('VB_ENTRY', 1);
define('VB_ROUTER_SEGMENT', 'content');
define('GET_EDIT_TEMPLATES', 'picture');
define('CMS_SCRIPT', true);
define('THIS_SCRIPT', 'vbcms');
define('FRIENDLY_URL_LINK', 'vbcms');
define('CSRF_PROTECTION', false);
define('VB_AREA', 'Forum');
define('SKIP_WOLPATH', 1);

$phrasegroups = array('vbcms');

require('./includes/class_bootstrap.php');
$bootstrap = new vB_Bootstrap();
$bootstrap->datastore_entries = array('routes');
$bootstrap->bootstrap();
$bootstrap->process_templates();

if (!defined('VB_ENTRY'))
{
    define('VB_ENTRY', 1);
}

define('VB_ENTRY_TIME', microtime(true));
define('VB_PATH', MCWD . '/../vb/');
define('VB_PKG_PATH', realpath(VB_PATH . '../packages') . '/');

require_once(DIR . '/vb/phrase.php');
require_once(DIR . '/vb/vb.php');
vB::init();

require_once(DIR . '/vb/vb.php');
require_once(DIR . '/vb/cache.php');
require_once(DIR . '/vb/cache/db.php');
require_once(DIR . '/vb/cache/observer.php');
require_once(DIR . '/vb/cache/observer/db.php');

require_once(DIR . '/vb/content.php');
require_once(DIR . '/vb/model.php');
require_once(DIR . '/vb/types.php');
require_once(DIR . '/vb/item.php');
require_once(DIR . '/vb/item/content.php');

require_once(DIR . '/packages/vbcms/permissions.php');

require_once(DIR . '/packages/vbcms/content.php');

require_once(DIR . '/packages/vbcms/item/content.php');
require_once(DIR . '/packages/vbcms/content/section.php');

require_once(DIR . '/packages/vbcms/item/content/section.php');

require_once(DIR . '/packages/vbattach/attach.php');

function
do_get_cms_section ()
{
    global $vbulletin, $db;

    $vbulletin->input->clean_array_gpc('r', array(
	'sectionid'  => TYPE_UINT,
	'page' => TYPE_UINT,
	'perpage' => TYPE_UINT,
    ));

    $sectionid = $vbulletin->GPC['sectionid'];
    if (!$vbulletin->GPC_exists['sectionid']) {
	$sectionid = 1;
    }
    $sectionid = intval($sectionid);

    $page = 1;
    if ($vbulletin->GPC['page']) {
	$page = $vbulletin->GPC['page'];
    }
    $perpage = 10;
    if ($vbulletin->GPC['perpage']) {
	$perpage = $vbulletin->GPC['perpage'];
    }

    if ($perpage > 50 || $perpage < 5) {
	$perpage = 10;
    }
    if ($page < 1) {
	$page = 1;
    }

    $limitsql = 'LIMIT ' . (($page - 1) * $perpage) . ', ' . $perpage;

    if (!isset(vB::$vbulletin->userinfo['permissions']['cms'])) {
	vBCMS_Permissions::getUserPerms();
    }

    $config = $vbulletin->db->query_first("
	SELECT config1.value AS priority, config2.value AS contentfrom, nodeinfo.title AS section_title
	FROM " . TABLE_PREFIX . "cms_node AS node
	LEFT JOIN " . TABLE_PREFIX . "cms_nodeconfig AS config1 ON config1.nodeid = node.nodeid AND config1.name = 'section_priority'
	LEFT JOIN " . TABLE_PREFIX . "cms_nodeconfig AS config2 ON config2.nodeid = node.nodeid AND config2.name = 'contentfrom'
	LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS nodeinfo ON nodeinfo.nodeid = node.nodeid
	WHERE node.nodeid = $sectionid
	GROUP BY node.nodeid
    ");

    $sortby = 3;
    $exact = false;
    $section_title = 'News';

    if ($config) {
	if (isset($config['priority'])) {
	    $sortby = intval($config['priority']);
	}
	if (isset($config['contentfrom'])) {
	    if (intval($config['contentfrom']) != 2) {
		$exact = true;
	    }
	}
	if (isset($config['section_title'])) {
	    $section_title = $config['section_title'];
	}
    }

    $extrasql = $orderby = '';
    if ($sortby == 3) {
	$extrasql =	" INNER JOIN (SELECT parentnode, MAX(lastupdated) AS lastupdated
	    FROM " . TABLE_PREFIX . "cms_node  AS node WHERE contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
	    " AND	" .  vBCMS_Permissions::getPermissionString() .
	    " GROUP BY parentnode ) AS ordering ON ordering.parentnode = node.parentnode
	    AND node.lastupdated = ordering.lastupdated WHERE 1=1";

	$orderby = " ORDER BY node.setpublish DESC, node.publishdate DESC ";
    }
    else if ($sortby == 2)
    {
	$orderby = " ORDER BY node.publishdate DESC ";
    }
    else if ($sortby == 4)
    {
	$orderby = " ORDER BY info.title ASC ";
    }
    else if ($sortby == 5)
    {
	$orderby = " ORDER BY sectionorder.displayorder ASC ";
    }
    else
    {
	$orderby = " ORDER BY CASE WHEN sectionorder.displayorder > 0 THEN sectionorder.displayorder ELSE 9999999 END ASC,
	    node.publishdate DESC";
    }

    $sql = "
	SELECT SQL_CALC_FOUND_ROWS
	    node.nodeid AS itemid,
	    (node.nodeleft = 1) AS isroot, node.nodeid, node.contenttypeid, node.contentid, node.url, node.parentnode, node.styleid, node.userid,
	    node.layoutid, node.publishdate, node.setpublish, node.issection, parent.permissionsfrom as parentpermissions,
	    node.permissionsfrom, node.publicpreview, node.showtitle, node.showuser, node.showpreviewonly, node.showall,
	    node.showupdated, node.showviewcount, node.showpublishdate, node.settingsforboth, node.includechildren, node.editshowchildren,
	    node.shownav, node.hidden, node.nosearch, node.nodeleft,
	    info.description, info.title, info.html_title, info.viewcount, info.creationdate, info.workflowdate,
	    info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid,
	    user.username, sectionorder.displayorder, thread.replycount, parentinfo.title AS parenttitle
	FROM " . TABLE_PREFIX . "cms_node AS node
	INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid
	LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
	LEFT JOIN " . TABLE_PREFIX . "cms_sectionorder AS sectionorder ON sectionorder.sectionid = $sectionid
	AND sectionorder.nodeid = node.nodeid
	LEFT JOIN " . TABLE_PREFIX . "cms_node AS parent ON parent.nodeid = node.parentnode
	LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
	" . ($sectionid ? " INNER JOIN " . TABLE_PREFIX . "cms_node AS rootnode
					ON rootnode.nodeid = $sectionid
					AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft " : '') .
				$extrasql .
				" AND node.contenttypeid <> " . vb_Types::instance()->getContentTypeID("vBCms_Section") .
				" AND node.new != 1 " .
				" AND ( (" . vBCMS_Permissions::getPermissionString() .
					") OR (node.setpublish AND node.publishdate <" . TIMENOW .
					" AND node.publicpreview > 0)) " .
				(($exact) ? "AND (node.parentnode = " . intval($sectionid) . " OR sectionorder.displayorder > 0 )": '') .
				(($sortby == 5) ? " AND sectionorder.displayorder > 0 " : '')
				. "
				$orderby
				$limitsql
    ";

    $articles = array();

    $items = $vbulletin->db->query_read_slave($sql);
    $total = $vbulletin->db->found_rows();

    while ($item = $vbulletin->db->fetch_array($items)) {
	$article = new vBCms_Item_Content_Article($item['nodeid'],  vBCms_Item_Content::INFO_CONTENT);

	$tmp = array(
	    'articleid' => $article->getNodeId(),
	    'title' => prepare_utf8_string($article->getTitle()),
	    'pubdate' => prepare_utf8_string(vbdate('M j, Y g:i A T', $article->getPublishDate())),
	    'preview' => prepare_utf8_string(preview_chop(str_replace(array("\n", "\r", "\t"), array('', '', ''), strip_tags($article->getPreviewText(false))), FR_PREVIEW_LEN)),
	);
	
	$thread_id = $article->getThreadId();
	if ($thread_id) {
	    $tmp['threadid'] = $thread_id;
	}
	$previewimage = $article->getPreviewImage();
	if ($previewimage) {
	    if (strpos($previewimage, 'http') === false) {
		 $previewimage = $vbulletin->options['bburl'] . '/' . $previewimage;
	    }
	    $tmp['image'] = $vbulletin->options['bburl'] . "/forumrunner/image.php?url=$previewimage&w=160&h=160";
	}

	$articles[] = $tmp;
    }

    $out = array(
	'total_articles' => $total,
	'articles' => $articles,
	'section_title' => prepare_utf8_string(strip_tags($section_title)),
    );

    return $out;
}

function
do_get_cms_sections ()
{
    global $vbulletin, $db;

    if (!isset($vbulletin->userinfo['permissions']['cms'])) {
	vBCMS_Permissions::getUserPerms();
    }

    $publishlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['canpublish']);
    $viewlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['allview']);

    $result = $vbulletin->db->query_read("
	SELECT node.nodeid, node.parentnode, node.url, node.permissionsfrom, node.setpublish, node.publishdate, node.noderight, info.title
	FROM " . TABLE_PREFIX . "cms_node AS node
	INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
	WHERE node.contenttypeid = " . vB_Types::instance()->getContentTypeID("vBCms_Section") . "
	    AND ((node.permissionsfrom IN ($viewlist)  AND node.hidden = 0) OR (node.permissionsfrom IN ($publishlist)))
	    ORDER BY node.nodeleft"
    );

    $sections = array();
    while ($section = $vbulletin->db->fetch_array($result)) {
	$sections[] = array(
	    'sectionid' => $section['nodeid'],
	    'title' => prepare_utf8_string(strip_tags($section['title'])),
	);
    }

    return array(
	'sections' => $sections,
    );
}

function
do_get_cms_article ()
{
    global $vbulletin, $db, $contenttype;

    $vbulletin->input->clean_array_gpc('r', array(
	'articleid'  => TYPE_UINT,
	'page' => TYPE_UINT,
	'perpage' => TYPE_UINT,
    ));

    if (!$vbulletin->GPC['articleid']) {
	json_error(ERR_NO_PERMISSION);
    }

    $page = 1;
    if ($vbulletin->GPC['page']) {
	$page = $vbulletin->GPC['page'];
    }
    $perpage = 10;
    if ($vbulletin->GPC['perpage']) {
	$perpage = $vbulletin->GPC['perpage'];
    }

    if ($perpage > 50 || $perpage < 5) {
	$perpage = 10;
    }
    if ($page < 1) {
	$page = 1;
    }

    $articleid = $vbulletin->GPC['articleid'];

    $article = new vBCms_Item_Content_Article($articleid,  vBCms_Item_Content::INFO_CONTENT);

    $associated_thread_id = $article->getAssociatedThreadId();
    if (!$associated_thread_id) {
	$associated_thread_id = create_associated_thread($article);
	if ($associated_thread_id) {
	    $article->setAssociatedThread($associated_thread_id);
	}
    }

    $posts_out = array();

    $fr_images = array();

    // Display article if on first page of comments
    if ($page == 1) {
	// First, the article
	$postdate = vbdate($vbulletin->options['dateformat'], $article->getPublishDate(), 1);
	$posttime = vbdate($vbulletin->options['timeformat'], $article->getPublishDate());

	// Parse the post for quotes and inline images
	$contenttype = vB_Types::instance()->getContentTypeID("vBCms_Article");

	// Attachments (images).
	if (count($post['attachments']) > 0) {
	    foreach ($post['attachments'] as $attachment) {
		$lfilename = strtolower($attachment['filename']);
		if (strpos($lfilename, '.jpe') !== false ||
		    strpos($lfilename, '.png') !== false ||
		    strpos($lfilename, '.gif') !== false ||
		    strpos($lfilename, '.jpg') !== false ||
		    strpos($lfilename, '.jpeg') !== false) 
		{
		    $fr_images[] = array(
			'img' => $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'],
			'tmb' => $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'] . '&stc=1&thumb=1',
		    );
		}
	    }
	}

	list ($text, $nuked_quotes, $images) = parse_post(strip_tags($article->getPageText()), false);
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
	if ($vbulletin->options['avatarenabled']) {
	    require_once(DIR . '/includes/functions_user.php');
	    $userinfo = fetch_userinfo($article->getUserId(), FETCH_USERINFO_AVATAR);
	    fetch_avatar_from_userinfo($userinfo);
	    if ($userinfo['avatarurl']) {
		$avatarurl = process_avatarurl($userinfo['avatarurl']);
	    }
	}

	$tmp = array(
	    'post_id' => ($article->getPostId() ? $article->getPostId() : 999999999),
	    'thread_id' => $associated_thread_id,
	    'username' => prepare_utf8_string($article->getUsername()),
	    'joindate' => prepare_utf8_string($userinfo['joindate']),
	    'usertitle' => prepare_utf8_string(strip_tags($userinfo['usertitle'])),
	    'numposts' => $userinfo['posts'],
	    'userid' => $userinfo['userid'],
	    'post_timestamp' => prepare_utf8_string(date_trunc($postdate) . ' ' . $posttime),
	    'fr_images' => $fr_images,
	    'image_thumbs' => array(),
	);
	$tmp['text'] = $text;
	$tmp['quotable'] = $nuked_quotes;
	if ($post['editlink']) {
	    $tmp['canedit'] = true;
	    $tmp['edittext'] = prepare_utf8_string($post['pagetext']);
	}
	if ($avatarurl != '') {
	    $tmp['avatarurl'] = $avatarurl;
	}
	if ($article->getPreviewVideo()) {
	    if (preg_match(',data="(.*?)",', $article->getPreviewVideo(), $matches)) {
		$video = $matches[1];
		if (strpos($matches[1], 'vimeo')) {
		    $clip_id = 0;
		    if (preg_match(',clip_id=(\d+),', $matches[1], $matches2)) {
			$clip_id = $matches2[1];
		    } else if (preg_match(',vimeo\.com/(\d*)?,', $matches[1], $matches2)) {
			$clip_id = $matches2[1];
		    } else {
			$clip_id = $matches[1];
		    }
		    $video = "<iframe src=\"http://player.vimeo.com/video/$clip_id\" ignore=\"%@\" width=\"%0.0f\" height=\"%d\" frameborder=\"0\"></iframe>";
		} else {
		    $video = <<<EOF
<object id="videoc" width="%0.0f" height="%d">
<param name="movie" value="{$matches[1]}"></param>
<param name="wmode" value="transparent"></param>
<embed wmode="transparent" id="video" src="{$matches[1]}" type="application/x-shockwave-flash" width="%0.0f" height="%d"></embed>
</object>
EOF;
		}
		$tmp['video'] = prepare_utf8_string($video);
	    }
	}
	$posts_out[] = $tmp;
    }

    $this_user = new vB_Legacy_CurrentUser();

    $threadinfo = verify_id('thread', $associated_thread_id, 0, 1);

    // Now, get the posts
    $total = 0;
    if ($associated_thread_id) {
	$comments = get_article_comments($article, $associated_thread_id, $this_user, $page, $perpage, $total);

	$posts_out = array_merge($posts_out, $comments);
    }

    $canpost = true;
    $userid = $this_user->get_field('userid');
    if (empty($userid)) {
	$canpost = false;
    }
    if ($userid != $threadinfo['postuserid']) {
	$canpost = $this_user->hasForumPermission($threadinfo['forumid'], 'canreplyothers');
    } else {
	$canpost = $this_user->hasForumPermission($threadinfo['forumid'], 'canreplyown');
    }

    return array(
	'posts' => $posts_out,
	'total_posts' => $total,
	'page' => $page,
	'canpost' => $canpost,
	'threadid' => $associated_thread_id,
	'title' => prepare_utf8_string($article->getTitle()),
    );
}

function
get_article_comments ($article, $associated_thread_id, $userinfo, &$pageno, &$perpage, &$total)
{
    require_once DIR . '/includes/functions_misc.php';
    require_once DIR . '/includes/functions.php';
    require_once DIR . '/includes/functions_databuild.php';
    require_once DIR . '/includes/functions_bigthree.php';

    $posts_out = array();

    fetch_phrase_group('posting');

    $threadinfo = verify_id('thread', $associated_thread_id, 0, 1);
    $foruminfo = verify_id('forum', $threadinfo['forumid'], 0, 1);

    //First let's see if we have forum/thread view permissions. If not,
    // we're done
    if (! $permissions = can_view_thread($article->getNodeId(), $userinfo))
    {
	return array();
    }
    $forumperms = fetch_permissions($threadinfo['forumid']);

    //Normally this thread will be wide open, so let's get the list first
    // without checking. We'll verify each post anyway.

    //get our results
    $results = get_comments($permissions, $associated_thread_id);
    $record_count = count($results);

    if (!$results OR !count($results))
    {
	return array();
    }

    //we accept the parameter "last" for pageno.
    if ($pageno == FR_LAST_POST)
    {
	$pageno = intval(($record_count + $perpage -1) / $perpage);
	$first = ($pageno -1) * $perpage;
    }
    else
    {
	$pageno = max(1, intval($pageno) );
	$first = $perpage * ($pageno -1) ;
    }

    //Let's trim off the results we need.
    //This also tells us if we should show the "next" button.
    $post_array = array_slice($results, $first, $perpage, true);

    if (!$post_array) {
	return array();
    }

    $firstpostid = false;

    $displayed_dateline = 0;
    if (vB::$vbulletin->options['threadmarking'] AND vB::$vbulletin->userinfo['userid'])
    {
	$threadview = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - (vB::$vbulletin->options['markinglimit'] * 86400));
    }
    else
    {
	$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
	if (!$threadview)
	{
	    $threadview = vB::$vbulletin->userinfo['lastvisit'];
	}
    }
    require_once DIR . '/includes/functions_user.php';
    $show['inlinemod'] = false;
    $postids = array();

    $postids = ' post.postid in ('
	. implode(', ', $post_array) .')';

    $posts =  vB::$vbulletin->db->query_read($sql = "
	SELECT
	post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
	    user.*, userfield.*, usertextfield.*,
	    " . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
	    " . iif( vB::$vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
	    " . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
	    " . iif($deljoin, 'deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,') . "
	    editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
	    editlog.reason AS edit_reason, editlog.hashistory,
	    postparsed.pagetext_html, postparsed.hasimages,
	    sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
	    sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
	    IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid,
	    customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight
	    " . iif(!($permissions['genericpermissions'] &  vB::$vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']),  vB::$vbulletin->profilefield['hidden']) . "
	    $hook_query_fields
	    FROM " . TABLE_PREFIX . "post AS post
	    LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
	    LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	    LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
	    " . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
	    " . iif( vB::$vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
	    " . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
	    $deljoin
	    LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
	    LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
	    LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
	    LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
	    LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
	    $hook_query_joins
	    WHERE $postids
	    ORDER BY post.dateline
	    ");

    if (!($forumperms &  vB::$vbulletin->bf_ugp_forumpermissions['canseethumbnails']) AND !($forumperms &  vB::$vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    {
	vB::$vbulletin->options['attachthumbs'] = 0;
    }

    if (!($forumperms &  vB::$vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    {
	vB::$vbulletin->options['viewattachedimages'] = 0;
    }

    $postcount = count($postid_array);

    $counter = 0;
    $postbits = '';
    vB::$vbulletin->noheader = true;

    while ($post =  vB::$vbulletin->db->fetch_array($posts))
    {

	if (!$privileges['can_moderate_forums'] )
	{
	    if ( $privileges['is_coventry'] OR ($post['visible'] == 2))
	    {
		continue;
	    }
	}

	// post/thread is deleted by moderator and we don't have permission to see it
	if (!($post['visible'] OR $privileges['can_moderate_posts'])) {
	    continue;
	}

	if (! intval($post['userid']))
	{
	    $post['avatarid'] = false;
	}
	else if (!$post['hascustomavatar'])
	{
	    if ($post['profilepic'])
	    {
		$post['hascustomavatar'] = 1;
		$post['avatarid'] = true;
		$post['avatarpath'] = "./image.php?u=" . $post['userid']  . "&amp;dateline=" . $post['profilepicdateline'] . "&amp;type=profile";
		$post['avwidth'] = $post['ppwidth'];
		$post['avheight'] = $post['ppheight'];
	    }
	    else
	    {
		$post['hascustomavatar'] = 1;
		$post['avatarid'] = true;
		// explicity setting avatarurl to allow guests comments to show unknown avatar
		$post['avatarurl'] = $post['avatarpath'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
		$post['avwidth'] = 60;
		$post['avheight'] = 60;
	    }
	}

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
	    ( vB::$vbulletin->GPC['viewfull'] AND $post['postid'] == $postinfo['postid'] AND $fetchtype != 'post')
	    AND
	    (can_moderate($threadinfo['forumid']) OR !$post['isdeleted'])
	)
	{
	    $fetchtype = 'post';
	}

	if (!$firstpostid)
	{
	    $firstpostid = $post['postid'];
	}

	$post['islastshown'] = ($post['postid'] == $lastpostid);
	$post['isfirstshown'] = ($counter == 1 AND $fetchtype == 'post' AND $post['visible'] == 1);
	$post['islastshown'] = ($post['postid'] == $lastpostid);
	$post['attachments'] = $postattach["$post[postid]"];

	$canedit = false;
	if (
	    !$threadinfo['isdeleted'] AND !$post['isdeleted'] AND (
		can_moderate($threadinfo['forumid'], 'caneditposts') OR
		(
		    $threadinfo['open'] AND
		    $post['userid'] == vB::$vbulletin->userinfo['userid'] AND
		    ($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['caneditpost']) AND
		    ($post['dateline'] >= (TIMENOW - (vB::$vbulletin->options['edittimelimit'] * 60)) OR
		    vB::$vbulletin->options['edittimelimit'] == 0
		)
	    ))
	) {
	    $canedit = true;
	}

	// Get post date/time
	$postdate = vbdate(vB::$vbulletin->options['dateformat'], $post['dateline'], 1);
	$posttime = vbdate(vB::$vbulletin->options['timeformat'], $post['dateline']);

	$attachments = array();

	$fr_images = array();

	// Attachments (images).
	if (count($post['attachments']) > 0) {
	    foreach ($post['attachments'] as $attachment) {
		$lfilename = strtolower($attachment['filename']);
		if (strpos($lfilename, '.jpe') !== false ||
		    strpos($lfilename, '.png') !== false ||
		    strpos($lfilename, '.gif') !== false ||
		    strpos($lfilename, '.jpg') !== false ||
		    strpos($lfilename, '.jpeg') !== false) 
		{
		    $fr_images[] = array(
			'img' => vB::$vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'],
			'tmb' => vB::$vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachment['attachmentid'] . '&stc=1&thumb=1',
		    );
		}
	    }
	}

	// Parse the post for quotes and inline images
	list ($text, $nuked_quotes, $images) = parse_post($post['pagetext'], false);
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
	if (vB::$vbulletin->options['avatarenabled']) {
	    require_once(DIR . '/includes/functions_user.php');
	    $userinfo = fetch_userinfo($post['userid'], FETCH_USERINFO_AVATAR);
	    fetch_avatar_from_userinfo($userinfo);
	    if ($userinfo['avatarurl']) {
		$avatarurl = process_avatarurl($userinfo['avatarurl']);
	    }
	}

	$tmp = array(
	    'post_id' => $post['postid'],
	    'thread_id' => $post['threadid'],
	    'forum_id' => $foruminfo['forumid'],
	    'username' => prepare_utf8_string(strip_tags($post['username'])),
	    'joindate' => prepare_utf8_string($post['joindate']),
	    'usertitle' => prepare_utf8_string(strip_tags($post['usertitle'])),
	    'numposts' => $post['posts'],
	    'userid' => $post['userid'],
	    'title' => prepare_utf8_string($post['title']),
	    'post_timestamp' => prepare_utf8_string(date_trunc($postdate) . ' ' . $posttime),
	    'fr_images' => $fr_images,
	    'image_thumbs' => array(),
	);

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
	    if ($canedit) {
		$tmp['canedit'] = true;
		$tmp['edittext'] = prepare_utf8_string($post['pagetext']);
	    }
	}
	if ($avatarurl != '') {
	    $tmp['avatarurl'] = $avatarurl;
	}

	$posts_out[] = $tmp;
    }

    if ($LASTPOST['dateline'] > $displayed_dateline)
    {
	$displayed_dateline = $LASTPOST['dateline'];
	if ($displayed_dateline <= $threadview)
	{
	    $updatethreadcookie = true;
	}
    }

    // Set thread last view
    if ($displayed_dateline AND $displayed_dateline > $threadview)
    {
	mark_thread_read($threadinfo, $foruminfo, vB::$vbulletin->userinfo['userid'], $displayed_dateline);
    }

    vB::$vbulletin->db->free_result($posts);
    unset($post);

    $total = $record_count;

    return $posts_out;
}

function
get_comments ($permissions, $associatedthreadid)
{
    $sql = "SELECT distinct post.postid, post.visible, post.dateline
	FROM " . TABLE_PREFIX .	"post AS post
	WHERE threadid = $associatedthreadid AND parentid != 0 AND visible = 1 ORDER BY post.dateline ASC";

    if (! ($rst = vB::$vbulletin->db->query_read($sql)))
    {
	return false;
    }

    $ids = array();

    //Now we compare the fields. We need to check fields from the third
    // to the end of the row. If the value is different from the previous row,
    // we add a record.
    while($row =  vB::$vbulletin->db->fetch_array($rst))
    {
	if (can_view_post($row, $permissions))
	{
	    $ids[] = $row['postid'];
	}
    }

    if ((count($ids) == 1) and !intval($ids[0]))
    {
	$ids = false;
    }
    return $ids;
}

function
can_view_post ($post, $privileges)
{
    if (!$privileges['can_moderate_forums'] )
    {
	if ( $privileges['is_coventry'] OR ($post['visible'] == 2))
	{
	    return false;
	}
    }

    // post/thread is deleted by moderator and we don't have permission to see it
    return ($post['visible'] OR $privileges['can_moderate_posts']);
}

function
can_view_thread ($nodeid, $user)
{
    require_once DIR . '/vb/legacy/thread.php';

    if (! $row = vB::$vbulletin->db->query_first("SELECT nodeinfo.associatedthreadid
	AS threadid, thread.forumid FROM " . TABLE_PREFIX . "cms_nodeinfo
	AS nodeinfo LEFT JOIN " . TABLE_PREFIX . "thread AS thread
	ON thread.threadid = nodeinfo.associatedthreadid
	WHERE	nodeinfo.nodeid = $nodeid;" ))
    {
	return false;
    }

    //we have to worry about people deleting the thread or the forum. Annoying.
    if (intval($row['associatedthreadid']) AND ! intval($row['forumid']))
    {
	$this->repaircomments($record['associatedthreadid']);
	return false;
    }

    global $thread;
    $thread = vB_Legacy_Thread::create_from_id($row['threadid']);
    if (!$thread)
    {
	return false;
    }

    if (!$thread->can_view($user))
    {
	return false;
    }

    $can_moderate_forums = $user->canModerateForum($thread->get_field('forumid'));
    $can_moderate_posts = $user->canModerateForum($thread->get_field('forumid'), 'canmoderateposts');
    $is_coventry = false;

    if (!$can_moderate_forums)
    {
	//this is cached.  Should be fast.
	require_once (DIR . '/includes/functions_bigthree.php');
	$conventry = fetch_coventry();

	$is_coventry = (in_array($user->get_field('userid'), $conventry));

    }

    if (! $can_moderate_forums AND $is_coventry)
    {
	return false;
    }

    // If we got here, the user can at least see the thread. We still have
    // to check the individual records;
    return array('can_moderate_forums' => $can_moderate_forums,
	'is_coventry' => $is_coventry,
	'can_moderate_posts' => $can_moderate_posts);
}

function
create_associated_thread ($article)
{
    $foruminfo = fetch_foruminfo(vB::$vbulletin->options['vbcmsforumid']);

    if (!$foruminfo)
    {
	return false;
    }

    $dataman =& datamanager_init('Thread_FirstPost', vB::$vbulletin, ERRTYPE_ARRAY, 'threadpost');
    //$dataman->set('prefixid', $post['prefixid']);

    // set info
    $dataman->set_info('preview', '');
    $dataman->set_info('parseurl', true);
    $dataman->set_info('posthash', '');
    $dataman->set_info('forum', $foruminfo);
    $dataman->set_info('thread', array());
    $dataman->set_info('show_title_error', false);

    // set options
    $dataman->set('showsignature', true);
    $dataman->set('allowsmilie', false);

    // set data

    //title and message are needed for dupcheck later
    $title = new vB_Phrase('vbcms', 'comment_thread_title', htmlspecialchars_decode($article->getTitle()));
    $message = new vB_Phrase('vbcms', 'comment_thread_firstpost', vBCms_Route_Content::getURL(array('node' => $article->getUrlSegment())));
    $dataman->set('userid', $article->getUserId());
    $dataman->set('title', $title);
    $dataman->set('pagetext', $message);
    $dataman->set('iconid', '');
    $dataman->set('visible', 1);

    $dataman->setr('forumid', $foruminfo['forumid']);

    $errors = array();

    $dataman->pre_save();
    $errors = array_merge($errors, $dataman->errors);
    vB_Cache::instance()->event($article->getCacheEvents());

    if (sizeof($errors) > 0)
    {
	return false;
    }

    if (!($id = $dataman->save()))
    {
	throw (new vB_Exception_Content('Could not create comments thread for content'));
    }
    return $id;
}

?>
