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
 * @version $Id: staticpage.php 60418 2012-03-16 15:52:01Z pmarsden $
 * @since $Date: 2012-03-16 08:52:01 -0700 (Fri, 16 Mar 2012) $
 * @copyright vBulletin Solutions Inc.
 */

require_once DIR . '/vb/search/indexcontroller.php';
/**
 * @package vBulletin
 * @subpackage Search
 * @author Edwin Brown, vBulletin Development Team
 * @version $Revision: 60418 $
 * @since $Date: 2012-03-16 08:52:01 -0700 (Fri, 16 Mar 2012) $
 * @copyright vBulletin Solutions Inc.
 */
/**
 * vBCms_Search_IndexController_StaticPage
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: staticpage.php 60418 2012-03-16 15:52:01Z pmarsden $
 * @access public
 */
class vBCms_Search_IndexController_StaticPage extends vB_Search_IndexController
{
	/** Class name  **/
	protected $class = 'StaticPage';

	/** package name  **/
	protected $package = 'vBCms';

	/** content typeid **/
	protected $contenttypeid;


	// ###################### Start index ######################
	/**
	 * indexes one record
	 *
	 * @param integer $id : the record id to be indexed
	 */
	public function index($id)
	{
		//we just pull a record from the database.

		if ($record = $this->getIndexRecord($id))
		{

			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->recordToIndexfields($record);
			$indexer->index($fields);
		}
	}

	// ###################### Start index_id_range ######################
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

	// ###################### Start delete ######################
	/**
	 * deletes one record
	 *
	 * @param integer $id : the record id to be removed from the index
	 */
	public function delete($id)
	{
		vB_Search_Core::get_instance()->get_core_indexer()->delete($this->contenttypeid, $id);
	}

	// ###################### Start __construct ######################
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
	 * @param integer $id : the contentid of the StaticHtml
	 * @param integer $contenttypeid : the contenttypeid. We could look it up,
	 *   but this is only called from the indexcontroller which already has it.
	 * @return
	 */
	public function getIndexRecord($id)
	{
	return vB::$vbulletin->db->query_first("SELECT u.username, n.userid,
		i.title, n.publishdate, i.creationdate, i.html_title, nc.value AS pagetext, n.nodeid
		FROM " . TABLE_PREFIX . "cms_node AS n
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS i on i.nodeid = n.nodeid
  		LEFT JOIN " . TABLE_PREFIX . "user AS u ON u.userid = n.userid
  		LEFT JOIN " . TABLE_PREFIX . "cms_nodeconfig AS nc ON nc.nodeid = n.nodeid AND nc.name = 'pagetext'
		WHERE n.nodeid = $id ");
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
		$fields['primaryid'] = $record['nodeid'];

		$fields['dateline'] = $fields['publishdate'] ?
			$fields['publishdate'] :
			($fields['creationdate'] ? $fields['creationdate']: TIMENOW) ;
		$fields['userid'] = $record['userid'];
		$fields['username'] = $record['username'];
		$fields['title'] = $record['title'] ;
		$fields['keywordtext'] = $record['pagetext'] .
			'; ' . $record['title'] ;
		return $fields;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 60418 $
|| ####################################################################
\*======================================================================*/