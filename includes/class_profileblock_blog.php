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
* Class for Profile Blog Block
*
* @package vBulletin
*/
class vB_ProfileBlock_Blog extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'blog_member_block';

	/**
	* Whether or not the block is enabled
	* @param string added for PHP 5.4 strict standards compliance
	*
	* @return bool
	*/
	function block_is_enabled($id = '')
	{
		$continue = false;
		if ($this->profile->userinfo['canviewmyblog'] AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND ($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{ // Someone elses blog we can view
			$continue = true;
		}
		else if ($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'] AND ($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{ // Our own blog
			$continue = true;
		}

		if (!$continue)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		if (
			!empty($this->block_data['latestentries'])
				OR
			(
				(can_moderate_blog() OR $this->profile->userinfo['userid'] == $this->registry->userinfo['userid'])
					AND
				($this->profile->userinfo['blog_deleted'] OR $this->profile->userinfo['blog_moderation'])
			)
		)
		{
			return true;
		}
		return false;
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		if (!$this->registry->userinfo['userid'])
		{
			prepare_blog_category_permissions($this->registry->userinfo);
		}

		$show['lastentry'] = true;
		$this->block_data['entries'] = vb_number_format($this->profile->userinfo['entries']);

		$this->block_data['lastblogtitle'] = '';
		$this->block_data['lastblogdate'] = $vbphrase['never'];
		$this->block_data['lastblogtime'] = '';

		$memberblogs = explode(',', $this->profile->userinfo['memberblogids']);
		if (count($memberblogs) > 1)
		{
			$sqland = array(
				"bu.bloguserid IN (" . $this->profile->userinfo['memberblogids'] . ")"
			);

			if (!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
			{
				$sqland[] = "bu.bloguserid = " . $this->registry->userinfo['userid'];
			}
			if (!($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $this->registry->userinfo['userid'])
			{
				$sqland[] = "bu.bloguserid <> " . $this->registry->userinfo['userid'];
			}

			if (trim($this->registry->options['globalignore']) != '')
			{
				require_once(DIR . '/includes/functions_bigthree.php');
				if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
				{
					$sqland[] = "bu.bloguserid NOT IN ($coventry)";
				}
			}

			$sqlor = array();
			$sqljoin = array();
			if (!can_moderate_blog())
			{
				if ($this->registry->userinfo['userid'])
				{
					$sqlor[] = "bu.bloguserid IN (" . $this->registry->userinfo['memberblogids'] . ")";
					$sqlor[] = "(options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
					$sqlor[] = "(options_buddy & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
					$sqlor[] = "(options_member & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " .$this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
					$sqland[] = "(" . implode(" OR ", $sqlor) . ")";

					$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = bu.bloguserid AND buddy.relationid = " . $this->registry->userinfo['userid'] . " AND buddy.type = 'buddy')";
					$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = bu.bloguserid AND ignored.relationid = " . $this->registry->userinfo['userid'] . " AND ignored.type = 'ignore')";
				}
				else
				{
					$sqland[] = "options_guest & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
					$sqland[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
				}
			}

			if ($this->registry->userinfo['userid'] AND in_coventry($this->registry->userinfo['userid'], true))
			{
				$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastcomment, blog_tachyentry.lastcomment) AS lastcomment";
				$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastcommenter, blog_tachyentry.lastcommenter) AS lastcommenter";
				$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid) AS lastblogtextid";

				$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_tachyentry AS blog_tachyentry ON (blog_tachyentry.blogid = bu.lastblogid AND blog_tachyentry.userid = " . $this->registry->userinfo['userid'] . ")";
				$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid))";
			}
			else
			{
				$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = bu.lastblogtextid)";
			}

			$temp = $show['inlinemod'];
			$show['inlinemod'] = false;
			$blogs = $this->registry->db->query_read_slave("
				SELECT
					user.*,
					IF(bu.title <> '', bu.title, user.username) AS blogtitle, user.userid, user.username,
					bu.lastblog, bu.lastblogid AS lastblogid, bu.lastblogtitle,
					bu.lastcomment, bu.lastblogtextid AS lastblogtextid, bu.lastcommenter, bu.options_member, bu.options_buddy,
					bu.ratingnum, bu.ratingtotal, bu.title, bu.entries, bu.comments, bu.title, blog.categories,
					blog2.categories AS categories_lastcomment
				FROM " . TABLE_PREFIX . "blog_user AS bu
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = bu.bloguserid)
				LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = bu.lastblogid)
				" . (!empty($sqljoin) ? implode("\r\n", $sqljoin) : "") . "
				LEFT JOIN " . TABLE_PREFIX . "blog AS blog2 ON (blog2.blogid = blog_text.blogid)
				WHERE " . implode("\r\n\tAND ", $sqland) . "
			");
			while ($blog = $this->registry->db->fetch_array($blogs))
			{
				$blog = array_merge($blog, convert_bits_to_array($blog['options'], $this->registry->bf_misc_useroptions));
				$blog = array_merge($blog, convert_bits_to_array($blog['adminoptions'], $this->registry->bf_misc_adminoptions));

				$show['private'] = false;
				if (can_moderate() AND $blog['userid'] != $this->registry->userinfo['userid'])
				{
					$membercanview = $blog['options_member'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
					$buddiescanview = $blog['options_buddy'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
					if (!$membercanview AND (!$blog['buddyid'] OR !$buddiescanview))
					{
						$show['private'] = true;
					}
				}

				$blog['entries'] = vb_number_format($blog['entries']);
				$blog['comments'] = vb_number_format($blog['comments']);
				$blog['lastentrydate'] = vbdate($this->registry->options['dateformat'], $blog['lastblog'], true);
				$blog['lastentrytime'] = vbdate($this->registry->options['timeformat'], $blog['lastblog']);
				$blog['entrytitle'] = fetch_trimmed_title($blog['lastblogtitle'], 20);
				if ($blog['title'])
				{
					$blog['title'] = fetch_trimmed_title($blog['title'], 50);
				}
				$lastentrycats = explode(',', $blog['categories']);
				$lastcommentcats = explode(',', $blog['categories_lastcomment']);

				$show['lastentry'] = array_intersect($this->registry->userinfo['blogcategorypermissions']['cantview'], $lastentrycats) ? false : true;
				$show['lastcomment'] = array_intersect($this->registry->userinfo['blogcategorypermissions']['cantview'], $lastcommentcats) ? false : true;

				$templater = vB_Template::create('blog_blog_row');
					$templater->register('blog', $blog);
					$templater->register('thread', $thread);
				$groupbits .= $templater->render();
			}

			$this->block_data['groupblogs'] = $groupbits;
			$show['inlinemod'] = $temp;
		}

		if (!in_coventry($this->profile->userinfo['userid']) AND ($this->profile->userinfo['lastblog']))
		{
			$sql_and = array();
			$state = array('visible');

			$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
			$sql_and[] = "blog.dateline <= " . TIMENOW;
			$sql_and[] = "blog.pending = 0";
			$sql_and[] = "blog.userid = " . $this->profile->userinfo['userid'];

			if (!can_moderate_blog() AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND !$bloginfo['buddyid'])
			{
				$sql_and[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
			}

			if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']) AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'])
			{
				$joinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . "))";
				$sql_and[] = "cu.blogcategoryid IS NULL";
			}

			$blogids = array();
			$blogs = $this->registry->db->query_read_slave("
				SELECT blog.blogid, blog.attach
				FROM " . TABLE_PREFIX . "blog AS blog
				$joinsql
				WHERE " . implode("\r\n\tAND ", $sql_and) . "
				ORDER BY blog.dateline DESC
				LIMIT 5
			");
			while ($blog = $this->registry->db->fetch_array($blogs))
			{
				$blogids[] = $blog['blogid'];
				$attachcount += $blog['attach'];
			}

			if ($blogids)
			{

				// Query Attachments
				if ($attachcount)
				{
					require_once(DIR . '/packages/vbattach/attach.php');
					$attach = new vB_Attach_Display_Content($this->registry, 'vBBlog_BlogEntry');
					$postattach = $attach->fetch_postattach(0, $blogids);
				}

				$this->block_data['lastblogtitle'] = $this->profile->userinfo['lastblogtitle'];
				$this->block_data['lastblogdate'] = vbdate($this->registry->options['dateformat'], $this->profile->userinfo['lastblog']);
				$this->block_data['lastblogtime'] = vbdate($this->registry->options['timeformat'], $this->profile->userinfo['lastblog'], true);

				$categories = array();
				$cats = $this->registry->db->query_read_slave("
					SELECT blogid, title, blog_category.blogcategoryid, blog_categoryuser.userid, blog_category.userid AS creatorid
					FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
					LEFT JOIN " . TABLE_PREFIX . "blog_category AS blog_category ON (blog_category.blogcategoryid = blog_categoryuser.blogcategoryid)
					WHERE blogid IN (" . implode(',', $blogids) . ")
					ORDER BY blogid, displayorder
				");
				while ($cat = $this->registry->db->fetch_array($cats))
				{
					$categories["$cat[blogid]"][] = $cat;
				}

				require_once(DIR . '/includes/class_bbcode_blog.php');
				require_once(DIR . '/includes/class_blog_entry.php');

				$bbcode = new vB_BbCodeParser_Blog_Snippet($this->registry, fetch_tag_list());
				$factory = new vB_Blog_EntryFactory($this->registry, $bbcode, $categories);

				$first = true;
				// Last Five Entries
				$entries = $this->registry->db->query_read_slave("
					SELECT blog.*, blog.options AS blogoptions, blog_text.pagetext, blog_text.allowsmilie, blog_text.ipaddress, blog_text.reportthreadid,
						blog_text.ipaddress AS blogipaddress,
						blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username,
						user.*, userfield.*, usertextfield.*,
						IF(blog_user.title <> '', blog_user.title, user.username) AS blogtitle
						" . (($this->registry->options['threadvoted'] AND $this->registry->userinfo['userid']) ? ', blog_rate.vote' : '') . "
						" . (!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehiddencustomfields']) ? $this->registry->profilefield['hidden'] : "") . "
						" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime  AS bloguserread" : "") . "
					FROM " . TABLE_PREFIX . "blog AS blog
					INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
					INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog.userid = blog_user.bloguserid)
					LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog.firstblogtextid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
					LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
					LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
					" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? "
					LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $this->registry->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $this->registry->userinfo['userid'] . ")
					" : "") . "
					" . (($this->registry->options['threadvoted'] AND $this->registry->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "blog_rate AS blog_rate ON (blog_rate.blogid = blog.blogid AND blog_rate.userid = " . $this->registry->userinfo['userid'] . ")" : '') . "
					WHERE blog.blogid IN (" . implode(',', $blogids) . ")
					ORDER BY blog.dateline DESC
					LIMIT 5
				");
				while ($blog = $this->registry->db->fetch_array($entries))
				{
					if ($first)
					{
						$show['latestentry'] = true;
						$first = false;
					}
					else
					{
						$show['latestentry'] = false;
					}

					$entry_handler =& $factory->create($blog, '_Profile');
					$entry_handler->cachable = false;
					$entry_handler->excerpt = true;
					$entry_handler->attachments = $postattach["$blog[blogid]"];
					$this->block_data['latestentries'] .= $entry_handler->construct();
				}

				// Comments
				$state = array('visible');
				$commentstate = array('visible');
				$sql_and = array();

				$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
				$sql_and[] = "blog.dateline <= " . TIMENOW;
				$sql_and[] = "blog.pending = 0";
				$sql_and[] = "blog_text.state IN('" . implode("', '", $commentstate) . "')";
				$sql_and[] = "blog.firstblogtextid <> blog_text.blogtextid";
				$sql_and[] = "blog_text.bloguserid = " . $this->profile->userinfo['userid'];

				if (!can_moderate_blog() AND !is_member_of_blog($this->registry->userinfo, $this->profile->userinfo) AND !$bloginfo['buddyid'])
				{
					$sql_and[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
				}

				if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']) AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'])
				{
					$joinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . "))";
					$sql_and[] = "cu.blogcategoryid IS NULL";
				}

				$this->registry->options['vbblog_snippet'] = 20;
				require_once(DIR . '/includes/class_blog_response.php');
				$bbcode = new vB_BbCodeParser_Blog_Snippet_Featured($this->registry, fetch_tag_list());
				$factory = new vB_Blog_ResponseFactory($this->registry, $bbcode, $bloginfo);

				$comments = $this->registry->db->query_read_slave("
					SELECT
						blog_text.username AS postusername, blog_text.ipaddress AS blogipaddress, blog_text.state, blog_text.blogtextid, blog_text.title, blog_text.dateline, blog_text.pagetext, blog_text.allowsmilie,
						blog.userid AS blog_userid, blog.blogid, blog.title AS entrytitle, blog.state AS blog_state, blog.firstblogtextid, blog.options AS blogoptions, blog_user.memberids, blog_user.memberblogids, blog.postedby_userid, blog.postedby_username,
						user2.usergroupid AS blog_usergroupid, user2.infractiongroupids AS blog_inractiongroupids, user2.membergroupids AS blog_membergroupids,
						user.*,
						blog_user.title AS blogtitle,
						IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, user.infractiongroupid, options_ignore, options_buddy, options_member, options_guest, blog.userid AS blog_userid,
						blog.state AS blog_state, blog.firstblogtextid
					" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
					" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime AS bloguserread" : "") . "
					" . ($vbulletin->userinfo['userid'] ? ", gm.permissions AS grouppermissions" : "") . "
					FROM " . TABLE_PREFIX . "blog_text AS blog_text
					LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_text.userid)
					INNER JOIN " . TABLE_PREFIX . "user AS user2 ON (user2.userid = blog.userid)
					LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
					" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? "
					LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $this->registry->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $this->registry->userinfo['userid'] . ")
					" : "") . "
					" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "blog_groupmembership AS gm ON (blog.userid = gm.bloguserid AND gm.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
					" . ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
					$joinsql
					WHERE " . implode("\r\n\tAND ", $sql_and) . "
					ORDER BY blog_text.dateline DESC
					LIMIT 5
				");
				while ($comment = $this->registry->db->fetch_array($comments))
				{
					$bloginfo = array(
						'blogid'             => $comment['blogid'],
						'userid'             => $comment['blog_userid'],
						'state'              => $comment['blog_state'],
						'firstblogtextid'    => $comment['firstblogtextid'],
						'blogread'           => $comment['blogread'],
						'bloguserread'       => $comment['bloguserread'],
						'usergroupid'        => $comment['blog_usergroupid'],
						'infractiongroupids' => $comment['blog_infractiongroupids'],
						'membergroupids'     => $comment['blog_membergroupids'],
						'memberids'          => $comment['memberids'],
						'memberblogids'      => $comment['memberblogids'],
						'postedby_userid'    => $comment['postedby_userid'],
						'postedby_username'  => $comment['postedby_username'],
						'grouppermissions'   => $comment['grouppermissions'],
					);
					cache_permissions($bloginfo, false);
					$response_handler->bloginfo =& $bloginfo;

					$response_handler =& $factory->create($comment, 'Comment_Profile');
					$response_handler->cachable = false;
					$response_handler->linkblog = true;
					$this->block_data['commentsreceived'] .= $response_handler->construct();
				}
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 17893 $
|| ####################################################################
\*======================================================================*/
?>
