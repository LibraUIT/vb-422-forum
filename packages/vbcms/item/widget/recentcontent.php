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
 * Test Widget Item
 *
 * @package vBulletin
 * @author Edwin Brown, vBulletin Development Team
 * @version $Revision: 37602 $
 * @since $Date: 2010-06-18 11:37:15 -0700 (Fri, 18 Jun 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_RecentContent extends vBCms_Item_Widget_RecentArticle
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'RecentContent';

	/** The default configuration **/
	protected $config = array(
		'categories'    => '',
		'sections'    => '',
		'template_name' => 'vbcms_widget_recentcontent_page',
		'days' => 7,
		'count' => 6,
		'cache_ttl' => 5
	);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 37602 $
|| ####################################################################
\*======================================================================*/