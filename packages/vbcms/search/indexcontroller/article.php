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
 * @package vBCms
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Id: article.php 30443 2009-04-23 21:55:01Z ebrown $
 * @since $Date: 2009-04-23 14:55:01 -0700 (Thu, 23 Apr 2009) $
 * @copyright vBulletin Solutions Inc.
 */

require_once DIR . '/vb/search/indexcontroller.php';
/**
 * @package vBulletin
 * @subpackage Search
 * @author Edwin Brown, vBulletin Development Team
 * @version $Revision: 30443 $
 * @since $Date: 2009-04-23 14:55:01 -0700 (Thu, 23 Apr 2009) $
 * @copyright vBulletin Solutions Inc.
 */
/**
 * vBCms_Search_IndexController_Article
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: article.php 30443 2009-04-23 21:55:01Z ebrown $
 * @access public
 */
class vBCms_Search_IndexController_Article extends vB_Search_IndexController
{

	/** Class name  **/
	protected $class = 'Article';

	/** package name  **/
	protected $package = 'vBCms';

	/** content typeid **/
	protected $contenttypeid;

	/**
	 * indexes a single record
	 *
	 * @param integer $id : the record id to be indexed
	 */
	public function index($id)
	{
		//we just pull a record from the database.
		if ($record = $this->getIndexRecord($id))
		{
			if (intval($record['nosearch']) OR intval($record['new']))
			{
				$this->delete($id);
			}
			else
			{
				$indexer = vB_Search_Core::get_instance()->get_core_indexer();
				$fields = $this->recordToIndexfields($record);
				$indexer->index($fields);
			}
		}
	}

	/**
	 * This will index a range of id's
	 *
	 * @param integer $start
	 * @param integer $finish
	 */
	public function index_id_range($start, $finish)
	{
		for ($id = $start; $id <= $finish; $id++)
		{
			$this->index($id);
		}
	}

	/**
	 *	Return the maximum id for the item type
	 *
	 * @return int
	 */
	public function get_max_id()
	{
		$record = vB::$vbulletin->db->query_first("SELECT MAX(contentid) AS id FROM " .
			TABLE_PREFIX . "cms_article ");
		return $record['id'];
	}


	/**
	 * deletes a single record
	 *
	 * @param integer $id : the record id to be removed from the index
	 */
	public function delete($id)
	{
		vB_Search_Core::get_instance()->get_core_indexer()->delete(
			$this->contenttypeid, $id);
	}

	/**
	 *  standard constructor, takes no parameters. We do need to set
	 *  the content type
	 */
	public function __construct()
	{
		$this->contenttypeid = vB_Types::instance()->getContentTypeID(
			array('package' =>$this->package, 'class' => $this->class));
	}
	/**
	 * This function is used to give the indexer a record to index
	 *
	 * @param integer $id : the contentid of the article
	 * @param integer $contenttypeid : the contenttypeid. We could look it up,
	 *   but this is only called from the indexcontroller which already has it.
	 * @return
	 */
	public function getIndexRecord($id)
	{
		return vB::$vbulletin->db->query_first("SELECT u.username, n.userid, a.contentid,
		a.pagetext, i.title, n.publishdate, i.creationdate, i.html_title, n.nosearch,
		n.new
		FROM " . TABLE_PREFIX . "cms_article a
		LEFT JOIN " . TABLE_PREFIX . "cms_node n on n.contentid = a.contentid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo i on i.nodeid = n.nodeid
  		LEFT JOIN " . TABLE_PREFIX . "user u on u.userid = n.userid
		WHERE a.contentid = $id AND n.contenttypeid = "
		. $this->contenttypeid);
	}

	/**
	 * Converts the visitormessage table row to the indexable fieldset
	 *
	 * @param associative array $visitormessage
	 * @return associative array $fields= the fields populated to match the
	 *   searchcored table in the database
	 */
	private function recordToIndexfields($record)
	{
		$fields['contenttypeid'] = $this->contenttypeid;
		$fields['primaryid'] = $record['contentid'];
		$fields['dateline'] = $record['publishdate'] ?
			$record['publishdate'] :
			($record['creationdate'] ? $record['creationdate']: TIMENOW) ;
		$fields['userid'] = $record['userid'];
		$fields['username'] = $record['username'];
		$fields['title'] = $record['title'] ;
		$fields['keywordtext'] = $record['html_title'] .
			'; ' . $record['title'] . '; ' . $record['pagetext'];
		return $fields;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 30443 $
|| ####################################################################
\*======================================================================*/