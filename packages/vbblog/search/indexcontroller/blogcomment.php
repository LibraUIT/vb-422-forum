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

require_once (DIR."/vb/search/core.php");
/**
 * Index Controller for group Messages
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBBlog_Search_IndexController_BlogComment extends vB_Search_IndexController
{
	Const TYPE_FORUM = 'Forum';

	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT max(blogtextid) AS max FROM " . TABLE_PREFIX . "blog_text"
		);
		return $row['max'];
	}

	/**
	 * Index group message
	 *
	 * @param int $id
	 */
	public function index($id)
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave($this->make_query("blog_text.blogtextid = " . intval($id)));
		if ($row)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}

	public function index_id_range($start, $finish)
	{
		global $vbulletin;
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$set = $vbulletin->db->query_read_slave($this->make_query("blog_text.blogtextid BETWEEN " .
			intval($start) . " AND " . intval($finish)));
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}

	public function index_group($groupid)
	{
		global $vbulletin;
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$set = $vbulletin->db->query_read_slave($this->make_query("blog.blogid = " . intval($groupid)));
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}

	public function delete_group($groupid)
	{
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		if (method_exists($indexer, 'delete_group'))
		{
			$indexer->delete_group($this->get_groupcontenttypeid(), $groupid);
		}
		else
		{
			$this->index_group($groupid);
		}
	}

	public function group_data_change($groupid)
	{
		global $vbulletin;
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		if (method_exists($indexer, 'group_data_change'))
		{
			$data = $vbulletin->db->query_first_slave("
				SELECT
					blog.blogid AS groupid,
					blog.userid AS groupuserid,
					blog.dateline AS groupdateline,
					blog.title AS grouptitle,
					user.username AS groupusername
				FROM " . TABLE_PREFIX . "blog as blog JOIN
					" . TABLE_PREFIX . "user as user ON blog.userid = user.userid
				WHERE blog.blogid = " . intval($groupid)
			);
			if (!$data)
			{
				return;
			}
			$data['groupcontenttypeid'] = $this->get_groupcontenttypeid();
			$indexer->group_data_change($data);
		}
		else
		{
			$this->index_group($groupid);
		}
	}

	/**
	*	Merging isn't allowed so we don't have to account for it.
	*/
	public function merge_group($oldid, $newid)
	{

	}


	//We need to set the content types. This is available in a static method as
  // below
  public function __construct()
  {
  	$this->groupcontenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBBlog', 'BlogEntry');
  	$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBBlog', 'BlogComment');
  }

	private function make_query($filter)
	{
		//we filter out the actual blog post here -- we don't really want to index those.
		return "
			SELECT blog.blogid, blog.userid AS blog_userid, blog.dateline AS blog_dateline,
				blog.title AS blog_title,
				blog_text.dateline, blog_text.blogtextid, blog_text.ipaddress, blog_text.pagetext,
				blog_text.userid, blog_text.username AS text_username, blog_text.title,
				user.username, blog_user.username AS blog_username
			FROM " . TABLE_PREFIX . "blog AS blog JOIN
				" . TABLE_PREFIX . "blog_text AS blog_text ON blog.blogid = blog_text.blogid LEFT JOIN
				" . TABLE_PREFIX . "user AS blog_user ON blog.userid = blog_user.userid LEFT JOIN
				" . TABLE_PREFIX . "user AS user ON blog_text.userid = user.userid
			WHERE blog.firstblogtextid <> blog_text.blogtextid AND $filter
		";
	}

   /**
	 * Convert the basic table row to the index fieldset
	 *
	 * @param array $record
	 * @return return index fields
	 */
	private function record_to_indexfields($record)
	{
		//common fields
		$fields = array();
		$fields['contenttypeid'] = $this->get_contenttypeid();
		$fields['groupcontenttypeid'] = $this->get_groupcontenttypeid();

		$fields['id'] = $record['blogtextid'];
		$fields['groupid'] = $record['blogid'];
		$fields['dateline'] = $record['dateline'];
		$fields['groupdateline'] = $record['blog_dateline'];
		$fields['userid'] = $record['userid'];
		$fields['groupuserid'] = $record['blog_userid'];
		$fields['username'] = $record['username'] ?  $record['username'] :  $record['text_username'];
		$fields['groupusername'] = $record['blog_username'];;
		$fields['ipaddress'] = $record['ipaddress'];
		$fields['title'] = $record['title'];
		$fields['grouptitle'] = $record['blog_title'];
		$fields['keywordtext'] = $record['pagetext'];
		return $fields;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/