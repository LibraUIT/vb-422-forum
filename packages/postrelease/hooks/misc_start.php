<?php
/**
 * PostRelease vBulletin Plugin
 *
 * @author Postrelease
 * @version 4.2.0
 * @copyright © PostRelease, Inc.
 */
if ($_REQUEST['do'] == 'postrelease_get_key'){
	require_once('pr_utility.php');
	if ( authIP() == 1){
		$key = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',5)),0,10);
		$db->query("UPDATE " . TABLE_PREFIX . "postrelease SET secretKey='". $key."'");
		header("Content-type: text/xml");
		echo "<prkey>" . $key . "</prkey>";
	}
	exit;
}

if ($vbulletin->options['postrelease_enable'] && $_REQUEST['do'] == 'postrelease')
{
	require_once('pr_utility.php');
	$prx_forum_url = "http://" . $_SERVER["HTTP_HOST"] .  $_SERVER["REQUEST_URI"];
	
	$mobile = 0;
	if (defined('IS_MOBILE_STYLE')){
		if (IS_MOBILE_STYLE){
			$mobile = 1;
		} else {
			$mobile = 0;
		}
	}
		
	$pr_data = pr_get_data_array_redux($prx_forum_url, "template", $mobile);
	if (isset($pr_data)){
		$prx_author = $pr_data->Ad->User;
		$prx_author_url  = $pr_data->Ad->UserUrl;
		$prx_author_img = $pr_data->Ad->UserImg;
		$prx_title = $pr_data->Ad->Title;
		$prx_body = $pr_data->Ad->HtmlBody;
		$prx_forum_id = $pr_data->Ad->WebsiteID;
		$prx_imp_pixel_url = $pr_data->Ad->SecondaryImpressionPixelUrl;
		$prx_tracking_pixel_url = $pr_data->Sys->TrackingPixelUrl;
		if (isset($prx_tracking_pixel_url) && $prx_tracking_pixel_url != null){
			$prx_tracking_pixel_url = '<img src="' . $prx_tracking_pixel_url . '" width="1" height="1" />';
		} else {
			$prx_tracking_pixel_url = "";
		}
		if (isset($prx_imp_pixel_url ) && $prx_imp_pixel_url != null){
			$prx_imp_pixel_url = '<img src="' . $prx_imp_pixel_url . '" width="1" height="1" />';
		} else {
			$prx_imp_pixel_url = "";
		}
		if ($mobile == 0){
			vB_Template::preRegister('postrelease_vb4',array('prx_title' => $prx_title));
			vB_Template::preRegister('postrelease_vb4',array('prx_imp_pixel_url' => $prx_imp_pixel_url));
			vB_Template::preRegister('postrelease_vb4',array('prx_tracking_pixel_url' => $prx_tracking_pixel_url));
		} else {
			vB_Template::preRegister('postrelease_vb4_mobile',array('prx_title' => $prx_title));
			vB_Template::preRegister('postrelease_vb4_mobile',array('prx_imp_pixel_url' => $prx_imp_pixel_url));
			vB_Template::preRegister('postrelease_vb4_mobile',array('prx_tracking_pixel_url' => $prx_tracking_pixel_url));
		}
		$visitorID = $_COOKIE['prx_visitor'];
		$visitID = $_COOKIE['prx_visit'];
		if (!isset($visitorID)){
			setcookie('prx_visitor', $pr_data->Sys->VisitorID, time()+86400); // 1 day
		}
		if (!isset($visitID)){
			setcookie('prx_visit', $pr_data->Sys->VisitID, time()+1800); // 30 minutes
		}
		
	}
	if ($mobile == 0){
	    if ($vbulletin->options['legacypostbit'])
	    {
			if(isset($pr_data)){  
				vB_Template::preRegister('postrelease_vb4_postbits_legacy',array('prx_author' => $prx_author));
				vB_Template::preRegister('postrelease_vb4_postbits_legacy',array('prx_author_url' => $prx_author_url));
				vB_Template::preRegister('postrelease_vb4_postbits_legacy',array('prx_author_img' => $prx_author_img));
				vB_Template::preRegister('postrelease_vb4_postbits_legacy',array('prx_title' => $prx_title));
				vB_Template::preRegister('postrelease_vb4_postbits_legacy',array('prx_body' => $prx_body));
			}
	        $templater = vB_Template::create('postrelease_vb4_postbits_legacy');
	        $postbits = $templater->render(); 
	         
	    }
	    else
	    {
			if(isset($pr_data)){  
				vB_Template::preRegister('postrelease_vb4_postbits',array('prx_author' => $prx_author));
				vB_Template::preRegister('postrelease_vb4_postbits',array('prx_author_url' => $prx_author_url));
				vB_Template::preRegister('postrelease_vb4_postbits',array('prx_author_img' => $prx_author_img));			
				vB_Template::preRegister('postrelease_vb4_postbits',array('prx_title' => $prx_title));
				vB_Template::preRegister('postrelease_vb4_postbits',array('prx_body' => $prx_body));
			}
	        $templater = vB_Template::create('postrelease_vb4_postbits');
	        $postbits = $templater->render();  
	    } 
	} else {
		if(isset($pr_data)){  
			vB_Template::preRegister('postrelease_vb4_postbits_mobile',array('prx_author' => $prx_author));
			vB_Template::preRegister('postrelease_vb4_postbits_mobile',array('prx_author_url' => $prx_author_url));
			vB_Template::preRegister('postrelease_vb4_postbits_mobile',array('prx_author_img' => $prx_author_img));
			vB_Template::preRegister('postrelease_vb4_postbits_mobile',array('prx_title' => $prx_title));
			vB_Template::preRegister('postrelease_vb4_postbits_mobile',array('prx_body' => $prx_body));
		}
        $templater = vB_Template::create('postrelease_vb4_postbits_mobile');
        $postbits = $templater->render(); 
	}
	
	$foruminfo = fetch_foruminfo($prx_forum_id);
	$navbits = array();
	if (SIMPLE_VERSION > 410){
		$navbits[fetch_seo_url('forumhome', array())] = $vbphrase['forum'];
	} else {
		$navbits[$vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q']] = $vbphrase['forum'];
	}
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits[fetch_seo_url('forum', array('forumid' => $forumID, 'title' => $forumTitle))] = $forumTitle;
	}
	
	$navbits[''] = $prx_title;
	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);	 
    if ($mobile == 0){
    	$templater = vB_Template::create('postrelease_vb4');
    } else {
    	$templater = vB_Template::create('postrelease_vb4_mobile');
    }
    $templater->register_page_templates();
    $templater->register('navbar', $navbar);
    $templater->register('postbits', $postbits);
    print_output($templater->render());
}
else if ($_REQUEST['do'] == 'postrelease_get_status')
{
	$vbseo_proc = 0;
	if (defined('VBSEO_ENABLED') && VBSEO_ENABLED){
		if (!defined('VBSEO_UNREG_EXPIRED')){
			$vbseo_proc = 1;
		}
		$vbseoversion = VBSEO_VERSION2_MORE;
	}
	require_once('pr_utility.php');
	$ip = getIPfromXForwarded();
	if (strlen($ip) == 0){
		$ip = getenv("REMOTE_ADDR");
	} else {
		$ip = trim($ip);
	}
	header("Content-type: text/xml");
	$xml = "<postrelease>"; //threads
	$xml .= '<enabled>'. $vbulletin->options['postrelease_enable']  .'</enabled>'; 
	$result =$db->query_read("SELECT version FROM " . TABLE_PREFIX . "product WHERE productid='postrelease';");
	$product = $db->fetch_array($result);	
	$xml .= '<version>'. $product['version'] .'</version>';
	$db->free_result($result);
	$xml .= '<vbversion>'. SIMPLE_VERSION  .'</vbversion>'; 
	$xml .= '<vbfileversion><![CDATA['. FILE_VERSION.']]></vbfileversion>';
	$xml .= '<vbseo>'. $vbseo_proc  .'</vbseo>';
	$xml .= '<vbseoversion>'. $vbseoversion  .'</vbseoversion>';
	$xml .= '<styleid>'. $vbulletin->styleid  .'</styleid>'; 
	$xml .= '<mobilestyleid_advanced>'. $vbulletin->options['mobilestyleid_advanced']  .'</mobilestyleid_advanced>'; 
	$xml .= '<mobilestyleid_basic>'. $vbulletin->options['mobilestyleid_basic']  .'</mobilestyleid_basic>'; 
	$xml .= '<bbtitle><![CDATA['. utf8_encode($vbulletin->options['bbtitle'])  .']]></bbtitle>';
	$xml .= '<healthcheck><![CDATA['. healthChek()  .']]></healthcheck>';
	$xml .= '<remotehost><![CDATA['. $ip  .']]></remotehost>';
	$xml .= '</postrelease>';
	echo $xml;
	exit;
}

$pr_validation = false;
$key = $_REQUEST['id'];
if (($key != null) || (strlen($key) == 32)){
	$PR_query =$db->query_first("SELECT secretKey FROM " . TABLE_PREFIX . "postrelease;");
	$prkey = $PR_query['secretKey'];
    $ukey = md5($prkey);
    if ($ukey == $key){
        $pr_validation = true;
    }
} 

if ($vbulletin->options['postrelease_enable'] && $_REQUEST['do'] == 'postrelease_get_categories' && $pr_validation )
{
	require_once('pr_utility.php');
	$result=$db->query_read("SELECT forumid AS id,parentid,title_clean,description_clean, showprivate, options, parentlist, childlist, displayorder FROM " . TABLE_PREFIX . "forum ORDER BY displayorder, parentid, id;");
  	header("Content-type: text/xml; charset=UTF-8");
  	$xml = "<categories>";
  	while($categories = $db->fetch_array($result))
    {
    	if ($categories['options'] & $vbulletin->bf_misc_forumoptions['active']){
    		$id = $categories['id'];
      		$xml .= "<category id=\"" . $id . "\">\n";
			$xml .= "<name><![CDATA[" . utf8_encode($categories['title_clean']) . "]]></name>\n";
			$xml .= "<description><![CDATA[" . utf8_encode($categories['description_clean']) . "]]></description>\n";
			$xml .= "<url><![CDATA[" . @build_url($id, utf8_encode($categories['title_clean'])) .  "]]></url>\n";
			$xml .= "<parentId>" . $categories['parentid'] . "</parentId>\n";
			$forumperms = ($vbulletin->userinfo['forumpermissions'][$id] ? 0 : 1);
			$xml .= "<private>" . $forumperms . "</private>\n";
	      	if ( $categories['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']){
				$xml .= "<acceptThreads>1</acceptThreads>\n";
	      	} else {
				$xml .= "<acceptThreads>0</acceptThreads>\n";
	      	}
			$xml .= "<childlist>" . $categories['childlist']  . "</childlist>\n";
			$xml .= "<parentlist>" . $categories['parentlist'] . "</parentlist>\n";
			$xml .= "<displayorder>" . $categories['displayorder'] . "</displayorder>\n";
			$xml .= "</category>\n";
    	}
    }
  	$xml .= "</categories>";
    $db->free_result($result);
  	echo $xml;
  	exit;
}
else if ($vbulletin->options['postrelease_enable'] && $_REQUEST['do'] == 'postrelease_get_threads' && $pr_validation )
{
	header("Content-type: text/xml; charset=UTF-8");
	$xml;
	if (isset($_REQUEST['forumid'])){
		$xml = "<ts>"; //threads
		$threads=$db->query_read("SELECT threadid, title, keywords FROM " . TABLE_PREFIX . "thread WHERE forumid='" . $vbulletin->db->escape_string($_REQUEST['forumid']). "' ORDER BY lastpost DESC LIMIT 200;");		
  		while($thread = $db->fetch_array($threads))
    	{
    		$xml .= '<t id="'. $thread['threadid'] .'">'; //thread
    		$xml .= '<tt><![CDATA['. utf8_encode($thread['title']) .']]></tt>'; //title
    		$xml .= '<k><![CDATA['. utf8_encode($thread['keywords']) .']]></k>'; //keywords
    		$xml .= '<ps>'; //posts
			$posts=$db->query_read("SELECT title, pagetext FROM " . TABLE_PREFIX . "post WHERE threadid=". $thread['threadid']." ORDER BY dateline DESC LIMIT 50;");
			while($post = $db->fetch_array($posts))
    		{
    			$xml .= '<p>'; //post
				$xml .= '<ti><![CDATA['. utf8_encode($post['title']) .']]></ti>'; //title
    			$xml .= '<tx><![CDATA['. utf8_encode($post['pagetext']) .']]></tx>'; // text		
    			$xml .= '</p>';
    		}
    		$db->free_result($posts);
			$xml .= '</ps>';
			$xml .= "</t>";
    	}
    	$db->free_result($threads);
    	$xml .= "</ts>";
	}
	echo $xml;
	exit;
} 
else if ($vbulletin->options['postrelease_enable'] && $_REQUEST['do'] == 'postrelease_get_stats' && $pr_validation )
{
	header("Content-type: text/xml");
	$xml;
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $days=$_REQUEST['numberOfDays'];
    $start = mysql_escape_string($start);
    $end = mysql_escape_string($end);
    $days=mysql_escape_string($days);
    $numberOfPosts = 0;
    $numberOfUsers = 0;
    $numberOfThreads = 0;
    if (($start != '') && ($end != '')){
        if (is_numeric($start) && is_numeric($end)){
            $PR_query = $db->query_first("SELECT COUNT(postid) as posts FROM " . TABLE_PREFIX . "post WHERE dateline BETWEEN ".$start." AND ".$end.";");
            $numberOfPosts = $PR_query['posts'];
            $PR_query = $db->query_first("SELECT COUNT(userid) as users FROM " . TABLE_PREFIX . "user WHERE joindate BETWEEN ".$start." AND ".$end.";");
            $numberOfUsers = $PR_query['users'];
            $PR_query = $db->query_first("SELECT COUNT(threadid) as threads FROM " . TABLE_PREFIX . "thread WHERE dateline BETWEEN ".$start." AND ".$end.";");
            $numberOfThreads = $PR_query['threads'];
        } 
    } else {
        $PR_query = $db->query_first("SELECT COUNT(postid) as posts FROM " . TABLE_PREFIX . "post;");
        $numberOfPosts = $PR_query['posts'];
        $PR_query = $db->query_first("SELECT COUNT(userid) as users FROM " . TABLE_PREFIX . "user;");
        $numberOfUsers = $PR_query['users'];
        $PR_query = $db->query_first("SELECT COUNT(threadid) as threads FROM " . TABLE_PREFIX . "thread;");
        $numberOfThreads = $PR_query['threads'];
    }
    if (!is_numeric($days)){
    	$days = 30;
    }
    $daysInUnixTime = floatval($days);
    $daysInUnixTime = $daysInUnixTime * 86400;
	$PR_query = $db->query_first("SELECT COUNT(userid) as users FROM " . TABLE_PREFIX . "user WHERE lastactivity > (UNIX_TIMESTAMP(NOW())-" . $daysInUnixTime . ");");
	$activeUsers = $PR_query['users'];
    $xml = "<stats>";
    $xml .= "<numberOfThreads>" . $numberOfThreads ."</numberOfThreads>";
    $xml .= "<numberOfPosts>". $numberOfPosts ."</numberOfPosts>";
    $xml .= "<numberOfUsers>". $numberOfUsers ."</numberOfUsers>";
    $xml .= "<activeUsers>". $activeUsers ."</activeUsers>";
	$xml .= "</stats>"; 
	echo $xml;
	exit;
} 


?>