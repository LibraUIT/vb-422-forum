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
 * CMS Rate DataManager
 * Note: The rate datamanager can be used to save cms content ratings.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_DM_Rate extends vB_DM
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
		'rateid' => 	array(vB_Input::TYPE_UINT,	self::REQ_INC,	self::VM_TYPE),
		'nodeid' => 	array(vB_Input::TYPE_UINT,	self::REQ_YES,	self::VM_CALLBACK,	array('$this', 'validateNode')),
		'userid' => 	array(vB_Input::TYPE_UINT,	self::REQ_NO,	self::VM_TYPE),
		'vote' =>		array(vB_Input::TYPE_INT,	self::REQ_YES,	self::VM_CALLBACK,	array('$this', 'validateVote')), # TYPE_INT to allow negative rating
		'ipaddress' => 	array(vB_Input::TYPE_STR,	self::REQ_AUTO)
	);

	/**
	 * Map of table => field for fields that can automatically be updated with their
	 * set value.
	 *
	 * @var array (tablename => array(fieldnames))
	 */
	protected $table_fields = array(
		'cms_rate' =>			array('rateid', 'nodeid', 'userid', 'vote', 'ipaddress')
	);

	/**
	 * Table name of the primary table.
	 *
	 * @var string
	 */
	protected $primary_table = 'cms_rate';

	/**
	 * vB_Item Class.
	 *
	 * @var string
	 */
	protected $item_class = 'vBCms_Item_Rate';


	/**
	 * Whether the insert id is required for further queries during an insert.
	 * The rateid is required to save the config.
	 *
	 * @var bool
	 */
	protected $require_auto_increment_id = true;

	/*** The highest allowed vote, or perfect score  ***/
	protected $max_vote = 5;


	/*Validate======================================================================*/

	/**
	* Checks that the vote is between 0 and 5
	*
	* @param mixed $value						- The value to validate
	* @param mixed $error						- The var to assign an error to
	* @return mixed | bool						- The filtered value or boolean false
	*/
	protected function validateVote($vote, &$error)
	{
		if ($vote >= 0 AND $vote <= $this->max_vote)
		{
			return $vote;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Validates a nodeid.
	 * Checks if the node exists.
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateNode($value, &$error)
	{
		$node = new vBCms_Item_Content($value);

		if (!$node->isValid())
		{
			return false;
		}

		return $value;
	}

	/*Save==========================================================================*/

	/**
	 * Run any checks or tranformations before saving.
	 * Return false to cancel the save.
	 *
	 * @param bool $deferred					- Save will be deferred until shutdown
	 * @param bool $replace						- Save will use REPLACE
	 * @param bool $ignore						- Save will use IGNORE if inserting
	 *
	 * @return bool								- Whether to save
	 */
	protected function preSave($deferred = false, $replace = false, $ignore = false)
	{
		$this->set_fields['ipaddress'] = IPADDRESS;
		if ($this->isUpdating())
		{
			$nodeid = intval($this->set_fields['nodeid']);
			if (!$nodeid)
			{
				return false;
			}
			if ($userid = intval($this->set_fields['userid']))
			{
				$exists = vB::$db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "cms_rate
					WHERE userid = $userid
						AND nodeid = " . $nodeid
				);
			}
			else if ($ipaddress = $this->set_fields['ipaddress'])
			{
				$exists = vB::$db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "cms_rate
					WHERE userid = 0
						AND nodeid = " . $nodeid . "
						AND ipaddress = '" . vB::$db->escape_string($ipaddress) . "'
				");
			}

			if ($exists)
			{
				$this->existing_fields = $exists;
			}
		}
		return true;
	}

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
		$nodeid = intval($this->set_fields['nodeid']);
		if (!$nodeid)
		{
			return false;
		}

		$nodedm = new vBCms_DM_Node();
		$nodedm->setExisting($nodeid);
		$nodedm->set('nodeid', $nodeid);

		if ($this->isUpdating())
		{
			$nodedm->removeRating($this->existing_fields['vote']);
		}

		$nodedm->addRating($this->getField('vote'));
		$nodedm->save();
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

		return 'rateid = ' . intval($this->item->getId());
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

		$nodeid = intval($this->item->getNodeId());

		$nodedm = new vBCms_DM_Node();
		$nodedm->setExisting($nodeid);
		$nodedm->set('nodeid', $nodeid);
		$nodedm->removeRating(intval($this->item->getVote()));
		$nodedm->save();

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/