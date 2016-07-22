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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

require_once(DIR . '/includes/functions_newpost.php');

/**
* Base data manager for blogs and blogtexts. Uninstantiable.
*
* @package	vBulletin
* @version	$Revision: 77475 $
* @date		$Date: 2013-09-11 12:17:48 -0700 (Wed, 11 Sep 2013) $
*/
class vB_DataManager_Blog_Abstract extends vB_DataManager
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Abstract(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_Blog_Abstract'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_Blog_Abstract class prohibited.", E_USER_ERROR);
		}

		parent::vB_DataManager($registry, $errtype);
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_postedby_userid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['postedbyuser'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = $userinfo = fetch_userinfo($userid))
		{
			$this->info['postedbyuser'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return == true)
		{
			if (isset($this->validfields['postedby_username']))
			{
				$this->do_set('postedby_username', $this->info['postedbyuser']['username']);
			}
		}

		return $return;
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['user'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return == true)
		{
			if (isset($this->validfields['username']))
			{
				$this->do_set('username', $this->info['user']['username']);
			}
		}

		return $return;
	}

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if ($this->registry->options['titlemaxchars'] AND $title != $this->existing['title'])
		{
			if (!empty($this->info['show_title_error']))
			{
				if (($titlelen = vbstrlen($title)) > $this->registry->options['titlemaxchars'])
				{
					// title too long
					$this->error('title_toolong', $titlelen, $this->registry->options['titlemaxchars']);
					return false;
				}
			}
			else if (empty($this->info['skip_title_error']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->registry->options['titlemaxchars']);
			}
		}

		// censor, remove all caps subjects, and htmlspecialchars title
		$title = fetch_no_shouting_text(fetch_censored_text($title));

		// do word wrapping
		$title = fetch_word_wrapped_string($title, $this->registry->options['blog_wordwrap']);

		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	* @param	bool	Whether to run the case stripper - Added for PHP 5.4 strict standards compliance
	*/
	function verify_pagetext(&$pagetext, $noshouting = false)
	{
		if (empty($this->info['skip_charcount']))
		{
			$maxchars = $this->table == 'blog' ? $this->registry->options['vbblog_entrymaxchars'] : $this->registry->options['vbblog_commentmaxchars'];
			if ($maxchars != 0 AND ($postlength = vbstrlen($pagetext)) > $maxchars)
			{
				$this->error('toolong', $postlength, $maxchars);
				return false;
			}

			$this->registry->options['postminchars'] = intval($this->registry->options['postminchars']);
			if ($this->registry->options['postminchars'] <= 0)
			{
				$this->registry->options['postminchars'] = 1;
			}
			if (vbstrlen(strip_bbcode($pagetext)) < $this->registry->options['postminchars'])
			{
				$this->error('tooshort', $this->registry->options['postminchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext, $noshouting);
	}

	/**
	* Converts ip address into an integer
	*
	* @param	string	IP Address
	*
	* @param	bool	Whether the ip is valid
	*/
	function verify_ipaddress(&$ipaddress)
	{
		// need to run it through sprintf to get the integer representation
		$ipaddress = sprintf('%u', ip2long($ipaddress));
		return true;
	}

	/**
	* Verifies the state is valid
	*
	* @param	string	State
	*
	* @param	bool	Whether the state is valid
	*/
	function verify_state(&$state)
	{
		if (!in_array($state, array('moderation', 'visible', 'deleted', 'draft')))
		{
			$state = 'visible';
		}

		return true;
	}

	/**
	* Fetches the amount of attachments associated with a posthash and user
	*
	* @param	string	Post hash
	* @param	integer	User ID associated with post hash (-1 means current user)
	*
	* @return	integer	Number of attachments
	*/
	function fetch_attachment_count($posthash, $userid = -1)
	{
		if ($userid == -1)
		{
			$userid = $this->fetch_field('userid', 'blog_text');
		}
		$userid = intval($userid);

		$attachcount = $this->dbobject->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE posthash = '" . $this->dbobject->escape_string($posthash) . "'
				AND userid = $userid
		");

		return intval($attachcount['count']);
	}

	function insert_dupehash($blogid = -1, $blogtextid = -1)
	{
		if ($blogid == -1)
		{
			$blogid = $this->fetch_field('blogid');
		}

		if ($blogtextid == -1)
		{
			$blogtextid = $this->fetch_field('blogtextid') !== NULL ? $this->fetch_field('blogtextid') : $this->fetch_field('firstblogtextid');
		}

		$type = ($blogid > 0 ? 'comment' : 'blog');

		$blogcategoryid = $this->fetch_field('blogcategoryid');
		if (!$blogcategoryid)
		{
			$blogcategoryid = $this->info['blog']['blogcategoryid'];
		}

		$userid = $this->fetch_field('userid');

		$dupehash = md5($blogcategoryid . $this->fetch_field('title') . $this->fetch_field('pagetext', 'blog_text') . $userid . $type);
		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "blog_hash
			(userid, blogid, blogtextid, dupehash, dateline)
			VALUES
			(" . intval($userid) . ", " . intval($blogid) . ", " . intval($blogtextid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}

	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->registry->options['floodchecktime'];
		if (!can_moderate_blog() AND $this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "blog_hash
				WHERE userid = " . $this->fetch_field('userid') . "
					AND dateline > " . $floodmintime . "
				ORDER BY dateline DESC
				LIMIT 1
			");
			if ($flood)
			{
				$this->error(
					'postfloodcheck',
					$this->registry->options['floodchecktime'],
					($flood['dateline'] - $floodmintime)
				);
				return true;
			}
		}

		return false;
	}

	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;
		if ($this->fetch_field('dateline') > $dupemintime)
		{
			$type = ($this->table == 'blog') ? 'blog' : 'comment';
			// ### DUPE CHECK ###
			$dupehash = md5($this->fetch_field('blogcategoryid') . $this->fetch_field('title') . $this->fetch_field('pagetext', 'blog_text') . $this->fetch_field('userid') . $type);
			if ($dupe = $this->registry->db->query_first("
				SELECT hash.blogid, blog.title
				FROM " . TABLE_PREFIX . "blog_hash AS hash
				LEFT JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
				WHERE hash.userid = " . $this->fetch_field('userid') . " AND
					hash.dupehash = '" . $this->registry->db->escape_string($dupehash) . "' AND
					hash.dateline > " . $dupemintime . "
			"))
			{
				if ($type == 'blog' AND $dupe['blogid'] == 0)
				{
					$this->error('duplicate_blogentry');
					return true;
				}
				else if ($type == 'comment' AND $dupe['blogid'] == $this->info['blog']['blogid'])
				{
					$this->error('duplicate_comment', fetch_seo_url('entry', $dupe), $dupe['title']);
					return true;
				}
			}
		}
		return false;
	}

	/**
	* Pre save function run on each record. Applies only if there was a blogtext entry.
	*
	* @return	bool	True on success, false on failure
	*/
	function pre_save_blogtext($doquery = true)
	{
		if (!$this->condition)
		{
			if ($this->fetch_field('state', 'blog_text') === null)
			{
				$this->set('state', 'visible');
			}

			if ($this->fetch_field('dateline', 'blog_text') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress', 'blog_text') === null)
			{
				$this->set('ipaddress', ($this->registry->options['logip'] ? IPADDRESS : ''));
			}

			if ($this->registry->options['floodchecktime'] > 0 AND empty($this->info['preview']) AND empty($this->info['skip_floodcheck']) AND $this->fetch_field('userid', 'blog_text') AND $this->is_flooding())
			{
				return false;
			}

			if (!$this->info['preview'] AND $this->is_duplicate())
			{
				return false;
			}
		}

		$this->registry->options['maximages'] = $this->table == 'blog' ? $this->registry->options['vbblog_entrymaximages'] : $this->registry->options['vbblog_commentmaximages'];
		$this->registry->options['maxvideos'] = $this->table == 'blog' ? $this->registry->options['vbblog_entrymaxvideos'] : $this->registry->options['vbblog_commentmaxvideos'];
		if (!$this->verify_image_count('pagetext', 'allowsmilie', ($this->table == 'blog' ? 'blog_entry' : 'blog_comment'), 'blog_text'))
		{
			return false;
		}

		if ($this->info['posthash'])
		{ // set newattach to have a value so that BlogText can find it to update Blog
			// userid needs to be blog owner..
			$this->info['newattach'] = $this->fetch_attachment_count($this->info['posthash'], $this->fetch_field('userid', 'blog_text'));
			$this->set('attach',
				intval($this->fetch_field('attach')) +
				$this->info['newattach']
			);
		}

		return true;
	}

	/**
	* Post save function run on each record. Applies only if there was a blogtext entry.
	*/
	function post_save_each_blogtext($doquery = true)
	{
		$blogid = intval($this->fetch_field('blogid'));
		$blogtextid = intval($this->fetch_field($this->table == 'blog_text' ? 'blogtextid' : 'firstblogtextid'));

		if (!$this->info['user'])
		{
			if ($this->registry->userinfo['userid'] AND $this->fetch_field('userid', 'blog_text') == $this->registry->userinfo['userid'])
			{
				$this->set_info('user', $this->registry->userinfo);
			}
			else
			{
				if (!defined('VBBLOG_PERMS'))
				{
					define('VBBLOG_PERMS', true);
				}
				$userinfo = fetch_userinfo($this->fetch_field('userid', 'blog_text'));
				$this->set_info('user', $userinfo);
			}
		}

		if ($this->info['posthash'] AND $this->fetch_field('attach') AND $blogid)
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "attachment
				SET
					contentid = $blogid,
					posthash = ''
				WHERE
					posthash = '" . $this->dbobject->escape_string($this->info['posthash']) . "'
						AND
					userid = " . intval($this->fetch_field('userid', 'blog_text')) . "
			");
		}

		if ($this->condition AND $this->blog_text['pagetext'] AND $blogtextid)
		{
			$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_textparsed WHERE blogtextid = " . intval($blogtextid));
		}

		if ($this->table == 'blog')
		{
			if (empty($this->info['user']['bloguserid']))
			{
				$userdata =& datamanager_init('Blog_user', $this->registry, ERRTYPE_SILENT);
				// Create a record in blog_user if we need one
				$userdata->set('bloguserid', $this->info['user']['userid']);
				$userdata->save();
			}

			$userinfo = array('bloguserid' => $this->info['user']['userid']);
		}
		else
		{
			$userinfo = array('bloguserid' => $this->info['blog']['userid']);
		}

		$userdata =& datamanager_init('Blog_user', $this->registry, ERRTYPE_SILENT);
		$userdata->set_existing($userinfo);

		if (!$this->condition AND $this->fetch_field('state') == 'visible')
		{
			if ($this->info['user'] AND $this->fetch_field('dateline') <= TIMENOW)
			{
				if ($this->table == 'blog')
				{
						$userdata->set('entries', 'entries + 1', false);
						if (empty($this->info['categories']))
						{
							$userdata->set('uncatentries', 'uncatentries + 1', false);
						}
				}
				else
				{
					$userdata->set('comments', 'comments + 1', false);
				}

				$setoptions = $this->fetch_field('options');
				if (!in_coventry($this->fetch_field('userid', 'blog_text'), true) AND !$setoptions["{$this->bitfields['options']['private']}"])
				{
					// only write last entry info if the entry isn't private
					$userdata->set('lastcomment', $this->fetch_field('dateline'));
					$userdata->set('lastblogtextid', $blogtextid);
					$userdata->set('lastcommenter', $this->fetch_field('username', 'blog_text'));
					if ($this->table == 'blog')
					{
							$userdata->set('lastblog', $this->fetch_field('dateline'));
							$userdata->set('lastblogtitle', $this->fetch_field('title'));
							$userdata->set('lastblogid', $blogid);
					}
				}

				$userdata->save();
			}
		}
		unset($userdata);
	}
}

class vB_DataManager_Blog extends vB_DataManager_Blog_Abstract
{
	/**
	* Array of recognised and required fields for entries, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'blogid'               => array(TYPE_UINT,       REQ_INCR,  VF_METHOD, 'verify_nonzero'),
		'firstblogtextid'      => array(TYPE_UINT,       REQ_NO),
		'userid'               => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'dateline'             => array(TYPE_UNIXTIME,   REQ_AUTO),
		'postercount'          => array(TYPE_UINT,       REQ_AUTO),
		'comments_visible'     => array(TYPE_UINT,       REQ_AUTO),
		'comments_moderation'  => array(TYPE_UINT,       REQ_NO),
		'comments_deleted'     => array(TYPE_UINT,       REQ_NO),
		'attach'               => array(TYPE_UINT,       REQ_NO),
		'state'                => array(TYPE_STR,        REQ_NO,    VF_METHOD),
		'views'                => array(TYPE_UINT,       REQ_NO),
		'username'             => array(TYPE_NOHTMLCOND, REQ_NO,    VF_METHOD),
		'title'                => array(TYPE_NOHTMLCOND, REQ_YES,   VF_METHOD),
		'options'              => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'trackback_visible'    => array(TYPE_UINT,       REQ_NO),
		'trackback_moderation' => array(TYPE_UINT,       REQ_NO),
		'lastcomment'          => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastblogtextid'       => array(TYPE_UINT,       REQ_NO),
		'lastcommenter'        => array(TYPE_NOHTMLCOND, REQ_NO),
		'ratingtotal'          => array(TYPE_UINT,       REQ_NO),
		'ratingnum'	           => array(TYPE_UINT,       REQ_NO),
		'rating'               => array(TYPE_NUM,        REQ_NO),
		'pending'              => array(TYPE_UINT,       REQ_NO),
		'categories'           => array(TYPE_STR,        REQ_NO),
		'taglist'              => array(TYPE_STR,        REQ_NO),
		'postedby_userid'      => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'postedby_username'    => array(TYPE_STR,        REQ_NO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog';

	/**
	* Array to store stuff to save to blog table
	*
	* @var	array
	*/
	var $blog = array();

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogid = %1$d', 'blogid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Blog_Abstract($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_data_start')) ? eval($hook) : false;
	}

	/**
	* Verifies the title. Does the same processing as the general title verifier,
	* but also requires there be a title.
	*
	* @param	string	Title text
	*
	* @return	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		if (!parent::verify_title($title))
		{
			return false;
		}

		if ($title == '')
		{
			$this->error('noentrytitle');
			return false;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'] AND $title != $this->existing['title'])
		{
			require_once(DIR . '/includes/blog_functions_log_error.php');
			$info = array(
				'id1' => $this->fetch_field('userid'),
				'id2' => $this->fetch_field('blogid'),
			);
			blog_moderator_action($info, 'blogentry_title_x_changed_to_y', array($this->existing['title'], $title));
		}

		return true;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition AND $this->fetch_field('dateline') === null)
		{
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_data_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		$this->build_category_counters();
		build_blog_stats();

		($hook = vBulletinHook::fetch_hook('blog_data_postsave')) ? eval($hook) : false;

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogEntry', 'index', intval($this->fetch_field('blogid')));
		vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogComment', 'group_data_change', intval($this->fetch_field('blogid')));
	}

	/**
	 *	Delete function
	 * 
	 * @param  boolean $doquery  - Added for PHP 5.4 strict standards compliance
	 * @return boolean 			 - Delete status
	 */
	public function delete($doquery = true)
	{
		if ($blogid = $this->existing['blogid'])
		{
			$db =& $this->registry->db;
			require_once(DIR . '/includes/blog_functions_log_error.php');

			if ($this->info['hard_delete'])
			{
				require_once(DIR . '/vb/search/indexcontroller/queue.php');
				vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogEntry', 'delete', $blogid);
				vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogComment', 'delete_group', $blogid);

				/* NOTE: There queries are all used in the post delete function in class_dm_blog_user.php, if you add another please add it there too */
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_categoryuser WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_deletionlog WHERE primaryid = $blogid AND type = 'blogid'");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_moderation WHERE primaryid = $blogid AND type = 'blogid'");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_pinghistory WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_rate WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_read WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_tachyentry WHERE blogid = $blogid");

				$textids = array();
				$comments = $db->query_read("
					SELECT blogtextid
					FROM " . TABLE_PREFIX . "blog_text
					WHERE blogid = $blogid
				");
				while ($comment = $db->fetch_array($comments))
				{
					$textids[] = $comment['blogtextid'];
				}
				$activity = new vB_ActivityStream_Manage('blog', 'comment');
				$activity->set('contentid', $textids);
				$activity->delete();

				// 4.0 doesn't like aliases, 4.1 requires the alias be used :rolleyes:!!!
				$db->query_write("
					DELETE " . TABLE_PREFIX . "blog_text, " . TABLE_PREFIX . "blog_textparsed, " . TABLE_PREFIX . "blog_editlog, " . TABLE_PREFIX . "blog_moderation, " . TABLE_PREFIX . "blog_deletionlog
					FROM " . TABLE_PREFIX . "blog_text
					LEFT JOIN " . TABLE_PREFIX . "blog_textparsed ON (" . TABLE_PREFIX . "blog_textparsed.blogtextid = " . TABLE_PREFIX . "blog_text.blogtextid)
					LEFT JOIN " . TABLE_PREFIX . "blog_editlog ON (" . TABLE_PREFIX . "blog_editlog.blogtextid = " . TABLE_PREFIX . "blog_text.blogtextid)
					LEFT JOIN " . TABLE_PREFIX . "blog_moderation ON (" . TABLE_PREFIX . "blog_moderation.primaryid = " . TABLE_PREFIX . "blog_text.blogtextid AND " . TABLE_PREFIX . "blog_moderation.type = 'blogtextid')
					LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog ON (" . TABLE_PREFIX . "blog_deletionlog.primaryid = " . TABLE_PREFIX . "blog_text.blogtextid AND " . TABLE_PREFIX . "blog_deletionlog.type = 'blogtextid')
					WHERE " . TABLE_PREFIX . "blog_text.blogid = $blogid
				");

				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_trackback WHERE blogid = $blogid");
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_views WHERE blogid = $blogid");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_hash
					WHERE blogid = $blogid AND
						blogtextid = " . intval($this->fetch_field('firstblogtextid'))
				);

				require_once(DIR . '/includes/class_taggablecontent.php');
				$content = vB_Taggable_Content_Item::create($this->registry, "vBBlog_BlogEntry", $blogid);
				$content->delete_tag_attachments();

				$contenttypeid = vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry');

				$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT, 'attachment');
				$attachdata->condition = "a.contentid = $blogid AND a.contenttypeid = " . intval($contenttypeid);
				$attachdata->delete(true, false);

				$db->query_write("DELETE  FROM " . TABLE_PREFIX . "blog WHERE blogid = $blogid");
				$activity = new vB_ActivityStream_Manage('blog', 'entry');
				$activity->set('contentid', $blogid);
				$activity->delete();

				if (!$this->info['skip_moderator_log'])
				{
					blog_moderator_action($this->existing, 'blogentry_removed');
					$db->query_write("
						UPDATE " . TABLE_PREFIX . "moderatorlog
						SET threadtitle = '". $db->escape_string($this->existing['title']) ."'
						WHERE id2 = $blogid
					");
				}
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();

				if (!$this->info['skip_moderator_log'])
				{
					blog_moderator_action($this->existing, 'blogentry_softdeleted');
				}

				// soft delete
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "blog_deletionlog
						(primaryid, type, userid, username, reason, dateline)
					VALUES
						($blogid,
						'blogid',
						" . $this->registry->userinfo['userid'] . ",
						'" . $db->escape_string($this->registry->userinfo['username']) . "',
						'" . $db->escape_string($this->info['reason']) . "',
						" . TIMENOW . ")
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_moderation WHERE primaryid = $blogid AND type = 'blogid'
				");

				if (!$this->info['keep_attachments'])
				{
					$contenttypeid = vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry');

					$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT, 'attachment');
					$attachdata->condition = "a.contentid = $blogid AND a.contenttypeid = " . intval($contenttypeid);
					$attachdata->delete(true, false);
				}
			}

			if (!$this->info['skip_build_blog_counters'])
			{
				build_blog_user_counters($this->fetch_field('userid'));
				build_blog_stats();
			}

			($hook = vBulletinHook::fetch_hook('blog_data_delete')) ? eval($hook) : false;
			return true;
		}
		return false;
	}

	function build_category_counters()
	{
		if ($this->info['skip_build_category_counters'] OR !is_array($this->info['categories']))
		{
			return;
		}

		$this->info['categories'] = $this->registry->input->clean($this->info['categories'], TYPE_ARRAY_UINT);

		$blogid = intval($this->fetch_field('blogid'));
		$userid = $this->fetch_field('userid');

		// Make sure to include all parents of a child
		$addcats = array();

		foreach ($this->info['categories'] AS $categoryid)
		{
			if (count($cats = explode(',', $this->registry->vbblog['categorycache']["$userid"]["$categoryid"]['parentlist'])) > 1)
			{
				foreach ($cats AS $parentid)
				{
					if ($parentid != 0 AND $parentid != $categoryid)
					{
						$addcats[] = $parentid;
					}
				}
			}
		}

		$this->info['categories'] = array_unique(array_merge($this->info['categories'], $addcats));

		if ($this->info['userinfo']['userid'] != $this->registry->userinfo['userid'])
		{
			$cantusecats = array_unique(array_merge($this->info['userinfo']['blogcategorypermissions']['cantpost'], $this->registry->userinfo['blogcategorypermissions']['cantpost'], $this->info['userinfo']['blogcategorypermissions']['cantview'], $this->registry->userinfo['blogcategorypermissions']['cantview']));
		}
		else
		{
			$cantusecats = array_unique(array_merge($this->info['userinfo']['blogcategorypermissions']['cantpost'], $this->info['userinfo']['blogcategorypermissions']['cantview']));
		}

		foreach($this->info['categories'] AS $categoryid)
		{
			if (in_array($categoryid, $cantusecats))
			{
				unset($this->info['categories']["$categoryid"]);
			}
		}

		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "blog
			SET categories = '" . $this->dbobject->escape_string(implode(",", $this->info['categories'])) . "'
			WHERE blogid = $blogid
		");

		$currentcategories = array();
		if ($this->condition)
		{
			$categories = $this->dbobject->query_read("
				SELECT blogcategoryid
				FROM " . TABLE_PREFIX . "blog_categoryuser
				WHERE blogid = $blogid
			");
			while ($category = $this->dbobject->fetch_array($categories))
			{
				$currentcategories[] = $category['blogcategoryid'];
			}
		}

		$addcategories = array_diff($this->info['categories'], $currentcategories);

		if (!empty($addcategories))
		{
			$sql = array();
			foreach ($addcategories AS $categoryid)
			{
				if ($categoryid = intval($categoryid))
				{
					$sql[] = "($categoryid, $blogid, $userid)";
				}
			}
			if (!empty($sql))
			{
				$this->dbobject->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "blog_categoryuser
						(blogcategoryid, blogid, userid)
					VALUES
						" . implode(',', $sql) . "
				");
			}
		}

		$this->setr_info('addcategories', $addcategories);

		$staticcategories_increase = array();
		// Delete any categories that were removed from this post on edit
		if ($this->condition)
		{
			$staticcategories_decrease = array();
			if (isset($this->existing['state']))
			{
				if ($this->existing['state'] == 'visible' AND $this->existing['pending'] == 0 AND ($this->fetch_field('state') != 'visible' OR $this->fetch_field('pending') == 1))
				{	// post went from visible to hidden, existing cats need to be decreased
					$staticcategories_decrease = array_intersect($this->info['categories'], $currentcategories);
				}
				else if ($this->fetch_field('state') == 'visible' AND $this->fetch_field('pending') == 0 AND ($this->existing['state'] != 'visible' OR $this->existing['pending'] == 1))
				{	// post went from hidden to visible, existing cats need to be increased
					$staticcategories_increase = array_intersect($this->info['categories'], $currentcategories);
				}
			}

			$deletecategories = array_diff($currentcategories, $this->info['categories']);

			if (!empty($deletecategories))
			{
				$this->dbobject->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_categoryuser
					WHERE
						userid = $userid AND
						blogid = $blogid AND
						blogcategoryid IN (" . implode(',', $deletecategories) . ")
				");
			}
		}
	}
}

/**
* Class to do data save/delete operations for BLOGs
*
* @package	vBulletin
* @version	$Revision: 77475 $
* @date		$Date: 2013-09-11 12:17:48 -0700 (Wed, 11 Sep 2013) $
*/
class vB_DataManager_BlogText extends vB_DataManager_Blog_Abstract
{
	/**
	* Array of recognised and required fields for blogtexts, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'blogtextid'     => array(TYPE_UINT,       REQ_INCR,  VF_METHOD, 'verify_nonzero'),
		'blogid'         => array(TYPE_UINT,       REQ_YES),
		'userid'         => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'bloguserid'     => array(TYPE_UINT,       REQ_NO),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'pagetext'       => array(TYPE_STR,        REQ_YES,   VF_METHOD),
		'title'          => array(TYPE_NOHTMLCOND, REQ_NO,    VF_METHOD),
		'state'          => array(TYPE_STR,        REQ_NO),
		'allowsmilie'    => array(TYPE_UINT,       REQ_NO),
		'username'       => array(TYPE_NOHTMLCOND, REQ_NO,    VF_METHOD),
		'ipaddress'      => array(TYPE_STR,        REQ_AUTO,  VF_METHOD),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
		'htmlstate'      => array(TYPE_STR,        REQ_NO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_text';

	/**
	* Array to store stuff to save blogtext tables
	*
	* @var	array
	*/
	var $blog_text = array();

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogtextid = %1$d', 'blogtextid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_BlogText(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Blog_Abstract($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_textdata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->pre_save_blogtext($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		/* perform Akismet on new comments that would be visible */
		if (!$this->condition AND $this->fetch_field('state') == 'visible' AND !$this->info['skip_akismet'])
		{
			$akismet_url = fetch_seo_url('bloghome|nosession|bburl', array());
			if (!empty($this->registry->options['vb_antispam_key']))
			{ // global key, use the global URL aka blog.php
				$akismet_key = $this->registry->options['vb_antispam_key'];
			}
			else
			{
				//if feels like chaning the format of this url is a bad idea, so we'll force it to basic.
				//we don't have the blog title and we won't use it for basic urls anyway, but if we switch
				//the format we'll need to fix that.
				require_once(DIR . '/includes/class_friendly_url.php');
				$akismet_url = vB_Friendly_Url::fetchLibrary($this->registry, 'blog|nosession|bburl',
					array('userid' => $this->fetch_field('bloguserid')));
				$akismet_url = $akismet_url->get_url(FRIENDLY_URL_OFF);

				$akismet_key = $this->info['akismet_key'];
			}

			if (!empty($akismet_key))
			{
				// these are taken from the Akismet API: http://akismet.com/development/api/
				$akismet_data = array();
				$akismet_data['user_ip'] = $this->fetch_field('ipaddress');
				$akismet_data['user_agent'] = USER_AGENT;
				$akismet_data['comment_type'] = 'comment';
				$akismet_data['comment_author'] = $this->fetch_field('username');
				$akismet_data['comment_content'] = $this->fetch_field('pagetext');
				if (verify_akismet_status($akismet_key, $akismet_url, $akismet_data) == 'spam')
				{
					$this->set('state', 'moderation');
				}
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_textdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		$blogid = intval($this->fetch_field('blogid'));
		$blogtextid = intval($this->fetch_field('blogtextid'));
		$userid = intval($this->fetch_field('userid'));

		$this->post_save_each_blogtext($doquery);

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogComment', 'index', $blogtextid);

		if ($this->info['blog'] AND ($attach = intval($this->info['newattach']) OR !$this->condition))
		{
			// things that apply to a new comment and an edit
			$blog =& datamanager_init('Blog', $this->registry, ERRTYPE_SILENT, 'blog');
			$blog->set_existing($this->info['blog']);

			if ($attach)
			{
				$blog->set('attach', "attach + $attach", false);
			}
		}

		if (!$this->condition)
		{ // things that apply just to a new comment
			if ($this->fetch_field('dateline') == TIMENOW)
			{
				$this->insert_dupehash($this->fetch_field('blogid'));
			}

			if ($this->fetch_field('state') == 'visible' AND $this->info['blog'] AND $this->info['blog']['state'] == 'visible')
			{
				$blog->set('comments_visible', 'comments_visible + 1', false);

				/*
				 * Yes this will miss one unqiue user if this very post is their
				 * first. I choose to take this over running this query after the save
				 * which means I have to run a second update query on thread. This value
				 * is only used for the activity stream popularity where exactness
				 * is not required.
				 */
				if (!in_coventry($userid, true))
				{
					require_once(DIR . '/includes/functions_bigthree.php');
					$coventry = fetch_coventry('string');
					$uniques = $this->registry->db->query_first("
						SELECT COUNT(DISTINCT(userid)) AS total
						FROM " . TABLE_PREFIX . "blog_text
						WHERE
							blogid = $blogid
								AND
							state = 'visible'
							" . ($coventry ? "AND userid NOT IN ($coventry)" : "") . "
					");
					if (!$uniques['total'])
					{
						$uniques['total'] = 1;
					}
					$blog->set('postercount', $uniques['total']);
				}

				if (in_coventry($userid, true))
				{
					// posted by someone in coventry, so don't update the blog last post time
					// just put it in this person's tachy last post table

					$replaceval = "$userid,
						$blogid,
						" . intval(TIMENOW) . ",
						'" . $this->dbobject->escape_string($this->fetch_field('username')) . "',
						$blogtextid
					";

					$this->dbobject->query_write("
						REPLACE INTO " . TABLE_PREFIX . "blog_tachyentry
							(userid, blogid, lastcomment, lastcommenter, lastblogtextid)
						VALUES
							($replaceval)
					");
				}
				else
				{
					$blog->set('lastcomment', TIMENOW);
					$blog->set('lastcommenter', $this->fetch_field('username'));
					$blog->set('lastblogtextid', $blogtextid);

					// empty out the tachy posts for this blog
					$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "blog_tachyentry
						WHERE blogid = $blogid
					");

					// Send Email Notification
					if ($this->registry->options['enableemail'])
					{
						$lastposttime = $this->dbobject->query_first("
							SELECT MAX(dateline) AS dateline
							FROM " . TABLE_PREFIX . "blog_text AS blog_text
							WHERE blogid = $blogid
								AND dateline < " . $this->fetch_field('dateline') . "
								AND state = 'visible'
						");

						$entrytitle = unhtmlspecialchars($this->info['blog']['title']);
						if (defined('VBBLOG_PERMS') AND $this->registry->userinfo['userid'] == $this->info['blog']['userid'])
						{
							$blogtitle = unhtmlspecialchars($this->registry->userinfo['blog_title']);
							$username = unhtmlspecialchars($this->registry->userinfo['username']);
							$userinfo =& $this->registry->userinfo;
						}
						else
						{
							if (!defined('VBBLOG_PERMS'))
							{	// Tell the fetch_userinfo plugin that we need the blog fields in case this class is being called by a non blog script
								define('VBBLOG_PERMS', true);
							}
							$userinfo = fetch_userinfo($this->info['blog']['userid'], 1);
							cache_permissions($userinfo, false);
							$blogtitle = unhtmlspecialchars($userinfo['blog_title']);
							if ($userinfo['userid'] != $this->fetch_field('userid'))
							{
								$userinfo2 = fetch_userinfo($this->fetch_field('userid'), 1);
								$username = unhtmlspecialchars($userinfo2['username']);
							}
							else
							{
								$username = unhtmlspecialchars($userinfo['username']);
							}
						}

						require_once(DIR . '/includes/class_bbcode_alt.php');
						$plaintext_parser = new vB_BbCodeParser_PlainText($this->registry, fetch_tag_list());
						$pagetext_cache = array(); // used to cache the results per languageid for speed

						$pagetext_orig =& $this->fetch_field('pagetext');

						($hook = vBulletinHook::fetch_hook('blog_post_notification_start')) ? eval($hook) : false;

						$useremails = $this->dbobject->query_read_slave($q = "
							SELECT
								user.*,
								blog_subscribeentry.blogsubscribeentryid,
								blog_moderator.blogmoderatorid,
								ignored.relationid AS ignoreid,
								buddy.relationid AS buddyid,
								blog.categories,
								blog.options
							FROM " . TABLE_PREFIX . "blog_subscribeentry AS blog_subscribeentry
							INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog_subscribeentry.userid = user.userid)
							LEFT JOIN " . TABLE_PREFIX . "blog_moderator AS blog_moderator ON (blog_moderator.userid = user.userid)
							LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = $userinfo[userid] AND buddy.relationid = user.userid AND buddy.type = 'buddy')
							LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = $userinfo[userid] AND ignored.relationid = user.userid AND ignored.type = 'ignore')
							LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_subscribeentry.blogid)
							WHERE blog_subscribeentry.blogid = $blogid AND
								blog_subscribeentry.type = 'email' AND
								user.usergroupid <> 3 AND
								user.userid <> " . intval($userid) . " AND
								user.lastactivity >= " . intval($lastposttime['dateline']) . "
						");

						vbmail_start();

						$evalemail = array();
						while ($touser = $this->dbobject->fetch_array($useremails))
						{
							if (!($this->registry->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup']))
							{
								continue;
							}

							cache_permissions($touser, false);
							// Check if entry is private, only send to blog owner, contacts, and moderators if so
							if ($touser['options'] & $this->bitfields['options']['private'] AND !$touser['blogmoderatorid'] AND !$touser['buddyid'] AND !is_member_of_blog($touser, $userinfo))
							{
								continue;
							}

							prepare_blog_category_permissions($touser);
							$entrycats = explode(',', $touser['categories']);
							if (array_intersect($touser['blogcategorypermissions']['cantview'], $entrycats) AND $userinfo['userid'] != $touser['userid'])
							{
								continue;
							}
							else if ($userinfo['userid'] != $touser['userid'] AND !($touser['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
							{
								continue;
							}
							else if ($userinfo['userid'] == $touser['userid'] AND !($touser['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown']))
							{
								continue;
							}
							else if (
								!$touser['blogmoderatorid']
									AND
								!($touser['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
									AND
								!($touser['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
									AND
								(!$userinfo['ignore_canviewmyblog'] OR !$touser['ignoreid'])
									AND
								(!$userinfo['buddy_canviewmyblog'] OR !$touser['buddyid'])
									AND
								(!$userinfo['member_canviewmyblog'] OR (!$userinfo['buddy_canviewmyblog'] AND $touser['budyid']) OR (!$userinfo['ignore_canviewmyblog'] AND $touser['ignoreid']))
									AND
								!is_member_of_blog($touser, $userinfo)
							)
							{
								continue;
							}

							$touser['username'] = unhtmlspecialchars($touser['username']);
							$touser['languageid'] = iif($touser['languageid'] == 0, $this->registry->options['languageid'], $touser['languageid']);
							$touser['auth'] = md5($touser['userid'] . $touser['blogsubscribeentryid'] . $touser['salt'] . COOKIE_SALT);

							if (empty($evalemail))
							{
								$email_texts = $this->dbobject->query_read_slave("
									SELECT text, languageid, fieldname
									FROM " . TABLE_PREFIX . "phrase
									WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'blog_entry_notify'
								");

								while ($email_text = $this->dbobject->fetch_array($email_texts))
								{
									$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
								}

								require_once(DIR . '/includes/functions_misc.php');

								foreach ($emails AS $languageid => $email_text)
								{
									// lets cycle through our array of notify phrases
									$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
									$text_message = replace_template_variables($text_message);
									$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
									$text_subject = replace_template_variables($text_subject);

									$evalemail["$languageid"] = '
										$message = "' . $text_message . '";
										$subject = "' . $text_subject . '";
									';
								}
							}

							// parse the page text into plain text, taking selected language into account
							if (!isset($pagetext_cache["$touser[languageid]"]))
							{
								$plaintext_parser->set_parsing_language($touser['languageid']);
								$pagetext_cache["$touser[languageid]"] = $plaintext_parser->parse($pagetext_orig);
							}
							$pagetext = $pagetext_cache["$touser[languageid]"];

							($hook = vBulletinHook::fetch_hook('blog_post_notification_message')) ? eval($hook) : false;

							//these will get automagically used by the email phrase when the eval below is triggered.
							$blog_entry_url = fetch_seo_url('entry|bburl|nosession|js', $this->info['blog'], array('goto' => 'newpost'));
							$blog_unsub_url = fetch_seo_url('blogsub|nosession|js|bburl', array(), array(
								'do' => 'unsubscribe',
								'blogsubscribeuserid' => $touser['blogsubscribeuserid'],
								'auth' => $touser['auth']
							));

							eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));

							vbmail($touser['email'], $subject, $message);
						}

						unset($plaintext_parser, $pagetext_cache);

						vbmail_end();
					}
				}
			}
			else if ($this->fetch_field('state') == 'moderation' AND $this->info['blog'])
			{
				$blog->set('comments_moderation', 'comments_moderation + 1', false);
			}
		}

		if ($this->condition AND $this->info['emailupdate'] == 'none' AND ($userid != $this->registry->userinfo['userid'] OR ($userid == $this->registry->userinfo['userid'] AND $this->info['blog']['entrysubscribed'])))
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry
				WHERE blogid = $blogid AND userid = $userid
			");
		}
		else if ($this->info['emailupdate'] == 'email' OR $this->info['emailupdate'] == 'usercp')
		{
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "blog_subscribeentry
				(blogid, dateline, type, userid)
				VALUES
				($blogid, " . TIMENOW . ", '" . $this->info['emailupdate'] . "', $userid)
			");
		}

		if (is_object($blog))
		{
			$blog->save();
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('state') == 'moderation' AND $this->info['blog'])
			{
				$blogman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_SILENT);
				$userinfo = array('bloguserid' => $this->info['blog']['userid']);
				$blogman->set_existing($userinfo);
				$blogman->set('comments_moderation', 'comments_moderation + 1', false);
				$blogman->save();
			}
		}

		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "blog_moderation (primaryid, type, dateline) VALUES ($blogtextid, 'blogtextid', " . TIMENOW . ")");
		}

		// Email blog owner here based on their settings

		if (!$this->condition)
		{
			$activity = new vB_ActivityStream_Manage('blog', 'comment');
			$activity->set('contentid', $blogtextid);
			$activity->set('userid', $userid);
			$activity->set('dateline', $this->fetch_field('dateline'));
			$activity->set('action', 'create');
			$activity->save();
		}

		($hook = vBulletinHook::fetch_hook('blog_textdata_postsave')) ? eval($hook) : false;
	}

	/**
	 * Delete function 
	 * 
	 * @param  boolean $doquery - Added for PHP 5.4 strict standards compliance
	 * @return boolean			- Delete status
	 */
	public function delete($doquery = true)
	{
		if ($blogtextid = $this->existing['blogtextid'])
		{
			$db =& $this->registry->db;
			require_once(DIR . '/includes/blog_functions_log_error.php');

			if ($this->info['hard_delete'])
			{

				require_once(DIR . '/vb/search/indexcontroller/queue.php');
				vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogComment', 'delete', $blogtextid);

				$db->query_write("
					DELETE " . TABLE_PREFIX . "blog_text, " . TABLE_PREFIX . "blog_textparsed
					FROM " . TABLE_PREFIX . "blog_text
					LEFT JOIN " . TABLE_PREFIX . "blog_textparsed ON (" . TABLE_PREFIX . "blog_textparsed.blogtextid = " . TABLE_PREFIX . "blog_text.blogtextid)
					WHERE " . TABLE_PREFIX . "blog_text.blogtextid = $blogtextid
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_deletionlog
					WHERE primaryid = $blogtextid AND type = 'blogtextid'
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_moderation
					WHERE primaryid = $blogtextid AND type = 'blogtextid'
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_hash
					WHERE blogtextid = " . intval($blogtextid) . " AND
						dateline > " . (TIMENOW - 300)
				);

				if (!$this->info['skip_moderator_log'])
				{
					blog_moderator_action($this->existing, 'comment_x_by_y_removed', array($this->existing['title'], $this->existing['username']));
				}

				$activity = new vB_ActivityStream_Manage('blog', 'comment');
				$activity->set('contentid', $blogtextid);
				$activity->delete();
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();

				if (!$this->info['skip_moderator_log'])
				{
					blog_moderator_action($this->existing, 'comment_x_by_y_softdeleted', array($this->existing['title'], $this->existing['username']));
				}

				// soft delete
				// We have a DM for this
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "blog_deletionlog
						(primaryid, type, userid, username, reason, dateline)
					VALUES
						($blogtextid,
						'blogtextid',
						" . $this->registry->userinfo['userid'] . ",
						'" . $db->escape_string($this->registry->userinfo['username']) . "',
						'" . $db->escape_string($this->info['reason']) . "',
						" . TIMENOW . ")
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_moderation
					WHERE primaryid = $blogtextid AND type = 'blogtextid'
				");
			}

			if (!$this->info['skip_build_blog_counters'])
			{
				build_blog_entry_counters($this->existing['blogid']);
				if (empty($this->info['blog']['userid']))
				{
					$bloginfo = fetch_bloginfo($this->existing['blogid']);
					build_blog_user_counters($bloginfo['userid']);
				}
				else
				{
					build_blog_user_counters($this->info['blog']['userid']);
				}
			}

			($hook = vBulletinHook::fetch_hook('blog_textdata_delete')) ? eval($hook) : false;
			return true;
		}

		return false;
	}
}

/**
* Class to do data save options for Blogs
*
* @package	vBulletin
* @version	$Revision: 77475 $
* @date		$Date: 2013-09-11 12:17:48 -0700 (Wed, 11 Sep 2013) $
*/
class vB_DataManager_Blog_Firstpost extends vB_DataManager_Blog
{
	/**
	* Array of recognised and required fields for entries, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'firstblogtextid'      => array(TYPE_UINT,       REQ_AUTO),
		'lastcomment'          => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastcommenter'        => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastblogtextid'       => array(TYPE_UINT,       REQ_NO),
		'comments_visible'     => array(TYPE_UINT,       REQ_AUTO),
		'comments_moderation'  => array(TYPE_UINT,       REQ_NO),
		'comments_deleted'     => array(TYPE_UINT,       REQ_NO),
		'attach'               => array(TYPE_UINT,       REQ_NO),
		'views'                => array(TYPE_UINT,       REQ_NO),
		'trackback_visible'    => array(TYPE_UINT,       REQ_NO),
		'trackback_moderation' => array(TYPE_UINT,       REQ_NO),
		'pending'              => array(TYPE_UINT,       REQ_NO),
		'postedby_userid'      => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'postedby_username'    => array(TYPE_UINT,       REQ_NO),
		'notify'			   => array(TYPE_UINT, 		 REQ_NO),
		// shared fields
		'blogid'               => array(TYPE_UINT,       REQ_INCR,  VF_METHOD, 'verify_nonzero'),
		'userid'               => array(TYPE_UINT,       REQ_NO,    VF_METHOD),
		'bloguserid'           => array(TYPE_UINT,       REQ_NO),
		'dateline'             => array(TYPE_UNIXTIME,   REQ_AUTO),
		'title'                => array(TYPE_NOHTMLCOND, REQ_YES,   VF_METHOD),
		'state'                => array(TYPE_STR,        REQ_NO),
		'username'             => array(TYPE_NOHTMLCOND, REQ_NO,    VF_METHOD),

		// blogtext only fields
		'pagetext'             => array(TYPE_STR,        REQ_YES,   VF_METHOD),
		'allowsmilie'          => array(TYPE_UINT,       REQ_NO),
		'ipaddress'            => array(TYPE_STR,        REQ_AUTO,  VF_METHOD),
		'htmlstate'            => array(TYPE_STR,        REQ_AUTO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'options' => 'bf_misc_vbblogoptions',
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog';

	/**
	* Array to store stuff to save to blog table
	*
	* @var	array
	*/
	var $blog = array();

	/**
	* Array to store stuff to save blogtext tables
	*
	* @var	array
	*/
	var $blog_text = array();

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogid = %1$d', 'blogid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Firstpost(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_fpdata_start')) ? eval($hook) : false;
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	- The name of the field to which the supplied data should be applied
	* @param	mixed	- The data itself
	* @param			- Added for PHP 5.4 strict standards compliance
	*/
	public function do_set($fieldname, &$value, $table = null)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'blogid':
			case 'userid':
			case 'username':
			case 'title':
			case 'dateline':
			{
				$tables = array('blog', 'blog_text');
			}
			break;

			case 'state':
			{
				$this->blog_text['state'] = 'visible';
				$this->blog['state'] =& $value;
			}
			break;

			case 'htmlstate':
			case 'pagetext':
			case 'allowsmilie':
			case 'ipaddress':
			case 'bloguserid':
			{
				$tables = array('blog_text');
			}
			break;

			default:
			{
				$tables = array('blog');
			}
		}

		($hook = vBulletinHook::fetch_hook('blog_fpdata_doset')) ? eval($hook) : false;

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
		}
	}

	/**
	* Saves blog data to the database
	*
	* @param	boolean	Do the query?
	* @param	mixed	Added for PHP 5.4 strict standards compliance
	* @param 	bool 	Added for PHP 5.4 strict standards compliance
	* @param 	bool	Added for PHP 5.4 strict standards compliance
	* @param 	bool	Added for PHP 5.4 strict standards compliance
	* 
	* @return	mixed
	*/
	public function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return 0;
		}

		if ($this->condition)
		{
			// update query
			$return = $this->db_update(TABLE_PREFIX, 'blog', $this->condition, $doquery);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'blog_text', 'blogtextid = ' . $this->fetch_field('firstblogtextid'), $doquery);
			}
		}
		else
		{
			// insert query
			$return = $this->blog['blogid'] = $this->db_insert(TABLE_PREFIX, 'blog', $doquery);

			if ($return)
			{
				$this->do_set('blogid', $return);

				$firstblogtextid = $this->blog['firstblogtextid'] = $this->db_insert(TABLE_PREFIX, 'blog_text', $doquery);
				$this->do_set('firstblogtextid', $firstblogtextid);
				if ($doquery)
				{
					$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "blog SET firstblogtextid = $firstblogtextid, lastblogtextid = $firstblogtextid WHERE blogid = $return");
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
		}

		return $return;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->pre_save_blogtext($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if ($this->fetch_field('dateline') > TIMENOW)
		{
			$this->set('pending', 1);
		}
		else
		{
			$this->set('pending', 0);
		}

		if (!$this->condition)
		{
			if (!$this->fetch_field('dateline'))
			{
				$this->set('dateline', TIMENOW);
			}
			else if (($this->fetch_field('pending') AND $this->registry->options['vbblog_pending']) OR ($this->fetch_field('state') == 'draft' AND $this->registry->options['vbblog_draft']) AND $this->fetch_field('userid'))
			{
				if (defined('VBBLOG_PERMS') AND $this->registry->userinfo['userid'] == $this->fetch_field('userid'))
				{
					$userinfo =& $this->registry->userinfo;
				}
				else
				{
					if (!defined('VBBLOG_PERMS'))
					{	// Tell the fetch_userinfo plugin that we need the blog fields in case this class is being called by a non blog script
						define('VBBLOG_PERMS', true);
					}
					$userinfo = fetch_userinfo($this->fetch_field('userid'), 1);
				}
				if ($this->fetch_field('pending') AND $userinfo['blog_pending'] >= $this->registry->options['vbblog_pending'])
				{
					$this->error('maximum_pending_entries', $this->registry->options['vbblog_pending']);
					return false;
				}
				else if ($this->fetch_field('state') == 'draft' AND $userinfo['blog_draft'] >= $this->registry->options['vbblog_draft'])
				{
					$this->error('maximum_draft_entries', $this->registry->options['vbblog_draft']);
					return false;
				}
			}

			$this->set('lastcomment', $this->fetch_field('dateline'));
			$this->set('lastcommenter', $this->fetch_field('username', 'blog_text'));
			$this->set('comments_visible', 0);
			$this->set('comments_moderation', 0);
			$this->set('comments_deleted', 0);
			$this->set('trackback_visible', 0);
			$this->set('trackback_moderation', 0);
		}
		else
		{
			if (!$this->fetch_field('firstblogtextid'))
			{
				$getfirstpost = $this->dbobject->query_first("SELECT blogtextid FROM " . TABLE_PREFIX . "blog WHERE blogid = " . $this->fetch_field('blogid') . " ORDER BY dateline, blogtextid LIMIT 1");
				$this->set('firstblogtextid', $getfirstpost['blogtextid']);
			}
			if ($this->fetch_field('state') == 'draft' AND $this->existing['state'] != 'draft')
			{
				$this->error('existing_entries_can_not_be_draft');
				return false;
			}
			if ($this->fetch_field('pending') AND $this->existing['pending'] != 1 AND $this->existing['state'] != 'draft')
			{
				$this->error('published_entries_can_not_be_set_to_the_future');
				return false;
			}
		}

		if ($this->fetch_field('state') == 'draft' AND $this->info['notify'])
		{
			$this->error('cant_notify_drafts_entries');
			return false;
		}

		// Check flood time
		if ($this->fetch_field('pending') AND $this->registry->options['floodchecktime'] > 0 AND empty($this->info['skip_floodcheck']) AND !can_moderate_blog() AND $this->fetch_field('userid'))
		{
			if (!$this->condition OR ($this->existing['dateline'] != $this->fetch_field('dateline')))
			{
				// Want this to hit the master to lessen potential delays that would allow higher flood oppurtunity
				$lotime = $this->fetch_field('dateline') - $this->registry->options['floodchecktime'];
				$hitime = $this->fetch_field('dateline') + $this->registry->options['floodchecktime'];
				$wheresql = array();
				$wheresql[] = "dateline < $hitime";
				$wheresql[] = "dateline > $lotime";
				$wheresql[] = "userid = " . $this->fetch_field('userid');
				$wheresql[] = "pending = 1";
				if ($this->condition)
				{
					$wheresql[] = "blogid <> " . $this->fetch_field('blogid');
				}

				if ($this->dbobject->query_first("
					SELECT blogid
					FROM " . TABLE_PREFIX . "blog
					WHERE " . implode(" AND ", $wheresql) . "
				"))
				{
					$this->error('allow_x_seconds_between_entries', $this->registry->options['floodchecktime']);
					return false;
				}
			}
		}

		if (is_array($this->info['categories']))
		{
			$userid = $this->fetch_field('userid');
			require_once(DIR . '/includes/blog_functions_category.php');
			fetch_ordered_categories($userid);

			foreach ($this->info['categories'] AS $categoryid)
			{
				if (empty($this->registry->vbblog['categorycache']["$userid"]["$categoryid"]))
				{
					$this->error('invalid_blog_category');
					return false;
				}
			}
			if (sizeof($this->info['categories']) > $this->registry->options['blog_catpostlimit'])
			{
				$this->error('blog_category_entry_limit', $this->registry->options['blog_catpostlimit']);
				return false;
			}
		}

		$return_value = true;

		($hook = vBulletinHook::fetch_hook('blog_fpdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		$blogid = intval($this->fetch_field('blogid'));
		$userid = intval($this->fetch_field('userid'));
		$blogtextid = $this->fetch_field('blogtextid');
		$postedby_userid = intval($this->fetch_field('postedby_userid'));

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogEntry', 'index', $blogid);
		vb_Search_Indexcontroller_Queue::indexQueue('vBBlog', 'BlogComment', 'group_data_change', $blogid);

		if (!$this->condition AND $this->info['addtags'])
		{
			// invalidate users tag cloud
			$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_SILENT);
			$info = array('bloguserid' => $userid);
			$dataman->set_existing($info);
			$dataman->set('tagcloud', '');
			$dataman->save();
		}

		$this->build_category_counters();
		build_blog_stats();

		// Insert entry for moderation
		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->dbobject->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "blog_moderation
					(primaryid, type, dateline)
				VALUES
					($blogid, 'blogid', " . TIMENOW . ")
			");
		}

		// Insert entry for moderation
		if (!$this->condition AND ($this->fetch_field('state') == 'moderation' OR $this->fetch_field('state') == 'draft') OR $this->fetch_field('pending'))
		{
			$userinfo = array('bloguserid' => $userid);
			$userdata =& datamanager_init('Blog_user', $this->registry, ERRTYPE_SILENT);
			$userdata->set_existing($userinfo);
			if ($this->fetch_field('state') == 'moderation' OR $this->fetch_field('state') == 'draft')
			{
				$userdata->set($this->fetch_field('state'), $this->fetch_field('state') . ' + 1', false);
			}
			if ($this->fetch_field('pending'))
			{
				$userdata->set('pending', 'pending + 1', false);
			}
			$userdata->save();
		}

		// Insert Activity
		if (((!$this->condition AND !$this->fetch_field('pending')) OR $this->info['send_notification']) AND ($this->fetch_field('state') == 'visible' OR $this->fetch_field('state') == 'moderation'))
		{
			$activity = new vB_ActivityStream_Manage('blog', 'entry');
			$activity->set('contentid', $blogid);
			$activity->set('userid', $postedby_userid);
			$activity->set('dateline', $this->fetch_field('dateline'));
			$activity->set('action', 'create');
			$activity->save();
		}

		// Send Email Notification
		if (((!$this->condition AND !$this->fetch_field('pending')) OR $this->info['send_notification']) AND ($this->fetch_field('state') == 'visible' OR $this->fetch_field('state') == 'moderation') AND $this->registry->options['enableemail'])
		{
			$lastposttime = $this->dbobject->query_first("
				SELECT MAX(dateline) AS dateline
				FROM " . TABLE_PREFIX . "blog AS blog
				WHERE blogid = $blogid
					AND dateline < " . $this->fetch_field('dateline') . "
					AND state = 'visible'
			");

			$entrytitle = unhtmlspecialchars($this->fetch_field('title'));
			if (defined('VBBLOG_PERMS') AND $this->registry->userinfo['userid'] == $this->fetch_field('userid'))
			{
				$blogtitle = unhtmlspecialchars($this->registry->userinfo['blog_title']);
				$username = unhtmlspecialchars($this->registry->userinfo['username']);
				$userinfo =& $this->registry->userinfo;
			}
			else
			{
				if (!defined('VBBLOG_PERMS'))
				{	// Tell the fetch_userinfo plugin that we need the blog fields in case this class is being called by a non blog script
					define('VBBLOG_PERMS', true);
				}
				$userinfo = fetch_userinfo($this->fetch_field('userid'), 1);
				cache_permissions($userinfo, false);
				$blogtitle = unhtmlspecialchars($userinfo['blog_title']);
				if ($userinfo['userid'] != $this->fetch_field('userid'))
				{
					$userinfo2 = fetch_userinfo($this->fetch_field('userid'), 1);
					$username = unhtmlspecialchars($userinfo2['username']);
				}
				else
				{
					$username = unhtmlspecialchars($userinfo['username']);
				}
			}

			require_once(DIR . '/includes/class_bbcode_alt.php');
			$plaintext_parser = new vB_BbCodeParser_PlainText($this->registry, fetch_tag_list());
			$pagetext_cache = array(); // used to cache the results per languageid for speed

			$pagetext_orig =& $this->fetch_field('pagetext', 'blog_text');

			($hook = vBulletinHook::fetch_hook('blog_user_notification_start')) ? eval($hook) : false;

			$useremails = $this->dbobject->query_read_slave("
				SELECT
					user.*,
					blog_subscribeuser.blogsubscribeuserid,
					bm.blogmoderatorid,
					ignored.relationid AS ignoreid, buddy.relationid AS buddyid,
					bu.isblogmoderator, IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
				FROM " . TABLE_PREFIX . "blog_subscribeuser AS blog_subscribeuser
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog_subscribeuser.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "blog_moderator AS bm ON (bm.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = $userid AND buddy.relationid = user.userid AND buddy.type = 'buddy')
				LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = $userid AND ignored.relationid = user.userid AND ignored.type = 'ignore')
				LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = user.userid)
				WHERE
					blog_subscribeuser.bloguserid = $userid
						AND
					" . ($userid == $postedby_userid ? "blog_subscribeuser.userid <> $userid AND" : "") . "
					blog_subscribeuser.type = 'email'
						AND
					user.usergroupid <> 3
						AND
					user.lastactivity >= " . intval($lastposttime['dateline']) . "
			");

			vbmail_start();

			$setoptions = $this->fetch_field('options');



			$evalemail = array();
			while ($touser = $this->dbobject->fetch_array($useremails))
			{

				cache_permissions($touser, false);
				// only send private entries to contacts and moderators
				if ($setoptions["{$this->bitfields['options']['private']}"] AND !$touser['buddyid'] AND !$touser['blogmoderatorid'] AND !is_member_of_blog($touser, $userinfo))
				{
					continue;
				}

				if (!($this->registry->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup']))
				{
					continue;
				}

				if ($this->fetch_field('state') == 'moderation')
				{
					if ($touser['userid'] != $userid AND !can_moderate_blog('canmoderateentries', $touser))
					{
						continue;
					}
				}

				if (!empty($this->info['categories']))
				{
					prepare_blog_category_permissions($touser);
					if (array_intersect($touser['blogcategorypermissions']['cantview'], $this->info['categories']) AND $userinfo['userid'] != $touser['userid'])
					{
						continue;
					}
				}

				if (!($touser['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
				{
					continue;
				}
				else if (
					!$touser['blogmoderatorid']
						AND
					!($touser['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
						AND
					!($touser['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
						AND
					(!$userinfo['ignore_canviewmyblog'] OR !$touser['ignoreid'])
						AND
					(!$userinfo['buddy_canviewmyblog'] OR !$touser['buddyid'])
						AND
					(!$userinfo['member_canviewmyblog'] OR (!$userinfo['buddy_canviewmyblog'] AND $touser['budyid']) OR (!$userinfo['ignore_canviewmyblog'] AND $touser['ignoreid']))
						AND
					!is_member_of_blog($touser, $userinfo)
				)
				{
					continue;
				}

				$touser['username'] = unhtmlspecialchars($touser['username']);
				$touser['languageid'] = iif($touser['languageid'] == 0, $this->registry->options['languageid'], $touser['languageid']);
				$touser['auth'] = md5($touser['userid'] . $touser['blogsubscribeuserid'] . $touser['salt'] . COOKIE_SALT);

				if (empty($evalemail))
				{
					$email_texts = $this->dbobject->query_read_slave("
						SELECT text, languageid, fieldname
						FROM " . TABLE_PREFIX . "phrase
						WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'blog_user_notify'
					");

					while ($email_text = $this->dbobject->fetch_array($email_texts))
					{
						$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
					}

					require_once(DIR . '/includes/functions_misc.php');

					foreach ($emails AS $languageid => $email_text)
					{
						// lets cycle through our array of notify phrases
						$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
						$text_message = replace_template_variables($text_message);
						$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
						$text_subject = replace_template_variables($text_subject);

						$evalemail["$languageid"] = '
							$message = "' . $text_message . '";
							$subject = "' . $text_subject . '";
						';
					}
				}

				// parse the page text into plain text, taking selected language into account
				if (!isset($pagetext_cache["$touser[languageid]"]))
				{
					$plaintext_parser->set_parsing_language($touser['languageid']);
					$pagetext_cache["$touser[languageid]"] = $plaintext_parser->parse($pagetext_orig);
				}
				$pagetext = $pagetext_cache["$touser[languageid]"];

				($hook = vBulletinHook::fetch_hook('blog_user_notification_message')) ? eval($hook) : false;

				//this will automagically be used when we evail the email phrases below.
				$blog_url = fetch_seo_url('blog|nosession|js|bburl', array('userid' => $this->fetch_field('userid'), 'blog_title' => $blogtitle));
				$blog_unsub_url = fetch_seo_url('blogsub|nosession|js|bburl', array(), array(
					'do' => 'unsubscribe',
					'blogsubscribeuserid' => $touser['blogsubscribeuserid'],
					'auth' => $touser['auth']
				));

				eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));
				vbmail($touser['email'], $subject, $message);
			}
			unset($plaintext_parser, $pagetext_cache);

			vbmail_end();
		}

		$this->post_save_each_blogtext($doquery);

		if ($this->fetch_field('dateline') <= TIMENOW)
		{
			$this->insert_dupehash($this->fetch_field('blogid'));
		}

		if ($this->condition AND $this->info['emailupdate'] == 'none' AND ($userid != $this->registry->userinfo['userid'] OR ($userid == $this->registry->userinfo['userid'] AND $this->existing['entrysubscribed'])))
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry
				WHERE blogid = $blogid AND userid = $userid
			");
		}
		else if ($this->info['emailupdate'] == 'email' OR $this->info['emailupdate'] == 'usercp')
		{
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "blog_subscribeentry
				(blogid, dateline, type, userid)
				VALUES
				($blogid, " . TIMENOW . ", '" . $this->info['emailupdate'] . "', $userid)
			");
		}

		($hook = vBulletinHook::fetch_hook('blog_fpdata_postsave')) ? eval($hook) : false;
	}

	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('blog_fpdata_delete')) ? eval($hook) : false;

		return parent::delete($physically_delete, $reason, $keep_attachments);
	}

}


/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77475 $
|| ####################################################################
\*======================================================================*/
?>
