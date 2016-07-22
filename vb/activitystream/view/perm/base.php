<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 4.2.2 - Nulled By VietVBB Team
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

abstract class vB_ActivityStream_View_Perm_Base
{
	/**
	 * Classes that must execute before this one
	 * Example. post->process() must execute before thread->process() so that threadids from the posts can
	 * be added to the thread content array.
	 *
	 * @var Array
	 */
	protected $requireFirst = array();

	/**
	 * Classes that are required for this one to work
	 * Example. The post class is going to generate threadids so the thread class must also be loaded, which it possibly
	 * wouldn't be if this stream had no threads (new thread), just posts (new reply).
	 *
	 * @var Array
	 */
	protected $requireExist = array();

	/*
	 *
	 */
	protected $content = array();

	/*
	 *
	 */
	protected $vbphrase = array();

	/**
	 * Processed users tracker
	 *
	 */
	protected $usersdone = false;

	/**
	 * Constructor - info about this content
	 *
	 * @param	array
	 *
	 */
	public function __construct(&$content, &$vbphrase)
	{
		$this->content =& $content;
		$this->vbphrase =& $vbphrase;
	}

	/**
	 * Retrieve data once contentids have been set
	 *
	 * At the end of the function, set the idlist to an empty array so that if further records
	 * are requested, the already retrieved ids won't be needlessy retrieved again
	 *
	 */
	public function process()
	{

	}

	/*
	 *
	 */
	public function setRequiredFirst($value)
	{
		$this->requireFirst[$value] = 1;
	}

	/*
	 *
	 */
	public function setRequiredExist($value)
	{
		$this->requireExist[$value] = 1;
	}

	/*
	 *
	 */
	public function fetchRequiredExist()
	{
		return $this->requireExist;
	}

	/*
	 * Fetch permission to view this activity
	 *
	 * return boolean
	 */

	public function fetchCanView($activity)
	{
		return true;
	}

	/*
	 *
	 *
	 */

	public function verifyRequiredFirst(&$classes, $done)
	{
		if (!$this->requireFirst)
		{
			return true;
		}
		else
		{
			$okay = true;
			$need = array();
			foreach ($this->requireFirst AS $classname => $null)
			{
				if (!$done[$classname] AND $classes[$classname])
				{
					$okay = false;
					$need[] = $classname;
				}
			}

			if (!$okay)
			{
				$this->sort($classes);
				return false;
			}
			else
			{
				return true;
			}
		}
	}

	public function sort(&$classes)
	{
		$thisclass = get_class($this);
		$new = array();
		foreach ($classes AS $classname => $object)
		{
			if ($new[$classname])
			{
				continue;
			}

			if ($classname == $thisclass)
			{
				foreach ($this->requireFirst AS $_classname => $null)
				{
					if (!$classes[$_classname])
					{
						continue;
					}
					$new[$_classname] = $classes[$_classname];
				}
				$new[$classname] = $object;
			}
			else
			{
				$new[$classname] = $object;
			}
		}
		$classes = $new;
	}

	/*
	 * Register Template
	 *
	 * @param	string	Template Name
	 * @param	array	Activity Record
	 *
	 * @return	string	Template
	 */
	abstract public function fetchTemplate($templatename, $activity, $skipgroup = false, $fetchphrase = false);

	public function fetchPhrase($templatename, $activity, $skipgroup = false)
	{
		return $this->fetchTemplate($templatename, $activity, $skipgroup, true);
	}

	protected function processRecord(&$activity)
	{
		return;
	}

	protected function parse_array($array, $index)
	{
		$return = array();
		$length = strlen($index);
		foreach($array AS $key => $value)
		{
			if (strpos($key, $index, 0) !== false)
			{
				$return[substr($key, $length)] = $value;
			}
		}

		return $return;
	}

	protected function processPicturecommentids()
	{
		if (!$this->content['picturecommentid'])
		{
			return true;
		}

		$comments = vB::$db->query_read_slave("
			SELECT
				p.commentid, p.postuserid, p.postuserid AS userid, p.postusername, p.state, p.title, p.dateline, p.pagetext,
				p.sourcecontentid, p.sourcecontenttypeid, p.postusername,
				a.attachmentid, a.dateline AS adateline, fd.thumbnail_width, fd.thumbnail_height, a.counter
			FROM " . TABLE_PREFIX . "picturecomment AS p
			INNER JOIN " . TABLE_PREFIX . "attachment AS a ON (a.attachmentid = p.sourceattachmentid)
			INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
			WHERE
				p.commentid IN (" . implode(",", array_keys($this->content['picturecommentid'])) . ")
					AND
				p.state <> 'deleted'
		");
		while ($comment = vB::$db->fetch_array($comments))
		{
			$this->content['userid'][$comment['postuserid']] = 1;
			$class  = vB_Types::instance()->getContentClassFromId($comment['sourcecontenttypeid']);
			if ($class['package'] = 'Forum' AND $class['class'] == 'SocialGroup')
			{
				$comment['groupid'] = $comment['sourcecontentid'];
				$this->content['socialgroup_picturecomment'][$comment['commentid']] = $comment;
				$this->content['socialgroup_attachmentid'][$comment['attachmentid']] = 1;
			}
			else if ($class['package'] = 'Forum' AND $class['class'] == 'Album')
			{
				$comment['albumid'] = $comment['sourcecontentid'];
				$this->content['album_picturecomment'][$comment['commentid']] = $comment;
				$this->content['album_attachmentid'][$comment['attachmentid']] = 1;
			}
		}

		// unset picture commentids so that these only get processed once, either by socialgroups or albums
		unset($this->content['picturecommentid']);
	}

	/*
	 * Why are we querying users directly and not joining in the other queries?
	 *
	 * Avatars are the main reason. If we join then it gets complicated with the
	 * cache system. We query all messages before discussions and group. The discussion and group belonging to the
	 * message is then cached so we don't have to query for them individually.
	 * Take a group message query. We have to join one for the message, once
	 * for the discussion, and once for the group.  All within the same query. This query
	 * is fast and, in the end, probably faster than joining in all of the other queries
	 * especially when some will be redundant.
	 *
	 */
	protected function processUsers()
	{
		if (!$this->content['userid'])
		{
			return;
		}

		/* This bit handles processUsers() being executed multiple times .. remove any users that we already know about
		 * and only query if we have any unknown users left
		 */
		if ($this->content['user'])
		{
			foreach ($this->content['user'] AS $userid => $foo)
			{
				unset($this->content['userid'][$userid]);
			}
			if (!$this->content['userid'])
			{
				return;
			}
		}

		require_once(DIR . '/includes/functions_user.php');
		$users = vB::$db->query_read("
			SELECT u.*
				" . (vB::$vbulletin->options['avatarenabled'] ? ", av.avatarpath, NOT ISNULL(cu.userid) AS hascustomavatar,
					cu.dateline AS avatardateline, cu.width AS avwidth, cu.height AS avheight, cu.height_thumb AS avheight_thumb,
					cu.width_thumb AS avwidth_thumb, NOT ISNULL(cu.filedata_thumb) AS filedata_thumb" : "") . "
					" . (vB::$vbulletin->userinfo['userid'] ? ",IF(userlist.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
			FROM " . TABLE_PREFIX . "user AS u
			" . (vB::$vbulletin->options['avatarenabled'] ? "
				LEFT JOIN " . TABLE_PREFIX . "avatar AS av ON(av.avatarid = u.avatarid)
				LEFT JOIN " . TABLE_PREFIX . "customavatar AS cu ON(cu.userid = u.userid)" : "") . "
				" . (vB::$vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.userid = u.userid AND userlist.type = 'buddy' AND userlist.relationid = " . vB::$vbulletin->userinfo['userid'] . ")" : "") . "
			WHERE u.userid IN (" . implode(",", array_keys($this->content['userid'])) . ")
		");
		while ($user = vB::$db->fetch_array($users))
		{
			$user = array_merge($user , convert_bits_to_array($user['options'], vB::$vbulletin->bf_misc_useroptions));
			fetch_avatar_from_userinfo($user, true);
			cache_permissions($user, false);

			if (
				empty($user['avatarurl'])
					OR
				(!$user['avatarid'] AND !($user['permissions']['genericpermissions'] & vB::$vbulletin->bf_ugp_genericpermissions['canuseavatar']) AND !$user['adminavatar'])
			)
			{
				$user['avatarurl'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
				$user['showavatar'] = true;
				$user['avatarclass'] = 'hasavatar';
			}
			else if (vB::$vbulletin->userinfo['userid'] AND !vB::$vbulletin->userinfo['showavatars'])
			{
				$user['showavatar'] = false;
				$user['avatarclass'] = 'noavatar';
			}
			else
			{
				$user['showavatar'] = true;
				$user['avatarclass'] = 'hasavatar';
			}

			$this->content['user'][$user['userid']] = $user;
		}

		if (!$this->content['user'])
		{
			$this->content['user'] = array();
		}

		/*
		 * Reset the array so that if processUsers() is executed again we won't query anything
		 * unless new ids have been added to the array
		 */
		$this->content['userid'] = array();
	}

	/*
	 * Retrieve a user from the cache
	 * If no user then send back some guest stuff
	 *
	 * @param	int		userid to grab
	 * @param	string	Alternate username to use if this is a guest
	 *
	 * @return	array	User info
	 */
	protected function fetchUser($userid, $username = '')
	{
		global $vbphrase;

		if ($this->content['user'][$userid])
		{	// avatar stuff set by processUsers()
			return $this->content['user'][$userid];
		}
		else	// Guest
		{
			$showavatar = ((!vB::$vbulletin->userinfo['userid'] OR vB::$vbulletin->userinfo['showavatars']) AND vB::$vbulletin->options['avatarenabled']);
			return array(
				'userid'      => 0,
				'username'    => $username ? $username : $vbphrase['unregistered'],
				'avatarurl'   => vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif',
				'showavatar'  => $showavatar,
				'avatarclass' => $showavatar ? 'hasavatar' : 'noavatar',
			);
		}
	}

	protected function fetchCanViewMembers()
	{
		return (vB::$vbulletin->userinfo['permissions']['genericpermissions'] & vB::$vbulletin->bf_ugp_genericpermissions['canviewmembers']);
	}

	protected function fetchCanViewVisitorMessage($vmid)
	{
		if (!($message = $this->content['visitormessage'][$vmid]))
		{
			return false;
		}

		if (!($userinfo = $this->content['user'][$message['userid']]))
		{
			return false;
		}

		if ($userinfo['usergroupid'] == 4 AND !(vB::$vbulletin->userinfo['permissions']['adminpermissions'] & vB::$vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			return false;
		}

		if ((
			$userinfo['vm_contactonly']
				AND
			!can_moderate(0,'canmoderatevisitormessages')
				AND
			$userinfo['userid'] != vB::$vbulletin->userinfo['userid']
				AND
			!$userinfo['bbuser_iscontact_of_user']
			)
				OR
			(
				!$userinfo['vm_enable']
					AND
				(
					!can_moderate(0,'canmoderatevisitormessages')
						OR
					vB::$vbulletin->userinfo['userid'] == $userinfo['userid']
				)
			)
		)
		{
			return false;
		}

		if (
			!$this->fetchCanViewMembers()
				OR
			!(vB::$vbulletin->userinfo['forumpermissions'] & vB::$vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			!(vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_visitor_messaging'])
		)
		{
			return false;
		}

		if (!$this->content['user'][$message['userid']])
		{
			return false;
		}

		if (!can_view_profile_section($message['userid'], 'visitor_messaging'))
		{
			return false;
		}

		require_once(DIR . '/includes/functions_visitormessage.php');

		if (
			$message['state'] == 'moderation'
				AND
			!fetch_visitor_message_perm('canmoderatevisitormessages', $this->content['user'][$message['userid']], $message)
				AND
			$message['postuserid'] != vB::$vbulletin->userinfo['userid']
		)
		{
			return false;
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
