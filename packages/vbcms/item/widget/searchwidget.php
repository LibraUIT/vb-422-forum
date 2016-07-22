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
 * @version $Revision: 58155 $
 * @since $Date: 2012-01-23 12:40:59 -0800 (Mon, 23 Jan 2012) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_Searchwidget extends vBCms_Item_Widget
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
	protected $class = 'Searchwidget';

	/** The default configuration **/
	protected $config = array(
		'days'          => 7,
		'cache_ttl'      => 5,
		'keywords'      => '',
		'count'         => 10,
		'friends'       => 0,
		'username'      => '',
		'friends'       => 0,
		'childforums'   => 1,
		'tag'           => '',
		'contenttypeid' => array(),
		'group'         =>  '',
		'forumchoice'   =>  array(),
		'cat'           => array(),
		'prefixchoice'  => array(),
		'template'      => '',
		'template_name' => 'vbcms_widget_searchwidget_page',
		'type_info'     => array(),
	);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 58155 $
|| ####################################################################
\*======================================================================*/