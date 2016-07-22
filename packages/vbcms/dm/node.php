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
 * CMS Content Data Manager class.
 * Manages the CMS content nodes.
 *
 * Node: The nodes use the nested set model.  The DM is also responsible for
 * deleting and moving nodes and maintaining the integrity of the tree structure
 * as well as managing the nodes' associated information.
 *
 * @TODO: Provide move methods and support the various move types as defined by the
 * self::MOVE_ constants in update.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_DM_Node extends vBCMS_DM_Content
{
	/*Constants=====================================================================*/

	/**
	 * Move actions for orphaned nodes after a delete.
	 */
	const MOVE_PARENT = 1;
	const MOVE_ROOT = 2;
	const MOVE_REMOVE = 3;



	/*Properties====================================================================*/

	/**
	* Field definitions.
	* The field definitions are in the form:
	*	array(fieldname => array(VF_TYPE, VF_REQ, VF_METHOD, VF_VERIFY)).
	*
	* @var array string => array(int, int, mixed)
	*/
	protected $fields = array(
		'nodeid' => 			array(vB_Input::TYPE_UINT,		self::REQ_INC,	self::VM_TYPE),
		'contenttypeid' => 		array(vB_Input::TYPE_UINT,		self::REQ_YES,	self::VM_CALLBACK,	array('$this', 'validateContentTypeID')),
		'contentid' => 			array(vB_Input::TYPE_NOHTMLCOND, self::REQ_NO,	self::VM_TYPE),
		'item_id' => 			array(vB_Input::TYPE_NOHTMLCOND,self::REQ_NO,	self::VM_TYPE),
		'url' => 				array(vB_Input::TYPE_STR,		self::REQ_NO,	self::VM_CALLBACK,	array('$this', 'validateURL')),
		'nodeleft' =>			array(vB_Input::TYPE_NOCLEAN,	self::REQ_AUTO),
		'noderight' =>			array(vB_Input::TYPE_NOCLEAN,	self::REQ_AUTO),
		'parentnode' => 		array(vB_Input::TYPE_UINT,		self::REQ_NO,	self::VM_CALLBACK,	array('$this', 'validateParent')),
		'styleid' =>			array(vB_Input::TYPE_NOCLEAN,	self::REQ_NO,	self::VM_CALLBACK,	array('$this', 'validateStyleID')),
		'layoutid' =>			array(vB_Input::TYPE_UINT,		self::REQ_NO,	self::VM_CALLBACK,	array('$this', 'validateLayoutID')),
		'userid' =>				array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'publicpreview' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'comments_enabled' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'publicpreview' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'permissionsfrom' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'auto_displayorder' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'creationdate' =>		array(vB_Input::TYPE_UNIXTIME,	self::REQ_AUTO),
		'lastupdated' =>		array(vB_Input::TYPE_UNIXTIME,	self::REQ_NO),
		'publishdate' =>		array(vB_Input::TYPE_UNIXTIME,	self::REQ_NO),
		'setpublish' =>		array(vB_Input::TYPE_BOOL,		self::REQ_NO),
		'issection' =>			array(vB_Input::TYPE_BOOL,		self::REQ_NO),
		'description' =>		array(vB_Input::TYPE_NOHTMLCOND,self::REQ_NO),
		'title' => 				array(vB_Input::TYPE_NOHTMLCOND,self::REQ_NO),
		'html_title' => 		array(vB_Input::TYPE_NOHTMLCOND,self::REQ_NO),
		'viewcount' =>			array(vB_Input::TYPE_UINT,		self::REQ_AUTO),
		'workflowid' => 		array(vB_Input::TYPE_UINT,		self::REQ_AUTO),
		'workflowdate' =>		array(vB_Input::TYPE_UNIXTIME,	self::REQ_AUTO),
		'workflowstatus' => 	array(vB_Input::TYPE_STR,		self::REQ_AUTO),
		'workflowcheckedout' => array(vB_Input::TYPE_BOOL,		self::REQ_AUTO),
		'workflowpending' => 	array(vB_Input::TYPE_BOOL, 		self::REQ_AUTO),
		'workflowlevelid' => 	array(vB_Input::TYPE_UINT,		self::REQ_AUTO),
		'associatedthreadid' => 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'keywords' => 			array(vB_Input::TYPE_STR,		self::REQ_NO),
		'ratingnum' =>			array(vB_Input::TYPE_UINT,		self::REQ_AUTO),
		'ratingtotal' =>		array(vB_Input::TYPE_UINT,		self::REQ_AUTO),
		'rating' =>				array(vB_Input::TYPE_UNUM,		self::REQ_AUTO),
		'config' => 			array(vB_Input::TYPE_NOCLEAN,	self::REQ_NO),
		'navigation' =>		array(vB_Input::TYPE_ARRAY_INT,	self::REQ_NO),
		'showtitle' => 		array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showuser' => 			array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showpreviewonly' => 		array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showupdated' => 		array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showviewcount' => 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showpublishdate' => 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'settingsforboth' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'includechildren' =>	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'hidden' =>				array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'shownav' =>			array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'nosearch' =>			array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showall' 	=> 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'editshowchildren' 	=> 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'showrating' 	=> 	array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'new' =>					array(vB_Input::TYPE_BOOL), self::REQ_AUTO
	);
	/**
	 * Map of table => field for fields that can automatically be updated with their
	 * set value.
	 *
	 * @var array (tablename => array(fieldnames))
	 */
	protected $table_fields = array(
		'cms_node' => 		array('nodeid', 'contenttypeid', 'contentid', 'url', 'nodeleft', 'noderight', 'new',
								  'parentnode', 'styleid', 'layoutid', 'userid', 'publishdate', 'setpublish', 'issection',
								'lastupdated', 'publicpreview',  'comments_enabled', 'auto_displayorder', 'permissionsfrom',
								'showtitle', 'showuser', 'showpreviewonly',	'showupdated', 'showviewcount', 'showpublishdate',
								'settingsforboth', 'includechildren', 'showall', 'editshowchildren', 'showrating',
								'hidden', 'shownav', 'nosearch'),
		'cms_nodeinfo' =>	array('nodeid', 'description', 'viewcount', 'creationdate',
								  'workflowid', 'workflowdate', 'workflowstatus', 'title', 'html_title',
									'workflowcheckedout', 'workflowpending', 'workflowlevelid', 'associatedthreadid',
									'keywords','ratingnum','ratingtotal','rating')
	);

	/**
	 * Table name of the primary table.
	 *
	 * @var string
	 */
	protected $primary_table = 'cms_node';

	/**
	 * A primary id for REQ_INC fields.
	 * @see vB_DM::save()
	 *
	 * @var mixed
	 */
	protected $primary_id = 'nodeid';

	/**
	 * vB_Item Class.
	 * Class of the vB_Item that this DM is responsible for updating and/or
	 * creating.  This is used to instantiate the item when lazy loading based on an
	 * item id.
	 *
	 * @var string
	 */
	protected $item_class = 'vBCms_Item_Content';

	/** This is used in child classes to save type-specific information ****/
	protected $type_table = false;

	/** This is used in child classes to save type-specific information ****/
	protected $type_fields = array();

	/** This is used in child classes to save type-specific information. These are fields
	* we can save automatically. ****/
	protected $type_table_fields = array();

	/** This is used in child classes to save type-specific information ****/
	protected $type_set_fields = array();

	/**
	 * Whether the insert id is required for further queries during an insert.
	 * This can be set manually, or left to be resolved with
	 * vB_DM::requireAutoIncrementId().
	 *
	 * @var bool
	 */
	protected $require_auto_increment_id = true;



	/**
	 * The nearest parent section node.
	 * This may not be the parentnode id given, but the nearest parent that is a
	 * section.
	 * @see vBCms_DM_Node::validateParent()
	 *
	 * @var int
	 */
	protected $section;

	//This will load and save the basic form data that's in the system-wide sections
	public function saveFromForm($nodeid)
	{
		vB::$vbulletin->input->clean_array_gpc('r', array(
			'html_title' => vB_Input::TYPE_NOHTML,
			'cms_node_title' => vB_Input::TYPE_NOHTML,
			'cms_node_url' => vB_Input::TYPE_STR,
			'description' => vB_Input::TYPE_NOHTML,
			'layoutid' => vB_Input::TYPE_INT,
			'section_styleid' => vB_Input::TYPE_NOCLEAN,
			'setpublish' => vB_Input::TYPE_INT,
			'publishdate' => vB_Input::TYPE_STR,
			'publishtime' => vB_Input::TYPE_ARRAY,
			'publicpreview' => vB_Input::TYPE_INT,
			'comments_enabled' => vB_Input::TYPE_INT,
			'keywords' => vB_Input::TYPE_NOHTML,
			'section_menu_inherit' => TYPE_BOOL,
			'section_menu_sections' => TYPE_ARRAY_INT,
			'showtitle' => vB_Input::TYPE_INT,
			'showuser' => 	vB_Input::TYPE_INT,
			'showpreviewonly' => vB_Input::TYPE_INT,
			'showupdated' => vB_Input::TYPE_INT,
			'showviewcount' => vB_Input::TYPE_INT,
			'showpublishdate' => vB_Input::TYPE_INT,
			'settingsforboth' => vB_Input::TYPE_INT,
			'includechildren' => vB_Input::TYPE_INT,
			'showall' => vB_Input::TYPE_INT,
			'editshowchildren' => vB_Input::TYPE_INT,
			'showrating' => vB_Input::TYPE_INT,
			'hidden' => vB_Input::TYPE_INT,
			'shownav' => vB_Input::TYPE_INT,
			'nosearch' => vB_Input::TYPE_INT,
			'display_order_select' => TYPE_ARRAY_INT
		));
		$this->set('nodeid', $nodeid);
		//set the values

		if (vB::$vbulletin->GPC_exists['cms_node_url'] and vB::$vbulletin->GPC['cms_node_url'] != '')
		{
			//Let's do some cleanup of the url.
			$this->set('url', vB_Friendly_Url::clean_entities(vB::$vbulletin->GPC['cms_node_url'], true));
			$this->item->setInfo(array('url'=> vB::$vbulletin->GPC['cms_node_url']));
		}

		if (vB::$vbulletin->GPC_exists['description'])
		{
			$this->set('description', vB::$vbulletin->GPC['description']);
			$this->item->setInfo(array('description'=> vB::$vbulletin->GPC['description']));

		}

		if (vB::$vbulletin->GPC_exists['cms_node_title'])
		{
			$this->set('title', vB::$vbulletin->GPC['cms_node_title']);
			$this->item->setInfo(array('title'=> vB::$vbulletin->GPC['cms_node_title']));
			$this->setNodeTitle(vB::$vbulletin->GPC['cms_node_title']);
		}


		if (vB::$vbulletin->GPC_exists['html_title'])
		{
			$this->set('html_title', vB::$vbulletin->GPC['html_title']);
			$this->item->setInfo(array('html_title'=> vB::$vbulletin->GPC['html_title']));
		}

		if (vB::$vbulletin->GPC_exists['setpublish'])
		{
			$this->set('setpublish', vB::$vbulletin->GPC['setpublish']);
			$this->item->setInfo(array('setpublish'=> vB::$vbulletin->GPC['setpublish']));
		}

		if (vB::$vbulletin->GPC_exists['publishdate'] AND intval(vB::$vbulletin->GPC['publishdate']))
		{
			$str_date = str_replace('-', '/', vB::$vbulletin->GPC['publishdate']);


			//if the user set a time it's from a different variable.
			if (vB::$vbulletin->GPC_exists['publishtime'])
			{
				$str_date .= ' ' . vB::$vbulletin->GPC['publishtime']['hour'] . ':' .
					vB::$vbulletin->GPC['publishtime']['minute'] .  ' ' .
					vB::$vbulletin->GPC['publishtime']['offset'];
			}

			// make sure we adjust for local time before saving publish timestamp
			$offset = vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo, false) - date('Z');
			$publishdate = strtotime($str_date,0) - $offset;
			$this->set('publishdate', $publishdate);
			$this->item->setInfo(array('publishdate'=> $publishdate));
		}

		if (vB::$vbulletin->GPC_exists['keywords'])
		{
			$this->set('keywords', vB::$vbulletin->GPC['keywords']);
			$this->item->setInfo(array('keywords'=> vB::$vbulletin->GPC['keywords']));
		}

		if (vB::$vbulletin->GPC_exists['section_styleid'])
		{
			$this->set('styleid', vB::$vbulletin->GPC['section_styleid']);
			$this->item->setInfo(array('styleid'=> vB::$vbulletin->GPC['section_styleid']));
		}

		if (vB::$vbulletin->GPC_exists['layoutid'])
		{
			$this->set('layoutid', vB::$vbulletin->GPC['layoutid']);
			$this->item->setInfo(array('layoutid'=> vB::$vbulletin->GPC['layoutid']));
		}

		if (vB::$vbulletin->GPC_exists['publicpreview'])
		{
			$this->set('publicpreview', vB::$vbulletin->GPC['publicpreview']);
			$this->item->setInfo(array('publicpreview'=> vB::$vbulletin->GPC['publicpreview']));
		}

		if (vB::$vbulletin->GPC_exists['comments_enabled'])
		{
			$this->set('comments_enabled', vB::$vbulletin->GPC['comments_enabled']);
			$this->item->setInfo(array('comments_enabled'=> vB::$vbulletin->GPC['comments_enabled']));
		}

		if (vB::$vbulletin->GPC_exists['showtitle'])
		{
			$this->set('showtitle', vB::$vbulletin->GPC['showtitle']);
			$this->item->setInfo(array('showtitle'=> vB::$vbulletin->GPC['showtitle']));
		}

		if (vB::$vbulletin->GPC_exists['showuser'])
		{
			$this->set('showuser', vB::$vbulletin->GPC['showuser']);
			$this->item->setInfo(array('showuser'=> vB::$vbulletin->GPC['showuser']));
		}

		if (vB::$vbulletin->GPC_exists['showpreviewonly'])
		{
			$this->set('showpreviewonly', vB::$vbulletin->GPC['showpreviewonly']);
			$this->item->setInfo(array('showpreviewonly'=> vB::$vbulletin->GPC['showpreviewonly']));
		}

		if (vB::$vbulletin->GPC_exists['showupdated'])
		{
			$this->set('showupdated', vB::$vbulletin->GPC['showupdated']);
			$this->item->setInfo(array('showupdated'=> vB::$vbulletin->GPC['showupdated']));
		}

		if (vB::$vbulletin->GPC_exists['showviewcount'])
		{
			$this->set('showviewcount', vB::$vbulletin->GPC['showviewcount']);
			$this->item->setInfo(array('showviewcount'=> vB::$vbulletin->GPC['showviewcount']));
		}

		if (vB::$vbulletin->GPC_exists['showpublishdate'])
		{
			$this->set('showpublishdate', vB::$vbulletin->GPC['showpublishdate']);
			$this->item->setInfo(array('showpublishdate'=> vB::$vbulletin->GPC['showpublishdate']));
		}

		if (vB::$vbulletin->GPC_exists['settingsforboth'])
		{
			$this->set('settingsforboth', vB::$vbulletin->GPC['settingsforboth']);
			$this->item->setInfo(array('settingsforboth'=> vB::$vbulletin->GPC['settingsforboth']));
		}

		if (vB::$vbulletin->GPC_exists['includechildren'])
		{
			$this->set('includechildren', vB::$vbulletin->GPC['includechildren']);
			$this->item->setInfo(array('includechildren'=> vB::$vbulletin->GPC['includechildren']));
		}

		if (vB::$vbulletin->GPC_exists['showall'])
		{
			$this->set('showall', vB::$vbulletin->GPC['showall']);
			$this->item->setInfo(array('showall'=> vB::$vbulletin->GPC['showall']));
		}

		if (vB::$vbulletin->GPC_exists['showrating'])
		{
			$this->set('showrating', vB::$vbulletin->GPC['showrating']);
			$this->item->setInfo(array('showrating'=> vB::$vbulletin->GPC['showrating']));
		}

		if (vB::$vbulletin->GPC_exists['hidden'])
		{
			$this->set('hidden', vB::$vbulletin->GPC['hidden']);
			$this->item->setInfo(array('hidden'=> vB::$vbulletin->GPC['hidden']));
		}

		if (vB::$vbulletin->GPC_exists['shownav'])
		{
			$this->set('shownav', vB::$vbulletin->GPC['shownav']);
			$this->item->setInfo(array('shownav'=> vB::$vbulletin->GPC['shownav']));
		}

		if (vB::$vbulletin->GPC_exists['nosearch'])
		{
			$this->set('nosearch', vB::$vbulletin->GPC['nosearch']);
			$this->item->setInfo(array('nosearch'=> vB::$vbulletin->GPC['nosearch']));
		}

		$this->set('editshowchildren',vB::$vbulletin->GPC_exists['editshowchildren'] ?
				 vB::$vbulletin->GPC['editshowchildren'] : 0);
		$this->item->setInfo(array('editshowchildren'=> vB::$vbulletin->GPC['editshowchildren']));


		if (vB::$vbulletin->GPC['section_menu_inherit'])
		{
			$section_menu = false;
		}
		else
		{
			$pre_sorted_section_menu = vB::$vbulletin->GPC['section_menu_sections'];
			$section_menu = array();

			//////////////////////////////////////////////////////
			// sort section_menu based on display order array
			//////////////////////////////////////////////////////
			$display_order = vB::$vbulletin->GPC['display_order_select'];
			asort($display_order, SORT_NUMERIC);
			// loop through sorted display order array, and grab correlating values from the
			// pre-sorted section menu array and put them into the sorted section menu
			foreach ($display_order as $key => $value)
			{
				// if display order is zero, or that item was not selected to be in the menu
				// we can ignore that item from the display order array
				if ($value != '0' AND isset($pre_sorted_section_menu[$key]))
				{
					$section_menu[] = $pre_sorted_section_menu[$key];
					unset($pre_sorted_section_menu[$key]);
				}
			}

			// if there are any section items that did not have a display order, we do not
			// care about their order and can add them into the section menu wherever we want
			foreach ($pre_sorted_section_menu as $value)
			{
				$section_menu[] = $value;
			}

		}

		$this->set('navigation', $section_menu);
		// add node info
		$result = $this->save();

		//That's the main data. Creating an associatedthreadid, if necessary,
		// is handled in vbcms/dm/content.php
		return $result;
	}

	/*Set===========================================================================*/

	/**
	 * Prepare fields before saving.
	 */
	protected function prepareFields()
	{
		parent::prepareFields();


		//The parent DM uses item_id, but the actual database field is $contentid. Let's make
		// sure both are set.
		if (isset($this->set_fields['contentid']) AND !isset($this->item_id))
		{
			$this->item_id = $this->set_fields['contentid'];
		}

		$this->set_fields['new'] = !intval($this->isUpdating());

		if (count($this->set_fields))
		{
			$this->set_fields['lastupdated'] = TIMENOW;
		}

		if (!$this->isUpdating() AND ($contenttype = $this->set_fields['contenttypeid']))
		{

			if ($controller = vB_Types::instance()->getContentTypeController($contenttype));
			{
				$this->set_fields['issection'] = (bool)$controller->isSection();
			}

			if (!isset($this->set_fields['title']))
			{
				$this->set_fields['title'] = vB_Types::instance()->getUntitledContentTypeTitle($contenttype);
			}
		}
	}



	/*Set===========================================================================*/

	/**
	 * Sets a field value.
	 * Set the nearest valid section parent as the parentnode.
	 * @see vBCms_DM_Node::validateParent()
	 *
	 * @param string $fieldname					- The name of the field to set
	 * @param mixed $value						- The value to set
	 */
	protected function setField($fieldname, $value)
	{
		if ($fieldname == 'parentnode')
		{
			$this->set_fields[$fieldname] = $this->section;
		}
		else
		{
			parent::setField($fieldname, $value);
		}
	}



	/*Validate======================================================================*/

	/**
	 * Ensures that a contenttypeid is valid.
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateContentTypeID($value, &$error)
	{
		if (vB_Types::instance()->contentTypeEnabled($value))
		{
			return $value;
		}

		return false;
	}


	/**
	 * Validates the URL segment
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateURL($value, &$error)
	{
		if (!isset($this->set_fields['url']))
		{
			return $value;
		}

		$nodeid =  $this->set_fields['nodeid'];

		if (($length = vbstrlen($value)) > 256)
		{
			// too long
			$error = new vB_Phrase('error', 'validation_toolong_x_y', $length, 256);
			return false;
		}

		//First thing- let's make sure this URL is not already in use.
		if ( $record = vB::$vbulletin->db->query_first($sql = "SELECT nodeid FROM " . TABLE_PREFIX .
			"cms_node WHERE new != 1 AND lower(url) = '" . vB::$vbulletin->db->escape_string(strtolower($this->set_fields['url'])) .
			(isset($this->set_fields['nodeid']) ? "' AND nodeid <> $nodeid;" : "' ") ))
		{
			//throw (new vB_Exception_Model($vbphrase['url_in_use'] ));
			standard_error(fetch_error('url_in_use'));
			return false;
		}

		return $value;
	}


	/**
	 * Gets an new URL segment "guaranteed" to be valid
	 *
	 * @param	string 	optional	the desired title. Defaults to $this->title
	 * @return string					- The assigned string
	 */
	public function getValidURL($title = false)
	{
		global $vbphrase;
		//If we don't have a nodeid we can't continue.
		if (!$title)
		{
			$title= $this->title;
		}

		if (empty($title))
		{
			//well, we got nothing so we'll have to improvise.
			$title = new $vbphrase['new_page'];
		}

		$error = string;
		$count = 0;
		$base_url = strtolower(vB_Friendly_Url::clean_entities($title));
		$test_url = $base_url;

		while ($count <= 250)
		{
			$sql = "SELECT nodeid FROM " . TABLE_PREFIX .
				"cms_node WHERE new != 1 AND lower(url) = '$test_url' " .
				(isset($this->set_fields['nodeid']) ? "' AND nodeid <> $nodeid;" : '');
			$record = vB::$vbulletin->db->query_first($sql);
			$count++;
			if (empty($record))
			{
				$this->set_fields['url'] = $test_url;
				return $test_url;
			}
			$test_url = $base_url . '_' . $count;
		}

		return false;
	}





	/**
	 * Validates a parentid.
	 * Checks if the parent exists.
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateParent($value, &$error)
	{
		$parent = new vBCms_Item_Content($value);

		if (!$parent->isValid())
		{
			return false;
		}

		$this->section = $parent->getSectionId();

		return $value;
	}


	/**
	 * Validates a layout id
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateLayoutID($value, &$error)
	{
		//If the value is 0, we're just setting to default.
		if (("" === $value) OR ($value < 1) OR (NULL === $value))
		{
			return $this->raw_fields['layoutid'] = NULL;
		}
		$layout = new vBCms_Item_Layout($value);

		if ($layout->isValid())
		{
			return $value;
		}

		return false;
	}


	/**
	 * Validates a style id
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	protected function validateStyleID($value, &$error)
	{
		if (("" === $value) OR ($value < 1) OR (NULL === $value))
		{
			return $this->raw_fields['styleid'] = NULL;
		}

		return intval($value);
	}


	/*** This function validates type-specific field information. It's taken
	* directly from vb/dm.php, modified to use the type_ variables
	* ***/
	protected function validateTypeField($fieldname, $value, &$error)
	{
		if (!isset($this->type_fields[$fieldname]))
		{
			throw (new vB_Exception_DM('Field \'' . htmlspecialchars_uni($fieldname) . '\' checked for validation in DM \'' . get_class($this) . '\' is undefined'));
		}

		$field = $this->type_fields[$fieldname];

		// Clean the value according to it's type
		$value = vB_Input::clean($value, $this->type_fields[$fieldname][self::VF_TYPE]);

		// If no validation method has been specified then we're done
		if (!isset($field[self::VF_METHOD]) OR (self::VM_TYPE == $field[self::VF_METHOD]))
		{
			return $value;
		}

		if (self::VM_LAMBDA == $field[self::VF_METHOD])
		{
			$lambda = create_function('$value', '&$error', $field[self::VF_VERIFY]);
			$value = $lambda($value, $error);
		}
		else if (self::VM_CALLBACK == $field[self::VF_METHOD])
		{
			// ensure a callback is specified
			if (!is_array($field[self::VF_VERIFY]) OR !sizeof($field[self::VF_VERIFY] >= 2))
			{
				throw (new vB_Exception_DM('Invalid callback function specified for field \'' . htmlspecialchars_uni($fieldname) . '\' in DM \'' . get_class($this) . '\''));
			}

			// check for extra parameters
			if (sizeof($field[self::VF_VERIFY] > 2))
			{
				// extract callback
				$callback = array_slice($field[self::VF_VERIFY], 0, 2);

				// add value and error reference as the first paramaters
				$params = array($value);
				$params[] =& $error;

				// extract defined parameters
				$params = array_merge($params, array_slice($field[self::VF_VERIFY], 2));

				// check if callback is this
				if ($callback[0] == '$this')
				{
					$value = call_user_func_array(array($this, $callback[1]), $params);
				}
				else
				{
					// call user func
					$value = call_user_func_array($callback, $params);
				}
			}
			else
			{
				// no extra parameters in field definition
				$value = call_user_func($field[self::VF_VERIFY], $value, $error);
			}
		}
		else
		{
			throw (new vB_Exception_DM('Unknown verify method given for dm field \'' . htmlspecialchars_uni($fieldname) . '\' in dm \'' . get_class($this) . '\''));
		}

		if (false !== $value)
		{
			return $value;
		}

		if (!$error)
		{
			$error = new vB_Phrase('error', 'invalid_dm_value_x_y', $fieldname, htmlspecialchars_uni($value));
		}


		return $value;
	}

	/**** We need to set type-specific fields like we do the generic fields.
	* If this is a type-specific field we handle it here. If not we pass
	* to the generic DM.
	* *****/
	public function set($fieldname, $value)
	{

		if (!$this->type_table OR ! count($this->type_fields)
			or ! isset($this->type_fields[$fieldname]) )
		{
			return parent::set($fieldname, $value);
		}

		//if we got here, we're validating a type-specific field.
		$error = false;
		if (false === $value)
		{
			$value = '';
		}

		if (false === ($value = $this->validateTypeField($fieldname, $value, $error)))
		{
			if ($this->strict)
			{
				throw (new vB_Exception_DM('Value given to set DM \'' . get_class($this) . '\' field \'' . hmtlspecialchars($fieldname) . '\' is not valid: ' . $error));
			}

			$this->error($error, $fieldname);
		}

		if ($fieldname == 'contentid')
		{
			//no more checking.
			$this->type_set_fields['contentid'] = $value;
		}

		else
		{
			if (self::REQ_AUTO == $this->type_fields[$fieldname][self::VF_REQ])
			{
				throw (new vB_Exception_DM('Cannot set the value of automatic field \'' . htmlspecialchars_uni($fieldname) . '\' in DM \'' . get_class($this) . '\''));
			}

			$this->type_set_fields[$fieldname] = $value;
		}

	}

	/*** Extended getField to allow retrieval of type-specific data
	****/
	public function getField($fieldname, $ignore_errors = false)
	{
		if (isset($this->type_set_fields[$fieldname]))
		{
			return $this->type_set_fields[$fieldname];
		}
		else
		{
			return parent::getField($fieldname, $ignore_errors);
		}
	}

	/*** We save the type-specific data, if there is any.
	****/
	protected function saveTypeData()
	{
		$error = '';
		$valid_values = array();
		if ($this->type_table AND count($this->type_set_fields))
		{
			foreach($this->type_set_fields as $fieldname => $value)
			{

			//the contentid field gets special handling.
				$valid_values[$fieldname] = ((vB_Input::TYPE_STR == $this->type_fields[$fieldname][self::VF_TYPE]
					or vB_Input::TYPE_NOHTMLCOND == $this->type_fields[$fieldname][self::VF_TYPE]
					or vB_Input::TYPE_NOTRIM == $this->type_fields[$fieldname][self::VF_TYPE]
					or vB_Input::TYPE_NOHTML == $this->type_fields[$fieldname][self::VF_TYPE])?
				"'" . vB::$vbulletin->db->escape_string($value) . "'"
				: $value);
			}
		}

		//validation errors raise an exception, so if we got here the fields are O.K.
		if (!count ($valid_values))
		{
			return true;
		}


		//We have data to save. If we have a contentid we do an update. Otherwise we
		// do an insert.
		if (isset($this->set_fields['contentid']) AND intval($this->set_fields['contentid']))
		{
			//this is an update
			$sql = "UPDATE " . TABLE_PREFIX . $this->type_table . " set ";
			$updates = array();

			foreach ($valid_values as $field => $value )
			{
				$updates[] = "$field = $value";
			}

			$sql .= implode(', ', $updates);
			//get the "where clause
			if (method_exists($this, 'getTypeConditionSQL'))
			{
				$sql .= " WHERE " .$this->getTypeConditionSQL($type_table);
			}
			else
			{
				$where = " WHERE contentid = " . $this->set_fields['contentid'];
			}
			vB::$vbulletin->db->query_write($sql);
			return $this->set_fields['contentid'];
		}
		else
		{
			$sql = "INSERT INTO ". TABLE_PREFIX . $this->type_table . " ("
				. implode(', ', array_keys($valid_values)) . ") values(" .
				implode(', ', $valid_values) . ") ";
			//we are doing an insert
			vB::$vbulletin->db->query_write($sql);
			$this->set('contentid', vB::$vbulletin->db->insert_id());
			return vB::$vbulletin->db->insert_id();
		}

	}

	/**
	 * Allows the node to do any final updates before save
	 * We save type specific data here. That allows us to have the
	 * content id available for saving in the node table.
	 * 
	 * @param  boolean $deferred added for PHP 5.4 strict standards compliance
	 * @param  boolean $replace  added for PHP 5.4 strict standards compliance
	 * @param  boolean $ignore   added for PHP 5.4 strict standards compliance
	 * @return [int|boolean]
	 */
	protected function preSave($deferred = false, $replace = false, $ignore = false)
	{
		//now, if we have type-specific data we need to save it.
		$item_id = $this->saveTypeData();

		parent::preSave();
		return $item_id;
	}
	/*Save==========================================================================*/

	/**
	* Resolves the condition SQL to be used in update queries.
	* This method is abstract and must be defined as there should always be a
	* condition for an existing item.
	*
	* @param string $table						- The table to get the condition for
	* @return string							- The resolved sql
	*/
	protected function getConditionSQL($table)
	{
		$this->assertItem();

		return 'nodeid = ' . intval($this->item->getNodeId());
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
		//First let's handle the configuration.
		if (isset($this->set_fields['config']))
		{
			if ($this->isUpdating())
			{
				$this->assertItem();
				$id = $this->item->getNodeId();
			}
			else
			{
				if (!$this->primary_id)
				{
					throw (new vB_Exception_DM('No primary id available for setting the node config in DM \'' . get_class($this) . '\''));
				}

				$id = $this->primary_id;
			}

			// delete the old config
			vB::$db->query_write(
				'DELETE FROM ' . TABLE_PREFIX . 'cms_nodeconfig
				 WHERE nodeid = ' . $id);

			// build the sql
			$sql = 'INSERT INTO ' . TABLE_PREFIX . 'cms_nodeconfig (nodeid, name, value, serialized) VALUES ';
			$values = array();

			// write the new config
			foreach ($this->set_fields['config'] AS $cvar => $value)
			{
				if (is_resource($value))
				{
					throw (new vB_Exception_DM('Trying to set a resource as a node config value'));
				}

				if (is_object($value) OR is_array($value))
				{
					$serialized = true;
					$value = serialize($value);
				}
				else
				{
					$serialized = false;
				}

				$values[] = '(' . $id . ', \'' . vB::$db->escape_string($cvar) . '\',\'' . vB::$db->escape_string($value) . '\',\'' . intval($serialized) . '\')';
			}
			// insert config
			vB::$db->insert_multiple($sql, $values, true);

		}

		//and set permissionsfrom the parent. Let's do this so we fix any close records.
		$nodeid = (isset($this->set_fields['nodeid']) ? $this->set_fields['nodeid'] : $this->primary_id);
		if (!intval($this->set_fields['permissionsfrom']))
		{
			// There are two possibilities:
			// 1)If this is a section and it has its own permissionsfrom, then we do nothing
			// 2)Otherwise it gets the permissions from its new parent.

			if (!$this->item OR ($this->item->getPermissionsFrom() != $this->item->getNodeId())
				OR ($this->item->getClass() != 'Section'))
			{

				//we'll pull from our parent.
				$rst = vB::$vbulletin->db->query_read("SELECT parent.nodeid, parent.parentnode,
					parent.permissionsfrom, parent.nodeleft, parent.noderight
					FROM " . TABLE_PREFIX . "cms_node AS node INNER JOIN " . TABLE_PREFIX .
					"cms_node AS parent ON (node.nodeleft >= parent.nodeleft AND node.nodeleft <=parent.noderight)
					WHERE node.nodeid = $nodeid
					ORDER BY parent.nodeleft DESC");
				//The default/last fallback is to get our permissions from the root node.
				$permissionsfrom = 1;
				while($record = vB::$vbulletin->db->fetch_array($rst))
				{
					if (intval($record['permissionsfrom']))
					{
						$permissionsfrom = $record['permissionsfrom'];
						if (intval($record['permissionsfrom']) != intval($nodeid))
						{
							break;
						}
					}
				}
				//either we found a parent with a permissionsfrom, or we hit the top- which is
				// just as good.
				vB::$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "cms_node SET permissionsfrom = " .
					$permissionsfrom . " WHERE nodeid = $nodeid" ) ;
			}
		}

		if (isset($this->set_fields['navigation']))
		{
			$nodeid = intval((isset($this->set_fields['nodeid']) ? $this->set_fields['nodeid'] : $this->item->getNodeId()));

			// if there is array for navigation menu, it means we are not inheriting from parent
			// so we must add/modify the record in the navigation table for this node
			if (is_array($this->set_fields['navigation']))
			{
				vB::$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "cms_navigation
					SET nodeid = $nodeid,
						nodelist = '" . implode(',', $this->set_fields['navigation']) . "'
				");
			}

			// if this is not an array, it means the drop-down was selected to inherit from parent
			// so delete any record in the navigation table for this node
			else
			{
				vB::$vbulletin->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "cms_navigation
					WHERE nodeid = $nodeid
				");
			}

		}

		if (isset($this->set_fields['setpublish']) OR isset($this->set_fields['navigation']))
		{
				// clear the navbar cache
			vB_Cache::instance()->event(array(vBCms_NavBar::GLOBAL_CACHE_EVENT,
				vBCms_NavBar::getCacheEventId($this->item->getNodeId()),
				$this->item->getCacheEvents(), $this->item->getContentCacheEvent()));
			vB_Cache::instance()->cleanNow();
			$nav_node = new vBCms_Item_Content($this->item->getNodeId(), vBCms_Item_Content::INFO_NAVIGATION);
			// reload the navbar for the page
			vBCms_NavBar::prepareNavBar($nav_node, true);
			unset($nav_node);
		}
		else if ($this->item)
		{
			vB_Cache::instance()->event(array($this->item->getCacheEvents(),
				$this->item->getContentCacheEvent()));
		}

		//Let's set the thread status, if there is one.
		//If we get called from dm/rate.php or somewhere like that, we skip this section
			if ($this->isUpdating() AND in_array('comments_enabled', $this->set_fields) AND
			isset($this->set_fields['comments_enabled']))
		{
				$record = vB::$vbulletin->db->query_first("SELECT info.associatedthreadid, thread.forumid FROM " .
				TABLE_PREFIX . "cms_nodeinfo AS info INNER JOIN " .
				TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
				WHERE info.nodeid = ". $this->item->getNodeId() );

			if ($record['associatedthreadid'])
			{
				require_once DIR . '/includes/functions_databuild.php';
				$thread = vB_Legacy_Thread::create_from_id($record['associatedthreadid']);

				if ($thread)
				{
					if (intval($this->set_fields['comments_enabled']))
					{

						//We need to ensure comments are enabled.
						//only if it is published
						$visible = $thread->get_field('visible');
						if (intval($visible) != 1 AND !empty($this->set_fields['setpublish']))
						{
							undelete_thread($record['associatedthreadid']);
						}

						//If the title has been updated in the article, update the thread title.
						if (($thread->getField('title') != '') AND isset($this->set_fields['title'])
							AND ($thread->getField('title') != $this->set_fields['title']))
						{
							$title = new vB_Phrase('vbcms', 'comment_thread_title', $this->set_fields['title']);
							$sql = "UPDATE " . TABLE_PREFIX . "thread SET title = '" .
								vB::$db->escape_string($title) .
								"' WHERE threadid = " . $record['associatedthreadid'];
							vB::$db->query_write($sql);
						}
					}
					else if (!method_exists($this->item, 'getKeepThread') OR !method_exists($this->item, 'getMoveThread')
						OR !($this->item->getKeepThread() AND !$this->item->getMoveThread()))
					{
						//If this is a promoted article and it's set to keep the existing
						// thread, we leave it alone, otherwise, we need to hide the thread.
						$thread->soft_delete(new vB_Legacy_CurrentUser(), '', true);
					}
				}
				build_thread_counters($record['associatedthreadid']);
				build_forum_counters($record['forumid']);
			}
		}

		parent::postSave($result, $deferred, $replace, $ignore);
		//we should never return false if we got here.
		$result = (intval($result) ? $result : true);

		return $result;
	}



	/*Insert========================================================================*/

	/**
	 * Performs an INSERT with the set fields.
	 *
	 * @param string $table						- The table to insert into
	 * @param bool $replace						- Whether to REPLACE instead of INSERT
	 * @param bool $ignore						- Whether to IGNORE
	 * @return int								- The insert id, or affected rows if using IGNORE
	 */
	protected function execInsert($table, $replace = false, $ignore = false)
	{
		if ($table == $this->primary_table)
		{
			return $this->insertNode();
		}

		parent::execInsert($table, $replace, $ignore);
	}


	/**
	 * Inserts a new node.
	 */
	protected function insertNode()
	{
		// check we don't already have errors
		if ($this->hasErrors())
		{
			return false;
		}

		$parent = $this->set_fields['parentnode'];

		if (! $this->getSet('userid'))
		{
			$this->set_fields['userid'] = vB::$vbulletin->userinfo['userid'];
		}
		$this->set_fields['creationdate'] = TIMENOW;

		// Lock the node table :(
		vB::$db->lock_tables(array('cms_node' => 'WRITE', 'language' =>read));

		// Get the new leftnode position
		$left = vB::$db->query_first("
				SELECT noderight
				FROM " . TABLE_PREFIX . "cms_node
				WHERE nodeid = " . intval($this->set_fields['parentnode']));

		// Shouldn't happen as we already validated the parentnode with set
		if (!$left)
		{
			throw (new vB_Exception_DM('No valid parent node found for inserting a new node'));
		}

		$left = $left['noderight'] - 1;

		// Make a space for the new node
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET noderight = noderight + 2
			WHERE noderight > " . intval($left)
		);

		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET nodeleft = nodeleft + 2
			WHERE nodeleft > " . intval($left)
		);

		// Fill the gap with our new leaf node
		$this->set_fields['nodeleft'] = $left + 1;
		$this->set_fields['noderight'] = $left + 2;

		// Insert the new node
		vB::$db->query_write($this->getInsertSQL($this->primary_table));

		// Get the new nodeid
		$insertid = vB::$db->insert_id();

		// Set the primary key based on the insert id
		$this->setAutoIncrementId(vB::$db->insert_id());

		// Unlock the node table :)
		vB::$db->unlock_tables();

		// Mark the tree as updated
		$this->treeUpdated();

		return $insertid;
	}



	/*Update========================================================================*/

	/**
	* Performs an UPDATE on the current item.
	*
	* @param string	$table						- The table to update
	* @param bool $deferred						- Whether to defer the update until shutdown
	* @param bool $get_affected_rows			- Whether to return the number of affected rows
	*
	* @return bool | int						- Success or affected rows
	*/
	protected function execUpdate($table, $deferred = false, $get_affected_rows = false)
	{
		// Check if the node was moved
		if (($table == $this->primary_table) AND isset($this->set_fields['parentid']))
		{
			$this->assertItem();

			$this->moveNode($this->item->getId(), $this->set_fields['parentid']);
		}

		return parent::execUpdate($table, $deferred, $get_affected_rows);
	}


	/**
	 * Moves a node to be the child of another node.
	 * @TODO: Ordering
	 *
	 * @param int $nodeid						- Id of the node to move
	 * @param int $parentid						- The parent / set root to move to
	 * @param int $order						- The order to place the node (0 first)
	 */
	public function moveNode($nodeid, $parentid, $order = false)
	{
			// Get the tree info for the src and new parent nodes
		$result = vB::$db->query("
			SELECT (nodeid = " . intval($parentid) . ") AS isparent, nodeleft, noderight, parentnode
			FROM " . TABLE_PREFIX . "cms_node
			WHERE nodeid IN (" . intval($parentid) . ", " . intval($nodeid) . ")"
		);

		$parent = $source = false;
		while ($node = vB::$db->fetch_array($result))
		{
			if ($node['isparent'])
			{
				$parent = $node;
			}
			else
			{
				$source = $node;
			}
		}

		if (!$parent OR !$source)
		{
			throw (new vB_Exception_DM('Source node \'' . htmlspecialchars_uni($nodeid) . '\' or parent node \'' . htmlspecialchars_uni($parentid) . '\' not valid for moving'));
		}

		if ($parent['nodeid'] == $parentid)
		{
			return true;
		}

		if (($parent['nodeleft'] >= $node['nodeleft']) AND ($parent['nodeleft'] <= $node['nodeleft']))
		{
			throw (new vB_Exception_DM('Cannot move node \'' . $node['nodeid'] . '\': destination \'' . $parent['nodeid'] . '\' is a descendant of the source (' . intval($node['nodeid']) . ')'));
		}

		// Get the width of the subtree we're moving
		 $src_width = ($source['noderight'] - $source['nodeleft']) + 1;

		 // Lock the node tree
		 vB::$db->lock_tables(array('cms_node' => 'WRITE'));

		 // Create space for the moving node to the right of the new parent's tree
		 vB::$db->query_write("
		 	UPDATE " . TABLE_PREFIX . "cms_node
		 	SET nodeleft = IF (nodeid != " . intval($parent['nodeid']) . ", nodeleft + $src_width, nodeleft),
		 		noderight = noderight + $src_width
		 	WHERE noderight >= " . intval($parent['noderight'])
		 );

		// If the source was to the right of the new parent then it was shifted to make the gap
		if ($node['nodeleft'] > $parent['noderight'])
		{
			$node['nodeleft'] += $src_width;
			$node['noderight'] += $src_width;
		}

		 // Check the distance that the node will move.  This works in both directions.
		 $distance = ($parent['noderight'] - $node['nodeleft']);

		 // Update the moved sub tree with it's new left and right values
		 vB::$db->query_write("
		 	UPDATE " . TABLE_PREFIX . "cms_node
		 	SET nodeleft = nodeleft + " . intval($distance) . ",
		 		noderight + noderight + " . intval($distance) . "
		 		parentnode = IF(nodeid = " . intval($node['nodeid']) . ", " . intval($parent['nodeid']) . ", parentnode)
		 	WHERE nodeleft BETWEEN " . intval($node['nodeleft']) . " AND " . intval($node['noderight'])
		);

		// Close the gap where the sub tree was moved from
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET nodeleft = IF(nodeleft >= " . intval($node['nodeleft']) . ", nodeleft - " . intval($src_width) . ", nodeleft),
				noderight = noderight - " . intval($src_width) . "
			WHERE noderight > " . $node['noderight']
		);

		// Unlock the node tree
		vB::$db->unlock_tables();
	}


	/**
	 * Saves a timestamp of the last node tree update time.
	 * This only needs to be done when the actual structure of the tree has been
	 * modified, not it's contents.  This allows us to notify users who are viewing
	 * the nodes if any changes occured before they attempted a change of their own.
	 */

	protected function treeUpdated()
	{
		// TODO:
	}



	/*Delete========================================================================*/

	/**
	* Deletes the existing item.
	*
	* @return int								- The number of affected rows.
	*/
	public function delete($move_children = self::MOVE_PARENT)
	{
		if ($this->hasErrors())
		{
			return false;
		}

		if (!$this->preDelete($result))
		{
			return false;
		}

		// Get the condition
		$condition = $this->getConditionSQL($this->primary_table);

		// Check condition
		if (!$condition)
		{
			throw (new vB_Exception_DM('execDelete() was called in DM \'' . get_class($this) . '\' with no condition'));
		}

		// Lock the node table :(
		vB::$db->lock_tables(array('cms_node' => 'WRITE'));

		// Get the node info of the nodes we are deleting
		$result = vB::$db->query("
			SELECT nodeid, nodeleft, noderight, parentnode
		 	FROM " . TABLE_PREFIX . "cms_node
		 	WHERE $condition
		 ");

		$nodes = array();
		while($node = vB::$db->fetch_array($result))
		{
			$nodes[$node['nodeid']] = $node;
		}

		// Set result for affected rows based only on number of nodes being deleted
		$affected_nodes = sizeof($nodes);

		// Find any children that will be inherently removed
		if (self::MOVE_REMOVE == $move_children)
		{
			foreach ($nodes AS $nodeid => $node)
			{
				$left = $node['nodeleft'];
				$right = $node['noderight'];

				foreach ($nodes AS $childid => $child)
				{
					if (($child['nodeleft'] > $left) AND $child['noderight'] < $right)
					{
						// this node will be removed anyway
						unset($nodes[$child['nodeid']]);
					}
				}
			}
		}

		// Remove nodes
		foreach ($nodes AS $node)
		{
			$this->deleteNode($node, $move_children);
		}

		// Unlock the node table :)
		vB::$db->unlock_tables();

		$affected_nodes = $this->postDelete($affected_nodes);

		return $affected_nodes;
	}


	/**
	 * Deletes a single specified node and all of it's children.
	 *
	 * @param array $node
	 */
	protected function deleteNode($node, $move_children)
	{
		if (1 == $node['nodeleft'])
		{
			throw (new vB_Exception_DM('Cannot delete the root cms node'));
		}

		// Get the size of the set being deleted
		$size = ($node['noderight'] - $node['nodeleft']) + 1;

		// Handle children
		if ($size > 2)
		{
			if (self::MOVE_REMOVE == $move_children)
			{
				// delete the set
				// TODO: Use a DM to clean up other data
				vB::$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "cms_node
					WHERE nodeleft BETWEEN " . intval($node['nodeleft']) . " AND " . intval($node['noderight'])
				);
			}
			else if (self::MOVE_PARENT == $move_children)
			{
				// delete the node
				vB::$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "cms_node
					WHERE nodeid = " . intval($node['nodeid'])
				);

				// move children up
				vB::$db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_node
					SET nodeleft = nodeleft - 1,
						noderight = noderight -1
					WHERE nodeleft BETWEEN " . intval($node['nodeleft']) . " AND " . intval($node['noderight'])
				);

				// update parentnode
				vB::$db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_node
					SET parentnode = " . intval($node['parentnode']) . "
					WHERE parentnode = " . intval($node['nodeid'])
				);

				// we only removed a single node
				$size = 2;
			}
			else if (self::MOVE_ROOT == $move_children)
			{
				// delete the node
				vB::$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "cms_node
					WHERE nodeid = " . intval($node['nodeid'])
				);

				// get entire tree width
				$root = vB::$db->query_first("
					SELECT noderight
					FROM " . TABLE_PREFIX . "cms_node
					WHERE nodeleft = 1"
				);

				// get the distance of the required move
				$distance = $root['noderight'] - $node['nodeleft'] - 1;

				// move children to the end of the root
				vB::$db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_node
					SET nodeleft = nodeleft + " . intval($distance) . ",
						noderight = noderight + " . intval($distance) . "
					WHERE nodeleft BETWEEN " . intval($node['nodeleft']) . " AND " . intval($node['noderight'])
				);

				// update the root node width
				vB::$db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_node
					SET noderight = noderight + " . (intval($size) - 2) . "
					WHERE nodeleft = 1
				");

				// update parentnode to root
				vB::$db->query_write("
					UPDATE " . TABLE_PREFIX . "cms_node
					SET parentnode = 1
					WHERE parentnode = " . intval($node['nodeid'])
				);
			}
			else
			{
				throw (new vB_Exception_DM('No valid move type specified for moving orphans after deleting a node'));
			}
		}
		else
		{
			// node is a leaf
			vB::$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "cms_node
				WHERE nodeid = " . intval($node['nodeid'])
			);
		}

		// Update right node left values
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET nodeleft = nodeleft - " . intval($size) . "
			WHERE nodeleft > " . intval($node['noderight'])
		);

		// Update right node right values
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cms_node
			SET noderight = noderight - " . intval($size) . "
			WHERE noderight > " . intval($node['noderight'])
		);
	}


	/**
	* Additional tasks to perform after a delete.
	*
	* Return false to indicate that the entire delete process was not a success.
	*
	* @param mixed								- The result of execDelete()
	*/
	protected function postDelete($result)
	{
		$this->treeUpdated();

		$this->assertItem();

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_nodeconfig
			WHERE nodeid = " . intval($this->item->getNodeId())
		);

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_nodeinfo
			WHERE nodeid = " . intval($this->item->getNodeId())
		);

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_navigation
			WHERE nodeid = " . intval($this->item->getNodeId())
		);

		// Delete associated thread
		if ($threadid = $this->item->getAssociatedThreadId())
		{
			// If 'Keep Original Thread' option is not chosen hard delete the whole thread
			if (!$this->item->getKeepThread())
			{
				if ($threadinfo = verify_id('thread', $threadid, false, true))
				{
					$threadman =& datamanager_init('Thread', vB::$vbulletin, ERRTYPE_SILENT, 'threadpost');
					$threadman->set_existing($threadinfo);
					$threadman->delete(true, true, NULL, false);
					unset($threadman);

					build_forum_counters($threadinfo['forumid']);
				}
			}
			else
			{
				// If the thread was moved to the vbcms section then just soft delete that
				// else don't do anything
				if ($this->item->getMoveThread())
				{
					if ($threadinfo = verify_id('thread', $threadid, false, true))
					{
						$threadman =& datamanager_init('Thread', vB::$vbulletin, ERRTYPE_SILENT, 'threadpost');
						$threadman->set_existing($threadinfo);
						$threadman->delete(true, false, $threadinfo, false);
						unset($threadman);

						build_forum_counters($threadinfo['forumid']);
					}
				}
			}
		}
		vB_Cache::instance()->event(vBCms_NavBar::GLOBAL_CACHE_EVENT);
		vB_Cache::instance()->event(vBCms_NavBar::getCacheEventId($this->item->getNodeId()));
		vB_Cache::instance()->event($this->item->getContentCacheEvent());

		vB_Cache::instance()->eventPurge($this->item->getCacheEvents());

		vB_Cache::instance()->cleanNow();

		return parent::postDelete($result);
	}

	/**
	* Add a rating
	*
	* @param int								- The vote ( between 0 and 5 )
	*/
	public function addRating($rating)
	{
		if (!is_int($rating) OR $rating < 0 OR $rating > 5)
		{
			throw (new vB_Exception_DM('Rating must be an integer between 0 and 5'));
		}

		$this->raw_fields['ratingnum'] = 'ratingnum+1';
		$this->raw_fields['ratingtotal'] = 'ratingtotal+' . $rating;
		$this->raw_fields['rating'] = 'ratingtotal/ratingnum';
		$this->set_fields['ratingnum'] = $this->set_fields['ratingtotal'] = $this->set_fields['rating'] = true;
	}

	/**
	* Remove a rating
	*
	* @param int								- The vote ( between 0 and 5 )
	*/
	public function removeRating($rating)
	{
		if (!is_int($rating) OR $rating < 0 OR $rating > 5)
		{
			throw (new vB_Exception_DM('Rating must be an integer between 0 and 5'));
		}

		$this->raw_fields['ratingnum'] = 'ratingnum-1';
		$this->raw_fields['ratingtotal'] = 'ratingtotal-' . $rating;
		$this->raw_fields['rating'] = 'ratingtotal/ratingnum';
		$this->set_fields['ratingnum'] = $this->set_fields['ratingtotal'] = $this->set_fields['rating'] = true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/
