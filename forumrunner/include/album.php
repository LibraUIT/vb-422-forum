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

define('THIS_SCRIPT', 'album');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_album.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/packages/vbattach/attach.php');

$vbulletin->input->clean_array_gpc('r', array(
    'albumid'   => TYPE_UINT,
    'pictureid' => TYPE_UINT,
    'userid'    => TYPE_UINT,
));

$canviewalbums = (
    $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
    AND
    $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
    AND
    $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
);
$canviewgroups = (
    $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
    AND
    $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
);

if (!$canviewalbums)
{
    // check for do=='unread', allow if user can view groups since the picture comment may be from there
    if (
        $_REQUEST['do'] != 'unread'
        OR
        !$canviewgroups
    )
    {
        print_no_permission();
    }
}

$moderatedpictures = (
    (
        $vbulletin->options['albums_pictures_moderation']
        OR
        !($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['picturefollowforummoderation'])
    )
    AND
    !can_moderate(0, 'canmoderatepictures')
);

($hook = vBulletinHook::fetch_hook('album_start_precheck')) ? eval($hook) : false;

// if we specify an album, make sure our user context is sane
if ($vbulletin->GPC['albumid'])
{
    $albuminfo = fetch_albuminfo($vbulletin->GPC['albumid']);
    if (!$albuminfo)
    {
        standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
    }

    $vbulletin->GPC['userid'] = $albuminfo['userid'];
}

if ($vbulletin->GPC['attachmentid'])
{
    // todo
    $pictureinfo = fetch_pictureinfo($vbulletin->GPC['attachmentid'], $albuminfo['albumid']);
    if (!$pictureinfo)
    {
        standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
    }
}

if ($_REQUEST['do'] == 'overview')
{
    if ((!$vbulletin->GPC['userid'] AND !$vbulletin->userinfo['userid']) OR !($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
    {
        $_REQUEST['do'] = 'latest';
    }
}

// don't need userinfo if we're only viewing latest
if ($_REQUEST['do'] != 'latest')
{
    if (!$vbulletin->GPC['userid'])
    {
        if (!($vbulletin->GPC['userid'] = $vbulletin->userinfo['userid']))
        {
            print_no_permission();
        }
    }

    $userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1, FETCH_USERINFO_USERCSS);

    // don't show stuff for users awaiting moderation
    if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
    {
        print_no_permission();
    }

    cache_permissions($userinfo, false);
    if (!can_moderate(0, 'caneditalbumpicture') AND !($userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
    {
        print_no_permission();
    }

    if (!can_view_profile_section($userinfo['userid'], 'albums'))
    {
        // private album that we can not see
        standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
    }

    // determine if we can see this user's private albums and run the correct permission checks
    if (!empty($albuminfo))
    {
        if ($albuminfo['state'] == 'private' AND !can_view_private_albums($userinfo['userid']))
        {
            // private album that we can not see
            standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
        }
        else if ($albuminfo['state'] == 'profile' AND !can_view_profile_albums($userinfo['userid']))
        {
            // profile album that we can not see
            standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
        }
    }

    $usercss = construct_usercss($userinfo, $show['usercss_switch']);
    $show['usercss_switch'] = ($show['usercss_switch'] AND $vbulletin->userinfo['userid'] != $userinfo['userid']);
    construct_usercss_switch($show['usercss_switch'], $usercss_switch_phrase);
}

function
do_get_albums ()
{
    global $vbulletin, $db, $show, $vbphrase, $foruminfo, $userinfo;

    $vbulletin->input->clean_array_gpc('r', array(
        'page' => TYPE_UINT,
        'perpage' => TYPE_UINT,
    ));

    $state = array('public');
    if (can_view_private_albums($userinfo['userid']))
    {
        $state[] = 'private';
    }
    if (can_view_profile_albums($userinfo['userid']))
    {
        $state[] = 'profile';
    }

    $albumcount = $db->query_first("
        SELECT COUNT(*) AS total
        FROM " . TABLE_PREFIX . "album
        WHERE userid = $userinfo[userid]
        AND state IN ('" . implode("', '", $state) . "')
        ");

    if ($vbulletin->GPC['page'] < 1)
    {
        $vbulletin->GPC['page'] = 1;
    }

    $perpage = $vbulletin->GPC['perpage'];
    if ($perpage <= 0) {
        $perpage = 10;
    }
    $total_pages = max(ceil($albumcount['total'] / $perpage), 1); // handle the case of 0 albums
    $pagenumber = ($vbulletin->GPC['page'] > $total_pages ? $total_pages : $vbulletin->GPC['page']);
    $start = ($pagenumber - 1) * $perpage;

    $hook_query_fields = $hook_query_joins = $hook_query_where = '';
    ($hook = vBulletinHook::fetch_hook('album_user_query')) ? eval($hook) : false;

    // fetch data and prepare data
    $albums = $db->query_read("
        SELECT album.*,
        attachment.attachmentid,
        IF(filedata.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, filedata.thumbnail_dateline, filedata.thumbnail_width, filedata.thumbnail_height
        $hook_query_fields
        FROM " . TABLE_PREFIX . "album AS album
        LEFT JOIN " . TABLE_PREFIX . "attachment AS attachment ON (album.coverattachmentid = attachment.attachmentid)
        LEFT JOIN " . TABLE_PREFIX . "filedata AS filedata ON (attachment.filedataid = filedata.filedataid)
        $hook_query_joins
        WHERE
        album.userid = $userinfo[userid]
        AND
        album.state IN ('" . implode("', '", $state) . "')
        $hook_query_where
        ORDER BY album.lastpicturedate DESC
        LIMIT $start, $perpage
        ");

    $out_albums = array();

    while ($album = $db->fetch_array($albums))
    {
        $album['picturecount'] = vb_number_format($album['visible']);
        $album['picturedate'] = vbdate($vbulletin->options['dateformat'], $album['lastpicturedate'], true);
        $album['picturetime'] = vbdate($vbulletin->options['timeformat'], $album['lastpicturedate']);

        $album['description_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($album['description'])));
        $album['title_html'] = fetch_word_wrapped_string(fetch_censored_text($album['title']));

        $album['coverdimensions'] = ($album['thumbnail_width'] ? "width=\"$album[thumbnail_width]\" height=\"$album[thumbnail_height]\"" : '');

        if ($album['state'] == 'private')
        {
            $show['personalalbum'] = true;
            $albumtype = $vbphrase['private_album_paren'];
        }
        else if ($album['state'] == 'profile')
        {
            $show['personalalbum'] = true;
            $albumtype = $vbphrase['profile_album_paren'];
        }
        else
        {
            $show['personalalbum'] = false;
        }

        if ($album['moderation'] AND (can_moderate(0, 'canmoderatepictures') OR $vbulletin->userinfo['userid'] == $album['userid']))
        {
            $show['moderated'] = true;
            $album['moderatedcount'] = vb_number_format($album['moderation']);
        }
        else
        {
            $show['moderated'] = false;
        }

        $out = array(
            'albumid' => $album['albumid'],
            'title' => prepare_utf8_string(strip_tags(fetch_censored_text($album['title']))),
            'description' => prepare_utf8_string(strip_tags(fetch_censored_text($album['description']))),
            'private' => ($album['state'] == 'private'),
            'photo_count' => strval($album['picturecount']),
        );
        if ($album['hasthumbnail']) {
            $out['cover_url'] = fr_fix_url("attachment.php?{$session[sessionurl]}attachmentid={$album['attachmentid']}&thumb=1");
        }
        if ($album['lastpicturedate']) {
            $out['update_date'] = prepare_utf8_string($album['picturedate'] . ' ' . $album['picturetime']);
        } else {
            $createdate = vbdate($vbulletin->options['dateformat'], $album['createdate'], true);
            $createtime = vbdate($vbulletin->options['timeformat'], $album['createdate']);
            $out['update_date'] = prepare_utf8_string($createdate . ' ' . $createtime);
        }

        $out_albums[] = $out;
    }

    $show['add_album_option'] = ($userinfo['userid'] == $vbulletin->userinfo['userid']);

    ($hook = vBulletinHook::fetch_hook('album_user_complete')) ? eval($hook) : false;

    $out = array(
        'albums' => $out_albums,
        'total_albums' => $albumcount['total'],
        'can_add' => ($userinfo['userid'] == $vbulletin->userinfo['userid']),
    );

    return $out;
}

function
do_get_photos ()
{
    global $vbulletin, $db, $show, $vbphrase, $foruminfo, $userinfo, $albuminfo, $session, $contenttypeid;

    if (empty($albuminfo))
    {
        standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
    }

    if ($vbulletin->GPC['addgroup'] AND $albuminfo['userid'] != $vbulletin->userinfo['userid'])
    {
        print_no_permission();
    }
    ($hook = vBulletinHook::fetch_hook('album_album')) ? eval($hook) : false;

    $perpage = 999999;
    $vbulletin->GPC['pagenumber'] = 1;

    $input_pagenumber = $vbulletin->GPC['pagenumber'];

    if (can_moderate(0, 'canmoderatepictures') OR $albuminfo['userid'] == $vbulletin->userinfo['userid'])
    {
        $totalpictures = $albuminfo['visible'] + $albuminfo['moderation'];
    }
    else
    {
        $totalpictures = $albuminfo['visible'];
    }

    $total_pages = max(ceil($totalpictures / $perpage), 1); // 0 pictures still needs an empty page
    $pagenumber = ($vbulletin->GPC['pagenumber'] > $total_pages ? $total_pages : $vbulletin->GPC['pagenumber']);
    $start = ($pagenumber - 1) * $perpage;

    $hook_query_fields = $hook_query_joins = $hook_query_where = '';
    ($hook = vBulletinHook::fetch_hook('album_album_query')) ? eval($hook) : false;

    $pictures = $db->query_read("
        SELECT
        a.attachmentid, a.userid, a.caption, a.dateline, a.state,
        fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_dateline, fd.thumbnail_width, fd.thumbnail_height
        $hook_query_fields
        FROM " . TABLE_PREFIX . "attachment AS a
        INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
        $hook_query_joins
        WHERE
        a.contentid = $albuminfo[albumid]
        AND
        a.contenttypeid = " . intval($contenttypeid) . "
        " . ((!can_moderate(0, 'canmoderatepictures') AND $albuminfo['userid'] != $vbulletin->userinfo['userid']) ? "AND a.state = 'visible'" : "") . "
        $hook_query_where
        ORDER BY a.dateline DESC
        LIMIT $start, $perpage
        ");

    // work out the effective picturebit height/width including any borders and paddings; the +4 works around an IE float issue
    $picturebit_height = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2 + 4;
    $picturebit_width = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2;

    $out_photos = array();

    $picnum = 0;
    while ($picture = $db->fetch_array($pictures))
    {
        $picture = prepare_pictureinfo_thumb($picture, $albuminfo);

        if ($picnum % $vbulletin->options['album_pictures_perpage'] == 0)
        {
            $show['page_anchor'] = true;
            $page_anchor = ($picnum / $vbulletin->options['album_pictures_perpage']) + 1;
        }
        else
        {
            $show['page_anchor'] = false;
        }

        $picnum++;

        if ($picture['state'] != 'visible')
        {
            continue;
        }

        ($hook = vBulletinHook::fetch_hook('album_album_picturebit')) ? eval($hook) : false;

        $photo_url = "attachment.php?{$session[sessionurl]}attachmentid=$picture[attachmentid]";

        $out_photos[] = array(
            'photoid' => $picture['attachmentid'],
            'userid' => $picture['userid'],
            'caption' => prepare_utf8_string(strip_tags(fetch_censored_text($picture['caption']))),
            'photo_date' => prepare_utf8_string($picture['date'] . ' ' . $picture['time']),
            'photo_url' => fr_fix_url($photo_url),
            'thumb_url' => fr_fix_url($photo_url . '&thumb=1'),
        );
    }

    $show['add_picture_option'] = (
        $userinfo['userid'] == $vbulletin->userinfo['userid']
        AND fetch_count_overage($userinfo['userid'], $albuminfo[albumid], $vbulletin->userinfo['permissions']['albummaxpics']) <= 0
        AND (
            !$vbulletin->options['album_maxpicsperalbum']
            OR $totalpictures - $vbulletin->options['album_maxpicsperalbum'] < 0
        )
    );

    if ($albuminfo['state'] == 'private')
    {
        $show['personalalbum'] = true;
        $albumtype = $vbphrase['private_album_paren'];
    }
    else if ($albuminfo['state'] == 'profile')
    {
        $show['personalalbum'] = true;
        $albumtype = $vbphrase['profile_album_paren'];
    }

    $out = array(
        'photos' => $out_photos,
        'total_photos' => $totalpictures,
        'can_add_photo' => $show['add_picture_option'] ? true : false,
    );

    return $out;
}

function
do_create_album ()
{
    global $vbulletin, $db, $show, $vbphrase, $foruminfo, $userinfo, $albuminfo, $session;

    // adding new, can only add in your own
    if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
    {
        print_no_permission();
    }

    $vbulletin->input->clean_array_gpc('p', array(
        // albumid cleaned at the beginning
        'title'       => TYPE_NOHTML,
        'description' => TYPE_NOHTML,
        'albumtype'   => TYPE_STR
    ));

    if (!$vbulletin->GPC['albumtype']) {
        $vbulletin->GPC['albumtype'] = 'public';
    }

    $albumdata =& datamanager_init('Album', $vbulletin, ERRTYPE_ARRAY);
    if (!empty($albuminfo['albumid']))
    {
        $albumdata->set_existing($albuminfo);
        $albumdata->rebuild_counts();
    }
    else
    {
        $albumdata->set('userid', $vbulletin->userinfo['userid']);
    }

    if ($vbulletin->GPC['title']) {
        $albumdata->set('title', $vbulletin->GPC['title']);
    }
    if ($vbulletin->GPC['description']) {
        $albumdata->set('description', $vbulletin->GPC['description']);
    }

    $albumdata->set('state', $vbulletin->GPC['albumtype']);

    $albumdata->pre_save();

    ($hook = vBulletinHook::fetch_hook('album_album_update')) ? eval($hook) : false;

    if ($albumdata->errors)
    {
        json_error($albumdata->errors[0]);
    }
    else
    {
        $albumdata->save();
    }

    return array('success' => true);
}

function
do_upload_photo ()
{
    global $vbulletin, $db, $show, $vbphrase, $foruminfo, $userinfo, $albuminfo, $session, $contenttypeid;

    $vbulletin->input->clean_array_gpc('p', array(
        'caption' => TYPE_STR,
    ));

    if (empty($albuminfo))
    {
        standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
    }

    // adding new, can only add in your own
    if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
    {
        print_no_permission();
    }

    $vbulletin->input->clean_gpc('f', 'photo',    TYPE_FILE);
    // format vbulletin expects: $files[name][x]... we only have one per post
    $vbulletin->GPC['attachment'] = array(
	'name' => array($vbulletin->GPC['photo']['name']),
	'tmp_name' => array($vbulletin->GPC['photo']['tmp_name']),
	'error' => array($vbulletin->GPC['photo']['error']),
	'size' => array($vbulletin->GPC['photo']['size']),
    );

    $values['albumid'] = $vbulletin->GPC['albumid'];

    if (!$attachlib =& vB_Attachment_Store_Library::fetch_library($vbulletin, $contenttypeid, 0, $values)) {
        json_error("could not create attachment store");
    }

    if (!$attachlib->verify_permissions()) {
        json_error(ERR_NO_PERMISSION);
    }

    $uploadids = $attachlib->upload($vbulletin->GPC['attachment'], array(), $vbulletin->GPC['filedata']);
    $uploads = explode(',', $uploadids);

    if (!empty($attachlib->errors))
    {
	$errorlist = '';
	foreach ($attachlib->errors AS $error)
	{
	    $filename = htmlspecialchars_uni($error['filename']);
	    $errormessage = $error['error'] ? $error['error'] : $vbphrase["$error[errorphrase]"];
	    json_error($errormessage, RV_UPLOAD_ERROR);
	}
    }

    // Fetch possible destination albums
    $destination_result = $db->query_read("
        SELECT
        albumid, userid, title, coverattachmentid, state
        FROM " . TABLE_PREFIX . "album
        WHERE
        userid = $userinfo[userid]
        ");

    $destinations = array();

    if ($db->num_rows($destination_result))
    {
        while ($album = $db->fetch_array($destination_result))
        {
            $destinations[$album['albumid']] = $album;
        }
    }
    $db->free_result($destination_result);

    $picture_sql = $db->query_read("
        SELECT
        a.contentid, a.userid, a.caption, a.state, a.dateline, a.attachmentid, a.contenttypeid,
        filedata.extension, filedata.filesize, filedata.thumbnail_filesize, filedata.filedataid
        FROM " . TABLE_PREFIX . "attachment AS a
        INNER JOIN " . TABLE_PREFIX . "filedata AS filedata ON (a.filedataid = filedata.filedataid)
        WHERE
        a.contentid = 0
        AND
        a.attachmentid IN (" . implode(',', $uploads) . ")
        ");

    while ($picture = $db->fetch_array($picture_sql))
    {
        $attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_ARRAY, 'attachment');
        $attachdata->set_existing($picture);
        $attachdata->set_info('albuminfo', $albuminfo);
        $attachdata->set_info('destination', $destinations[$albuminfo['albumid']]);
        $attachdata->set('contentid', $albuminfo['albumid']);
        $attachdata->set('posthash', '');
        $attachdata->set('caption', $vbulletin->GPC['caption']);
        $attachdata->save();
    }

    // update all albums that pictures were moved to
    foreach ($destinations as $albumid => $album)
    {
        if (sizeof($album['moved_pictures']))
        {
            $albumdata =& datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
            $albumdata->set_existing($album);

            if (!$album['coverattachmentid'])
            {
                $albumdata->set('coverattachmentid', array_shift($album['moved_pictures']));
            }

            $albumdata->rebuild_counts();
            $albumdata->save();
            unset($albumdata);
        }
    }

    $albumdata =& datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
    $albumdata->set_existing($albuminfo);
    $albumdata->rebuild_counts();
    if ($new_coverid OR $updatecounter)
    {
        if ($new_coverid OR $cover_moved)
        {
            $albumdata->set('coverattachmentid', $new_coverid);
        }
    }
    $albumdata->save();
    unset($albumdata);

    // add to updated list
    if (can_moderate(0, 'canmoderatepictures')
        OR
        (!$vbulletin->options['albums_pictures_moderation']
        AND
        ($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['picturefollowforummoderation']))
    )
    {
        exec_album_updated($vbulletin->userinfo, $albuminfo);
    }

    return array('success' => true);
}

?>
