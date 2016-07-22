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
class vB_ActivityStream_Populate_Forum_Thread extends vB_ActivityStream_Populate_Base
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
		$typeid = vB::$vbulletin->activitystream['forum_thread']['typeid'];
		$this->delete($typeid);

		if (!vB::$vbulletin->activitystream['forum_thread']['enabled'])
		{
			return;
		}

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					postuserid, dateline, threadid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "thread
				WHERE
					dateline >= {$timespan}
						AND
					open <> 10
					" . (vB::$vbulletin->options['vbcmsforumid'] ? "AND forumid <> " . vB::$vbulletin->options['vbcmsforumid'] : "") . "
				)
		");
	}

	/*
	 * Rebuild stream for one or more threads
	 *
	 * @param	array	list of threadids
	 */
	public static function rebuild_thread($threadids)
	{
		if (!is_array($threadids) OR empty($threadids))
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['forum_thread']['typeid'];
		// Delete thread data
		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				typeid = {$typeid}
					AND
				contentid IN (" . implode(",", $threadids) . ")
		");

		$typeid = vB::$vbulletin->activitystream['forum_post']['typeid'];
		// Delete post data
		vB::$db->query_write("DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				typeid = {$typeid}
					AND
				contentid IN (SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid IN (" . implode(",", $threadids) . "))
		");

		if ($typeid = vB::$vbulletin->activitystream['cms_comment']['typeid'])
		{	// delete CMS data (just in case)
			vB::$db->query_write("DELETE FROM " . TABLE_PREFIX . "activitystream
				WHERE
					typeid = {$typeid}
						AND
					contentid IN (SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid IN (" . implode(",", $threadids) . "))
			");
		}

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;

		if (!vB::$vbulletin->activitystream['forum_thread']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['forum_thread']['typeid'];
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					postuserid, dateline, threadid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "thread
				WHERE
					dateline >= {$timespan}
						AND
					open <> 10
						AND
					threadid IN (" . implode(",", $threadids) . ")
					" . (vB::$vbulletin->options['vbcmsforumid'] ? "AND forumid <> " . vB::$vbulletin->options['vbcmsforumid'] : "") . "
				)
		");

		if (!vB::$vbulletin->activitystream['forum_post']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['forum_post']['typeid'];
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					post.userid, post.dateline, post.postid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
				WHERE
					post.dateline >= {$timespan}
						AND
					post.postid <> thread.firstpostid
						AND
					thread.open <> 10
						AND
					thread.threadid IN (" . implode(",", $threadids) . ")
					" . (vB::$vbulletin->options['vbcmsforumid'] ? "AND thread.forumid <> " . vB::$vbulletin->options['vbcmsforumid'] : "") . "
				)
		");

		if (!vB::$vbulletin->products['vbcms'] OR !vB::$vbulletin->options['vbcmsforumid'])
		{
			return;
		}

		if (!vB::$vbulletin->activitystream['cms_comment']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['cms_comment']['typeid'];
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					post.userid, post.dateline, post.postid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
				WHERE
					post.dateline >= {$timespan}
						AND
					post.visible <> 2
						AND
					post.postid <> thread.firstpostid
						AND
					thread.open <> 10
						AND
					thread.visible <> 2
						AND
					thread.threadid IN (" . implode(",", $threadids) . ")
						AND
					thread.forumid = " . vB::$vbulletin->options['vbcmsforumid'] . "
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