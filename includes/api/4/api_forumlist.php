<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod_api_forumlist_4 extends vBI_APIMethod
{
	private $subscribed_forums;
	public function output()
	{
		global $vbulletin;
		
		//
		//	Get forum subscription info for this user
		//
		$this->subscribed_forums = array();
		if($vbulletin->userinfo['userid']) {
			$results = $vbulletin->db->query_read_slave("
				SELECT subscribe.userid, subscribe.forumid, forum.* 
				FROM " . TABLE_PREFIX . "subscribeforum AS subscribe
				INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (subscribe.forumid = forum.forumid)
				WHERE subscribe.userid = " . $vbulletin->userinfo['userid']
			);
			while ($row = $vbulletin->db->fetch_array($results)) {
				$this->subscribed_forums[$row['forumid']] = $row;
			}
		}
		
		require_once(DIR . '/includes/functions_forumlist.php');

		if (empty($vbulletin->iforumcache))
		{
			cache_ordered_forums(1, 1);
		}

		return $this->getforumlist(-1);
	}

	private function getforumlist($parentid)
	{
		global $vbulletin, $counters, $lastpostarray;

		if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
		{
			return;
		}

		// call fetch_last_post_array() first to get last post info for forums
		if (!is_array($lastpostarray))
		{
			fetch_last_post_array($parentid);
		}
		
		foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
		{
			$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
			if (
					(
						!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
						AND
						($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums']))
					)
					OR
					!$vbulletin->forumcache["$forumid"]['displayorder']
					OR
					!($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['active'])
				)
			{
				continue;
			}
			else
			{
				$forum = $vbulletin->forumcache["$forumid"];
				$is_category = !((bool) ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']));
                $is_link = $forum['link'];
				
				$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
				$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];
                
                $lastpostinfo = (empty($lastpostarray["$forumid"]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);

                // compare last post time for this forum with the last post time specified by
                // the $lastpostarray, and if it's less, use the last post info from the forum
                // specified by $lastpostarray
                if (!empty($lastpostinfo) AND $vbulletin->forumcache["$lastpostarray[$forumid]"]['lastpost'] > 0)
                {
                    if (
                        !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR 
                        (
                            !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND 
                            $lastpostinfo['lastposter'] != $vbulletin->userinfo['username']
                        )
                    )
                    {
                        $forum['lastpostinfo'] = '';
                    }
                    else
                    {
                        if ($lastpostinfo['lastprefixid'] AND $vbulletin->options['showprefixlastpost'])
                        {
                            $lastpostinfo['prefix'] = ($vbulletin->options['showprefixlastpost'] == 2 ?
                                $vbphrase["prefix_$lastpostinfo[lastprefixid]_title_rich"] :
                                htmlspecialchars_uni($vbphrase["prefix_$lastpostinfo[lastprefixid]_title_plain"])
                            );
                        }
                        else
                        {
                            $lastpostinfo['prefix'] = '';
                        }
                        $lastpostinfo2 = array();
                        
                        $lastpostinfo2['lastposter'] = $lastpostinfo['lastposter'];
                        $lastpostinfo2['lastposterid'] = $lastpostinfo['lastposterid'];
                        $lastpostinfo2['lastthread'] = $lastpostinfo['lastthread'];
                        $lastpostinfo2['lastthreadid'] = $lastpostinfo['lastthreadid'];
                        $lastpostinfo2['lastposttime'] = $lastpostinfo['lastpost'];
                        $lastpostinfo2['prefix'] = $lastpostinfo['prefix'];
                        
                        $forum['lastpostinfo'] = $lastpostinfo2;
                    }
                }
                
				$forum2 = array(
					'forumid' => $forum['forumid'],
					'title' => $forum['title'],
					'description' => $forum['description'],
					'title_clean' => $forum['title_clean'],
					'description_clean' => $forum['description_clean'],
					'parentid' => $forum['parentid'],
					'threadcount' => $forum['threadcount'],
					'replycount' => $forum['replycount'],
					'is_category' => $is_category,
					'depth' => $forum['depth'],
					'subscribed' => (array_key_exists($forum['forumid'], $this->subscribed_forums) ? 1 : 0)
				);
               
                if(!empty($forum[link])) {
                    $forum2['is_link'] = 1;
                    $forum2['link'] = $forum['link'];
                } else {
                    $forum2['is_link'] = 0;
                }
                 if(!empty($forum['lastpostinfo'])) {
                    $forum2['lastpostinfo'] = $forum['lastpostinfo'];
                }

				$children = explode(',', trim($forum['childlist']));
				if (sizeof($children) > 2)
				{
					if ($subforums = $this->getforumlist($forumid))
					{
						$forum2['subforums'] = $subforums;
					}
				}

				$forums[] = $forum2;
			} // if can view
		} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

		return $forums;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/