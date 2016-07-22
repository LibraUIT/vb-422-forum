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
 * Class to view the activity stream
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_View_MembertabAjax extends vB_ActivityStream_View
{
	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct(&$vbphrase, $fetchFriends)
	{
		$this->fetchFriends = $fetchFriends;
		return parent::__construct($vbphrase);
	}
	
	/*
	 * Process member stream ajax
	 *
	 * @param	array	Userinfo
	 *
	 */
	public function process()
	{
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'userid'      => TYPE_UINT,
			'tab'         => TYPE_NOHTML,
			'mindateline' => TYPE_UNIXTIME,
			'maxdateline' => TYPE_UNIXTIME,
			'minscore'    => TYPE_NUM,
			'minid'       => TYPE_STR,
			'maxid'       => TYPE_STR,
			'pagenumber'  => TYPE_UINT,
			'perpage'     => TYPE_UINT,
		));

		vB::$vbulletin->GPC['ajax'] = 1;

		vB_dB_Assertor::init(vB::$vbulletin->db, vB::$vbulletin->userinfo);
		vB_ProfileCustomize::getUserTheme(vB::$vbulletin->GPC['userid']);
		$userhastheme = (vB_ProfileCustomize::getUserThemeType(vB::$vbulletin->GPC['userid']) == 1) ? 1 : 0;
		$showusercss = (vB::$vbulletin->userinfo['options'] & vB::$vbulletin->bf_misc_useroptions['showusercss']) ? 1 : 0;

		if ($userhastheme AND $showusercss)
		{
			define('AS_PROFILE', true);
		}

		$userinfo = verify_id('user', vB::$vbulletin->GPC['userid'], 1, 1);
		$this->fetchMemberStreamSql(vB::$vbulletin->GPC['tab'], $userinfo['userid']);
		$this->processExclusions();
		$this->setPage(1, vB::$vbulletin->GPC['perpage']);
		$result = $this->fetchStream();
		$this->processAjax($result);
	}
}



/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
