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

/**
* Class to do data save/delete operations for blog users
*
* @package	vBulletin
* @version	$Revision: 77114 $
* @date		$Date: 2013-08-27 08:07:50 -0700 (Tue, 27 Aug 2013) $
*/
class vB_DataManager_Blog_User extends vB_DataManager
{
	/**
	* Array of recognised and required fields for threadrate, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'bloguserid'          => array(TYPE_UINT,       REQ_YES, VF_METHOD, 'verify_userid'),
		'title'               => array(TYPE_STR,        REQ_NO,  VF_METHOD, 'verify_title'),
		'description'         => array(TYPE_STR,        REQ_NO,  VF_METHOD, 'verify_pagetext'),
		'options'             => array(TYPE_UINT,       REQ_NO),
		'comments'            => array(TYPE_UINT,       REQ_NO),
		'lastblog'            => array(TYPE_UNIXTIME,   REQ_NO),
		'lastblogid'          => array(TYPE_UINT,       REQ_NO),
		'lastblogtitle'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastcomment'         => array(TYPE_UNIXTIME,   REQ_NO),
		'lastcommenter'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastblogtextid'      => array(TYPE_UINT,       REQ_NO),
		'entries'             => array(TYPE_UINT,       REQ_NO),
		'moderation'          => array(TYPE_UINT,       REQ_NO),
		'deleted'             => array(TYPE_UINT,       REQ_NO),
		'draft'               => array(TYPE_UINT,       REQ_NO),
		'pending'             => array(TYPE_UINT,       REQ_NO),
		'allowsmilie'         => array(TYPE_UINT,       REQ_NO),
		'subscribeown'        => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array(\'none\', \'usercp\', \'email\'))) { $data = \'none\'; } return true; '),
		'subscribeothers'     => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array(\'none\', \'usercp\', \'email\'))) { $data = \'none\'; } return true; '),
		'options_member'      => array(TYPE_UINT,       REQ_NO),
		'options_guest'       => array(TYPE_UINT,       REQ_NO),
		'options_buddy'       => array(TYPE_UINT,       REQ_NO),
		'options_ignore'      => array(TYPE_UINT,       REQ_NO),
		'ratingnum'           => array(TYPE_UINT,       REQ_NO),
		'ratingtotal'         => array(TYPE_UINT,       REQ_NO),
		'rating'              => array(TYPE_NUM,        REQ_NO),
		'uncatentries'        => array(TYPE_UINT,       REQ_NO),
		'akismet_key'         => array(TYPE_STR,        REQ_NO, VF_METHOD, 'verify_akismet'),
		'comments_moderation' => array(TYPE_UINT,       REQ_NO),
		'comments_deleted'    => array(TYPE_UINT,       REQ_NO),
		'isblogmoderator'     => array(TYPE_UINT,       REQ_NO, VF_METHOD),
		'categorycache'       => array(TYPE_STR,        REQ_NO),
		'tagcloud'            => array(TYPE_ARRAY,      REQ_NO, VF_METHOD, 'verify_serialized'),
		'sidebar'             => array(TYPE_ARRAY,      REQ_NO, VF_METHOD, 'verify_serialized'),
		'custompages'         => array(TYPE_ARRAY,      REQ_NO, VF_METHOD, 'verify_serialized'),
		'customblocks'        => array(TYPE_UINT,       REQ_NO),
		'memberids'           => array(TYPE_STR,        REQ_NO),
		'memberblogids'       => array(TYPE_STR,        REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('bloguserid = %1$s', 'bloguserid');

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'options'          => 'bf_misc_vbbloguseroptions',
		'options_member'   => 'bf_misc_vbblogsocnetoptions',
		'options_guest'    => 'bf_misc_vbblogsocnetoptions',
		'options_buddy'    => 'bf_misc_vbblogsocnetoptions',
		'options_ignore'   => 'bf_misc_vbblogsocnetoptions',
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_user';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_User(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_userdata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$this->registry->options['maximages'] = $this->registry->options['vbblog_usermaximages'];
		$this->registry->options['maxvideos'] = $this->registry->options['vbblog_usermaxvideos'];
		if (!$this->verify_image_count('description', 'allowsmilie', 'blog_user'))
		{
			return false;
		}

		if (!$this->condition)
		{
			// Set defaults

			$this->set_bitfield('options', 'allowpingback', ($this->registry->bf_misc_vbblogregoptions['allowpingback'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options', 'allowcomments', ($this->registry->bf_misc_vbblogregoptions['allowcomments'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options', 'moderatecomments', ($this->registry->bf_misc_vbblogregoptions['moderatecomments'] & $this->registry->options['vbblog_defaultoptions']));

			$this->set_bitfield('options_buddy', 'canviewmyblog', ($this->registry->bf_misc_vbblogregoptions['viewblog_buddy'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options_buddy', 'cancommentmyblog', ($this->registry->bf_misc_vbblogregoptions['commentblog_buddy'] & $this->registry->options['vbblog_defaultoptions']));

			$this->set_bitfield('options_ignore', 'canviewmyblog', ($this->registry->bf_misc_vbblogregoptions['viewblog_ignore'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options_ignore', 'cancommentmyblog', ($this->registry->bf_misc_vbblogregoptions['commentblog_ignore'] & $this->registry->options['vbblog_defaultoptions']));

			$this->set_bitfield('options_member', 'canviewmyblog', ($this->registry->bf_misc_vbblogregoptions['viewblog_member'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options_member', 'cancommentmyblog', ($this->registry->bf_misc_vbblogregoptions['commentblog_member'] & $this->registry->options['vbblog_defaultoptions']));

			$this->set_bitfield('options_guest', 'canviewmyblog', ($this->registry->bf_misc_vbblogregoptions['viewblog_guest'] & $this->registry->options['vbblog_defaultoptions']));
			$this->set_bitfield('options_guest', 'cancommentmyblog', ($this->registry->bf_misc_vbblogregoptions['commentblog_guest'] & $this->registry->options['vbblog_defaultoptions']));

			if ($this->registry->bf_misc_vbblogregoptions['subscribe_none_entry'] & $this->registry->options['vbblog_defaultoptions'])
			{
				$this->set('subscribeown', 'none');
			}
			else if ($this->registry->bf_misc_vbblogregoptions['subscribe_nonotify_entry'] & $this->registry->options['vbblog_defaultoptions'])
			{
				$this->set('subscribeown', 'usercp');
			}
			else
			{
				$this->set('subscribeown', 'email');
			}

			if ($this->registry->bf_misc_vbblogregoptions['subscribe_none_comment'] & $this->registry->options['vbblog_defaultoptions'])
			{
				$this->set('subscribeothers', 'none');
			}
			else if ($this->registry->bf_misc_vbblogregoptions['subscribe_nonotify_comment'] & $this->registry->options['vbblog_defaultoptions'])
			{
				$this->set('subscribeothers', 'usercp');
			}
			else
			{
				$this->set('subscribeothers', 'email');
			}

			$this->set('memberblogids', $this->fetch_field('bloguserid'));
			$this->set('memberids', $this->fetch_field('bloguserid'));
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_userdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// A user's blog entries can only be found from searching after the user is deleted.
		if (!$this->info['skip_blog_entries'])
		{
			$blogids = array();
			$blogs = $this->dbobject->query_read_slave("
				SELECT *
				FROM " . TABLE_PREFIX . "blog
				WHERE userid = " . intval($this->fetch_field('bloguserid')
			));
			while ($blog = $this->dbobject->fetch_array($blogs))
			{
				$blogids[] = intval($blog['blogid']);
			}

			if (sizeof($blogids))
			{
				$blogids = implode(',', $blogids);

				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_deletionlog WHERE primaryid IN ($blogids) AND type = 'blogid'");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_moderation WHERE primaryid IN ($blogids) AND type = 'blogid'");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_pinghistory WHERE blogid IN ($blogids)");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_rate WHERE blogid IN ($blogids)");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_read WHERE blogid IN ($blogids)");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry WHERE blogid IN ($blogids)");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_tachyentry WHERE blogid IN ($blogids)");

				$textids = array();
				$comments = $this->dbobject->query_read("
					SELECT blogtextid
					FROM " . TABLE_PREFIX . "blog_text
					WHERE blogid IN ($blogids)
				");
				while ($comment = $this->dbobject->fetch_array($comments))
				{
					$textids[] = $comment['blogtextid'];
				}
				$activity = new vB_ActivityStream_Manage('blog', 'comment');
				$activity->set('contentid', $textids);
				$activity->delete();

				$this->dbobject->query_write("
					DELETE " . TABLE_PREFIX . "blog_text, " . TABLE_PREFIX . "blog_textparsed, " . TABLE_PREFIX . "blog_editlog, " . TABLE_PREFIX . "blog_moderation, " . TABLE_PREFIX . "blog_deletionlog
					FROM " . TABLE_PREFIX . "blog_text
					LEFT JOIN " . TABLE_PREFIX . "blog_textparsed ON (" . TABLE_PREFIX . "blog_textparsed.blogtextid = " . TABLE_PREFIX . "blog_text.blogtextid)
					LEFT JOIN " . TABLE_PREFIX . "blog_editlog ON (" . TABLE_PREFIX . "blog_editlog.blogtextid = " . TABLE_PREFIX . "blog_text.blogtextid)
					LEFT JOIN " . TABLE_PREFIX . "blog_moderation ON (" . TABLE_PREFIX . "blog_moderation.primaryid = " . TABLE_PREFIX . "blog_text.blogtextid AND " . TABLE_PREFIX . "blog_moderation.type = 'blogtextid')
					LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog ON (" . TABLE_PREFIX . "blog_deletionlog.primaryid = " . TABLE_PREFIX . "blog_text.blogtextid AND " . TABLE_PREFIX . "blog_deletionlog.type = 'blogtextid')
					WHERE " . TABLE_PREFIX . "blog_text.blogid IN ($blogids)
				");

				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_trackback WHERE blogid IN ($blogids)");
				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "blog_views WHERE blogid IN ($blogids)");

				$this->dbobject->query_write("
					DELETE FROM " . TABLE_PREFIX . "blog_hash
					WHERE blogid IN ($blogids)
				");

				require_once(DIR . '/includes/class_taggablecontent.php');
				vB_Taggable_Content_Item::delete_tag_attachments_list("vBBlog_BlogEntry", explode(',', $blogids));

				$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT);
				$attachdata->condition = "a.contentid IN ($blogids)";
				$attachdata->delete();

				$contenttypeid = vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry');

				$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT, 'attachment');
				$attachdata->condition = "a.contentid IN ($blogids) AND a.contenttypeid = " . intval($contenttypeid);
				$attachdata->delete(true, false);

				$this->dbobject->query_write("DELETE  FROM " . TABLE_PREFIX . "blog WHERE blogid IN ($blogids)");
				$activity = new vB_ActivityStream_Manage('blog', 'entry');
				$activity->set('contentid', explode(',', $blogids));
				$activity->delete();
			}
		}
		else
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "blog SET
					username = '" . $this->dbobject->escape_string($this->info['verifyuser']['username']) . "',
					userid = 0
				WHERE userid = " . intval($this->fetch_field('bloguserid'))
			);
		}

		// User's comments
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "blog_text SET
				username = '" . $this->dbobject->escape_string($this->info['verifyuser']['username']) . "',
				userid = 0
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// Deleted items belonging to user
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "blog_deletionlog
			SET username = '" . $this->dbobject->escape_string($this->info['verifyuser']['username']) . "'
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// User's category to post list
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_categoryuser
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// User's categories
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_category
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// User's read status
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_read
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// User's search records
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_search
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// Blog Subscriptions
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// Post Subscriptions
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
			WHERE bloguserid = " . intval($this->fetch_field('bloguserid'))
		);

		// User's read status for blogs and anyone for they're blog
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_userread
			WHERE userid = " . intval($this->fetch_field('bloguserid')) . " OR bloguserid = " . intval($this->fetch_field('bloguserid'))
		);

		// Groups
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "blog
			SET
				postedby_userid = userid,
				postedby_username = username
			WHERE postedby_userid = " . intval($this->fetch_field('bloguserid'))
		);

		$users = $this->dbobject->query_read_slave("
			SELECT userid
			FROM " . TABLE_PREFIX . "blog_groupmembership
			WHERE bloguserid = " . intval($this->fetch_field('bloguserid'))
		);

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_groupmembership
			WHERE bloguserid = " . intval($this->fetch_field('bloguserid'))
		);

		while ($user = $this->dbobject->fetch_array($users))
		{
			build_blog_memberblogids($user['userid']);
		}

		$groups = $this->dbobject->query_read_slave("
			SELECT bloguserid
			FROM " . TABLE_PREFIX . "blog_groupmembership
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_groupmembership
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		// Blog Customizations
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_usercss
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_usercsscache
			WHERE userid = " . intval($this->fetch_field('bloguserid'))
		);

		while ($group = $this->dbobject->fetch_array($groups))
		{
			build_blog_memberids($group['bloguserid']);
		}

		($hook = vBulletinHook::fetch_hook('blog_userdata_delete')) ? eval($hook) : false;
	}

	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('blog_userdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Verifies that user really is a blog moderator
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_isblogmoderator(&$value)
	{
		if ($value)
		{
			if (!($ismoderator = $this->dbobject->query_first_slave("
				SELECT userid
				FROM " . TABLE_PREFIX . "blog_moderator
				WHERE userid = " . intval($this->fetch_field('bloguserid'))
			)))
			{
				$value = 0;
			}
		}

		return true;
	}

	/**
	* Take an array and return serialized output
	*
	* @param	array	Array of sidebar blocks
	*
	* @param	bool
	*/
	function serialize_data(&$value)
	{
		if (!is_array($value))
		{
			$value = array();
		}

		$value = serialize($value);

		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	* @param    bool    added for PHP 5.4 strict standards compliance
	*/
	function verify_pagetext(&$pagetext, $noshouting = true)
	{
		if (empty($this->info['skip_charcount']))
		{
			if ($this->registry->options['vbblog_usermaxchars'] != 0 AND ($postlength = vbstrlen($pagetext)) > $this->registry->options['vbblog_usermaxchars'])
			{
				$this->error('toolong', $postlength, $this->registry->options['vbblog_usermaxchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext);

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

		// censor, remove all caps subjects, and htmlspecialchars post title
		$title = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text(trim($title))));

		// do word wrapping
		$title = fetch_word_wrapped_string($title, $this->registry->options['blog_wordwrap']);

		return true;
	}

	/**
	* Verifies the akismet key is 0-9a-z
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_akismet(&$akismet_key)
	{
		if (!empty($akismet_key))
		{
			if (!preg_match('#^[a-z0-9]+$#i', $akismet_key))
			{
				$this->error('akismet_key_invalid', $akismet_key);
				return false;
			}

			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);

			$akismet->akismet_key = $akismet_key;
			$akismet->akismet_board = fetch_seo_url('bloghome|nosession|bburl', array());

			if (!$akismet->_build())
			{
				$this->error('akismet_key_invalid', $akismet_key);
				return false;
			}
		}
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77114 $
|| ####################################################################
\*======================================================================*/
?>
