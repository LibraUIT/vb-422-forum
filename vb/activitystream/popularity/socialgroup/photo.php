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
 * Class to update the popularity score of stream items
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_Popularity_SocialGroup_Photo extends vB_ActivityStream_Popularity_Base
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
	 * Update popularity score
	 *
	 */
	public function updateScore()
	{
		if (!vB::$vbulletin->activitystream['socialgroup_photo']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['socialgroup_photo']['typeid'];
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "activitystream AS a
			INNER JOIN " . TABLE_PREFIX . "attachment AS at ON (a.contentid = at.attachmentid)
			SET
				a.score = (1 + (at.counter / 1000))
			WHERE
				a.typeid = {$typeid}
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/