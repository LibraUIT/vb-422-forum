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
class vB_ActivityStream_View_Membertab extends vB_ActivityStream_View
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
	 * Process member stream
	 *
	 * @param	array	Userinfo
	 *
	 */
	public function process($userid, $options, &$block_data)
	{
		global $show;

		$options['type'] = $this->fetchMemberStreamSql($options['type'], $userid);
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}

		$block_data['selected_' . $options['type']] = 'selected';
		$block_data['pageinfo_all'] = array(
			'tab'  => 'activitystream',
			'type' => 'all'
		);
		$block_data['pageinfo_user'] = array(
			'tab'  => 'activitystream',
			'type' => 'user'
		);
		$block_data['pageinfo_subs'] = array(
			'tab'  => 'activitystream',
			'type' => 'subs'
		);
		$block_data['pageinfo_friends'] = array(
			'tab'  => 'activitystream',
			'type' => 'friends'
		);
		$block_data['pageinfo_photos'] = array(
			'tab'  => 'activitystream',
			'type' => 'photos'
		);
		$block_data['moreactivity'] = array(
			'tab'  => 'activitystream',
			'type' => $options['type'],
			'page' => $options['pagenumber'] + 1,
		);

		$show['asfriends'] = (vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends'] AND $this->fetchFriends);
		$this->setPage($pagenumber, vB::$vbulletin->options['as_perpage']);
		$result = $this->fetchStream();
		$block_data['mindateline'] = $result['mindateline'];
		$block_data['maxdateline'] = $result['maxdateline'];
		$block_data['minscore'] = $result['minscore'];
		$block_data['minid'] = $result['minid'];
		$block_data['maxid'] = $result['maxid'];
		$block_data['count'] = $result['count'];
		$block_data['totalcount'] = $result['totalcount'];
		$block_data['perpage'] = $result['perpage'];
		$block_data['refresh'] = $result['refresh'];

		$show['noactivity'] = false;
		$show['nomoreresults'] = false;
		$show['moreactivity'] = false;
		if ($result['totalcount'] == 0)
		{
			$show['noactivity'] = true;
		}
		else if ($result['totalcount'] < $result['perpage'])
		{
			$show['nomoreresults'] = true;
		}
		else
		{
			$show['moreactivity'] = true;
		}

		$block_data['activitybits'] = '';
		foreach ($result['bits'] AS $bit)
		{
			$block_data['activitybits'] .= $bit;
		}
	}
}



/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
