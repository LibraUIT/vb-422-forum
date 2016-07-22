<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

$VB_API_WHITELIST = array(
	'response' => array(
		'attachmentoption' => $VB_API_WHITELIST_COMMON['attachmentoption'],
		'disablesmiliesoption', 'emailchecked', 'folderbits',
		'explicitchecked', 'podcastauthor', 'podcastkeywords',
		'podcastsize', 'podcastsubtitle', 'podcasturl', 'posthash',
		'posticons',
		'postinfo' => array(
			'postid', 'username', 'userid', 'posttime'
		),
		'postpreview', 'poststarttime', 'prefix_options',
		'selectedicon',
		'threadinfo' => $VB_API_WHITELIST_COMMON['threadinfo'],
		'title', 'htmloption',
		'messagearea' => array(
			'newpost'
		)
	),
	'show' => array(
		'subscriptionfolders', 'empty_prefix_option', 'posticons', 'deletepostoption',
		'openclose', 'stickunstick', 'closethread', 'unstickthread', 'physicaldeleteoption',
		'keepattachmentsoption', 'firstpostnote', 'parseurl', 'misc_options', 'smiliebox', 'attach'
	)
);

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'editpost':
			$r['postinfo']['posttime'] = $r['postinfo']['dateline'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_2', 2);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/