<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');

/**
*
*/
class vBForum_Search_Result_Thread extends vB_Search_Result
{
	//We'll pull some data from post
	private $replydata = array();

	//for now do not allow creation via the normal way -- group item only
	public static function create($id)
	{
		require_once (DIR . '/vb/legacy/thread.php');
		if ($thread = vB_Legacy_Thread::create_from_id($id))
		{
			$item = new vBForum_Search_Result_Thread();
			$item->thread = $thread;
			$item->set_data($id);
			return($item);
		}
		//if we get here,  the id must be invalid.
		require_once (DIR . '/vb/search/result/null.php');
		return new vB_Search_Result_Null();
	}

	public static function create_from_thread($thread)
	{
		if ($thread)
		{
			$item = new vBForum_Search_Result_Thread();
			// if we just have an id, we need to create the
			//object
			$item->thread = $thread;
			return $item;
		}
		else
		{
			require_once (DIR . '/vb/search/result/null.php');
			return new vB_Search_Result_Null();
		}
	}

	//set reply data
	private function set_replydata($threadid, $current_user)
	{
		$this->replydata = array(
			'readtime' => $this->thread->get_lastread($current_user),
			'mylastpost' => 0,
		);
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Thread');
   }

	//we will use the legacy can_search and can_view, for now.
	public function can_search($user)
	{
		return $this->thread->can_search($user);
	}

	public function can_view($user)
	{
		//The user can search if they have one of the following:
		//
		return $this->thread->can_view($user);
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		require_once(DIR . '/includes/functions_forumdisplay.php');
		require_once(DIR . '/includes/functions_user.php');
		global $vbulletin, $show;

		if (!strlen($template_name)) {
			$template_name = 'search_threadbit';
		}

		$show['forumlink'] = true;

		// threadbit_deleted conditionals
		$show['threadtitle'] = true;
		$show['viewthread'] = true;
		$show['managethread'] = true;


		($hook = vBulletinHook::fetch_hook('search_results_thread_start')) ? eval($hook) : false;

		//thread isn't a great name for this, but it stays consistant with
		//previous use and what will be expected in the hook.
		$thread = $this->thread->get_record();
		$this->set_replydata($thread['threadid'], $current_user);

		// get info from thread
		$thread['postid'] = $thread['threadid'];
		$thread['threadtitle'] = $thread['title'];
		$thread['threadiconid'] = $thread['iconid'];
		$thread['postdateline'] = $thread['lastpost'];

		$thread['issubscribed'] = $this->thread->is_subscribed($current_user);
		$thread['threadread'] = $this->thread->get_lastread($current_user);

		/*
			This used to be precalculated by forum, but it doesn't look expensive enough to want to
			bother with that.  If that turns out to be wrong we'll need to do some kind of
			caching.
		*/
		$forum = $this->thread->get_forum();
		if (!$current_user->hasForumPermission($forum->get_field('forumid'), 'canviewthreads'))
		{
			unset($thread['preview']);
		}

		//set the correct status
		if ($this->replydata['mylastpost'] > 0)
		{
			$post_statusicon[] = 'dot';
		}

		if (!$thread['open'])
		{
			$post_statusicon[] = 'lock';
		}

		if ($this->replydata['lastread'] < $thread['lastpost'])
		{
			$post_statusicon[] = 'new';
		}

		if (! count($post_statusicon))
		{
				$post_statusicon[] = 'old';
		}
		$post_statusicon = implode('_', $post_statusicon);


		$show['deletereason'] = false;

		if ($thread['visible'] == 2)
		{
			$log = $this->thread->get_deletion_log_array();
			$thread['del_username'] = $log['username'];
			$thread['del_userid'] = $log['userid'];
			$thread['del_reason'] = $log['reason'];

			$thread['deletedcount']++;
			$show['deletereason'] = !empty($thread['del_reason']);
		}
		else if ($thread['visible'] == 0)
		{
			$thread['hiddencount']++;
		}

		$thread['highlight'] = $criteria->get_highlights();

		$show['moderated'] = ($thread['hiddencount'] > 0 AND
			$current_user->canModerateForum($thread['forumid'], 'canmoderateposts'));

		$show['deletedthread'] = ($thread['deletedcount'] > 0 AND
			($current_user->canModerateForum($thread['forumid']) OR
			$current_user->hasForumPermission($thread['forumid'], 'canseedelnotice')));

		$show['disabled'] = !$this->can_inline_mod($current_user);

		$lastread = $forum->get_last_read_by_current_user($current_user);

	/*	This uses $dotthreads which is built by calling fetch_dot_threads_array() in search/type/thread.php
		The data is very similar to data we now have, and at some point this call could probably be eliminated. */
		$thread = process_thread_array($thread, $lastread);

		($hook = vBulletinHook::fetch_hook('search_results_threadbit')) ? eval($hook) : false;

		$pageinfo = $pageinfo_lastpost = $pageinfo_firstpost = $pageinfo_lastpage = array();
		if ($show['pagenavmore'])
		{
			$pageinfo_lastpage['page'] = $thread['totalpages'];
		}
		$pageinfo_lastpost['p'] = $thread['lastpostid'];
		$pageinfo_newpost['goto'] = 'newpost';

		$pageinfo_thread = array();
		if (!empty($thread['highlight']))
		{
			$pageinfo_thread['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_newpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_lastpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_firstpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
		}

		// Work out if unread below notification needed
		if ($criteria->get_searchtype() == vB_Search_Core::SEARCH_NEW AND $criteria->get_sort() == 'groupdateline'
			AND $show['below_unread'] == 0 AND $thread['lastpost'] < $vbulletin->userinfo['lastvisit'])
		{
			$show['below_unread'] = ( $criteria->get_search_term('searchdate') == 'lastvisit' ? 1 : 2 );
		}

		if ($vbulletin->options['avatarenabled'])
		{
			$thread['lastpost_avatar'] = fetch_avatar_from_record($thread, true);
			$thread['firstpost_avatar'] = fetch_avatar_from_record($thread, true, 'postuserid','first_');
		}

		($hook = vBulletinHook::fetch_hook('search_results_thread_process')) ? eval($hook) : false;

		$template = vB_Template::create($template_name);
		$template->register('post_statusicon', $post_statusicon);
		$template->register('pageinfo_firstpost', $pageinfo_firstpost);
		$template->register('pageinfo_lastpost', $pageinfo_lastpost);
		$template->register('pageinfo_lastpage', $pageinfo_lastpage);
		$template->register('pageinfo_newpost', $pageinfo_newpost);
		$template->register('pageinfo', $pageinfo_thread);
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat', $vbulletin->options['timeformat']);
		$template->register('postdateline', $thread['lastpost']);
		$userinfo = array('userid' => $thread['postuserid'], 'username' => $thread['postusername']);
		$template->register('avatar', $thread['lastpost_avatar']);
		$template->register('userinfo', $userinfo);
		$template->register('show', $show);
		$template->register('thread', $thread);

		if ($show['below_unread'] > 0)
		{ // flag as shown.
			$show['below_unread'] = -1;
		}

		($hook = vBulletinHook::fetch_hook('search_results_thread_complete')) ? eval($hook) : false;

		return $template->render();
	}

	public function get_thread()
	{
		return $this->thread;
	}

	public function set_thread($thread)
	{
		$this->thread = $thread;
	}

	/**
	* Does the user have any inline mod privs for this results item?
	*
	* Might be a candidate to move to the thread object.  Kind of specific
	* to the search right now... depends on which options are in the options
	* list.  The privs don't quite break down the same way on the display
	* end as they are checked on the action end (in inlinemod.php) which is
	* a problem, but I'm not really inclined to try to untangle the checking
	* in inlinemod.
	*/
	private function can_inline_mod($user)
	{
		$forumid = $this->thread->get_field('forumid');
		return (
			$user->canModerateForum($forumid, 'canmanagethreads') OR
			$user->canModerateForum($forumid, 'candeleteposts') OR
			$user->canModerateForum($forumid, 'canremoveposts') OR
			$user->canModerateForum($forumid, 'canmoderateposts') OR
			$user->canModerateForum($forumid, 'canopenclose')
		);
	}

	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->thread) AND isset($this->thread['threadid']) )
		{
			return $this->thread['vmid'];
		}
		return false;
	}

	private $thread;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
