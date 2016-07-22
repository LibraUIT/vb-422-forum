<?php 
/**
 * PostRelease vBulletin Plugin
 *
 * @author Postrelease
 * @version 4.2.0
 * @copyright © PostRelease, Inc.
 */

define('BASE_URL', $_SERVER['HTTP_HOST']);
define('ADSERVE',"adserve.postrelease.com");
define('ADSERVE_PORT',80);

function pr_get_data_array_redux($forum_url, $action, $mobile=0){ 
	global $vbulletin; 
	$visitorID = $_COOKIE['prx_visitor'];
	$visitID = $_COOKIE['prx_visit'];
	if($vbulletin->userinfo['userid'] == 0){  
		$user_type = 'a';
	}  
	else {  
		$user_type = 'p';
	} 
	$json_string = get_json_string(call_adserve($forum_url,$action,$user_type,$visitorID, $visitID, $mobile));
	return json_decode($json_string);
} 

function get_json_string($http_result){ 
	$len = strpos($http_result, "\n{");  
	if($len and strlen($http_result)){
		return substr($http_result, $len+1, strlen($http_result) - $len); 
	} 
	return null; 
} 

function call_adserve($prx_url,$action, $user_type,$prx_visitorID=false,$prx_visitID=false, $mobile=0){ 
	global $db;
	$target_site = ADSERVE; 
	$target_port = ADSERVE_PORT; 
	
	$target_service = "/api/vb4/". $action."?prx_url=" . urlencode($prx_url); 
	if($prx_visitID) $target_service .= "&prx_visitid=" . $prx_visitID; 
	if($prx_visitorID) $target_service .= "&prx_visitor=" . $prx_visitorID; 
	$target_service .= "&prx_ro=" . $user_type;
	$target_service .= "&prx_mobile=" . $mobile;
	$ip = getIPfromXForwarded();
	if (strlen($ip) == 0){
		$ip = getenv("REMOTE_ADDR");
	} else {
		$ip = trim($ip);
	}
	$target_service .= "&prx_userip=" . $ip; // user ip
	$target_service .= "&prx_referrer=" . urlencode( $_SERVER['HTTP_REFERER']); // ReferrerUrl
	$target_service .= "&prx_agent=" .  urlencode($_SERVER['HTTP_USER_AGENT']); // user agent
	if (isset($_REQUEST['prx_rk'])){
		$target_service .= "&prx_rk=" .  $_REQUEST['prx_rk']; 
	}
	if (isset($_REQUEST['prx_t'])){
		$target_service .= "&prx_t=" .  $_REQUEST['prx_t']; 
	}
	if (isset ($db)){
		$PR_query =$db->query_first("SELECT secretKey FROM " . TABLE_PREFIX . "postrelease;");
		$target_service .= "&prx_sk=" .  $PR_query['secretKey']; 
	}
	return http_get_call($target_site,$target_service,$target_port);
} 

function http_get_call($target_site,$target_service,$port=80,$timeout=2){ 
	$fp = fsockopen($target_site, $port, $errno, $errstr, $timeout); 
	stream_set_timeout($fp,$timeout); 
	if (!$fp) { 
		return "$errstr ($errno)<br />\n"; 
	} else { 
		$out = "GET " . $target_service . " HTTP/1.1\r\n"; 
		$out .= "Host: " . $target_site . " \r\n"; 
		$out .= "User-Agent: PostRelease_vB_plugin\r\n"; 
		$out .= "Connection: Close\r\n\r\n"; 
		fwrite($fp, $out); 
		$result = ""; 
		while (!feof($fp)) { 
			$array = stream_get_meta_data($fp); 
			if ($array['timed_out']) { $result = ""; break; } 
			$result = $result . fgets($fp, 128); 
		} 
		fclose($fp); 
		return $result; 
	} 
}

function healthChek(){
	$health = @http_get_call(ADSERVE,'/',ADSERVE_PORT,2);
	if( strpos($health, "error") !== false) {
		return 2;
	}
	if(function_exists('fsockopen') == false) {
		return 3;
	}
	return 1;
}

function getIPfromXForwarded() { 
    $ipString=@getenv("HTTP_X_FORWARDED_FOR"); 
    $addr = explode(",",$ipString); 
    return $addr[sizeof($addr)-1]; 
}

function authIP(){
	$ip = getIPfromXForwarded();
	if (strlen($ip) == 0){
		$ip = getenv("REMOTE_ADDR");
	} else {
		$ip = trim($ip);
	}
	$data = @http_get_call('www.postrelease.com','/vbplugin/Api/AuthenticateIP?ip=' . $ip ,80,2);
	$json_string = get_json_string($data);
	$obj = json_decode($json_string);
	return $obj->{'result'};
}

function build_url($forumid, $forumTitle = '')
{
	global $vbulletin;
	$url = $vbulletin->input->fetch_basepath() . fetch_seo_url('forum', array('forumid' => $forumid, 'title' => $forumTitle));
	return $url;
}

?>