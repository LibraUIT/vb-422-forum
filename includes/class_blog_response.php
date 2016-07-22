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
* Blog response factory.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_ResponseFactory
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
	var $bloginfo = array();

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Permission cache for entries.
	*
	* @var	array
	*/
	var $blog_cache = array();

	/**
	* Excerpt doesn't match full post
	*
	* @var boolean
	*/
	var $readmore = false;

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
	function vB_Blog_ResponseFactory(&$registry, &$bbcode, &$bloginfo)
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
		$this->bloginfo =& $bloginfo;

	}

	/**
	* Create an blog response object for the specified response
	*
	* @param	array	Response information
	*
	* @return	vB_Blog_Response
	*/
	function &create(&$response, $type = 'Comment', $ignored_users = array())
	{
		$class_name = 'vB_Blog_Response_';
		if ($response['state'] == 'deleted' AND $this->registry->GPC['uh'])
		{
			$response['state'] = 'visible';
		}

		if ($ignored_users["$response[userid]"])
		{
			$class_name .= 'Ignore';
		}
		else
		{
			switch ($response['state'])
			{
				case 'deleted':
					$class_name .= 'Deleted';
					break;

				case 'moderation':
				case 'visible':
				default:
					if ($response['blogtrackbackid'])
					{
						$class_name .= 'Trackback';
					}
					else
					{
						$class_name .= $type;
					}
			}
		}

		/* Needs hooks */
		if (class_exists($class_name, false))
		{
			return new $class_name($this->registry, $this, $this->bbcode, $this->bloginfo, $response);
		}
		else
		{
			trigger_error('vB_Blog_ResponseFactory::create(): Invalid type.', E_USER_ERROR);
		}
	}
}

/**
* Generic blog response class.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_Response
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
	* @var	vB_Blog_ResponseFactory
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
	* Information about the blog this response belongs to
	*
	* @var	array
	*/
	var $bloginfo = array();

	/**
	* Information about this response
	*
	* @var	array
	*/
	var $response = array();

	/**
	* Information about the pageinfo for {vb:link}
	*
	* @var	array
	*/
	var $pageinfo = array();

	/**
	* Variable which identifies if the data should be cached
	*
	* @var	boolean
	*/
	var $cachable = true;

	/**
	* Comment template needs linking back to its owner since it is being used outside of a specific post
	*
	* @var	boolean
	*/
	var $linkblog = false;

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = '';

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Blog_ResponseFactory
	* @param	array			Blog info
	* @param	array			Response info
	*/
	function vB_Blog_Response(&$registry, &$factory, &$bbcode, $bloginfo, $response)
	{
		if (!is_subclass_of($this, 'vB_Blog_Response'))
		{
			trigger_error('Direct instantiation of vB_Blog_Response class prohibited. Use the vB_Blog_ResponseFactory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;

		$this->bloginfo = $bloginfo;
		$this->response = $response;
	}

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		global $vbulletin;

		($hook = vBulletinHook::fetch_hook('blog_comment_display_start')) ? eval($hook) : false;
		// preparation for display...
		$this->prepare_start();

		if ($this->response['userid'])
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
		$this->prepare_end();

		// actual display...
		$bloginfo =& $this->bloginfo;
		$response =& $this->response;
		if (defined('VB_API') AND VB_API === true)
		{
			$response['message_plain'] = build_message_plain($response['pagetext']);
			$response['message_bbcode'] = $response['pagetext'];
		}

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;

		($hook = vBulletinHook::fetch_hook('blog_comment_display_complete')) ? eval($hook) : false;

		$this->response['blogtitle'] = $this->bloginfo['title'];

		$pageinfo_ip = array(
			'do' => 'viewip',
			'bt' => $this->response['blogtextid'],
		);

		$templater = vB_Template::create($this->template);
		$templater->register('response', $response);
		$templater->register('pageinfo', $this->pageinfo);
		$templater->register('pageinfo_ip', $pageinfo_ip);
		$templater->register('avatarenabled', array("avatarenabled" => $this->registry->options['avatarenabled']));
		$templater->register('avatar_user_permission', array("avatar_user_permission" => (($this->registry->userinfo['showavatars'] == 0)? false : true)));

		if ($vbulletin->products['vbcms'])
		{
			if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
			{
				require_once DIR . '/packages/vbcms/permissions.php';
				vBCMS_Permissions::getUserPerms();
			}

			if (count(vB::$vbulletin->userinfo['permissions']['cms']['cancreate']))
			{
				$templater->register('promote_sectionid', vB::$vbulletin->userinfo['permissions']['cms']['canpublish'][0]);
				$templater->register('articletypeid', vB_Types::instance()->getContentTypeID('vBCms_Article'));
				$query = 'contenttypeid='. vB_Types::instance()->getContentTypeID('vBCms_Article') .
					'&amp;blogid' . $blog['blogid'] . '&amp;parentid=1';
				$promote_url = vB_Route::create('vBCms_Route_Content', '1/addcontent/')->getCurrentURL(null, null, $query);
				$templater->register('promote_url', $promote_url);
			}
		}


		return $templater->render(($this->registry->GPC['ajax']));
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{

		$this->response = array_merge($this->response, convert_bits_to_array($this->response['options'], $this->registry->bf_misc_useroptions));
		$this->response = array_merge($this->response, convert_bits_to_array($this->response['adminoptions'], $this->registry->bf_misc_adminoptions));
		$this->response = array_merge($this->response, convert_bits_to_array($this->response['blogoptions'], $this->registry->bf_misc_vbblogoptions));

		$this->response['checkbox_value'] = 0;
		$this->response['checkbox_value'] += ($this->response['state'] == 'moderation') ? POST_FLAG_INVISIBLE : 0;
		$this->response['checkbox_value'] += ($this->response['state'] == 'deleted') ? POST_FLAG_DELETED : 0;
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->response);

		$this->response['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->response['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->response['lastvisit'] != $this->response['lastactivity'])
		{
			if ($this->response['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->response['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->response['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->response['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->response['userid']}"]))
		{
			$this->factory->perm_cache["{$this->response['userid']}"] = cache_permissions($this->response, false);
		}
		else
		{
			$this->response['permissions'] =& $this->factory->perm_cache["{$this->response['userid']}"];
		}

		// get avatar
		if ($this->response['avatarid'])
		{
			$this->response['avatarurl'] = $this->response['avatarpath'];
		}
		else
		{
			if ($this->response['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$this->response['avatarurl'] = $this->registry->options['avatarurl'] . '/thumbs/avatar' . $this->response['userid'] . '_' . $this->response['avatarrevision'] . '.gif';
				}
				else
				{
					$this->response['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->response['userid'] . '&amp;dateline=' . $this->response['avatardateline'] . '&amp;type=thumb';
				}
				if ($this->response['avwidth'] AND $this->response['avheight'])
				{
					$this->response['avwidth'] = 'width="' . $this->response['avwidth'] . '"';
					$this->response['avheight'] = 'height="' . $this->response['avheight'] . '"';
				}
				else
				{
					$this->response['avwidth'] = '';
					$this->response['avheight'] = '';
				}
			}
			else
			{
				$this->response['avatarurl'] = '';
			}
		}

		if ( // no avatar defined for this user
			empty($this->response['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->response['avatarid'] AND !($this->factory->perm_cache["{$this->response['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->response['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}
 
		$show['emaillink'] = (
			$this->response['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
		);
		$show['homepage'] = ($this->response['homepage'] != '' AND $this->response['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->response['receivepm'] AND $this->factory->perm_cache["{$this->bloginfo['userid']}"]['pmquota'])
	 				)) ? true : false;
	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		global $show;

		$this->response['rank'] = '';
		$this->response['notesperday'] = 0;
		$this->response['displaygroupid'] = 1;
		$this->response['username'] = $this->response['postusername'];
		fetch_musername($this->response);
		//$this->response['usertitle'] = $vbphrase['guest'];
		$this->response['usertitle'] =& $this->registry->usergroupcache["0"]['usertitle'];
		$this->response['joindate'] = '';
		$this->response['notes'] = 'n/a';
		$this->response['avatar'] = '';
		$this->response['profile'] = '';
		$this->response['email'] = '';
		$this->response['useremail'] = '';
		$this->response['icqicon'] = '';
		$this->response['aimicon'] = '';
		$this->response['yahooicon'] = '';
		$this->response['msnicon'] = '';
		$this->response['skypeicon'] = '';
		$this->response['homepage'] = '';
		$this->response['findnotes'] = '';
		$this->response['signature'] = '';
		$this->response['reputationdisplay'] = '';
		$this->response['onlinestatus'] = '';

		$show['avatar'] = false;
		if (!isset($this->factory->perm_cache["{$this->response['userid']}"]))
		{
			$this->factory->perm_cache["{$this->response['userid']}"] = cache_permissions($this->response, false);
		}
		else
		{
			$this->response['permissions'] =& $this->factory->perm_cache["{$this->response['userid']}"];
		}
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		$this->bbcode->set_parse_userinfo($this->response, $this->factory->perm_cache["{$this->response['userid']}"]);
		$this->response['message'] = $this->bbcode->parse(
			$this->response['pagetext'],
			($this->bloginfo['firstblogtextid'] == $this->response['blogtextid']) ? 'blog_entry' : 'blog_comment',
			$this->response['allowsmilie'], // fix
			false,
			$this->response['pagetexthtml'], // fix
			$this->response['hasimages'], // fix
			$this->cachable
		);
		$this->parsed_cache =& $this->bbcode->cached;
		$this->readmore = ($this->bbcode->createdsnippet);
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;

		global $onload, $blogtextid;

		if ($this->registry->options['logip'] AND $this->response['blogipaddress'] AND (can_moderate_blog('canviewips') OR $this->registry->options['logip'] == 2))
		{
			$this->response['blogipaddress'] = htmlspecialchars_uni(long2ip($this->response['blogipaddress']));
		}
		else
		{
			$this->response['blogipaddress'] = '';
		}

		if ($blogtextid AND $this->response['blogtextid'] == $blogtextid)
		{
			$onload = " onload=\"if (is_ie || is_moz) { fetch_object('comment_" . $blogtextid . "').scrollIntoView(true); }\"";
		}

		$show['linkblog'] = ($this->linkblog);
		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}

	function process_date_status()
	{
		global $vbphrase;

		if (!empty($this->bloginfo))
		{
			if (isset($this->bloginfo['blogview']))
			{
				$lastvisit = $this->bloginfo['blogview'];
			}
			else if ($this->registry->userinfo['userid'] AND $vbulletin->options['threadmarking'])
			{
				$blogview = max($this->bloginfo['blogread'], $this->bloginfo['bloguserread'], TIMENOW - ($this->registry->options['markinglimit'] * 86400));
				$lastvisit = $this->bloginfo['blogview'] = intval($blogview);
			}
			else
			{
				$blogview = max(fetch_bbarray_cookie('blog_lastview', $this->bloginfo['blogid']), fetch_bbarray_cookie('blog_userread', $this->bloginfo['userid']), $this->registry->userinfo['lastvisit']);
				$lastvisit = intval($blogview);
			}
		}
		else
		{
			$lastvisit = $this->registry->userinfo['lastvisit'];
		}

		if ($this->response['dateline'] > $lastvisit)
		{
			$this->response['statusicon'] = 'new';
			$this->response['statustitle'] = $vbphrase['new_comment'];
		}
		else
		{
			$this->response['statusicon'] = 'old';
			$this->response['statustitle'] = $vbphrase['old_comment'];
		}

		$this->response['date'] = vbdate($this->registry->options['dateformat'], $this->response['dateline'], true);
		$this->response['time'] = vbdate($this->registry->options['timeformat'], $this->response['dateline']);
	}

	function process_display()
	{
		global $show;

		if (empty($this->bloginfo))
		{
			if ($this->factory->blog_cache["{$this->response['blogid']}"])
			{
				$this->bloginfo = $this->factory->blog_cache["{$this->response['blogid']}"];
			}
			else
			{
				$this->bloginfo = array(
					'blogid'             => $this->response['blogid'],
					'userid'             => $this->response['blog_userid'],
					'usergroupid'        => $this->response['blog_usergroupid'],
					'infractiongroupids' => $this->response['blog_infractiongroupids'],
					'membergroupids'     => $this->response['blog_membergroupids'],
					'memberids'          => $this->response['memberids'],
					'memberblogids'      => $this->response['memberblogids'],
					'postedby_userid'    => $this->response['postedby_userid'],
					'postedby_username'  => $this->response['postedby_username'],
					'grouppermissions'   => $this->response['grouppermissions'],
					'membermoderate'     => $this->response['membermoderate'],
					'allowcomments'      => $this->response['allowcomments'],
					'state'              => $this->response['blog_state'],
					'pending'            => $this->response['pending'],
				);

				if (!isset($this->factory->perm_cache_blog["{$this->bloginfo['userid']}"]))
				{
					$this->factory->perm_cache_blog["{$this->bloginfo['userid']}"] = cache_permissions($this->bloginfo, false);
				}
				else
				{
					$this->bloginfo['permissions'] =& $this->factory->perm_cache_blog["{$this->bloginfo['userid']}"];
				}

				foreach ($this->registry->bf_misc_vbblogsocnetoptions AS $optionname => $optionval)
				{

					if ($this->response['private'])
					{
						$this->bloginfo["guest_$optionname"] = false;
						$this->bloginfo["ignore_$optionname"] = false;
						$this->bloginfo["member_$optionname"] = false;
					}
					else
					{
						$this->bloginfo["member_$optionname"] = ($this->response['options_member'] & $optionval ? 1 : 0);
						$this->bloginfo["guest_$optionname"] = ($this->response['options_guest'] & $optionval ? 1 : 0);
						$this->bloginfo["ignore_$optionname"] = ($this->response['options_ignore'] & $optionval ? 1 : 0);
					}
					$this->bloginfo["buddy_$optionname"] = ($this->response['options_buddy'] & $optionval ? 1 : 0);

					$this->bloginfo["$optionname"] = (
						(
							(
								!$this->response['buddyid']
									OR
								$this->bloginfo["buddy_$optionname"]
							)
							AND
							(
								!$this->response['ignoreid']
									OR
								$this->bloginfo["ignore_$optionname"]
							)
							AND
							(
								(
									$this->bloginfo["member_$optionname"]
										AND
									$this->registry->userinfo['userid']
								)
								OR
								(
									$this->bloginfo["guest_$optionname"]
										AND
									!$this->registry->userinfo['userid']
								)
							)
						)
						OR
						(
							$this->bloginfo["ignore_$optionname"]
								AND
							$this->response['ignoreid']
						)
						OR
						(
							$this->bloginfo["buddy_$optionname"]
								AND
							$this->response['buddyid']
						)
						OR
							is_member_of_blog($this->registry->userinfo, $this->bloginfo)
						OR
							can_moderate_blog()
					) ? true : false;
				}

				$this->factory->blog_cache["{$this->response['blogid']}"] = $this->bloginfo;
			}
		}

		$show['quotecomment'] = fetch_can_comment($this->bloginfo, $this->registry->userinfo);
		$show['entryposter'] = ($this->userinfo AND $this->response['userid'] == $this->bloginfo['postedby_userid']);
		$show['moderation'] = ($this->response['state'] == 'moderation');
		$show['private'] = false;
		if ($this->response['private'])
		{
			$show['private'] = true;
		}
		else if (can_moderate() AND $this->response['blog_userid'] != $this->registry->userinfo['userid'])
		{
			$membercanview = $this->response['options_member'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $this->response['options_buddy'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];

			if (!$membercanview AND (!$this->response['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		$show['edit'] = fetch_comment_perm('caneditcomments', $this->bloginfo, $this->response);
		$show['inlinemod'] = (
			(
				fetch_comment_perm('canremovecomments', $this->bloginfo)
					OR
				fetch_comment_perm('candeletecomments', $this->bloginfo)
					OR
				fetch_comment_perm('canmoderatecomments', $this->bloginfo)
					OR
				fetch_comment_perm('canundeletecomments', $this->bloginfo)
			)
				AND
			(
				can_moderate_blog()
					OR
				(
					!empty($this->userinfo)
						AND
					is_member_of_blog($this->registry->userinfo, $this->userinfo)
				)
			)
		);

		if ($this->response['edit_userid'])
		{
			$this->response['edit_date'] = vbdate($this->registry->options['dateformat'], $this->response['edit_dateline'], true);
			$this->response['edit_time'] = vbdate($this->registry->options['timeformat'], $this->response['edit_dateline']);
			if ($this->response['edit_reason'])
			{
				$this->response['edit_reason'] = fetch_word_wrapped_string($this->response['edit_reason']);
			}
			$show['commentedited'] = true;
		}
		else
		{
			$show['commentedited'] = false;
		}

	}
}

class vB_Blog_Response_Ignore extends vB_Blog_Response
{
	var $template = 'blog_comment_ignore';

	function construct()
	{
		$this->pageinfo = array(
			'bt' => $this->response['blogtextid'],
			'uh' => 1
		);

		return parent::construct();
	}
}

class vB_Blog_Response_Deleted extends vB_Blog_Response
{
	var $template = 'blog_comment_deleted';

	function construct()
	{
		$this->pageinfo = array(
			'bt' => $this->response['blogtextid'],
			'uh' => 1
		);

		return parent::construct();
	}
}

class vB_Blog_Response_Comment extends vB_Blog_Response
{
	var $template = 'blog_comment';
}

class vB_Blog_Response_Comment_Profile extends vB_Blog_Response
{
	var $template = 'blog_comment_profile';

	function construct()
	{
		$this->pageinfo = array(
			'bt' => $this->response['blogtextid'],
		);

		return parent::construct();
	}
}

class vB_Blog_Response_Trackback extends vB_Blog_Response
{
	var $template = 'blog_trackback';

	function process_registered_user() {}
	function process_unregistered_user() {}

	function process_display()
	{
		global $show;

		parent::process_display();

		$show['edit_trackback'] = fetch_comment_perm('caneditcomments', $this->bloginfo, $this->response);
		$show['inlinemod_trackback'] = (
			fetch_comment_perm('canremovecomments', $this->bloginfo)
				OR
			fetch_comment_perm('candeletecomments', $this->bloginfo)
				OR
			fetch_comment_perm('canmoderatecomments', $this->bloginfo)
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 63231 $
|| ####################################################################
\*======================================================================*/
?>
