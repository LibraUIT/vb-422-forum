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

class vB_Upgrade_418b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '418b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.8 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.7';

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
	  Step 1 - VBIV-6641 : Add cache index for expires. 
	*/
	function step_1()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cache', 1, 1),
			'cache',
			'expires',
			array('expires')
		);
	}

	/*
	  Step 2 - VBIV-6641 : Clean out expired events in cache and cacheevent tables.
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['cache_update']),
				'DELETE cache, cacheevent FROM ' . TABLE_PREFIX . 'cache as cache
				INNER JOIN ' . TABLE_PREFIX . 'cacheevent as cacheevent USING (cacheid)
				WHERE expires BETWEEN 1 and ' . TIMENOW
		);
	}

	/*
	  Step 3 - VBIV-13190 : Mobile (basic) style default.
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['version']['418b1']['updating_mobile'], 1, 2),
				'UPDATE ' . TABLE_PREFIX . "setting
				SET value = -2, defaultvalue = -2
				WHERE value = 0 AND varname = 'mobilestyleid_basic'
		");
	}

	/*
	  Step 4 - VBIV-13190 : Mobile (advanced) style default.
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['version']['418b1']['updating_mobile'], 2, 2),
				'UPDATE ' . TABLE_PREFIX . "setting
				SET value = -3, defaultvalue = -3
				WHERE value = 0 AND varname = 'mobilestyleid_advanced'
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
