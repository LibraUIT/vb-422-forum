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
		'pollresults' => array(
			'pollbits' => array(
				'*' => array(
					'names' => array(
						'*' => array(
							'loggedin' => array(
								'userid', 'username', 'invisiblemark', 'buddymark'
							)
						)
					),
					'option' => array('question', 'votes', 'percentraw'),
					'show' => array('pollvoters')
				)
			),
			'pollinfo' => array(
				'question', 'timeout', 'postdate', 'posttime', 'public', 'closed'
			),
			'pollenddate', 'pollendtime', 'pollstatus'
		),
		'threadinfo' => $VB_API_WHITELIST_COMMON['threadinfo'],
	),
	'show' => array(
		'pollvoters', 'multiple', 'editpoll', 'pollenddate'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/