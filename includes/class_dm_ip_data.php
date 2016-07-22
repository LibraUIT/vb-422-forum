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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for IP Data
*
* Example usage:
*
* $ipdata =& datamanager_init('IP_Data', $vbulletin, ERRTYPE_STANDARD);
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_IP_Data extends vB_DataManager
{
	/**
	* Array of recognised and required fields for poll, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'ipid'			=> array(TYPE_UINT,	REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'userid'		=> array(TYPE_UINT,	REQ_YES),
		'contentid'		=> array(TYPE_UINT,	REQ_YES, VF_METHOD, 'verify_nonzero'),
		'contenttypeid'	=> array(TYPE_UINT,	REQ_YES, VF_METHOD, 'verify_nonzero'),
		'rectype'		=> array(TYPE_STR,	REQ_YES, VF_METHOD),
		'ip'			=> array(TYPE_STR,	REQ_YES, VF_METHOD),
		'altip'			=> array(TYPE_STR,	REQ_NO, VF_METHOD, 'verify_ip'),
		'dateline'      => array(TYPE_UINT,	REQ_AUTO, VF_METHOD, 'verify_nonzero'),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('ipid = %1$d', 'ipid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'ipdata';

	// Hooks, false = called locally //
	var $hook_start = false;
	var $hook_presave = false;
	var $hook_postsave = 'ipdata_postsave';
	var $hook_delete = 'ipdata_delete';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_IP_Data(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		// Defaults.
		$this->set_info('ip', IPADDRESS); 
		$this->set_info('altip', ALT_IP);

		if (!$this->hook_start)
		{
			($hook = vBulletinHook::fetch_hook('ipdata_start')) ? eval($hook) : false;
		}
	}

	/**
	* Format the data for saving
	*
	* @param	bool
	*
	* @return 	boolean	Function result
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$ip = $this->fetch_field('ip');
		$altip = $this->fetch_field('altip');
		
		// Set to default if necessary.
		$ip = $ip ? $ip : $this->info['ip'];
		$altip = $altip ? $altip : $this->info['altip'];

		$this->set('ip', compress_ip($ip));
		$this->set('altip', compress_ip($altip));

		if (!$this->fetch_field('contenttypeid'))
		{
			if ($this->info['contenttype'])
			{
				$this->set('contenttypeid', vB_Types::instance()->getContentTypeID($this->info['contenttype']));
			}
		}
		
		if (!$this->fetch_field('dateline'))
		{
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;

		if (!$this->hook_presave)
		{
			($hook = vBulletinHook::fetch_hook('ipdata_presave')) ? eval($hook) : false;
		}

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* We change the default for $replace to true, and then call the parent.
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = true, $ignore = false)
	{
		// We default $replace to true, and then call the parent.
		return parent::save($doquery, $delayed, $affected_rows, $replace, $ignore);
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
/*	
	function post_save_each($doquery = true)
	{

		if (!$this->hook_postsave)
		{
			($hook = vBulletinHook::fetch_hook('ipdata_postsave')) ? eval($hook) : false;
		}
	}
*/

	/**
	* Removing from the table
	*
	* @param	boolean	Do the query?
	*/
/*
	function post_delete($doquery = true)
	{

		if (!$this->hook_delete)
		{
			($hook = vBulletinHook::fetch_hook('ipdata_delete')) ? eval($hook) : false;
		}
	}
*/

	/**
	* Verifies the ip is a valid IPv4 or IPv6 address
	*
	* @param	string	IP Address
	*
	* @return 	boolean	Returns true if the address is valid
	*/
	function verify_ip($ip)
	{
		return get_iptype($ip) ? true : false;
	}

	/**
	* Verifies the record type
	*
	* @param	string	The type
	*
	* @return 	boolean	Returns true if the address is valid
	*/
	function verify_rectype($type)
	{
		return in_array($type, array('content','read','view','visit','register','logon','logoff','other'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
