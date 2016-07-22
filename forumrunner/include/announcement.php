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

require_once(MCWD . '/include/forumbits.php');

chdir('../');

define('THIS_SCRIPT', 'showthread');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');

function
do_get_announcement ()
{
    global $vbulletin, $db, $foruminfo;

    if (empty($foruminfo['forumid'])) {
	json_error(ERR_INVALID_FORUM);
    }

    $usesmilies = false;

    // begin vbulletin

	$forumlist = '';
	if ($announcementinfo['forumid'] > -1 OR $vbulletin->GPC['forumid'])
	{
		$foruminfo = verify_id('forum', $vbulletin->GPC['forumid'], 1, 1);
		$curforumid = $foruminfo['forumid'];
		$forumperms = fetch_permissions($foruminfo['forumid']);

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
		    json_error(ERR_NO_PERMISSION);
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
		$forumlist = fetch_forum_clause_sql($foruminfo['forumid'], 'announcement.forumid');
	}
	else if (!$announcementinfo['announcementid'])
	{
	    json_error(ERR_INVALID_ANNOUNCEMENT);
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';

	$announcements = $db->query_read_slave("
		SELECT announcement.announcementid, announcement.announcementid AS postid, startdate, enddate, announcement.title, pagetext, announcementoptions, views, announcement.pagetext,
			user.*, userfield.*, usertextfield.*,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
			" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			" . (($vbulletin->userinfo['userid']) ? ", NOT ISNULL(announcementread.announcementid) AS readannouncement" : "") . "
			$hook_query_fields
		FROM  " . TABLE_PREFIX . "announcement AS announcement
		" . (($vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "announcementread AS announcementread ON(announcementread.announcementid = announcement.announcementid AND announcementread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = announcement.userid)
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid)
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=announcement.userid)" : "") . "
		$hook_query_joins
		WHERE
			" . ($vbulletin->GPC['announcementid'] ?
				"announcement.announcementid = " . $vbulletin->GPC['announcementid'] :
				"startdate <= " . TIMENOW . " AND enddate >= " . TIMENOW . " " . (!empty($forumlist) ? "AND $forumlist" : "")
			) . "
			$hook_query_where
		ORDER BY startdate DESC, announcementid DESC
	");

	if ($db->num_rows($announcements) == 0)
	{ // no announcements
	    json_error(ERR_INVALID_ANNOUNCEMENT);
	}

	if (!$vbulletin->options['oneannounce'] AND $vbulletin->GPC['announcementid'] AND !empty($forumlist))
	{
		$anncount = $db->query_first_slave("
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "announcement AS announcement
			WHERE startdate <= " . TIMENOW . "
				AND enddate >= " . TIMENOW . "
				AND $forumlist
		");
		$anncount['total'] = intval($anncount['total']);
		$show['viewall'] = $anncount['total'] > 1 ? true : false;
	}
	else
	{
		$show['viewall'] = false;
	}

	require_once(DIR . '/includes/class_postbit.php');

	$show['announcement'] = true;

	$counter = 0;
	$anncids = array();
	$announcebits = '';
	$announceread = array();

	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	while ($post = $db->fetch_array($announcements))
	{
		$postbit_obj =& $postbit_factory->fetch_postbit('announcement');

		$post['counter'] = ++$counter;

		$postbit_obj->construct_postbit($post);
		$anncids[] = $post['announcementid'];
		$announceread[] = "($post[announcementid], " . $vbulletin->userinfo['userid'] . ")";

		// FRNR start

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
		list ($text, $nuked_quotes, $images) = parse_post($post['pagetext'], $usesmilies, $attachments);
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

		// Avatar work
		$avatarurl = '';
		if ($post['avatarurl']) {
		    $avatarurl = process_avatarurl($post['avatarurl']);
		}

		$tmp = array(
		    'username' => prepare_utf8_string(strip_tags($post['username'])),
		    'userid' => $post['userid'],
		    'title' => prepare_utf8_string($post['title']),
		    'text' => $text,
		    'post_timestamp' => prepare_utf8_string(date_trunc($post['startdate'])),
		    'fr_images' => $fr_images,
		);
		if ($avatarurl != '') {
		    $tmp['avatarurl'] = $avatarurl;
		}

		$posts_out[] = $tmp;
	}

	if (!empty($anncids))
	{
		$db->shutdown_query("
			UPDATE " . TABLE_PREFIX . "announcement
			SET views = views + 1
			WHERE announcementid IN (" . implode(', ', $anncids) . ")
		");

		if ($vbulletin->userinfo['userid'])
		{
			$db->shutdown_query("
				REPLACE INTO " . TABLE_PREFIX . "announcementread
					(announcementid, userid)
				VALUES
					" . implode(', ', $announceread) . "
			");
		}
	}

    

    if (!is_array($posts_out)) {
	$posts_out = array();
    }

	return array(
	    'posts' => $posts_out,
	    'total_posts' => count($posts_out),
	);

}

?>
