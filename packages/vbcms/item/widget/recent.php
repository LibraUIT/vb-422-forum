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
 * @version $Revision: 34955 $
 * @since $Date: 2010-01-13 15:30:49 -0800 (Wed, 13 Jan 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_Recent extends vBCms_Item_Widget
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
	protected $class = 'Recent';

	/** The default configuration **/
	protected $config = array(
		'recent_type'   => 'active',
		'days'          => 1,
		'count'         => 10,
		'childforums'   => 1 ,
		'forumchoice'   => array(),
		'min_replies'   => '2',
		'main_template' => 'vbcms_widget_recent_page',
		'template_name' => 'vbcms_searchresult_thread',
	);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 34955 $
|| ####################################################################
\*======================================================================*/