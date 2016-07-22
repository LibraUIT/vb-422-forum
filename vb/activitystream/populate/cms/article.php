<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 4.2.2 - Nulled By VietVBB Team
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * Class to populate the activity stream from existing content
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_Populate_Cms_Article extends vB_ActivityStream_Populate_Base
{
	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct()
	{
		return parent::__construct();
	}

	/*
	 * Don't get: Deleted threads, redirect threads, CMS comment threads
	 *
	 */
	public function populate()
	{
		if (!vB::$vbulletin->products['vbcms'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['cms_article']['typeid'];
		$this->delete($typeid);

		if (!vB::$vbulletin->activitystream['cms_article']['enabled'])
		{
			return;
		}

		$contenttypeid = vB_Types::instance()->getContentTypeID('vBCms_Article');
		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					userid, publishdate, nodeid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "cms_node AS cms_node
				WHERE
					publishdate >= {$timespan}
						AND
					contenttypeid = {$contenttypeid}
				)
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/