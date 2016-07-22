<?php
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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_417b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '417b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.7 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.6';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/*
	  Step 1 - VBIV-698 : Drop old vb3 fulltext index if it exists.
	*/
	function step_1()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			'thread',
			'title'
		);
	}


	/*
	  Steps 2 & 3 - VBIV-10514 : Add last_activity index.
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session DROP INDEX last_activity',	
			'1091'
		);
	}

	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 2, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session ADD INDEX last_activity USING BTREE (lastactivity)', 
			'1061'
		);
	}


	/*
	  Steps 4 & 5 - VBIV-10514 : Rebuild user_activity index as BTREE.
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 3, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session DROP INDEX user_activity',
			'1091'
		);
	}

	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 4, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session ADD INDEX user_activity USING BTREE (userid, lastactivity)',
			'1061'
		);
	}

	/*
	  Step 6 & 7 - VBIV-10525 : Correct clienthash index.
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'apiclient', 1, 2),
			'ALTER TABLE ' . TABLE_PREFIX . 'apiclient DROP INDEX clienthash',
			'1091'
		);
	}

	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'apiclient', 2, 2),
			'ALTER TABLE ' . TABLE_PREFIX . 'apiclient ADD INDEX clienthash (clienthash)',
			'1061'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
