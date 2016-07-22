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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for widgets for the admincp
*
* @package	vBulletin
* @version	$Revision: 15275 $
* @date		$Date: 2006-07-13 03:11:20 -0700 (Thu, 13 Jul 2006) $
*/
class vB_DataManager_vBCms_Widget extends vB_DataManager
{
	/**
	* Array of recognised and required fields for a CMS layout
	*
	* @var	array
	*/
	var $validfields = array(
		'widgetid'      => array(TYPE_UINT,       REQ_INCR, 'return ($data > 0);'),
		'title'         => array(TYPE_NOHTMLCOND, REQ_YES),
		'description'   => array(TYPE_NOHTMLCOND, REQ_NO),
		'varname'       => array(TYPE_NOHTML,     REQ_YES)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'cms_widget';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('widgetid = %1$d', 'widgetid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_vBCms_Widget(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('vbcms_widgetdm_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		global $vbphrase;

		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('vbcms_widgetdm_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('vbcms_widgetdm_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		if ($widgetid = intval($this->fetch_field('widgetid')))
		{
			$vbulletin->db->query_write("
				DELETE widgetconfig, layoutwidget
				FROM " . TABLE_PREFIX . "cms_widgetconfig AS widgetconfig
				LEFT JOIN " . TABLE_PREFIX . "cms_layoutwidget AS layoutwidget
				 ON layoutwidget.widgetid = widgetconfig.widgetid
				WHERE widgetconfig.widgetid = " . intval($widgetid)
			);
		}

		($hook = vBulletinHook::fetch_hook('vbcms_widgetdm_postdelete')) ? eval($hook) : false;

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 15275 $
|| ####################################################################
\*======================================================================*/