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
 * Activity Stream Widget
 *
 * @package vBulletin
 * @author Edwin Brown, vBulletin Development Team
 * @version $Revision: 37230 $
 * @since $Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Item_Widget_ActivityStream extends vBCms_Item_Widget
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
	protected $class = 'ActivityStream';

	/** The default configuration **/
	protected $config = array(
		'activitystream_limit'  => 5,
		'activitystream_sort'   => 0,
		'activitystream_date'   => 0,
		'activitystream_filter' => 0,
		'cache_ttl'             => 1,
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 37230 $
|| ####################################################################
\*======================================================================*/