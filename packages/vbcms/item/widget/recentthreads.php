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
 * @version $Revision: 35840 $
 * @since $Date: 2010-03-16 15:19:39 -0700 (Tue, 16 Mar 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_RecentThreads extends vBCms_Item_Widget
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
	protected $class = 'RecentThreads';

	/** The default configuration **/
	protected $config = array(
		'forumchoice'    => '',
		'template_name' => 'vbcms_widget_recentthreads_page',
		'days' => 7,
		'count' => 6,
		'threads_type' => 0,
		'allow_html' => 0,
		'cache_ttl' => 5
	);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 35840 $
|| ####################################################################
\*======================================================================*/