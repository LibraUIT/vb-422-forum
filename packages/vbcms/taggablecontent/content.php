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

require_once DIR . '/includes/class_taggablecontent.php' ;
/**
* Handle thread specific logic
*
*	Internal class, should not be directly referenced
* use vB_Taggable_Content_Item::create to get instances
*	see vB_Taggable_Content_Item for method documentation
*/
class vBCms_TaggableContent_Content extends vB_Taggable_Content_Item
{
	//nodeid is of the record
	private $nodeid = false;

	
	/**** This loads the content information
	 *
	 * @return mixed if successful. False if load fails
	 ****/
	protected function load_content_info()
	{
		$type_instance = vB_Types::instance();
		$class = $type_instance->getContentTypeClass($this->contenttypeid);
		$package = $type_instance->getContentTypePackage($this->contenttypeid);

		//this forces some of the fields we access to be loaded.
		$contentinfo = vBCms_Content::create($package, $class);

		//we have a contenttype and content id, but we need the nodeid.
		if ($record = vB::$vbulletin->db->query_first("SELECT nodeid FROM " . TABLE_PREFIX .
			"cms_node WHERE contenttypeid = " . $this->contenttypeid . " AND contentid = " .
			$this->contentid))
		{
			$item = vB_Item_Content::create($package, $class, $this->nodeid = $record['nodeid']);
			$item->requireInfo($contentinfo->getViewInfoFlags(vB_Content::VIEW_PREVIEW));
			$contentinfo->setContentItem($item);

			return $contentinfo;
		}
		return false;
	}

	/**** returns the permission setting - can this user moderate tags
	 *
	 * @return boolean
	 ****/
	public function can_moderate_tag()
	{
		return $this->can_add_tag();
	}
	
	/**** returns the permission setting - can this user add tags
	 *
	 * @return boolean
	 ****/
	public function can_add_tag()
	{
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		//until we have better developed permissions, limit tagging
		//to item editors only.
		return vBCMS_Permissions::canEdit($this->fetch_content_info()->getNodeId());
	}

	/**** returns flag- is this user the item owner?
	 *
	 * @return boolean
	 ****/
	public function is_owned_by_current_user()
	{
		$contentinfo = $this->fetch_content_info();
		return ($contentinfo->getUserId() == $this->registry->userinfo['userid']);
	}

	/**** Returns the display string for this content type
	 *
	 * @return string
	 ****/
	public function fetch_content_type_diplay()
	{
		return vB_Types::instance()->getContentTypeTitle($this->contenttypeid);
	}

	/**** returns the setting- can this be cached as part of the tag cloud?
	 *
	 * @return true
	 ****/
	public function is_cloud_cachable()
	{
		return true;
	}

	/**** returns the SQL string to get this from the tag cloud
	 *
	 * @return string
	 ****/
	public function fetch_tag_cloud_query_bits()
	{
		$join['cms_node'] = "JOIN " . TABLE_PREFIX . "cms_node AS cms_node ON
			tagcontent.contenttypeid = cms_node.contenttypeid AND tagcontent.contentid = cms_node.contentid
		";
		$where[] = 'cms_node.publishdate < ' . TIMENOW;
		return array('join' => $join, 'where' => $where);
	}

	/**** Returns the title
	 *
	 * @return string
	 ****/
	public function get_title()
	{
		//probably shouldn't leave this as the default, but provides
		//shim code for existing implementations
		return $this->fetch_content_info()->getTitle();
	}

	/**** Returns the url of the current page
	 *
	 * @return string
	 ****/
	public function fetch_return_url()
	{
		return $this->fetch_content_info()->getPageUrl();
	}

	/**** standard method to return page nav links. Not needed here
	 *
	 * @return array
	 ****/
	public function fetch_page_nav()
	{
		return array();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/