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
require_once (DIR . '/includes/blog_functions_search.php');
require_once(DIR . '/includes/class_bbcode_blog.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBBlog_Search_Result_BlogComment extends vB_Search_Result
{
	/** Parser, needed to get preview text **/
	protected $bbcode_parser = false;

	/** length of preview text to display**/
	protected $preview_length = 200;


	public static function create($id)
	{
		$items = self::create_array(array($id));
		if (count($items))
		{
			return array_shift($items);
		}
		else
		{
			//invalid object.
			return new vBBlog_Search_Result_BlogComment();
		}
	}

	public static function create_array($ids)
	{
		global $vbulletin;
		//where going to punt a little.  The permissions logic is nasty and complex
		//and tied to the current user.  I don't want to try to rewrite it.
		//So we'll pull in the current user here and go with it.
		$perm_parts = build_blog_permissions_query($vbulletin->userinfo);

		$blog_user_join = "";
		if (strpos($perm_parts['join'], 'blog_user AS blog_user') === false)
		{
			$blog_user_join = "LEFT JOIN " . TABLE_PREFIX .
				"blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)\n";
		}

		$set = $vbulletin->db->query_read_slave($q = "
			SELECT blog.*,
				IF(blog_user.title <> '', blog_user.title, blog.username) AS blogtitle,
				blog_user.memberids,
				blog_text.pagetext AS comment_pagetext,
				blog_text.username AS comment_username,
				blog_text.userid AS comment_userid,
				blog_text.title AS comment_title,
				blog_text.state AS comment_state,
				blog_text.dateline AS comment_dateline,
				blog_text.blogtextid
			FROM " . TABLE_PREFIX . "blog AS blog
			JOIN "  . TABLE_PREFIX ."blog_text AS blog_text ON (blog.blogid = blog_text.blogid)
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
				$blog_user_join $perm_parts[join]
			WHERE blog_text.blogtextid IN (" . implode(',', array_map('intval', $ids)) . ") AND ($perm_parts[where])
		");

		$items = array();
		while ($record = $vbulletin->db->fetch_array($set))
		{
			$item = new vBBlog_Search_Result_BlogComment();
			$item->record = $record;
			$items[$record['blogtextid']] = $item;
		}

		$ordered_items = array();
		foreach($ids AS $item_key)
		{
			if(isset($items[$item_key]))
			{
				$ordered_items[$item_key] = $items[$item_key];
				unset($items[$item_key]);
			}
		}

		return $ordered_items;
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBBlog', 'BlogComment');
	}

	public function can_search($user)
	{
		//blog level permissions handled in lookup, if we don't have a record its because
		//we can't see it.
		if (!$this->record)
		{
			return false;
		}

		//check state
		//if it its visible, we're all good
		if ($this->record['comment_state'] == 'visible')
		{
			return true;
		}

		//otherwise we need to check permissions
		else
		{
			if (can_moderate_blog())
			{
				return true;
			}

			if ($this->record['comment_state'] == 'deleted')
			{
				if (can_moderate_blog())
				{
					return true;
				}
			}

			if ($this->record['comment_state'] == 'moderation')
			{
				if((can_moderate_blog('canmoderatecomments')))
				{
					return true;
				}
			}

			//otherwise we have to be a member.  We skip a couple of checks regarding
			//the owner permissions to avoid loading them (could be expensive for lots
			//of different blogs).  Essentially if a user is a member of a blog that is
			//no longer marked to allow group joins then they may be able to see deleted
			//or moderated comments in a search result for that blog.
			//Otherwise we follow the logic in is_member_of
			if ($this->record['userid'] == $user->getField('userid'))
			{
				return true;
			}

			$members = explode(',', str_replace(' ', '', $this->record['memberids']));
			$can_search = (in_array($user->getField('userid'), $members) AND
				$user->hasPermission('vbblog_general_permissions', 'blog_canjoingroupblog'));
			return $can_search;
		}
	}

	public function get_group_item()
	{
		return vBBlog_Search_Result_BlogEntry::create_from_record($this->record);
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		require_once (DIR . '/includes/functions_user.php');
		if (!$this->record)
		{
			return "";
		}

		if (!strlen($template_name)) {
			$template_name = 'blog_comment_search_result';
		}

		global $vbulletin, $show;

		$urlinfo = array('blogid' => $this->record['blogid'], 'blog_title' => $this->record['title']);
		$this->record['page_url'] = fetch_seo_url('entry', $urlinfo, array('bt' => $this->record['blogtextid'])) . "#comment" . $this->record['blogtextid'] ;
		$comment = $this->record;

		$comment['comment_date'] = vbdate($vbulletin->options['dateformat'], $comment['dateline'], true);
		$comment['comment_time'] = vbdate($vbulletin->options['timeformat'], $comment['dateline']);

		if (! $this->bbcode_parser )
		{
			$this->bbcode_parser = new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list('', true));
		}
		$can_use_html = vB::$vbulletin->userinfo['permissions']['vbblog_entry_permissions']
			& vB::$vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowhtml'];
		$comment['comment_summary'] =
			fetch_censored_text($this->bbcode_parser->get_preview($comment['comment_pagetext'],
			$this->preview_length, $can_use_html));
		$templater = vB_Template::create($template_name);
		$templater->register('commentinfo', $comment);
		$templater->register('dateline', $this->message['dateline']);
		$templater->register('dateformat', $vbulletin->options['dateformat']);
		$templater->register('timeformat', $vbulletin->options['timeformat']);

		if ($vbulletin->options['avatarenabled'] AND (intval($comment['comment_userid'])))
		{
			$avatar = fetch_avatar_url($comment['comment_userid'], true);
		}

		if (!isset($avatar))
		{
			$avatar = false;
		}

		$templater->register('avatar', $avatar);
		$text = $templater->render();

		return $text;
	}

	private function get_summary_text($text, $length, $highlightwords)
	{
		$strip_quotes = true;

		//strip quotes unless they contain a word that we are searching for
		$page_text = preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe',
			"process_quote_removal('\\3', \$highlightwords)", $text);

		// Deal with the case that quote was the only content of the post
		if (trim($page_text) == '')
		{
			$page_text = $text;
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(
			trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes), $length))));
	}

	public function get_record()
	{
		return $this->record;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->record) AND isset($this->record['blogtextid']) )
		{
			return $this->record['blogtextid'];
		}
		return false;
	}

	private $record = null;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
