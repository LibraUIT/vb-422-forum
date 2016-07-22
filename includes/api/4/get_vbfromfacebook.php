<?php

/**
 *
 * @author Jorge Tiznado
 */
class vB_APIMethod_get_vbfromfacebook extends vBI_APIMethod
{
	public function output()
	{
		$data = array(
			'response' => array(
				'users' => $this->getvBUserWithForumList()
		));
		return $data;
	}

	private function getvBUserWithForumList()
	{
		global $vbulletin;
		$arrayResponse = array();

		$vbulletin->input->clean_array_gpc('p', array(
			'facebookidList' => TYPE_STR
		));
		
		// ensure list is only numbers and commas .. can't use intval() as fb uid can be 64bit and intval 
		// will eat that on a 32bit system
		$cleanlist = preg_replace('#[^0-9,]#s', '', $vbulletin->GPC['facebookidList']);
		$arraylist = preg_split("#,#s", $cleanlist, -1, PREG_SPLIT_NO_EMPTY);
		if ($arraylist)
		{
			$vBUserStringList = "";
			$separator = "";
			$vBUserlist = $vbulletin->db->query_read_slave("
				SELECT user.userid, user.username, user.fbuserid
				FROM " . TABLE_PREFIX . "user AS user
				WHERE fbuserid IN (" . implode(',', $arraylist) . ")
			");
			//error_log("SELECT user.userid, user.username FROM " . TABLE_PREFIX . "user WHERE fbuserid IN ($facebookidList)\n", 3, "/var/www/html/facebook/error/error1.txt");

			while ($vBUser = $vbulletin->db->fetch_array($vBUserlist))
			{
				$arrayResponse[] = array(
					'userid'   => $vBUser['userid'],
					'username' => $vBUser['username'],
					'fbuserid' => $vBUser['fbuserid']
				);

			}
		}
		
		if(!$arrayResponse)
		{
			$arrayResponse['response']['errormessage'][0] = 'no_users_in_facebook';
		}
		
		return $arrayResponse;
	}
}
?>
