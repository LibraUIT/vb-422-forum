<?php
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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
require_once(DIR . '/includes/blog_functions.php');

/**
* Class for displaying a vBulletin blog entry attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Single_vBBlog_BlogEntry extends vB_Attachment_Display_Single
{
	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	public function verify_attachment()
	{
		// Verification routines
		$selectsql = array(
			"blog.blogid, blog.pending, blog.postedby_userid, blog.state AS blog_state",
			"bu.memberids, bu.memberblogids",
			"user.usergroupid, user.membergroupids, user.infractiongroupids",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (a.contentid = blog.blogid)",
			"LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = a.userid)",
			"INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = a.userid)",
		);
		if ($this->registry->userinfo['userid'])
		{
			$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "blog_groupmembership AS gm ON (blog.userid = gm.bloguserid AND gm.userid = " . $this->registry->userinfo['userid'] . ")";
		}

		$wheresql = array();

		prepare_blog_category_permissions($this->registry->userinfo, true);
		if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']))
		{
			$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . "))";
			if ($this->registry->userinfo['userid'])
			{
				$wheresql[] = "(cu.blogcategoryid IS NULL OR blog.userid = " . $this->registry->userinfo['userid'] . ")";
			}
			else
			{
				$wheresql[] = "cu.blogcategoryid IS NULL";
			}
		}

		if (!can_moderate_blog())
		{
			if ($this->registry->userinfo['userid'])
			{
				if (!$this->registry->userinfo['memberblogids'])
				{
					$mb = $this->registry->db->query_first("
						SELECT
							memberblogids, memberids
						FROM " . TABLE_PREFIX . "blog_user
						WHERE
							bloguserid = {$this->registry->userinfo['userid']}
					");
					$this->registry->userinfo['memberblogids'] = $mb['memberblogids'] ? $mb['memberblogids'] : $this->registry->userinfo['userid'];
					$this->registry->userinfo['memberids'] = $mb ? $mb['memberids'] : $this->registry->userinfo['userid'];
				}

				$userlist_sql = array();
				$userlist_sql[] = "a.userid IN (" . $this->registry->userinfo['memberblogids'] . ")";
				$userlist_sql[] = "(options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_member & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
				$wheresql[] = "(" . implode(" OR ", $userlist_sql) . ")";

				$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = bu.bloguserid AND buddy.relationid = " . $this->registry->userinfo['userid'] . " AND buddy.type = 'buddy')";
				$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = bu.bloguserid AND ignored.relationid = " . $this->registry->userinfo['userid'] . " AND ignored.type = 'ignore')";

				$wheresql[] = "
					(
					a.userid IN (" .  $this->registry->userinfo['memberblogids'] . ")
						OR
					~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'] . "
						OR
					(options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL))";
			}
			else
			{
				$wheresql[] = "options_guest & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
				$wheresql[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
			}
		}

		if (!$this->verify_attachment_specific('vBBlog_BlogEntry', $selectsql, $joinsql, $wheresql))
		{
			return false;
		}

		$this->browsinginfo = array(
			'bloginfo' => array(
				'blogid' => $this->attachmentinfo['blogid'],
			),
			'userinfo' => array(
				'userid' => $this->attachmentinfo['userid'],
			),
		);

		cache_permissions($this->attachmentinfo, false);

		if ($this->attachmentinfo['contentid'] == 0)
		{
			if (!is_member_of_blog($this->registry->userinfo, $this->attachmentinfo) AND !can_moderate_blog('caneditentries'))
			{
				return false;
			}
		}
		else
		{
			# Block attachments belonging to soft deleted entries
			if (!can_moderate_blog() AND $this->attachmentinfo['blog_state'] == 'deleted')
			{
				return false;
			}

			# Block attachments belonging to moderated entries
			if (!can_moderate_blog('canmoderateentries') AND $this->attachmentinfo['blog_state'] == 'moderated')
			{
				return false;
			}

			# Block attachments belonging to draft entries if you are not the user
			if (
				($this->attachmentinfo['blog_state'] == 'draft' OR $this->attachmentinfo['pending'])
					AND
				!is_member_of_blog($this->registry->userinfo, $this->attachmentinfo)
			)
			{
				return false;
			}

			if (
				!($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_cangetattach'])
					OR
				(
					!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown'])
						AND
					$this->attachmentinfo['userid'] == $this->registry->userinfo['userid']
				)
					OR
				(
					!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers'])
						AND
					$this->attachmentinfo['userid'] != $this->registry->userinfo['userid']
				)
			)
			{
				return false;
			}

			if ($this->attachmentinfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries') AND !is_member_of_blog($this->registry->userinfo, $this->attachmentinfo))
			{
				return false;
			}
		}
		return true;
	}
}

/**
* Class for display of multiple vBulletin blog entry attachments
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Multiple_vBBlog_BlogEntry extends vB_Attachment_Display_Multiple
{
	/**
	* Constructor
	*
	* @param	vB_Registry
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid)
	{
		parent::__construct($registry);
		$this->contenttypeid = $contenttypeid;
	}

	/**
	* Return content specific information that relates to the ownership of attachments
	*
	* @param	array		List of attachmentids to query
	*
	* @return	void
	*/
	public function fetch_sql($attachmentids)
	{
		$selectsql = array(
			"blog.blogid, blog.title, blog.dateline, blog.userid, blog.postedby_userid, blog.postedby_username, blog.dateline AS b_dateline",
			"IF(bu.title <> '', bu.title, user.username) AS blogtitle",
			"user.username, user.membergroupids, user.usergroupid, user.infractiongroupids",
			"gm.permissions AS grouppermissions",
			"bu.memberids, bu.memberblogids",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = a.contentid)",
			"INNER JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
			"LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = blog.userid)",
			"LEFT JOIN " . TABLE_PREFIX . "blog_groupmembership AS gm ON (blog.userid = gm.bloguserid AND gm.userid = " . $this->registry->userinfo['userid'] . ")",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	* Fetches the SQL to be queried as part of a UNION ALL of an attachment query, verifying read permissions
	*
	* @param	string	SQL WHERE criteria
	* @param	string	Contents of the SELECT portion of the main query
	*
	* @return	string
	*/
	protected function fetch_sql_ids($criteria, $selectfields)
	{
		$subwheresql = array(
			"a.contentid <> 0",
		);
		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (a.contentid = blog.blogid)",
			"LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = blog.userid)",
			"INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog.userid)",
		);

		prepare_blog_category_permissions($this->registry->userinfo, true);
		if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']))
		{
			$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . "))";
			if ($this->registry->userinfo['userid'])
			{
				$subwheresql[] = "(cu.blogcategoryid IS NULL OR blog.userid = " . $this->registry->userinfo['userid'] . ")";
			}
			else
			{
				$subwheresql[] = "cu.blogcategoryid IS NULL";
			}
		}

		if ($this->registry->userinfo['userid'])
		{
			if (!$this->registry->userinfo['memberblogids'])
			{
				$mb = $this->registry->db->query_first("
					SELECT
						memberblogids, memberids
					FROM " . TABLE_PREFIX . "blog_user
					WHERE
						bloguserid = {$this->registry->userinfo['userid']}
				");
				$this->registry->userinfo['memberblogids'] = $mb['memberblogids'] ? $mb['memberblogids'] : $this->registry->userinfo['userid'];
				$this->registry->userinfo['memberids'] = $mb ? $mb['memberids'] : $this->registry->userinfo['userid'];
			}
		}
		else
		{
			$this->registry->userinfo['memberblogids'] = 0;
			$this->registry->userinfo['memberblogids'] = 0;
		}

		if (!can_moderate_blog())
		{
			if ($this->registry->userinfo['userid'])
			{
				$userlist_sql = array();
				$userlist_sql[] = "a.userid = " . $this->registry->userinfo['userid'];
				$userlist_sql[] = "blog.userid IN (" . $this->registry->userinfo['memberblogids'] . ")";
				$userlist_sql[] = "(options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_member & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
				$subwheresql[] = "(" . implode(" OR ", $userlist_sql) . ")";

				$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = bu.bloguserid AND buddy.relationid = " . $this->registry->userinfo['userid'] . " AND buddy.type = 'buddy')";
				$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = bu.bloguserid AND ignored.relationid = " . $this->registry->userinfo['userid'] . " AND ignored.type = 'ignore')";

				$subwheresql[] = "
					(
						a.userid = " . $this->registry->userinfo['userid'] . "
							OR
						blog.userid IN (" . $this->registry->userinfo['memberblogids'] . ")
							OR
						~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'] . "
							OR
						(options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)
					)";
			}
			else
			{
				$subwheresql[] = "options_guest & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
				$subwheresql[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
			}
			$subwheresql[] = "blog.state <> 'deleted'";
		}

		if (!can_moderate_blog('canmoderateentries'))
		{
			$subwheresql[] = "blog.state <> 'moderation'";
		}

		$subwheresql[] = "
			(
				(
					blog.state <> 'draft'
						AND
					blog.pending = 0
				)
				OR
					blog.userid IN (" . $this->registry->userinfo['memberblogids'] . ")
			)
		";

		if (!can_moderate_blog('canmoderateentries'))
		{
			$subwheresql[] = "
				(
					a.state <> 'moderation'
						OR
					blog.userid IN (" . $this->registry->userinfo['memberblogids'] . ")
				)
			";
		}

		if (!($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_cangetattach']))
		{
			$subwheresql[] = "1 = 2";
		}

		if (!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{
			$subwheresql[] = "a.userid = {$this->registry->userinfo['userid']}";
		}

		if (!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			$subwheresql[] = "a.userid <> {$this->registry->userinfo['userid']}";
		}

		if ($this->registry->userinfo['userid'])
		{
			$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "blog_groupmembership AS gm ON (blog.userid = gm.bloguserid AND gm.userid = " . $this->registry->userinfo['userid'] . ")";
		}

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	* Formats $bloginfo content for display
	*
	* @param	array		Entry information
	*
	* @return	array
	*/
	public function process_attachment_template($bloginfo, $showthumbs = false)
	{
		global $show, $vbphrase;

		cache_permissions($bloginfo, false);

		$show['thumbnail'] = ($bloginfo['hasthumbnail'] == 1 AND $this->registry->options['attachthumbs'] AND $showthumbs);
		$show['inprogress'] = $bloginfo['inprogress'];

		$show['candelete'] = false;
		if ($bloginfo['inprogress'] OR fetch_entry_perm('edit', $bloginfo))
		{
			$show['candelete'] = true;
		}

		$blog = array(
			'blogid' => $bloginfo['blogid'],
			'title'  => $bloginfo['title'],
		);
		$pageinfo = array(
			'p'        => $bloginfo['contentid'],
		);

		return array(
			'template' => 'entry',
			'blog'     => $blog,
			'entry'    => $bloginfo,
		  'pageinfo' => $pageinfo,
		);
	}

	/**
	* Return blog entry specific url to the owner an attachment
	*
	* @param	array		Content information
	*
	* @return	string
	*/
	protected function fetch_content_url_instance($contentinfo)
	{
		return fetch_seo_url('entry', $contentinfo);
	}
}

// #######################################################################
// ############################# STORAGE #################################
// #######################################################################

/**
* Class for storing a vBulletin blog entry attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Store_vBBlog_BlogEntry extends vB_Attachment_Store
{
	/**
	*	Bloginfo
	*
	* @var	array
	*/
	protected $bloginfo = array();

	/**
	* Given an attachmentid, retrieve values that verify_permissions needs
	*
	* @param	int	Attachmentid
	*
	* @return	array
	*/
	public function fetch_associated_contentinfo($attachmentid)
	{
		return $this->registry->db->query_first("
			SELECT
				b.blogid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "blog AS b ON (b.blogid = a.contentid)
			WHERE
				a.attachmentid = " . intval($attachmentid) . "
		");
	}

	/**
	* Verifies permissions to attach content to entries
	*
	* @param	array	Contenttype information - bypass reading environment settings
	*
	* @return	boolean
	*/
	public function verify_permissions($info = array())
	{
		global $show;

		if ($info['blogid'])
		{
			$this->values['blogid'] = $info['blogid'];
		}
		else
		{
			$this->values['blogid'] = intval($this->values['b']) ? intval($this->values['b']) : intval($this->values['blogid']);
		}

		if (!($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_canpostattach']))
		{
			return false;
		}

		if ($this->values['blogid'])
		{
			if (!($this->bloginfo = verify_blog($this->values['blogid'], false, 'modifychild')))
			{
				return false;
			}
			$this->contentid = $this->bloginfo['blogid'];
			$this->userinfo = fetch_userinfo($this->bloginfo['userid']);
			cache_permissions($this->userinfo);
		}
		else
		{
			if ($userid = intval($this->values['u']) AND $userinfo = fetch_userinfo($userid))
			{
				$this->userinfo = $userinfo;
				cache_permissions($this->userinfo);
			}
			else
			{
				$this->userinfo = $this->registry->userinfo;
			}
		}

		return true;
	}

	/**
	* Verifies permissions to attach content to posts
	*
	* @param	object	vB_Upload
	* @param	array		Information about uploaded attachment
	*
	* @return	integer
	*/
	protected function process_upload($upload, $attachment, $imageonly = false)
	{
		if (
			($attachmentid = parent::process_upload($upload, $attachment, $imageonly))
				AND
			$this->registry->userinfo['userid'] != $this->bloginfo['userid']
				AND
			can_moderate_blog('caneditcomments')
		)
		{
			$this->bloginfo['attachmentid'] = $attachmentid;
			require_once(DIR . '/includes/blog_functions_log_error.php');
			log_moderator_action($this->bloginfo, 'attachment_uploaded');
		}

		return $attachmentid;
	}
}

/**
* Class for deleting a vBulletin blog entry attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Dm_vBBlog_BlogEntry extends vB_Attachment_Dm
{
	/**
	* pre_approve function - extend if the contenttype needs to do anything
	*
	* @param	array		list of moderated attachment ids to approve
	* @param	boolean	verify permission to approve
	*
	* @return	boolean
	*/
	public function pre_approve($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'bloglist'   => array(),
		);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		// Blog attachments are not ever set moderated as far as I can see
		return can_moderate_blog();
	}

	/**
	* pre_delete function - extend if the contenttype needs to do anything
	*
	* @param	array		list of deleted attachment ids to delete
	* @param	boolean	verify permission to delete
	*
	* @return	boolean
	*/
	public function pre_delete($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'bloglist'   => array(),
		);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		$replaced = array();
		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress,
				blog.blogid, blog.firstblogtextid, blog.dateline AS blog_dateline, blog.state, blog.postedby_userid,
				bu.memberids, bu.memberblogids,
				gm.permissions AS grouppermissions,
				user.membergroupids, user.usergroupid, user.infractiongroupids,
				blog_deletionlog.moddelete AS del_moddelete, blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = a.contentid)
			LEFT JOIN " . TABLE_PREFIX . "blog_user AS bu ON (bu.bloguserid = blog.userid)
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_groupmembership AS gm ON (blog.userid = gm.bloguserid AND gm.userid = " . $this->registry->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')
			WHERE
				a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			cache_permissions($id, false);
			if ($checkperms AND !$id['inprogress'] AND !fetch_entry_perm('edit', $id))
			{
				return false;
			}

			if ($id['blogid'])
			{
				$this->lists['bloglist']["{$id['blogid']}"]++;

				if ($this->log)
				{
					if (($this->registry->userinfo['permissions']['genericoptions'] & $this->registry->bf_ugp_genericoptions['showeditedby']) AND $id['p_dateline'] < (TIMENOW - ($this->registry->options['noeditedbytime'] * 60)))
					{
						if (empty($replaced["$id[firstblogtextid]"]))
						{
							/*insert query*/
							$this->registry->db->query_write("
								REPLACE INTO " . TABLE_PREFIX . "blog_editlog
										(blogtextid, userid, username, dateline)
								VALUES
									(
										$id[firstblogtextid],
										" . $this->registry->userinfo['userid'] . ",
										'" . $this->registry->db->escape_string($this->registry->userinfo['username']) . "',
										" . TIMENOW . "
									)
							");
							$replaced["$id[firstblogtextid]"] = true;
						}
					}
					if (!is_member_of_blog($this->registry->userinfo, $id) AND can_moderate_blog('caneditentries'))
					{
						$bloginfo = array(
							'blogid'       => $id['blogid'],
							'attachmentid' => $id['attachmentid'],
						);
						require_once(DIR . '/includes/blog_functions_log_error.php');
						log_moderator_action($bloginfo, 'attachment_removed');
					}
				}
			}
		}
		return true;
	}

	/**
	* post_delete function - extend if the contenttype needs to do anything
	*
	* @param	$attachdm	added for PHP 5.4 strict standards compliance
	*
	* @return	void
	*/
	public function post_delete(&$attachdm = '')
	{
		// Update attach in the blog table
		if (!empty($this->lists['bloglist']))
		{
			$contenttypeid = vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry');

			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "blog AS b
				SET b.attach = COALESCE( (
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE
						b.blogid = a.contentid
							AND
						a.contenttypeid = $contenttypeid
					GROUP BY a.contentid
				),0)
				WHERE b.blogid IN (" . implode(", ", array_keys($this->lists['bloglist'])) . ")
			");
		}
	}
}

class vB_Attachment_Upload_Displaybit_vBBlog_BlogEntry extends vB_Attachment_Upload_Displaybit
{
	/**
	*	Parses the appropiate template for contenttype that is to be updated on the calling window during an upload
	*
	* @param	array	Attachment information
	* @param	array	Values array pertaining to contenttype
	* @param	boolean	Disable template comments
	*
	* @return	string
	*/
	public function process_display_template($attach, $values = array(), $disablecomment = false)
	{
		$attach['extension'] = strtolower(file_extension($attach['filename']));
		$attach['filename']  = fetch_censored_text(htmlspecialchars_uni($attach['filename'], false));
		$attach['filesize']  = vb_number_format($attach['filesize'], 1, true);
		$attach['imgpath']   = $this->fetch_imgpath($attach['extension']);

		$templater = vB_Template::create('newpost_attachmentbit');
			$templater->register('attach', $attach);
		return $templater->render($disablecomment);
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 29983 $
|| ####################################################################
\*======================================================================*/
?>
