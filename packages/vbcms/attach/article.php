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
 * Class for displaying a vBulletin article attachment
 *
 * @package 		vBulletin
 * @version		$Revision: 77181 $
 * @date 		$Date: 2013-08-29 14:44:06 -0700 (Thu, 29 Aug 2013) $
 *
 */
class vB_Attachment_Display_Single_vBCMS_Article extends vB_Attachment_Display_Single
{
	/**
	 * Verify permissions of a single attachment
	 *
	 * @return	bool
	*/
	public function verify_attachment()
	{
		if (!$this->verify_attachment_specific('vBCms_Article'))
		{
			return false;
		}

		//Now we need to get a content id. It could come from several places
		if (!isset($this->contentid))
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'f'            => vB_Input::TYPE_UINT,
				'attachmentid' => vB_Input::TYPE_UINT
				));
		}

		if (vB::$vbulletin->GPC_exists['values'] and isset(vB::$vbulletin->GPC['values']['f']))
		{
			$this->contentid = vB::$vbulletin->GPC['values']['f'];
		}
		else if (vB::$vbulletin->GPC_exists['attachmentid'] AND $this->vbulletininfo['contentid'])
		{
			$this->contentid = $this->vbulletininfo['contentid'];
		}
		else if (vB::$vbulletin->GPC_exists['attachmentid'] AND intval(vB::$vbulletin->GPC['attachmentid']))
		{
			if ($record = vB::$db->query_first("SELECT contentid, contenttypeid FROM " . TABLE_PREFIX . "attachment
			where attachmentid = " . vB::$vbulletin->GPC['attachmentid']))
			{
				if ($record['contenttypeid'] == vB_Types::instance()->getContentTypeID("vBCms_Article"))
				{
					$this->contentid = $record['contentid'];
				}
			}
		}

		//If we have a contentid, we can check the permissions
		if (isset($this->contentid))
		{
			return vBCMS_Permissions::canDownload($this->contentid);
		}
		return false;
	}

	/*** Constructor
	* @param registry (i.e. vB::$vbulleting
	* @param intr
	* @param string
	* @param int
	*
	* *********/
	public function __construct(&$registry, $attachmentid, $thumbnail, $attachmentid_2 = false)
	{
		parent::__construct($registry, $attachmentid, $thumbnail, $attachmentid_2 = false);
/*
		$this->attachmentinfo = vB::$vbulletin->db->query_first($q = "
			SELECT a.attachmentid, a.contenttypeid,
				a.contentid, a.userid, a.state, a.posthash, a.filename, a.caption,
				fd.filedataid, fd.userid as uploader, fd.filedata, fd.filehash, fd.thumbnail, fd.extension, " .

				($this->thumbnail ? "fd.thumbnail_dateline AS dateline, fd.thumbnail_filesize AS filesize" : "fd.dateline, fd.filesize ") .
				",at.extension, at.mimetype

			FROM " .
				TABLE_PREFIX . "filedata fd INNER JOIN " .
				TABLE_PREFIX . "attachment a ON a.filedataid = fd.filedataid LEFT JOIN " .
				TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)

			WHERE a.attachmentid = " . $this->attachmentid);

	//	 $this->attachmentinfo['mimetype'] = 'a:1:{i:0;s:23:"Content-type: image/' . $this->attachmentinfo['extension'] . '";}' ;
*/
	}
}

/**
 * Class for display of multiple vBulletin blog entry attachments
 *
 * @package 		vBulletin
 * @version		$Revision: 77181 $
 * @date 		$Date: 2013-08-29 14:44:06 -0700 (Thu, 29 Aug 2013) $
 *
 */
class vB_Attachment_Display_Multiple_vBCMS_Article extends vB_Attachment_Display_Multiple
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
			"node.nodeid, info.title, node.url, node.setpublish, node.publishdate, node.userid, node.permissionsfrom ",
			"perms.permissions"
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "cms_node AS node ON (node.nodeid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
			"LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON (info.nodeid = node.nodeid)",
			"LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)",
			"LEFT JOIN " . TABLE_PREFIX . "cms_permissions AS perms ON (perms.usergroupid = usergroup.usergroupid AND perms.nodeid = node.permissionsfrom)",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	 * Fetches the SQL to be queried as part of a UNION ALL of an attachment query, verifying read permissions
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
			"LEFT JOIN " . TABLE_PREFIX . "cms_node AS node ON (a.contentid = node.nodeid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = node.userid)",
		);

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	 * Formats articleinfo content for display
	 *
	 * @param	array		Entry information
	 *
	 * @return	array
	 */
	public function process_attachment_template($articleinfo, $showthumbs = false)
	{
		global $show, $vbphrase;

		$show['thumbnail'] = ($showthumbs AND $articleinfo['hasthumbnail']);

		// Todo: fix these conditions
		$show['candelete'] = false;
		if ($articleinfo['inprogress'] OR !$articleinfo['nodeid'])
		{
			$show['candelete'] = true;
		}
		else
		{
			$show['candelete'] = vBCMS_Permissions::canEdit($articleinfo['nodeid']);
		}

		return array(
			'template' => 'article',
			'article'  => $articleinfo,
			'url'      => $this->fetch_content_url_instance($articleinfo),
		);
	}

	/**
	 * Return item-specific url to the owner of an attachment
	 *
	 * @param	array		Content information
	 *
	 * @return	string
	 */
	protected function fetch_content_url_instance($contentinfo)
	{
		return vBCms_Route_Content::getURL(array('node' => vBCms_Item_Content::buildUrlSegment($contentinfo['nodeid'], $contentinfo['url'])));
	}
}

// #######################################################################
// ############################# STORAGE #################################
// #######################################################################

/**
 * Class for storing an article attachment
 *
 * @package 		vBulletin
 * @version		$Revision: 77181 $
 * @date 		$Date: 2013-08-29 14:44:06 -0700 (Thu, 29 Aug 2013) $
 *
 */
class vB_Attachment_Store_vBCMS_Article extends vB_Attachment_Store
{
	/**
	 *	Bloginfo
	 *
	 * @var	array
	 */
	protected $bloginfo = array();
	protected $contentid = false;

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
				n.nodeid AS contentid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "cms_node AS n ON (n.nodeid = a.contentid)
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
		if ($info['contentid'])
		{
			$this->contentid = $info['contentid'];
			return vBCMS_Permissions::canEdit($this->contentid);
		}

		if (!isset($this->contentid) and !vB::$vbulletin->GPC_exists['values'])
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'f' => vB_Input::TYPE_UINT,
				'attachmentid' => vB_Input::TYPE_UINT
			));
		}

		if (vB::$vbulletin->GPC_exists['values'] and isset(vB::$vbulletin->GPC['values']['f']))
		{
			$this->contentid = vB::$vbulletin->GPC['values']['f'];
		}

		if (isset($this->contentid))
		{

			return vBCMS_Permissions::canDownload($this->contentid);
		}

		if (vB::$vbulletin->GPC_exists['attachmentid'] AND $record = vB::$vbulletin->db->query_first('SELECT contentid FROM ' .
			TABLE_PREFIX . "attachment WHERE attachmentid = " . vB::$vbulletin->GPC['attachmentid']))
		{
			$this->contentid = $record['contentid'];
			return vBCMS_Permissions::canEdit($this->contentid);
		}
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
		$attachmentid = parent::process_upload($upload, $attachment, $imageonly);

		if (!vB::$vbulletin->GPC['values']['f'])
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'values' => vB_Input::TYPE_ARRAY
			));

		}

		if ($attachmentid AND vB::$vbulletin->GPC_exists['values'] AND isset(vB::$vbulletin->GPC['values']['f']))
		{
			$this->contentid = vB::$vbulletin->GPC['values']['f'];
			vB::$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "attachment SET contentid = " . $this->contentid	. " WHERE attachmentid = $attachmentid" );
		}
		return $attachmentid;
	}
}

/**
 * Class for deleting a vBulletin blog entry attachment
 *
 * @package 		vBulletin
 * @version		$Revision: 77181 $
 * @date 		$Date: 2013-08-29 14:44:06 -0700 (Thu, 29 Aug 2013) $
 *
 */
class vB_Attachment_Dm_vBCMS_Article extends vB_Attachment_Dm
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
		if (!isset($this->contentid) and !vB::$vbulletin->GPC_exists['values'])
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'f' => vB_Input::TYPE_UINT
			));
		}

		if (!isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		if (vB::$vbulletin->GPC_exists['values'] and isset(vB::$vbulletin->GPC['values']['f']))
		{
			$this->contentid = vB::$vbulletin->GPC['values']['f'];
			return vBCMS_Permissions::canEdit($this->contentid);
		}

		if (count($list))
		{
			$rst = vB::$vbulletin->db->query_read("
				SELECT DISTINCT node.permissionsfrom
				FROM " . TABLE_PREFIX . "attachment AS attach
				INNER JOIN " . TABLE_PREFIX . "cms_node AS node ON (node.nodeid = attach.contentid AND node.contenttypeid = attach.contenttypeid)
			 	WHERE
					attachmentid IN (" . implode(',' , $list) . ")
			 ");
			while ($record = vB::$vbulletin->db->fetch_array($rst))
			{
				if (!in_array($record['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canedit']))
				{
					return false;
				}
			}
			return true;
		}
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
		if (!isset($this->contentid) and !vB::$vbulletin->GPC_exists['values'])
		{
			vB::$vbulletin->input->clean_array_gpc('r', array(
				'f' => vB_Input::TYPE_UINT
			));
		}

		if (!isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}

		if (vB::$vbulletin->GPC_exists['values'] and isset(vB::$vbulletin->GPC['values']['f']))
		{
			$this->contentid = vB::$vbulletin->GPC['values']['f'];
			return vBCMS_Permissions::canEdit($this->contentid);
		}

		if (count($list))
		{
			$rst = vB::$vbulletin->db->query_read("
				SELECT DISTINCT node.permissionsfrom
				FROM " . TABLE_PREFIX . "attachment AS attach
				INNER JOIN " . TABLE_PREFIX . "cms_node AS node ON (node.nodeid = attach.contentid AND node.contenttypeid = attach.contenttypeid)
			 	WHERE
					attachmentid IN (" . implode(',' , $list) . ")
			 ");
			while ($record = vB::$vbulletin->db->fetch_array($rst))
			{
				if (!in_array($record['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canedit']))
				{
					return false;
				}
			}
			return true;
		}
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
		return true;
	}
}
/**
 * Class for handing the attachment upload
 *
 * @package 		vBulletin
 * @version		$Revision: 77181 $
 * @date 		$Date: 2013-08-29 14:44:06 -0700 (Thu, 29 Aug 2013) $
 *
 */
class vB_Attachment_Upload_Displaybit_vBCMS_Article extends vB_Attachment_Upload_Displaybit
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
   || # CVS: $RCSfile$ - $Revision: 77181 $
   || ####################################################################
   \*======================================================================*/
?>