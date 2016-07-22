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
require_once (DIR . '/includes/class_bbcode.php');
require_once (DIR . '/includes/functions.php');

define('VBBLOG_PERMS', true);

/**
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBBlog_Search_Result_BlogEntry extends vB_Search_Result
{
	protected $preview_length = 200;
	/** Parser, needed to get preview text **/
	protected $bbcode_parser = false;

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
			return new vBBlog_Search_Result_BlogEntry();
		}
	}

	public static function create_array($ids)
	{
		global $vbulletin, $usercache;
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

		$set = $vbulletin->db->query_read_slave("
			SELECT blog.*, IF(blog_user.title <> '', blog_user.title, blog.username) AS blogtitle,
				blog_text.pagetext
			FROM " . TABLE_PREFIX ."blog AS blog
			LEFT JOIN " . TABLE_PREFIX ."blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
			$blog_user_join $perm_parts[join]
			WHERE blog.blogid IN (" . implode(',', array_map('intval', $ids)) . ") AND ($perm_parts[where])
		");

		$items = array();
		while ($record = $vbulletin->db->fetch_array($set))
		{
			$item = new vBBlog_Search_Result_BlogEntry();
			$item->record = $record;
			$items[$record['blogid']] = $item;
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

	public function create_from_record($record)
	{
		$item = new vBBlog_Search_Result_BlogEntry();
		$item->record = $record;
		return $item;
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBBlog', 'BlogEntry');
	}

	public function can_search($user)
	{
		//if we sucessfully loaded it, we can search on it.
		return (bool) $this->record;
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $show;
		global $vbulletin;

		require_once(DIR . '/includes/class_bbcode.php');
		require_once(DIR . '/includes/class_bbcode_blog.php');
		require_once (DIR . '/includes/functions.php');
		require_once (DIR . '/includes/blog_functions.php');
		require_once (DIR . '/includes/functions_user.php');

		if (!$this->record)
		{
			return "";
		}

		if (!strlen($template_name)) {
			$template_name = 'blog_search_results_result';
		}

		if (! $this->bbcode_parser )
		{
			$this->bbcode_parser = new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list('', true));
		}
		$blog = $this->record;
		$blog['previewtext']  = htmlspecialchars_uni(fetch_censored_text(
			fetch_trimmed_title(strip_bbcode($blog['pagetext'], true, true, true, true),
					$this->preview_length)
		));

		$canmoderation = (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $blog['userid']);
		$blog['trackbacks_total'] = $blog['trackback_visible'] + ($canmoderation ? $blog['trackback_moderation'] : 0);
		$blog['comments_total'] = $blog['comments_visible'] + ($canmoderation ? $blog['comments_moderation'] : 0);
		$blog['lastcommenter_encoded'] = urlencode($blog['lastcommenter']);
		$blog['lastposttime'] = vbdate($vbulletin->options['timeformat'], $blog['lastcomment']);
		$blog['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $blog['lastcomment'], true);
		$blog['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $blog['lastcomment'], true);
		$show['blogtitle'] = $blog['blogtitle'];
		$blog['time'] = vbdate($vbulletin->options['timeformat'], $blog['dateline']);
		$blog['date'] = vbdate($vbulletin->options['dateformat'], $blog['dateline'], true);
		$blog['lastcommenter_link'] = $vbulletin->options['vbforum_url'] . ($vbulletin->options['vbforum_url'] ? '/' : '') . 'member.php?' . $vbulletin->session->vars['sessionurl'] . 'username=' . $blog['lastcommenter_encoded'];

		$templater = vB_Template::create($template_name);
		$templater->register('blog', $blog);
		$templater->register('dateline', $blog['dateline']);
		$templater->register('dateformat', $vbulletin->options['dateformat']);
		$templater->register('timeformat', $vbulletin->options['timeformat']);

		if ($vbulletin->options['avatarenabled'] AND (intval($blog['userid'])))

		{
			$avatar = fetch_avatar_url($blog['userid'], true);
		}

		if (!isset($avatar) )
		{
			$avatar = false;
		}

		//to make the link to the poster
		$blogposter = array('userid' => $blog['postedby_userid'], 'username' => $blog['postedby_username']);

		$templater->register('blogposter', $blogposter);
		$templater->register('avatar', $avatar);
		return $templater->render();
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
		if (isset($this->record) AND isset($this->record['blogid']) )
		{
			return $this->record['blogid'];
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
