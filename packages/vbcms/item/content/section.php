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
 * CMS Section Content Item
 * The model item for CMS sections.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29171 $
 * @since $Date: 2009-01-19 02:05:50 +0000 (Mon, 19 Jan 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Content_Section extends vBCms_Item_Content
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'Section';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	protected $dm_class = 'vBCms_DM_Section';

	/**
	 * 
	 *
	 * @return int
	 */
	/**
	 * Fetches the contentid, which for a section is the nodeid.
	 * How this is interpreted is up to the content handler for the contenttype.
	 * 
	 * @param  boolean $contentonly added for php 5.4 srtrict standards compliance
	 * @return int
	 */
	public function getContentId($contentonly = false)
	{
		$this->Load();
		//for sections, and probably for some other types in the futurne
		return ($this->nodeid);
	}


}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/