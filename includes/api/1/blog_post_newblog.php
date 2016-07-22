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

define('VB_API_LOADLANG', true);

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'content' => array(
			'attachmentoption' => $VB_API_WHITELIST_COMMON['attachmentoption'],
			'disablesmiliesoption', 'draft_options',
			'bloginfo' => $VB_API_WHITELIST_COMMON['bloginfo'],
			'globalcategorybits' => array(
				'*' => array(
					'blogcategoryid',
					'category' => array(
						'title'
					),
					'checked'
				)
			),
			'localcategorybits' => array(
				'*' => array(
					'blogcategoryid',
					'category' => array(
						'title'
					),
					'checked'
				)
			),
			'messagearea' => array(
				'newpost'
			),
			'notification', 'posthash', 'postpreview', 'poststarttime',
			'publish_selected', 'reason', 'taglist', 'tags_remain',
			'tag_delimiters', 'title', 'userid', 'htmlchecked'
		)
	),
	'vboptions' => array(
		'postminchars', 'titlemaxchars'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/