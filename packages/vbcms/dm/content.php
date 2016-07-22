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
 * CMS Base Content Data Manager.
 * Ensures that a node id is set so the node can be updated when needed.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vBCms_DM_Content extends vB_DM
{
	/*Properties====================================================================*/

	/**
	 * The node that the content belongs to.
	 *
	 * @var int
	 */
	protected $nodeid;

	/**
	 * An overridden node title.
	 *
	 * @var string
	 */
	protected $node_title;

	/**
	 * An overridden node description.
	 *
	 * @var string
	 */
	protected $node_description;

	/**
	 * An overridden node url segment.
	 *
	 * @var string
	 */
	protected $node_segment;

	/**
	 * Whether to reindex the content after an update.
	 *
	 * @var bool
	 */
	protected $index_search;

	protected $delete_ids = array();

	/**
	 * Constructor.
	 *
	 * @param vB_Item $existing_item				An existing item that will be updated.
	 */
	public function __construct(vBCms_Item_Content $existing_item = null)
	{
		parent::__construct($existing_item);

		if (isset($existing_item))
		{
			$this->index_search = vB_Search_Core::get_instance()->get_cansearch($existing_item->getPackage(true), $existing_item->getClass());
		}
	}



	/*Set===========================================================================*/

	/**
	 * Specifies the node that the content belongs to.
	 *
	 * @param int $nodeid
	 */
	public function setNode($nodeid)
	{
		if (!is_numeric($nodeid))
		{
			throw (new vB_Exception_DM('Nodeid set for cms content dm is not an integer in DM \'' . get_class($this) . '\''));
		}

		$this->nodeid = $nodeid;
	}


	/**
	 * Resets all set changes.
	 */
	protected function reset()
	{
		parent::reset();
		unset($this->nodeid);
	}



	/*Save==========================================================================*/

	/**
	 * Performs additional queries or tasks after saving.
	 * Updates the node description with the title.
	 *
	 * @param mixed								- The save result
	 * @param bool $deferred						- Save was deferred
	 * @param bool $replace						- Save used REPLACE
	 * @param bool $ignore						- Save used IGNORE if inserting
	 * @return bool								- Whether the save can be considered a success
	 */
	protected function postSave($result, $deferred, $replace, $ignore)
	{
		//result will normally be the nodeid if this was an insert. Let's check.
		if ($this->isUpdating())
		{
			$nodeid = $this->item->getNodeId();
		}
		else if (is_array($result))
		{
			$nodeid = $result['nodeid'];
		}
		else
		{
			$nodeid = $result;
		}

		if (!$result)
		{
			return false;
		}
		parent::postSave($result, $deferred, $replace, $ignore);

		//We need to update category information. Let's figure out what the current categories
		// are and only make the necessary changes.
		vB::$vbulletin->input->clean_array_gpc('r', array('categoryids' =>TYPE_ARRAY));

		//if we don't have a categoryids variable around, we don't want to update categories.
		if (vB::$vbulletin->GPC_exists['categoryids'])
		{
			$newcategories = array();
			$currcategories = array();
			foreach (vB::$vbulletin->GPC['categoryids'] as $categoryid)
			{
				if (isset($_REQUEST["cb_category_$categoryid"]))
				{
					$newcategories[]= $categoryid;
				}
			}
			$newcategories = array_unique($newcategories);

			if ($rst = vB::$vbulletin->db->query_read("SELECT categoryid FROM "
				. TABLE_PREFIX . "cms_nodecategory WHERE nodeid =" . $nodeid))
			{
				while($row = vB::$vbulletin->db->fetch_array($rst))
				{
					$currcategories[] = $row['categoryid'];
				}
			}

			if (count($update = array_diff($newcategories, $currcategories)))
			{
				foreach ($update as $categoryid)
				{
					vB::$vbulletin->db->query_write("INSERT INTO ". TABLE_PREFIX .
						 "cms_nodecategory (nodeid, categoryid) values (" . $nodeid .
						 ", $categoryid) ");
				}
			}

			if (count($update = array_diff($currcategories, $newcategories)))
			{
				vB::$vbulletin->db->query_write("DELETE FROM ". TABLE_PREFIX .
					 "cms_nodecategory WHERE nodeid =" . $nodeid .
					 " AND categoryid in (" . implode(', ', $update) . ")" );
			}
			vB_Cache::instance()->event('categories_updated');
			vB_Cache::instance()->event($this->item->getContentCacheEvent());
		}

		if ($this->index_search)
		{
			$this->indexSearchContent();
		}

		vB_Cache::instance()->event('cms_count_published');

	}

	/**
	 * Fetches the value to update the node title when content is updated.
	 *
	 * @return string
	 */
	protected function getUpdatedNodeTitle()
	{
		return isset($this->node_title) ? $this->node_title : false;
	}


	/**
	 * Fetches the value to update the node description when content is updated.
	 *
	 * @return string
	 */
	protected function getUpdatedNodeDescription()
	{
		return isset($this->node_description) ? $this->node_description : false;
	}

	/**
	 * Fetches the value to update the node url segment when content is updates.
	 *
	 * @return string
	 */
	protected function getUpdatedNodeURLSegment()
	{
		return isset($this->node_segment) ? $this->node_segment : false;
	}



	/*Info==========================================================================*/

	/**
	 * Allows a node title to be set.
	 *
	 * @param string $title
	 */
	public function setNodeTitle($title)
	{
		$this->node_title = $title;
	}


	/**
	 * Allows a node description to be set.
	 *
	 * @param string $description
	 */
	public function setNodeDescription($description)
	{
		$this->node_description = $description;
	}


	/**
	 * Allows a node url segment to be set.
	 *
	 * @param string $segment
	 */
	public function setNodeURLSegment($segment)
	{
		$this->node_segment = $segment;
	}


	/*Delete========================================================================*/

	/**
	 * Additional tasks to perform after a delete.
	 *
	 * Return false to indicate that the entire delete process was not a success.
	 *
	 * @param mixed								- The result of execDelete()
	 */
	protected function postDelete($result)
	{
		$this->assertItem();

		if ($nodeid = $this->item->getNodeId())
		{
			vB::$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cms_nodeinfo
				WHERE nodeid = $nodeid");
			vB::$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cms_nodeconfig
				WHERE nodeid = $nodeid" );
		}

		if ($this->index_search)
		{
			$this->deleteSearchContent();
		}

		return $result;
	}

	/*Search========================================================================*/

	/**
	 * Adds content to the index queue.
	 */
	protected function indexSearchContent()
	{
		if ($this->isUpdating())
		{
			$package = $this->item->getPackage();
			$class = $this->item->getClass();

			$index_controller = vB_Search_Core::get_instance()->get_index_controller($package, $class);

			if (!($index_controller instanceof vb_Search_Indexcontroller_Null))
			{
				//$this->item->getId() (returns the contentid) reflects the value prior to saving anything.
				//this means that on first save it will be 0 because the content record hasn't been set yet.
				vB_Search_Indexcontroller_Queue::indexQueue($package, $class, 'index', $this->getField('contentid'));
			}
		}
	}


	/**
	 * Removes content from the indes.
	 */
	protected function deleteSearchContent()
	{
		if (!$type_info = vB_Search_Core::get_instance()->get_indexed_types($this->item->getContentTypeID()))
		{
			//$this->item->getId() (returns the contentid) reflects the value prior to saving anything.
			//this means that on first save it will be 0 because the content record hasn't been set yet.
			vB_Search_Indexcontroller_Queue::indexQueue($this->item->getPackage(), $this->item->getClass(), 'delete', $this->getField('contentid'));
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/