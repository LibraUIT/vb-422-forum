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

/**
* Blog entry factory.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_EntryFactory
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Information about the blog this response belongs to
	*
	* @var	array
	*/
	var $categories = array();

	/**
	* True if an entry can be deleted
	*
	* @var	boolean
	*/
	var $delete = false;

	/**
	* True if an entry can be undeleted
	*
	* @var	boolean
	*/
	var $undelete = false;

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Array holding some conditional values for status codes
	*
	* @var	array
	*/
	var $status = array();

	/**
	* Array holding information about a specific user if we are viewing a specific user's entries
	*
	* @var	array
	*/
	var $userinfo = array();

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	array			Blog info
	*/
	function vB_Blog_EntryFactory(&$registry, &$bbcode, &$categories)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->bbcode =& $bbcode;
		$this->categories =& $categories;
	}

	/**
	* Create an blog response object for the specified response
	*
	* @param	array	 Response information
	* @param string Override auto detection
	*
	* @return	vB_Blog_Response
	*/
	function &create($entry, $type = '', $ignored_users = array())
	{
		$class_name = 'vB_Blog_Entry';

		if ($type == 'external')
		{
			$class_name .= '_External';
		}
		else if ($ignored_users["$entry[userid]"] AND !$type)
		{
			$class_name .= '_Ignore';
		}
		else
		{
			switch ($entry['state'])
			{
				case 'deleted':
					$class_name .= '_Deleted';
					break;

				case 'moderation':
				case 'visible':
				default:
					if ($type)
					{
						$class_name .= $type;
					}
					break;
			}
		}

		($hook = vBulletinHook::fetch_hook('blog_entry_factory')) ? eval($hook) : false;

		if (class_exists($class_name, false))
		{
			return new $class_name($this->registry, $this, $this->bbcode, $this->categories, $entry, $ignored_users);
		}
		else
		{
			trigger_error('vB_Blog_EntryFactory::create(): Invalid type (' . htmlspecialchars_uni($class_name) . ')', E_USER_ERROR);
		}
	}
}

/**
* Generic blog entry class.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_Entry
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Factory object that created this object. Used for permission caching.
	*
	* @var	vB_Blog_EntryFactory
	*/
	var $factory = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Cached information from the BB code parser
	*
	* @var	array
	*/
	var $parsed_cache = array();

	/**
	* Information about the possible categories we need
	*
	* @var	array
	*/
	var $categories = array();

	/**
	* Information about the blog this entry belongs to
	*
	* @var	array
	*/
	var $blog = array();

	/**
	* Variable which identifies if the data should be cached
	*
	* @var	boolean
	*/
	var $cachable = true;

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'blog_entry';

	/**
	* The array of attachment information
	*
	* @var	array
	*/
	var $attachments = array();

	/**
	* Excerpt doesn't match full post
	*
	* @var boolean
	*/
	var $readmore = false;

	public $is_first = false;

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Blog_EntryFactory
	* @param	array	Blog info
	*	@param	array	Ignored Users
	*/
	function vB_Blog_Entry(&$registry, &$factory, &$bbcode, &$categories, $blog, $ignored_users)
	{
		if (!is_subclass_of($this, 'vB_Blog_Entry'))
		{
			//trigger_error('Direct instantiation of vB_Blog_Entry class prohibited. Use the vB_Blog_EntryFactory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Blog_Entry::Registry object is not an object", E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;
		$this->categories =& $categories;
		$this->ignored_users = $ignored_users;
		$this->blog = $blog;
	}

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		global $vbulletin;
		($hook = vBulletinHook::fetch_hook('blog_entry_display_start')) ? eval($hook) : false;

		// preparation for display...
		$this->prepare_start();

		if ($this->blog['userid'])
		{
			$this->process_registered_user();
		}
		else
		{
			$this->process_unregistered_user();
		}

		$this->process_date_status();
		$this->process_display();
		$this->process_text();
		$this->process_attachments();
		$this->prepare_end();

		// actual display...
		$blog =& $this->blog;
		$status =& $this->status;

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;
		$show['ignoreduser'] = ($this->ignored_users[$this->blog['userid']]);

		if (defined('VB_API') AND VB_API === true)
		{
			$bloginfo = fetch_bloginfo($this->blog['blogid']);
			$show['postcomment'] = fetch_can_comment($bloginfo, $vbulletin->userinfo);
		}

		// prepare the member action drop-down menu
		$memberaction_dropdown = construct_memberaction_dropdown($blog);

		//set up the ad for the first blog entry
		global $ad_location;
		if ($this->is_first)
		{
			 $ad_location['bloglist_first_entry'] = vB_Template::create('ad_bloglist_first_entry')->render();
		}

		($hook = vBulletinHook::fetch_hook('blog_entry_display_complete')) ? eval($hook) : false;

		$templater = vB_Template::create($this->template);
			$templater->register('blog', $blog);
			$templater->register('memberaction_dropdown', $memberaction_dropdown);
			$templater->register('status', $status);
			$templater->register('is_first', $this->is_first);
			$templater->register('ad_location', $ad_location);
			if ($vbulletin->products['vbcms'])
			{

				if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
				{
					require_once DIR . '/packages/vbcms/permissions.php';
					vBCMS_Permissions::getUserPerms();
				}

				if (count(vB::$vbulletin->userinfo['permissions']['cms']['canpublish']))
				{
					$templater->register('promote_sectionid', vB::$vbulletin->userinfo['permissions']['cms']['canpublish'][0]);
					$templater->register('articletypeid', vB_Types::instance()->getContentTypeID('vBCms_Article'));
					$query = 'contenttypeid='. vB_Types::instance()->getContentTypeID('vBCms_Article') .
						'&amp;blogid' . $blog['blogid'] . '&amp;parentid=1';
					$promote_url = vB_Route::create('vBCms_Route_Content', '1/addcontent/')->getCurrentURL(null, null, $query);
					$templater->register('promote_url', $promote_url);
				}
			}

		$output = $templater->render(($this->registry->GPC['ajax']));


		return $output;
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		$this->blog = array_merge($this->blog, convert_bits_to_array($this->blog['options'], $this->registry->bf_misc_useroptions));
		$this->blog = array_merge($this->blog, convert_bits_to_array($this->blog['adminoptions'], $this->registry->bf_misc_adminoptions));
		$this->blog = array_merge($this->blog, convert_bits_to_array($this->blog['blogoptions'], $this->registry->bf_misc_vbblogoptions));

		$this->blog['checkbox_value'] = 0;
		$this->blog['checkbox_value'] += ($this->blog['state'] == 'moderation') ? POST_FLAG_INVISIBLE : 0;
		$this->blog['checkbox_value'] += ($this->blog['state'] == 'deleted') ? POST_FLAG_DELETED : 0;
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->blog, 'displaygroupid');

		$this->blog['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->blog['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->blog['lastvisit'] != $this->blog['lastactivity'])
		{
			if ($this->blog['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->blog['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->blog['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->blog['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->blog['userid']}"]))
		{
			$this->factory->perm_cache["{$this->blog['userid']}"] = cache_permissions($this->blog, false);
		}
		else
		{
			$this->blog['permissions'] =& $this->factory->perm_cache["{$this->blog['userid']}"];
		}

		fetch_avatar_html($this->blog, true);
		fetch_profilepic_html($this->blog);

		$show['subscribelink'] = ($this->blog['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['userid']);
		$show['blogsubscribed'] = $this->blog['blogsubscribed'];
		$show['entrysubscribed'] = $this->blog['entrysubscribed'];

		$show['emaillink'] = (
			$this->blog['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
		);
		$show['homepage'] = ($this->blog['homepage'] != '' AND $this->blog['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->blog['receivepm'] AND $this->factory->perm_cache["{$this->blog['userid']}"]['pmquota'])
	 				)) ? true : false;

	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		global $show;

		$show['subscribelink'] = false;

		$this->blog['rank'] = '';
		$this->blog['notesperday'] = 0;
		$this->blog['displaygroupid'] = 1;
		fetch_musername($this->blog);
		//$this->blog['usertitle'] = $vbphrase['guest'];
		$this->blog['usertitle'] =& $this->registry->usergroupcache["0"]['usertitle'];
		$this->blog['joindate'] = '';
		$this->blog['notes'] = 'n/a';
		$this->blog['avatar'] = '';
		$this->blog['profile'] = '';
		$this->blog['email'] = '';
		$this->blog['useremail'] = '';
		$this->blog['icqicon'] = '';
		$this->blog['aimicon'] = '';
		$this->blog['yahooicon'] = '';
		$this->blog['msnicon'] = '';
		$this->blog['skypeicon'] = '';
		$this->blog['homepage'] = '';
		$this->blog['findnotes'] = '';
		$this->blog['signature'] = '';

		$this->blog['reputationdisplay'] = '';
		$this->blog['onlinestatus'] = '';

		if (!isset($this->factory->perm_cache["{$this->blog['userid']}"]))
		{
			$this->factory->perm_cache["{$this->blog['userid']}"] = cache_permissions($this->blog, false);
		}
		else
		{
			$this->blog['permissions'] =& $this->factory->perm_cache["{$this->blog['userid']}"];
		}
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		global $vbphrase;

		$this->bbcode->attachments =& $this->attachments;
		$this->bbcode->unsetattach = true;
		$this->bbcode->set_parse_userinfo($this->blog, $this->factory->perm_cache["{$this->blog['userid']}"]);
		$this->bbcode->containerid = $this->blog['blogid'];
		$this->blog['message'] = $this->bbcode->parse(
			$this->blog['pagetext'],
			'blog_entry',
			$this->blog['allowsmilie'],
			false,
			$this->blog['pagetexthtml'], // fix
			$this->blog['hasimages'], // fix
			$this->cachable,
			$this->blog['htmlstate']
		);
		if (defined('VB_API') AND VB_API === true)
		{
			$this->blog['message_plain'] = build_message_plain($this->blog['pagetext']);
			$this->blog['message_bbcode'] = $this->blog['pagetext'];
		}

		if (defined('VB_API') AND VB_API === true)
		{
			$this->registry->input->clean_gpc('r', 'nohtml', TYPE_STR);
			if ($this->registry->GPC['nohtml'])
			{
				$this->blog['message'] = unhtmlspecialchars(strip_tags($this->blog['message']), false);
			}
		}
		if ($this->bbcode->createdsnippet !== true)
		{
			$this->parsed_cache =& $this->bbcode->cached;
		}
		$this->readmore = ($this->bbcode->createdsnippet);
	}

	/**
	* Processes any attachments to this entry.
	*/
	function process_attachments()
	{
		require_once(DIR . '/packages/vbattach/attach.php');
		$attach = new vB_Attach_Display_Content($this->registry, 'vBBlog_BlogEntry');
		$attach->process_attachments($this->blog, $this->attachments, (THIS_SCRIPT == 'blogexternal'), true, true, true);
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;

		if ($this->registry->options['logip'] AND $this->blog['blogipaddress'] AND (can_moderate_blog('canviewips') OR $this->registry->options['logip'] == 2))
		{
			$this->blog['blogipaddress'] = htmlspecialchars_uni(long2ip($this->blog['blogipaddress']));
		}
		else
		{
			$this->blog['blogipaddress'] = '';
		}

		$show['reportlink'] = (
			$this->blog['state'] != 'draft'
			AND
			!$this->blog['pending']
			AND
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}

	function process_date_status()
	{
		global $vbphrase, $show;

		if (!empty($this->blog))
		{
			if ($this->registry->userinfo['userid'] AND $this->registry->options['threadmarking'])
			{
				$blogview = max($this->blog['blogread'], $this->blog['bloguserread'], TIMENOW - ($this->registry->options['markinglimit'] * 86400));
				$lastvisit = intval($blogview);
			}
			else
			{
				$blogview = max(fetch_bbarray_cookie('blog_lastview', $this->blog['blogid']), fetch_bbarray_cookie('blog_userread', $this->blog['userid']), $this->registry->userinfo['lastvisit']);
				$lastvisit = intval($blogview);
			}
		}
		else
		{
			$lastvisit = $this->registry->userinfo['lastvisit'];
		}

		if ($this->blog['dateline'] > $lastvisit)
		{
			$this->blog['statusicon'] = 'new';
			$this->blog['statustitle'] = $vbphrase['unread_date'];
		}
		else
		{
			$this->blog['statusicon'] = 'old';
			$this->blog['statustitle'] = $vbphrase['old'];
		}

		// show new comment arrow
		if ($this->blog['lastcomment'] > $lastvisit)
		{
			if ($this->registry->options['threadmarking'] AND $this->blog['blogread'])
			{
				$blogview = $this->blog['blogread'];
			}
			else
			{
				$blogview = intval(fetch_bbarray_cookie('blog_lastview', $this->blog['blogid']));
			}

			if ($this->blog['lastcomment'] > $blogview)
			{
				$show['gotonewcomment'] = true;
			}
			else
			{
				$show['gotonewcomment'] = false;
			}
		}
		else
		{
			$show['gotonewcomment'] = false;
		}

		$this->blog['date'] = vbdate($this->registry->options['dateformat'], $this->blog['dateline'], true);
		$this->blog['time'] = vbdate($this->registry->options['timeformat'], $this->blog['dateline']);
	}

	function process_display()
	{
		global $show, $vbphrase;
		static $delete, $approve;

		$blog =& $this->blog;

		if ($this->blog['ratingnum'] >= $this->registry->options['vbblog_ratingpost'] AND $this->blog['ratingnum'])
		{
			$this->blog['ratingavg'] = vb_number_format($this->blog['ratingtotal'] / $this->blog['ratingnum'], 2);
			$this->blog['rating'] = intval(round($this->blog['ratingtotal'] / $this->blog['ratingnum']));
			$show['rating'] = true;
		}
		else
		{
			$show['rating'] = false;
		}

		if (!$this->blog['blogtitle'])
		{
			$this->blog['blogtitle'] = $this->blog['username'];
		}

		$categorybits = array();

		if (!empty($this->categories["{$this->blog[blogid]}"]))
		{
			foreach ($this->categories["{$this->blog[blogid]}"] AS $index => $category)
			{
				$category['blogtitle']= $this->blog['blogtitle'];
				$show['cattitleonly'] = (!$category['creatorid'] AND !($this->registry->userinfo['blogcategorypermissions']["$category[blogcategoryid]"] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewcategory']));
				$templater = vB_Template::create('blog_entry_category');
					$templater->register('category', $category);
					$templater->register('pageinfo', array('blogcategoryid' => $category['blogcategoryid']));
				$categorybits[] = $templater->render();
			}
		}
		else
		{
			$category = array(
				'blogcategoryid' => -1,
				'title'          => $vbphrase['uncategorized'],
				'userid'         => $this->blog['userid'],
				'blogtitle'      => $this->blog['blogtitle'],
			);
			$templater = vB_Template::create('blog_entry_category');
				$templater->register('category', $category);
				$templater->register('pageinfo', array('blogcategoryid' => $category['blogcategoryid']));
			$categorybits[] = $templater->render();
		}

		$show['category'] = true;
		$this->blog['categorybits'] = implode($vbphrase['comma_space'], $categorybits);

		$show['trackback_moderation'] = ($this->blog['trackback_moderation'] AND ($this->blog['userid'] == $this->registry->userinfo['userid'] OR can_moderate_blog('canmoderatecomments'))) ? true : false;
		$show['comment_moderation'] = ($this->blog['hidden'] AND ($this->blog['userid'] == $this->registry->userinfo['userid'] OR can_moderate_blog('canmoderatecomments'))) ? true : false;

		$show['edit'] = fetch_entry_perm('edit', $this->blog);
		$show['delete'] = fetch_entry_perm('delete', $this->blog);
		$show['remove'] = fetch_entry_perm('remove', $this->blog);
		$show['undelete'] = fetch_entry_perm('undelete', $this->blog);
		$show['approve'] = fetch_entry_perm('moderate', $this->blog);

		$show['inlinemod'] = (($show['delete'] OR $show['remove'] OR $show['approve'] OR $show['undelete'])
			AND
		(
			can_moderate_blog()
				OR
			(
				!empty($this->userinfo)
					AND
				is_member_of_blog($this->registry->userinfo, $this->userinfo)
			)
		));

		if ($this->blog['dateline'] > TIMENOW OR $this->blog['pending'])
		{
			$this->status['phrase'] = $vbphrase['pending_blog_entry'];
			$this->status['image'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . "/blog/pending.gif";
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'deleted')
		{
			$this->status['image'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . "/trashcan.gif";
			$this->status['phrase'] = $vbphrase['deleted_blog_entry'];
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'moderation')
		{
			$this->status['phrase'] = $vbphrase['moderated_blog_entry'];
			$this->status['image'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . "/moderated.gif";
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'draft')
		{
			$this->status['phrase'] = $vbphrase['draft_blog_entry'];
			$this->status['image'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . "/blog/draft.gif";
			$show['status'] = true;
		}
		else
		{
			$show['status'] = false;
		}

		$show['private'] = false;
		if ($blog['private'])
		{
			$show['private'] = true;
		}
		else if (can_moderate() AND !is_member_of_blog($this->registry->userinfo, $blog))
		{
			$membercanview = $blog['options_member'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $blog['options_buddy'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$membercanview AND (!$blog['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		if ($this->blog['edit_userid'])
		{
			$this->blog['edit_date'] = vbdate($this->registry->options['dateformat'], $this->blog['edit_dateline'], true);
			$this->blog['edit_time'] = vbdate($this->registry->options['timeformat'], $this->blog['edit_dateline']);
			if ($this->blog['edit_reason'])
			{
				$this->blog['edit_reason'] = fetch_word_wrapped_string($this->blog['edit_reason']);
			}
			$show['entryedited'] = true;
		}
		else
		{
			$show['entryedited'] = false;
		}

		$show['tags'] = false;
		if ($this->registry->options['vbblog_tagging'])
		{
			require_once(DIR . '/includes/blog_functions_tag.php');

			$this->blog['tag_list'] = fetch_entry_tagbits($this->blog, $this->userinfo);
			$show['tag_edit'] = (
				(($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_cantagown']) AND $this->blog['userid'] == $this->registry->userinfo['userid'])
				OR ($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_cantagothers'])
				OR (($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_candeletetagown']) AND $this->blog['userid'] == $this->registry->userinfo['userid'])
				OR can_moderate_blog('caneditentries')
			);
			$show['tags'] = ($show['tag_edit'] OR $this->blog['taglist']);
			$show['notags'] = !$this->blog['taglist'];
		}
	}
}

class vB_Blog_Entry_Ignore extends vB_Blog_Entry
{
	var $template = 'blog_entry_ignore';
}

class vB_Blog_Entry_Deleted extends vB_Blog_Entry
{
	var $template = 'blog_entry_deleted';
}

class vB_Blog_Entry_Featured extends vB_Blog_Entry
{
	var $template = 'blog_entry_featured';
}

class vB_Blog_Entry_Profile extends vB_Blog_Entry
{
	var $template = 'blog_entry_profile';
}

class vB_Blog_Entry_User extends vB_Blog_Entry
{
	var $template = 'blog_entry';
}

class vB_Blog_Entry_External extends vB_Blog_Entry
{
	var $template = 'blog_entry_external';

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		($hook = vBulletinHook::fetch_hook('blog_entry_display_start')) ? eval($hook) : false;

		// preparation for display...
		$this->prepare_start();

		$imgdir_attach = vB_Template_Runtime::fetchStyleVar('imgdir_attach');
		if (!preg_match('#^[a-z]+:#siU', vB_Template_Runtime::fetchStyleVar('imgdir_attach')))
		{
			if ($imgdir_attach[0] == '/')
			{
				$url = $this->registry->input->parse_url($this->registry->options['bburl']);
				vB_Template_Runtime::addStyleVar('imgdir_attach', 'http://' . $url['host'] . vB_Template_Runtime::fetchStyleVar('imgdir_attach'), 'imgdir');
			}
			else
			{
				vB_Template_Runtime::addStyleVar('imgdir_attach', $this->registry->options['bburl'] . '/' . vB_Template_Runtime::fetchStyleVar('imgdir_attach'), 'imgdir');
			}
		}

		if ($this->blog['userid'])
		{
			$this->process_registered_user();
		}
		else
		{
			$this->process_unregistered_user();
		}

		$this->process_date_status();
		$this->process_display();
		$this->process_text();
		$this->process_attachments();
		$this->prepare_end();

		// actual display...
		$blog = $this->blog;
		$status =& $this->status;

		if ($this->attachments)
		{
			$search = '#(href|src)="attachment\.php#si';
			$replace = '\\1="' . $this->registry->options['bburl'] . '/' . 'attachment.php';
			$items = array(
				't' => $blog['thumbnailattachments'],
				'a' => $blog['imageattachments'],
				'l' => $blog['imageattachmentlinks'],
				'o' => $blog['otherattachments'],
			);

			$newitems = preg_replace($search, $replace, $items);
			unset($items);
			$blog['thumbnailattachments'] = $newitems['t'];
			$blog['imageattachments'] = $newitems['a'];
			$blog['imageattachmentlinks'] = $newitems['l'];
			$blog['otherattachments'] = $newitems['o'];
		}

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;

		$sessionurl = $this->registry->session->vars['sessionurl'];
		$this->registry->session->vars['sessionurl'] = '';

		($hook = vBulletinHook::fetch_hook('blog_entry_display_complete')) ? eval($hook) : false;

		$templater = vB_Template::create($this->template);
			$templater->register('blog', $blog);
			$templater->register('status', $status);
		$output = $templater->render();

		$this->registry->session->vars['sessionurl'] = $sessionurl;
		vB_Template_Runtime::addStyleVar('imgdir_attach', $imgdir_attach, 'imgdir');

		return $output;
	}
	/**
	* Parses the post for BB code.
	*/
	function process_text()
	{
		$this->blog['allowsmilie'] = false;
		parent::process_text();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 76725 $
|| ####################################################################
\*======================================================================*/
?>
