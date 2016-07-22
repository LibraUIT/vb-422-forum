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
 * @version $Revision: 35393 $
 * @since $Date: 2010-02-11 14:43:34 -0800 (Thu, 11 Feb 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_RecentBlogComments extends vBCms_Item_Widget
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
	protected $class = 'RecentBlogComments';

	/** The default configuration **/
	protected $config = array(
		'template_name' => 'vbcms_widget_recentblogcomments_page',
		'categories' => 0,
		'commentuserid' => '',
		'postuserid' => '',
		'blogid' => '',
		'taglist' => '',
		'days' => 7,
		'count' => 6,
		'messagemaxchars' => 200,
		'cache_ttl' => 5
	);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 35393 $
|| ####################################################################
\*======================================================================*/