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
class vBBlog_Search_IndexController_BlogEntry extends vB_Search_IndexController
{
	public function __construct()
  {
  	$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBBlog', 'BlogEntry');
  }

	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT max(blogid) AS max FROM " . TABLE_PREFIX . "blog"
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
		$row = $vbulletin->db->query_first_slave($this->make_query("blog.blogid = " . intval($id)));
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
		$set = $vbulletin->db->query_read_slave($this->make_query("blog.blogid BETWEEN " .
			intval($start) . " AND " . intval($finish)));
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}



	private function make_query($filter)
	{
		return "
			SELECT blog.blogid, blog.userid, blog.dateline, blog.title, user.username,
				blog_text.ipaddress, blog_text.pagetext
			FROM " . TABLE_PREFIX . "blog as blog JOIN
				" . TABLE_PREFIX . "blog_text as blog_text ON blog.firstblogtextid = blog_text.blogtextid JOIN
				" . TABLE_PREFIX . "user as user ON blog.userid = user.userid
			WHERE $filter
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
		$fields['id'] = $record['blogid'];
		$fields['dateline'] = $record['dateline'];
		$fields['userid'] = $record['userid'];
		$fields['username'] = $record['username'];
		$fields['ipaddress'] = $record['ipaddress'];
		$fields['title'] = $record['title'];
		$fields['keywordtext'] = $record['pagetext'];

		//set the "group" fields as duplicates of this type.
		//this makes things easier with the advanced search hack that
		//combines blogs and blog comments.
		//if grouping returns the "right" results for blogs, we can
		//assume that we are always grouping and things will work
		//out okay.
		$fields['groupcontenttypeid'] = $this->get_contenttypeid();
		$fields['groupid'] = $record['blogid'];
		$fields['groupuserid'] = $record['userid'];
		$fields['groupdateline'] = $record['dateline'];
		$fields['groupusername'] = $record['username'];
		return $fields;
	}

//	protected $contenttypeid;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/