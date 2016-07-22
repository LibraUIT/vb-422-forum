<?php 
/**
 * PostRelease vBulletin Plugin
 *
 * @author Postrelease
 * @version 4.2.0
 * @copyright © PostRelease, Inc.
 */

if ($vbulletin->options['postrelease_enable']){
	$disabledGroups = explode(",", $vbulletin->options['postrelease_optout']);
	$usergroupid = $vbulletin->userinfo['usergroupid']; 
	if (!in_array($usergroupid ,$disabledGroups)){ 
		require_once('pr_utility.php');
		
		$prx_forum_url = build_url($foruminfo['forumid'],utf8_encode($foruminfo['title_clean']));
		$prx_forum_url = str_replace("&amp;", "&", $prx_forum_url);
		$pos = strpos($prx_forum_url, "&");
		if ($pos != false){
			$prx_forum_url = substr($prx_forum_url, 0, $pos);
		}
		
		$mobile = 0;
		if (defined('IS_MOBILE_STYLE')){
			if (IS_MOBILE_STYLE){
				$mobile = 1;
			} else {
				$mobile = 0;
			}
		}

		$pr_data = @pr_get_data_array_redux($prx_forum_url, "forumcategory", $mobile); 
		
		if(isset($pr_data)){ 
			$pr_thread['preview'] = $pr_data->Ad->Preview;
			$pr_thread['prx_title_url'] = $pr_data->Ad->PrimaryClickUrl;
			$pr_thread['threadtitle'] = $pr_data->Ad->Title;
			$pr_thread['prx_author_url'] = $pr_data->Ad->UserUrl;
			$pr_thread['prx_author_name'] = $pr_data->Ad->User;
			$pr_thread['prx_impressions'] = $pr_data->Ad->ThreadViews;
			$pr_thread['prx_imp_pixel_url'] = $pr_data->Ad->PrimaryImpressionPixelUrl;
			$pr_thread['prx_tracking_pixel_url'] = $pr_data->Sys->TrackingPixelUrl;
		
			$visitorID = $_COOKIE['prx_visitor'];
			$visitID = $_COOKIE['prx_visit'];
			if (!isset($visitorID)){
				setcookie('prx_visitor', $pr_data->Sys->VisitorID, time()+86400); // 1 day
			}
			if (!isset($visitID)){
				setcookie('prx_visit', $pr_data->Sys->VisitID, time()+1800); // 30 minutes
			}
			if (isset($pr_data->Ad->TemplateHtml)){
				@eval($pr_data->Ad->TemplateHtml);
				$threadbits = @get_pr_threadbit($pr_thread, SIMPLE_VERSION) . $threadbits;
			}
		}
	}
}

?>