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

class vB_Upgrade_4110a2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4110a2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.10 Alpha 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.10 Alpha 1';

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

	/**
	* Step #1
	*/
	function step_1()
	{
		$this->add_adminmessage(
			'after_upgrade_4110_stylevars',
			array(
				'dismissible' => 1,
				'script'      => '',
				'action'      => '',
				'execurl'     => 'misc.php?do=removeorphanstylevars',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}
	
	/*
	  Step 2 - Separate [VIDEO] permissions from [IMG]
	*/
	function step_2()
	{	
		// Set [VIDEO] permission where [IMG] permission is active. 
		// [VIDEO] permission is 262144, [IMG] permission is 128
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "forum"),
			"UPDATE " . TABLE_PREFIX . "forum
				SET options = options | 262144
				WHERE options & 128
		");		
		
		// Set [VIDEO] permission where [IMG] permission is active. 
		// [VIDEO] permission is 262144, [IMG] permission is 2048
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET signaturepermissions = signaturepermissions | 262144
				WHERE signaturepermissions & 2048
		");			
		
		// Set [VIDEO] permission where [IMG] permission is active. 
		// [VIDEO] permission is 1024, [IMG] permission is 32
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "calendar"),
			"UPDATE " . TABLE_PREFIX . "calendar
				SET options = options | 1024
				WHERE options & 32
		");		
		
		// Set [VIDEO] permission where [IMG] permission is active. 
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
			"UPDATE " . TABLE_PREFIX . "setting SET
				value = value | " . (ALLOW_BBCODE_VIDEO) . "
			WHERE
				varname IN ('sg_allowed_bbcode', 'vm_allowed_bbcode', 'pc_allowed_bbcode')
					AND
				value & " . (ALLOW_BBCODE_IMG) . "
		");			
	}	
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
