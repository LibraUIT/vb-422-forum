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

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'pagenumbers', 'totalposts',
		'activeusers' => $VB_API_WHITELIST_COMMON['activeusers'],
		'bookmarksites' => $VB_API_WHITELIST_COMMON['bookmarksites'],
		'FIRSTPOSTID',
		'firstunread', 'forumrules', 'LASTPOSTID', 'nextthreadinfo',
		'numberguest', 'numberregistered',
		'pagenav' => $VB_API_WHITELIST_COMMON['pagenav'],
		'pagenumber',
		'perpage',
		'poll' => array(
			'pollbits' => array(
				'*' => array(
					'option' => array('question', 'votes', 'percentraw'),
				),
			),
			'pollenddate', 'pollendtime',
			'pollinfo' => array(
				'pollid', 'question', 'numbervotes', 'multiple'
			),
			'pollstatus'
		),
		'postbits' => $VB_API_WHITELIST_COMMON['postbits'],
		'prevthreadinfo', 'postid', 
		'similarthreads' => array(
			'similarthreadbits' => array(
				'*' => array(
					'simthread' => array(
						'threadid', 'forumid', 'title', 'prefixid', 'taglist', 'postusername',
						'postuserid', 'replycount', 'preview', 'lastreplytime', 'prefix_plain_html',
						'prefix_rich'
					)
				)
			)
		),
		'tag_list',
		'thread' => $VB_API_WHITELIST_COMMON['threadinfo'],
		'threadlist', 'totalonline',
	),
	'show' => array(
		'threadinfo', 'threadedmode', 'linearmode', 'hybridmode', 'viewpost',
		'managepost', 'approvepost', 'managethread', 'approveattachment',
		'inlinemod', 'spamctrls', 'rating', 'editpoll', 'pollenddate', 'multiple',
		'publicwarning', 'largereplybutton', 'multiquote_global', 'firstunreadlink',
		'tag_box', 'manage_tag', 'activeusers', 'deleteposts', 'editthread',
		'movethread', 'stickunstick', 'openclose', 'moderatethread', 'deletethread',
		'adminoptions', 'addpoll', 'search', 'subscribed', 'threadrating', 'ratethread',
		'closethread', 'approvethread', 'unstick', 'reputation', 'sendtofriend',
		'next_prev_links'
	)
);

function api_result_prerender($t, &$r)
{
	switch ($t)
	{
		case 'showthread_similarthreadbit':
			$r['simthread']['lastreplytime'] = $r['simthread']['lastpost'];
			break;
		case 'SHOWTHREAD':
			$r['thread']['title'] = unhtmlspecialchars($r['thread']['title']);
			if ($r['postbits'][0])
			{
				foreach ($r['postbits'] as $k => &$v)
				{
					$v['post']['title'] = unhtmlspecialchars($v['post']['title']);
				}
			}
			else
			{
				$r['postbits']['post']['title'] = unhtmlspecialchars($r['postbits']['post']['title']);
			}
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender', 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/