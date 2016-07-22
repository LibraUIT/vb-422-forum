<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * Widget DataManager
 * Note: The widget datamanager can be used to save the config for a widget.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_DM_Widget extends vB_DM
{
	/*Properties====================================================================*/

	/**
	* Field definitions.
	* The field definitions are in the form:
	*	array(fieldname => array(VF_TYPE, VF_REQ, VF_METHOD, VF_VERIFY)).
	*
	* @var array string => array(int, int, mixed)
	*/
	protected $fields = array(
		'widgetid' => 		array(vB_Input::TYPE_UINT,		self::REQ_INC,	self::VM_TYPE),
		'widgettypeid' => 	array(vB_Input::TYPE_UINT,		self::REQ_YES,	self::VM_CALLBACK,	array('$this', 'validateWidgetTypeID')),
		'title' => 			array(vB_Input::TYPE_NOHTMLCOND,self::REQ_YES,	self::VM_CALLBACK,	array('vB_Validate', 'stringLength', 1, 256)),
		'description' => 	array(vB_Input::TYPE_STR,		self::REQ_NO,	self::VM_TYPE),
		'config' => 		array(vB_Input::TYPE_NOCLEAN,	self::REQ_NO),
		'product' => 		array(vB_Input::TYPE_STR,		self::REQ_NO),
	);

	/**
	 * Map of table => field for fields that can automatically be updated with their
	 * set value.
	 *
	 * @var array (tablename => array(fieldnames))
	 */
	protected $table_fields = array(
		'cms_widget' =>			array('widgetid', 'widgettypeid', 'title', 'description', 'product')
	);

	/**
	 * Table name of the primary table.
	 *
	 * @var string
	 */
	protected $primary_table = 'cms_widget';

	/**
	 * vB_Item Class.
	 *
	 * @var string
	 */
	protected $item_class = 'vBCms_Item_Widget';


	/**
	 * Whether the insert id is required for further queries during an insert.
	 * The widgetid is required to save the config.
	 *
	 * @var bool
	 */
	protected $require_auto_increment_id = true;


	/**
	 * The nodeid for saving the config.
	 * Leave this unset or false to save the instance config
	 *
	 * @var int
	 */
	protected $nodeid;



	/*Validate======================================================================*/

	/**
	 * Validate the set widgettype.
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateWidgetTypeID($value, &$error)
	{
		if (vBCms_Types::instance()->widgetTypeEnabled($value))
		{
			return $value;
		}

		return false;
	}



	/*Set===========================================================================*/

	/**
	 * Specifies the node that the config is being updated for.
	 *
	 * @param int $nodeid
	 */
	public function setConfigNode($nodeid)
	{
		if (!is_numeric($nodeid))
		{
			throw (new vB_Exception_DM('Nodeid set for widget config is not an integer in DM \'' . get_class($this) . '\''));
		}

		$this->nodeid = $nodeid;
	}


	/**
	 * Resets all set changes.
	 */
	protected function Reset()
	{
		parent::Reset();
		unset($this->nodeid);
	}



	/*Save==========================================================================*/

	/**
	* Performs additional queries or tasks after saving.
	*
	* @param mixed								- The save result
	* @param bool $deferred						- Save was deferred
	* @param bool $replace						- Save used REPLACE
	* @param bool $ignore						- Save used IGNORE if inserting
	* @return bool								- Whether the save can be considered a success
	*/
	protected function postSave($result, $deferred, $replace, $ignore)
	{
		if (isset($this->set_fields['config']))
		{
			if ($this->isUpdating())
			{
				$this->assertItem();
				$id = $this->item->getId();
			}
			else
			{
				if (!$this->primary_id)
				{
					throw (new vB_Exception_DM('No primary id available for setting the widget config in DM \'' . get_class($this) . '\''));
				}

				$id = $this->primary_id;
			}

			// delete the old config
			vB::$db->query_write(
				"DELETE FROM " . TABLE_PREFIX . "cms_widgetconfig
				 WHERE widgetid = " . intval($id) . "
				AND nodeid = " . intval($this->nodeid)
			);

			// build the sql
			$sql = 'INSERT INTO ' . TABLE_PREFIX . 'cms_widgetconfig (widgetid, nodeid, name, value, serialized) VALUES ';
			$values = array();

			// write the new config
			foreach ($this->set_fields['config'] AS $cvar => $value)
			{
				if (is_resource($value))
				{
					throw (new vB_Exception_DM('Trying to set a resource as a widget config value'));
				}

				if (is_object($value) OR is_array($value))
				{
					$serialized = 1;
					$value = serialize($value);
				}
				else
				{
					$serialized = 0;
				}

				$values[] = '(' . intval($id) . ',' . intval($this->nodeid) . ',\'' . vB::$db->escape_string($cvar) . '\',\'' . vB::$db->escape_string($value) . '\',\'' . $serialized . '\')';
			}

			// insert config
			vB::$db->insert_multiple($sql, $values, true);
		}
		vB_Cache::instance()->event('widget_template_list');

		return $result;
	}


	/**
	* Resolves the condition SQL to be used in update queries.
	*
	* @param string $table						- The table to get the condition for
	* @return string							- The resolved sql
	*/
	protected function getConditionSQL($table)
	{
		$this->assertItem();

		return 'widgetid = ' . intval($this->item->getId());
	}



	/*Delete========================================================================*/

	/**
	* Additional tasks to perform after a delete.
	*
	* @param mixed								- The result of execDelete()
	*/
	function postDelete($result)
	{
		$this->assertItem();

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_widgetconfig
			WHERE widgetid = " . intval($this->item->getId())
		);

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/
