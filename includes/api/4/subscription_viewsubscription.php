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

$threadbit = $VB_API_WHITELIST_COMMON['threadbit'];
$threadbit['thread'][] = 'forumtitle';

$VB_API_WHITELIST = array(
	'response' => array(
		'HTML' => array(
			'folder', 'folderjump', 'pagenav', 'totalallthreads',
			'threadbits' => array(
				//'*' => $VB_API_WHITELIST_COMMON['threadbit']
                                '*' => $threadbit
			)
		)
	),
	'show' => array(
		'allfolders', 'threadicons', 'dotthreads', 'havethreads',
	)
);
/*
function api_result_prewhitelist_1(&$value)
{
    //error_log("r =" . print_r($value, true) . " \n", 3, "/var/www/html/facebook/error/error1.txt");
    /*if ($value['response'])
    {
            $value['response']['layout']['content']['contents'] = $value['response']['layout']['content']['content_rendered']['contents'];
    }
    $threadbits = $value['response']['HTML']['threadbits'];
    $forumArray = array();
    foreach($threadbits as $thread){

        $forumArray[$thread['memberaction_dropdown']['memberinfo']['realthreadid']]['forumid'] = $thread['memberaction_dropdown']['memberinfo']['forumid'];
        $forumArray[$thread['memberaction_dropdown']['memberinfo']['realthreadid']]['forumtitle'] = $thread['memberaction_dropdown']['memberinfo']['forumtitle'];
        //$forumArray['threadid']
        //$thread['thread']['forum']['forumid'] = $thread['memberaction_dropdown']['memberinfo']['forumtitle'];
    }
    //error_log("r =" . print_r($forumArray, true) . " \n", 3, "/var/www/html/facebook/error/error1.txt");
     $value['response']['HTML']['forums'] = $forumArray;
}

vB_APICallback::instance()->add('result_prewhitelist', 'api_result_prewhitelist_1', 1);

function api_result_prerender_1($t, &$r){


    $r['forumid'] = $r['foruminfo']['forumid'];
    $r['forumtitle'] = $r['foruminfo']['title'];
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_1', 1);
*/
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/