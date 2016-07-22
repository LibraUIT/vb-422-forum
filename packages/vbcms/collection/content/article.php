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
 * CMS Content Collection
 * Fetches CMS specific content items, including node related info.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28696 $
 * @since $Date: 2008-12-04 16:24:20 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Collection_Content_Article extends vBCms_Collection_Content
{
	/*Item==========================================================================*/

	/**
	 * The package identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_package = 'vBCms';

	/**
	 * The class identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_class = 'Article';

	protected $query_hook = 'vbcms_collection_article_querydata';


	/*Constants=====================================================================*/

	/**
	 * Map of query => info.
	 * INFO_CONTENT is queried with QUERY_BASIC.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => /* vB_Item::INFO_BASIC | vB_Item::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 19,
		self::QUERY_PARENTS => vBCms_Item_Content::INFO_PARENTS,
		self::QUERY_CONFIG => vBCms_Item_Content::INFO_CONFIG
	);



	/*LoadInfo======================================================================*/

	/**
	 * Fetches additional fields for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryFields()
	{
		return ", article.pagetext, article.previewimage, user.userid, user.username, node.showrating";
	}


	/**
	 * Fetches additional join for querying INFO_CONTENT in QUERY_BASIC.
	 * Note: Child classes may provide a seperate query for INFO_CONTENT.  In that
	 * case, this does not need to be redefined.
	 *
	 * @return string
	 */
	protected function getContentQueryJoins()
	{
		return "INNER JOIN " . TABLE_PREFIX . "cms_article AS article ON article.contentid = node.contentid
			INNER JOIN " . TABLE_PREFIX . "user AS user ON user.userid = node.userid";
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28696 $
|| ####################################################################
\*======================================================================*/