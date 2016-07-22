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

define('THIS_SCRIPT', 'forumrunner');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_file.php');
require_once(DIR . '/packages/vbattach/attach.php');

$vbulletin->input->clean_array_gpc('r', array(
    'poststarttime' => TYPE_UINT,
));

$attachmentid = 0;
$contenttypeid = 1;

if (!$vbulletin->userinfo['userid'] OR empty($vbulletin->GPC['poststarttime'])) {
    json_error(ERR_NO_PERMISSION);
}

$vbulletin->GPC['posthash'] = md5($vbulletin->GPC['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

if ($vbulletin->GPC_exists['forumid']) {
    $values[f] = $vbulletin->GPC['forumid'];
}
if ($vbulletin->GPC_exists['threadid']) {
    $values[t] = $vbulletin->GPC['threadid'];
}
$values[poststarttime] = $vbulletin->GPC['poststarttime'];
$values[posthash] = $vbulletin->GPC['posthash'];

if (!$attachlib =& vB_Attachment_Store_Library::fetch_library($vbulletin, $contenttypeid, $vbulletin->GPC['categoryid'], $values)) {
    json_error("eek");
}

if (!$attachlib->verify_permissions()) {
    json_error(ERR_NO_PERMISSION);
}

function
do_upload_attachment ()
{
    global $vbulletin, $db, $foruminfo, $attachlib;

    $vbulletin->input->clean_gpc('f', 'attachment',    TYPE_FILE);
    // format vbulletin expects: $files[name][x]... we only have one per post
    $vbulletin->GPC['attachment'] = array(
	'name' => array($vbulletin->GPC['attachment']['name']),
	'tmp_name' => array($vbulletin->GPC['attachment']['tmp_name']),
	'error' => array($vbulletin->GPC['attachment']['error']),
	'size' => array($vbulletin->GPC['attachment']['size']),
    );

    if ($vbulletin->GPC['flash'] AND is_array($vbulletin->GPC['attachment']))
    {
	$vbulletin->GPC['attachment']['utf8_names'] = true;
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

    return array(
	'attachmentid' => $uploads[0],
    );
}

function
do_delete_attachment ()
{
    global $vbulletin, $attachlib;

    $vbulletin->input->clean_array_gpc('r', array(
	'attachmentid' => TYPE_UINT,
    ));

    $delete[$vbulletin->GPC['attachmentid']] = 1;
    $attachlib->delete($delete);

    return array(
	'success' => 1,
    );
}
