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

//define('VB_API_LOADLANG', true);

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'actdata', 'activitybits' => array(
			'*' => array(
				'activity' => array(
					'posttime', 'postdate', 'section', 'type', 'score',
				),

				// This is filter by 'photo' output
				'activitybits' => array(
					'*' => array(
						'activity' => array(
							'posttime', 'postdate', 'section', 'type', 'score',
						),
						'attachmentinfo' => array(
							'attachmentid', 'dateline', 'thumbnail_width', 'thumbnail_height',
						),
						'albuminfo' => array(
							'albumid',
						),
						'groupinfo' => array(
							'groupid',
						),
					)
				),
				// -- end filter by 'photo' output
				'date',
				'photocount',
				'albuminfo' => array(
					'albumid', 'title', 'picturecount', 'views',
				),
				'articleinfo' => array(
					'fullurl', 'preview',
				),
				'attach' => array(
					'*' => array(
						'attachmentid', 'dateline', 'thumbnail_width', 'thumbnail_height',
					),
				),
				'bloginfo' => array(
					'blogid', 'title', 'blog_title', 'comments_visible', 'views', 'preview',
				),
				'blogtextinfo' => array(
					'blogtextid', 'preview',
				),
				'calendarinfo' => array(
					'calendarid', 'title',
				),
				'commentinfo' => array(
					'attachmentid', 'adateline', 'thumbnail_width', 'thumbnail_height', 'preview', 'commentid',
				),
				'discussioninfo' => array(
					'discussionid', 'title', 'preview', 'visible',
				),
				'eventinfo' => array(
					'eventid', 'title', 'preview',
				),
				'foruminfo' => array(
					'forumid', 'title',
				),
				'groupinfo' => array(
					'groupid', 'name',
				),
				'messageinfo' => array(
					'gmid', 'vmid', 'preview',
				),
				'nodeinfo' => array(
					'title', 'parenturl', 'parenttitle', 'replycount', 'viewcount', 'nodeid',
				),
				'postinfo' => array(
					'postid', 'threadid', 'preview',
				),
				'threadinfo' => array(
					'threadid', 'pollid', 'title', 'forumid', 'replycount', 'views', 'preview',
				),
				'show' => array(
					'threadcontent',
				),
				'userinfo' => array(
					'userid', 'username', 'avatarurl', 'showavatar',
				),
				'userinfo2' => array(
					'userid', 'username',
				),
			)
		)
	),
	'vboptions' => array(

	),
	'show' => array(
		'more_results', 'as_blog', 'as_cms', 'as_socialgroup', 'filterbar',
	)
);

function api_result_prewhitelist(&$value)
{
	if (is_array($value['response']['activitybits']['activitybits']))
	{
		$value['response']['activitybits'] = $value['response']['activitybits']['activitybits'];
	}
	foreach ($value['response']['activitybits'] as $k => &$v) 
	{
		if (isset($v['threadinfo']))
		{
			$v['threadinfo']['title'] = unhtmlspecialchars($v['threadinfo']['title']);
			$v['threadinfo']['preview'] = unhtmlspecialchars($v['threadinfo']['preview']);
		}
		if (isset($v['albuminfo']))
		{
			$v['albuminfo']['title'] = unhtmlspecialchars($v['albuminfo']['title']);
		}
		if (isset($v['articleinfo']))
		{
			$v['articleinfo']['preview'] = unhtmlspecialchars($v['articleinfo']['preview']);
		}
		if (isset($v['bloginfo']))
		{
			$v['bloginfo']['title'] = unhtmlspecialchars($v['bloginfo']['title']);
			$v['bloginfo']['blog_title'] = unhtmlspecialchars($v['bloginfo']['blog_title']);
			$v['bloginfo']['preview'] = unhtmlspecialchars($v['bloginfo']['preview']);
		}
		if (isset($v['blogtextinfo']))
		{
			$v['blogtextinfo']['preview'] = unhtmlspecialchars($v['blogtextinfo']['preview']);
		}
		if (isset($v['calendarinfo']))
		{
			$v['calendarinfo']['preview'] = unhtmlspecialchars($v['calendarinfo']['preview']);
		}
		if (isset($v['commentinfo']))
		{
			$v['commentinfo']['preview'] = unhtmlspecialchars($v['commentinfo']['preview']);
		}
		if (isset($v['discussioninfo']))
		{
			$v['discussioninfo']['title'] = unhtmlspecialchars($v['discussioninfo']['title']);
			$v['discussioninfo']['preview'] = unhtmlspecialchars($v['discussioninfo']['preview']);
		}
		if (isset($v['eventinfo']))
		{
			$v['eventinfo']['title'] = unhtmlspecialchars($v['eventinfo']['title']);
			$v['eventinfo']['preview'] = unhtmlspecialchars($v['eventinfo']['preview']);
		}
		if (isset($v['foruminfo']))
		{
			$v['foruminfo']['title'] = unhtmlspecialchars($v['foruminfo']['title']);
		}
		if (isset($v['groupinfo']))
		{
			$v['groupinfo']['name'] = unhtmlspecialchars($v['groupinfo']['name']);
		}
		if (isset($v['messageinfo']))
		{
			$v['messageinfo']['preview'] = unhtmlspecialchars($v['messageinfo']['preview']);
		}
		if (isset($v['nodeinfo']))
		{
			$v['nodeinfo']['title'] = unhtmlspecialchars($v['nodeinfo']['title']);
			$v['nodeinfo']['parenttitle'] = unhtmlspecialchars($v['nodeinfo']['parenttitle']);
		}
		if (isset($v['postinfo']))
		{
			$v['postinfo']['preview'] = unhtmlspecialchars($v['postinfo']['preview']);
		}
	}
}

vB_APICallback::instance()->add('result_prewhitelist', 'api_result_prewhitelist', 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/