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

require_once(DIR . '/includes/class_taggablecontent.php');
require_once(DIR . '/includes/blog_functions.php');

/**
* Handle thread specific logic
*
*	Internal class, should not be directly referenced
* use vB_Taggable_Content_Item::create to get instances
*	see vB_Taggable_Content_Item for method documentation
*/
class vBBlog_TaggableContent_BlogEntry extends vB_Taggable_Content_Item
{
	protected function load_content_info()
	{
		return verify_blog($this->contentid);
	}

	public function can_delete_tag($taguserid)
	{
		//the user can delete his own tag associations
		if ($taguserid == $this->registry->userinfo['userid'])
		{
			return true;
		}
		
		return $this->can_manage_tag();
	}

	public function can_moderate_tag()
	{
		return can_moderate_blog('caneditentries');
	}

	public function can_add_tag()
	{
		$cantagothers = ($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & 
			$this->registry->bf_ugp_vbblog_entry_permissions['blog_cantagothers']);

		$cantagown = ($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & 
				$this->registry->bf_ugp_vbblog_entry_permissions['blog_cantagown']);

		//copied from existing code.  "cantagothers" appear to imply "cantagown" as there is 
		//no user check on it.  This is not typically how canxxxothers and canxxxown logic
		//has been implemented in blog.

		return ($this->can_moderate_tag() OR $cantagothers OR ($cantagown AND	$this->is_owned_by_current_user()));
	}

	public function can_manage_tag()
	{
		$candeleteown = ($this->registry->userinfo['permissions']['vbblog_entry_permissions'] & 
			$this->registry->bf_ugp_vbblog_entry_permissions['blog_candeletetagown']);

		return $this->can_moderate_tag() OR $this->can_add_tag() OR 
			($candeleteown AND $this->is_owned_by_current_user());
	}

	/**
	*	Get the user permission to create tags
	*
	* @return bool
	*/
	function check_user_permission()
	{
		return $this->registry->check_user_permission('vbblog_entry_permissions', 'blog_cancreatetag');
	}

	// VBIV-12424
	public function is_owned_by_current_user()
	{
		$contentinfo = $this->fetch_content_info();
		return ($contentinfo['postedby_userid'] == $this->registry->userinfo['userid']);
	}

	public function fetch_tag_limits()
	{
		if ($this->can_moderate_tag())
		{
			$user_limit = 0;
		}
		else
		{
			if ($this->is_owned_by_current_user())
			{
				$user_limit = $this->registry->options['vbblog_maxtagstarter'];
			}
			else
			{
				$user_limit = $this->registry->options['vbblog_maxtaguser'];
			}
		}
		return array('content_limit' => $this->registry->options['vbblog_maxtag'], 'user_limit' => $user_limit);
	}

	public function fetch_content_type_diplay()
	{
		global $vbphrase;
		return $vbphrase['blog'];
	}

	public function rebuild_content_tags()
	{
		$contentinfo = $this->fetch_content_info();
		
		// invalidate users tag cloud
		$dataman =& datamanager_init('Blog_User', $this->registry, ERRTYPE_STANDARD);
		$info = array('bloguserid' => $contentinfo['userid']);
		$dataman->set_existing($info);
		$dataman->set('tagcloud', '');
		$dataman->save();
		unset($dataman);

		//update tag field on object.
		$tags = $this->fetch_existing_tag_list();
		$taglist = implode(', ', $tags);
		$dataman =& datamanager_init('Blog', $this->registry, ERRTYPE_SILENT, 'blog');
		$dataman->set_existing($contentinfo);
		$dataman->set('taglist', $taglist);
		$dataman->save();
		unset($dataman);
	}

	public function fetch_rendered_tag_list()
	{
		require_once(DIR . "/includes/blog_functions_tag.php");
		$contentinfo = $this->fetch_content_info();
		$contentinfo['taglist'] = implode(', ',  $this->fetch_existing_tag_list());
	
		//I don't really like this, but I don't know how else to handle it.
		//the blog tag rendering needs an extra parameter, so we grab it from
		//the ether.
		$userid = $this->registry->input->clean_gpc('r', 'userid');
		$userinfo = array();
		if($userid)
		{
			$userinfo['userid'] = $userid;
		}

		return fetch_entry_tagbits($contentinfo, $userinfo);
	}

	public function is_cloud_cachable()
	{
		return $this->registry->options['vbblog_tagcloud_cachetype'] != 1;
	}

	public function fetch_tag_cloud_query_bits()
	{
		$joinsql['blog'] = "INNER JOIN " . TABLE_PREFIX . "blog AS blog ON (tagcontent.contentid = blog.blogid)";
		$wheresql = array(
			"blog.dateline <= " . TIMENOW,
			"blog.pending = 0",
			"blog.state = 'visible'",
			"~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'],
		);

		if ($this->registry->options['vbblog_tagcloud_cachetype'] == 1)
		{
			$joinsql['blog_user'] = "INNER JOIN " . TABLE_PREFIX . 
				"blog_user AS blog_user ON (blog.userid = blog_user.bloguserid)";
				
			if ($this->registry->userinfo['userid'])
			{
				//user options
				$canviewblogflag = $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];

				$userlist_sql = array();
				$userlist_sql[] = "(blog_user.options_ignore & $canviewblogflag " . 
					" AND ignored.relationid IS NOT NULL)";
				$userlist_sql[] = "(blog_user.options_buddy & $canviewblogflag " . 
					" AND buddy.relationid IS NOT NULL)";
				$userlist_sql[] = "(
				  blog_user.options_member & $canviewblogflag AND 
					(blog_user.options_buddy & $canviewblogflag OR buddy.relationid IS NULL) AND
					(blog_user.options_ignore &  $canviewblogflag OR ignored.relationid IS NULL)
				)";
				$wheresql[] = "(" . implode(" OR ", $userlist_sql) . ")";

				$joinsql['buddy'] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON 
					(buddy.userid = blog.userid AND buddy.relationid = " . $this->registry->userinfo['userid'] . " 
						AND buddy.type = 'buddy')";

				$joinsql['ignored'] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON 
					(ignored.userid = blog.userid AND ignored.relationid = " . $this->registry->userinfo['userid'] . " 
					AND ignored.type = 'ignore')";

				//make sure that this gets initialized
				if (!$this->registry->userinfo['blogcategorypermissions'])
				{
					require_once (DIR . '/includes/blog_functions_shared.php');
					prepare_blog_category_permissions($this->registry->userinfo, true);
				}
				
				if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']))
				{
					$joinsql['cu'] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON
						(cu.blogid = blog.blogid AND cu.blogcategoryid IN (" . 
							implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . ")
					)";
					$wheresql[] = "cu.blogcategoryid IS NULL";
				}					
			}
			else
			{
				$wheresql[] = "blog_user.options_guest & " . $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
				$wheresql[] = "~blog.options & " . $this->registry->bf_misc_vbblogoptions['private'];
			}
		}

		// remove blog entries that don't interest us
		require_once(DIR . '/includes/functions_bigthree.php');
		if ($coventry = fetch_coventry('string'))
		{
			$wheresql[] = "blog.userid NOT IN ($coventry)";
		}

		return array('join' => $joinsql, 'where' => $wheresql);
	}

	public function fetch_return_url()
	{
		$contentinfo = $this->fetch_content_info();
		$url = parent::fetch_return_url();
		if(!$url)
		{
			$url = fetch_seo_url('entry', $contentinfo) .  "#blogtaglist_$contentinfo[blogid]";
		}
		return $url;
	}

	public function fetch_page_nav()
	{
		$contentinfo = $this->fetch_content_info();
		// navbar and output
		$navbits[fetch_seo_url('blog', $contentinfo)] = $contentinfo['blog_title'];
		$navbits[fetch_seo_url('entry', $contentinfo)] = $contentinfo['title'];
		return $navbits;
	}

	public function verify_ui_permissions()
	{
		if (!$this->registry->options['vbblog_tagging'])
		{
			print_no_permission();
		}

		if ( !($this->can_add_tag() OR $this->can_manage_tag()) ) 
		{
			print_no_permission();
		}
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
