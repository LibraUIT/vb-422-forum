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
class vB_ActivityStream_Popularity_Cms_Article extends vB_ActivityStream_Popularity_Base
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
		if (!vB::$vbulletin->products['vbcms'])
		{
			return;
		}

		if (!vB::$vbulletin->activitystream['cms_article']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['cms_article']['typeid'];

		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "activitystream AS a
			INNER JOIN " . TABLE_PREFIX . "cms_node AS n ON (a.contentid = n.nodeid)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS ni ON (n.nodeid = ni.nodeid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS t ON (t.threadid = ni.associatedthreadid)
			SET
				a.score = (1 + ((IF(t.replycount IS NOT NULL, t.replycount, 0) + IF(t.postercount IS NOT NULL, t.postercount, 0)) / 10) + (ni.ratingnum / 100) + (ni.viewcount / 1000) )
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