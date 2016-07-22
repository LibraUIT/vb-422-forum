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
 * CMS Article Content Item
 * The model item for CMS articles.
 *
 * @author vBulletin Development Team
 * @version $Revision: 37602 $
 * @since $Date: 2010-06-18 11:37:15 -0700 (Fri, 18 Jun 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Content_StaticPage extends vBCms_Item_Content
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'StaticPage';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * The DM for handling CMS Article data.
	 *
	 * @var string
	 */
	protected $dm_class = 'vBCms_DM_Node';

	/**
	 * Map of query => info.
	 * Include INFO_CONTENT in QUERY_BASIC.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => /* self::INFO_BASIC | self::INFO_NODE  */ 3,
		self::QUERY_PARENTS => self::INFO_PARENTS,
		self::QUERY_NAVIGATION => self::INFO_NAVIGATION,
		self::QUERY_CONFIG => self::INFO_CONFIG
	);
	/**
	 * The total flags for all info.
	 * This would be a constant if we had late static binding.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 91 ;//self::INFO_NODE | self::INFO_PARENTS
		// self::INFO_CONFIG | self::INFO_NAVIGATION

	/**
	 * Fetches the contentid.
	 * How this is interpreted is up to the content handler for the contenttype.
	 * Note that to make vB_Model work properly when instantiating a new item
	 * we need to return the nodeid if we don't have a content id. But we should
	 * be able to get only the contentid if we don't want the nodeid.
	 * @return int
	 */
	public function getContentId($contentonly = false)
	{
		return parent::getNodeId();
	}

	/**
	 * Fetches the pagetext.
	 *
	 * @return string
	 */
	public function getPageText()
	{
		$this->Load(self::INFO_CONFIG);
		$config = $this->getConfig();
		return $config['pagetext'] ;
	}
	
	/**** returns the item previewtext
	 *
	 * @return string
	 ****/
	public function getPreviewText()
	{
		$this->Load(self::INFO_CONFIG);
		$config = $this->getConfig();
		return $config['previewtext'] ;
	}

	/**** returns the previewimage value from the database record
	 *
	 * @return string
	 ****/
	public function getPreviewImage()
	{
		$this->Load(self::INFO_CONFIG);
		$config = $this->getConfig();
 		return $config['previewimage'] ;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 37602 $
|| ####################################################################
\*======================================================================*/