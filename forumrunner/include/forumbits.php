<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

function fr_construct_forum_bit ($parentid, $depth = 0, $subsonly = 0)
{
	global $vbulletin, $vbphrase, $show;
	global $imodcache, $lastpostarray, $counters, $inforum;

	// Get exclude IDs
	$exclude_ids = @explode(',', $vbulletin->options['forumrunner_exclude']);
	if (in_array('-1', $exclude_ids)) {
	    $exclude_ids = array();
	}
	if (in_array($parentid, $exclude_ids)) {
	    return;
	}

	// this function takes the constant MAXFORUMDEPTH as its guide for how
	// deep to recurse down forum lists. if MAXFORUMDEPTH is not defined,
	// it will assume a depth of 2.

	// call fetch_last_post_array() first to get last post info for forums
	if (!is_array($lastpostarray))
	{
		fetch_last_post_array($parentid);
	}

	if (empty($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	if (!defined('MAXFORUMDEPTH'))
	{
		define('MAXFORUMDEPTH', 2);
	}

	$forumbits = '';
	$depth++;

	if ($parentid == -1)
	{
		$parent_is_category = false;
	}
	else
	{
			$parentforum = $vbulletin->forumcache[$parentid];
			$parent_is_category	= !((bool) ($parentforum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']));
	}

	foreach ($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
	    if (in_array($forumid, $exclude_ids)) {
		continue;
	    }
		    
		// grab the appropriate forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];
		//$lastpostforum = $vbulletin->forumcache["$lastpostarray[$forumid]"];
		$lastpostforum = (empty($lastpostarray[$forumid]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);

		if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			continue;
		}

		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		$lastpostforumperms = (empty($lastpostarray[$forumid]) ? 0 : $vbulletin->userinfo['forumpermissions']["$lastpostarray[$forumid]"]);

		if (
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND 
			(
				$vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR 
				(!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums'])
			)
		)
		{ // no permission to view current forum
			continue;
		}

		if ($subsonly)
		{
			$childforumbits = fr_construct_forum_bit($forum['forumid'], 1, $subsonly);
		}
		else if ($depth < MAXFORUMDEPTH)
		{
			$childforumbits = fr_construct_forum_bit($forum['forumid'], $depth, $subsonly);
		}
		else
		{
			$childforumbits = '';
		}

		// do stuff if we are not doing subscriptions only, or if we ARE doing subscriptions,
		// and the forum has a subscribedforumid
		if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
		{

			$GLOBALS['forumshown'] = true; // say that we have shown at least one forum

			if (($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
			{ // get appropriate suffix for template name
				$tempext = '_post';
			}
			else

			{
				$tempext = '_nopost';
			}

			if (!$vbulletin->options['showforumdescription'])
			{ // blank forum description if set to not show
				$forum['description'] = '';
			}

			// dates & thread title
			$lastpostinfo = (empty($lastpostarray["$forumid"]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);

			// compare last post time for this forum with the last post time specified by
			// the $lastpostarray, and if it's less, use the last post info from the forum
			// specified by $lastpostarray
			if (!empty($lastpostinfo) AND $vbulletin->forumcache["$lastpostarray[$forumid]"]['lastpost'] > 0)
			{
				if (
					!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR 
					(
						!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND 
						$lastpostinfo['lastposter'] != $vbulletin->userinfo['username']
					)
				)
				{
					$forum['lastpostinfo'] = $vbphrase['private'];
				}
				else
				{
					$lastpostinfo['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $lastpostinfo['lastpost'], 1);
					$lastpostinfo['lastposttime'] = vbdate($vbulletin->options['timeformat'], $lastpostinfo['lastpost']);
					$lastpostinfo['trimthread'] = fetch_trimmed_title(fetch_censored_text($lastpostinfo['lastthread']));

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

					if ($vbulletin->forumcache["$lastpostforum[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'] AND $icon = fetch_iconinfo($lastpostinfo['lasticonid']))
					{
						$show['icon'] = true;
					}
					else
					{
						$show['icon'] = false;
					}

					$show['lastpostinfo'] = (!$lastpostforum['password'] OR verify_forum_password($lastpostforum['forumid'], $lastpostforum['password'], false));

					$pageinfo_lastpost = array('p' => $lastpostinfo['lastpostid']);
					$pageinfo_newpost = array('goto' => 'newpost');
					$threadinfo = array(
						'title'    => $lastpostinfo['lastthread'],
						'threadid' => $lastpostinfo['lastthreadid'],
					);
				}
			}
			else if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				$forum['lastpostinfo'] = $vbphrase['private'];
			}
			else
			{
				$forum['lastpostinfo'] = $vbphrase['never'];
			}

			// do light bulb
			$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);

			// add lock to lightbulb if necessary
			// from 3.6.9 & 3.7.0 we now show locks only if a user can not post AT ALL
			// previously it was just if they could not create new threads
			if (
				$vbulletin->options['showlocks'] // show locks to users who can't post
				AND !$forum['link'] // forum is not a link
				AND
				(
					!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']) // forum does not allow posting
					OR
					(
						    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) // can't post new threads
						AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) // can't reply to own threads
						AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) // can't reply to others' threads
					)
				)
			)
			{
				$forum['statusicon'] .= '_lock';
			}

			// get counters from the counters cache ( prepared by fetch_last_post_array() )
			$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
			$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];

			// get moderators ( this is why we needed cache_moderators() )
			if ($vbulletin->options['showmoderatorcolumn'])
			{
				$showmods = array();
				$listexploded = explode(',', $forum['parentlist']);
				foreach ($listexploded AS $parentforumid)
				{
					if (!isset($imodcache["$parentforumid"]) OR $parentforumid == -1)
					{
						continue;
					}
					foreach($imodcache["$parentforumid"] AS $moderator)
					{
						if (isset($showmods["$moderator[userid]"]))
						{
							continue;
						}

						($hook = vBulletinHook::fetch_hook('forumbit_moderator')) ? eval($hook) : false;

						$showmods["$moderator[userid]"] = true;

						if (!isset($forum['moderators']))
						{
							$forum['moderators'] = '';
						}
					}
				}
				if (!isset($forum['moderators']))
				{
					$forum['moderators'] = '';
				}
			}

			if ($forum['link'])
			{
				$forum['replycount'] = '-';
				$forum['threadcount'] = '-';
				$forum['lastpostinfo'] = '-';
			}
			else
			{
				$forum['replycount'] = vb_number_format($forum['replycount']);
				$forum['threadcount'] = vb_number_format($forum['threadcount']);
			}

			if (($subsonly OR $depth == MAXFORUMDEPTH) AND $vbulletin->options['subforumdepth'] > 0)
			{
			    //$forum['subforums'] = construct_subforum_bit($forumid, ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'] ) );
			    $forum['subforums'] = '';
			}
			else
			{
				$forum['subforums'] = '';
			}

			$forum['browsers'] = 0;
			$children = explode(',', $forum['childlist']);
			foreach($children AS $childid)
			{
				$forum['browsers'] += (isset($inforum["$childid"]) ? $inforum["$childid"] : 0);
			}

			if ($depth == 1 AND $tempext == '_nopost')
			{
				global $vbcollapse;
				$collapseobj_forumid =& $vbcollapse["collapseobj_forumbit_$forumid"];
				$collapseimg_forumid =& $vbcollapse["collapseimg_forumbit_$forumid"];
				$show['collapsebutton'] = true;
			}
			else
			{
				$show['collapsebutton'] = false;
			}

			$show['forumsubscription'] = ($subsonly ? true : false);
			$show['forumdescription'] = ($forum['description'] != '' ? true : false);
			$show['subforums'] = ($forum['subforums'] != '' ? true : false);
			$show['browsers'] = ($vbulletin->options['displayloggedin'] AND !$forum['link'] AND $forum['browsers'] ? true : false);

			// FRNR Start

			// If this forum has a password, check to see if we have
			// the proper cookie.  If so, don't prompt for one
			$password = 0;
			if ($forum['password']) {
			    $pw_ok = verify_forum_password($forum['forumid'], $forum['password'], false);
			    if (!$pw_ok) {
				$password = 1;
			    }
			}

			$new = array(
			    'id' => $forum['forumid'],
			    'new' => ($forum['statusicon'] == 'new' ? true : false),
			    'name' => prepare_utf8_string(strip_tags($forum['title'])),
			    'password' => $password,
			);
			$icon = fr_get_forum_icon($forum['forumid'], ($forum['statusicon'] == 'new' ? true : false));
			if ($icon) {
			    $new['icon'] = $icon;
			}
			if ($forum['link'] != '') {
			    $link = fr_fix_url($forum['link']);
			    if (is_int($link)) {
				$new['id'] = $link;
			    } else {
				$new['link'] = $link;
			    }
			    $linkicon = fr_get_forum_icon($forum['forumid'], false, true);
			    if ($linkicon) {
				$new['icon'] = $linkicon;
			    }
			}
			if ($forum['description'] != '') {
			    $desc = prepare_utf8_string(strip_tags($forum['description']));
			    if (strlen($desc) > 0) {
				$new['desc'] = $desc;
			    }
			}
			$out[] = $new;
			// FRNR End
		} // end if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
		else
		{
			$forumbits .= $childforumbits;
		}
	}

	return $out;
}

?>
