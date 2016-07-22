<?php

class vB_APIMethod_facebook_getnewforummembers extends vBI_APIMethod
{
	public function output()
	{
		$data = array(
			'response' => array(
				'vBUsers' => $this->getvBUserWithForumList()
		));

		return $data;
	}

	private function getvBUserWithForumList()
	{
		global $vbulletin;

		$arrayResponse = array();

		if (is_facebookenabled() AND vB_Facebook::instance()->userIsLoggedIn())
		{
			$vbulletin->input->clean_array_gpc('r', array(
				'facebookidList' => TYPE_STR,
				'timestamp'      => TYPE_INT,
			));

			// ensure list is only numbers and commas .. can't use intval() as fb uid can be 64bit and intval 
			// will eat that on a 32bit system
			$cleanlist = preg_replace('#[^0-9,]#s', '', $vbulletin->GPC['facebookidList']);
			$arraylist = preg_split("#,#s", $cleanlist, -1, PREG_SPLIT_NO_EMPTY);

			if ($arraylist)
			{
				$timestamp = $vbulletin->GPC['timestamp'] ? $vbulletin->GPC['timestamp'] : 7 * 3600 * 24;

				$vBUserlist = $vbulletin->db->query_read_slave("
					SELECT user.userid, user.username, user.fbuserid
					FROM " . TABLE_PREFIX . "userlist AS userlist
					INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = userlist.relationid)
					WHERE
						user.fbuserid IN (" . implode(',', $arraylist) . ")
						AND userlist.userid = " . $vbulletin->userinfo['userid'] . "
						AND userlist.type = 'buddy'
						AND joindate >= $timestamp
				");

				while ($vBUser = $vbulletin->db->fetch_array($vBUserlist))
				{
					$arrayResponse[] = array(
						'vBuserid'   => $vBUser['userid'],
						'vBusername' => $vBUser['username'],
						'fbUserId'   => $vBUser['fbuserid'],
						'forums'     => $this->getSubscribedForumsOfTheUser($vBUser['userid'])
					);

				}
			}
			
			if (!$arrayResponse)
			{
				$arrayResponse['response']['errormessage'][0] = 'no_users_in_facebook';
			}
		}

		return $arrayResponse;
	}

	private function getSubscribedForumsOfTheUser($userId)
	{
		global $vbulletin;
		$forumsArray = array();

		$forums = $vbulletin->db->query_read_slave("
			SELECT forum.forumid, forum.title
			FROM " . TABLE_PREFIX . "forum AS forum
			INNER JOIN " . TABLE_PREFIX . "subscribeforum AS subscribeforum ON(forum.forumid = subscribeforum.forumid)
			WHERE userid = $userId
        ");

		while ($forum = $vbulletin->db->fetch_array($forums))
		{
			$forumsArray[] = array(
				'forumid' => $forum['forumid'],
				'forumname' => $forum['title']
			);
		}
		return $forumsArray;
	}
}

?>
