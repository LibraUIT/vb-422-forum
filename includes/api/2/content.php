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

global $methodsegments;

// $methodsegments[1] 'action'
if ($methodsegments[1] == 'view')
{
	// format switch
	if ($_REQUEST['apitextformat'])
	{
		foreach ($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'] as $k => $v)
		{
			switch ($_REQUEST['apitextformat'])
			{
				case '1': // plain
					if ($v == 'message' OR $v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '2': // html
					if ($v == 'message_plain' OR $v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode
					if ($v == 'message' OR $v == 'message_plain')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // plain & html
					if ($v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode & html
					if ($v == 'message_plain')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode & plain
					if ($v == 'message')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
			}
		}
	}
}

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'vbcms_content_article_page':
		case 'vbcms_content_article_preview':
			$r['previewtext'] = strip_tags($r['previewtext']);
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_2', 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/