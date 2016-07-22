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
 * CMS Section Data Manager
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 77270 $
 * @since $Date: 2013-09-03 07:35:27 -0700 (Tue, 03 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_DM_Section extends vBCms_DM_Node
{
	/*******
	* Most of the work is already done for us, but we need to save ,
	* for each of the child nodes- any combination of delete, published, order, and public preview.
	*
	* For all the standard node and nodeinfo content we want to just let the generic
	********/


	/**
	 * vB_Item Class.
	 *
	 * @var string
	 */
	protected $item_class = 'vBCms_Item_Content_Section';

	/**
	 * Whether to reindex the content after an update.
	 *
	 * @var bool
	 */
	protected $index_search = true;

	/** A flag so if we're creating a new record from admincp we can have it
	* NOT save any children for child nodes ***/
	protected $save_children = true;


	/*Save==========================================================================*/

	/**** this function is run before the actual save, and allows the node to do
	* any final rendering
	*
	* @param none
	* @return none
	****/

	protected function prepareFields()
	{
		$this->set('contenttypeid', vb_Types::instance()->getContentTypeID("vBCms_Section"));

		if ($this->set_fields['nodeid'])
		{
			$this->item_id = $this->set_fields['nodeid'];
		}
		parent::prepareFields();
	}

	/** allows us to toggle the save_children flag***/
	public function setSaveChildren($this_save)
	{
		$this->save_children = $this_save;
	}

	//Walk the list of entries.
	protected function postSave($result, $deferred, $replace, $ignore)
	{
			if (! $this->save_children)
		{
			return true;
		}

		vB::$vbulletin->input->clean_array_gpc('p', array(
			'ids' => vB_Input::TYPE_ARRAY_UINT
		));
		//The parent classes insist on setting new=1, but we want new=0;
		vB::$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "cms_node
		SET new = 0 WHERE nodeid = " . (isset($this->set_fields['nodeid']) ? $this->set_fields['nodeid'] : $this->primary_id));

		//We have a minor issue here. The edit screen does not have a time and date
		//So- if the item is currently published and is still published, we should do nothing.
		//To make that decision we need to know what's currently published.

		$existing = array();
		if (count(vB::$vbulletin->GPC['ids']))
		{
			$rst = $record = vB::$vbulletin->db->query_read($sql = "SELECT nodeid, publishdate, setpublish, publicpreview FROM " .
				TABLE_PREFIX . "cms_node AS node WHERE nodeid in (" . implode(', ', vB::$vbulletin->GPC['ids']) . ")");
			if ($rst)
			{
				while($record = vB::$vbulletin->db->fetch_array($rst))
				{
					$existing[$record['nodeid']] = $record;
				}
			}
		}

		$orders = array();
		foreach (vB::$vbulletin->GPC['ids'] as $nodeid)
		{
			vB::$vbulletin->input->clean_array_gpc('p', array(
				"cb_preview_$nodeid" => vB_Input::TYPE_INT,
				"cb_delete_$nodeid" => vB_Input::TYPE_INT,
				"order_$nodeid" => vB_Input::TYPE_INT,
				"published_$nodeid" => vB_Input::TYPE_INT
				));


			//If we're deleting we need to instantiate the appropriate data manager
			// and use that to delete. Otherwise we know where the variables are and
			// can just update them directly.
			if (vB::$vbulletin->GPC_exists["cb_delete_$nodeid"])
			{
				if ($record = vB::$vbulletin->db->query_first("SELECT contenttype.class, package.class
				AS package FROM " . TABLE_PREFIX . "cms_node node
				INNER JOIN " . TABLE_PREFIX . "contenttype AS contenttype ON contenttype.contenttypeid = node.contenttypeid
				INNER JOIN " . TABLE_PREFIX . "package AS package ON package.packageid = contenttype.packageid
				 WHERE nodeid = " . $nodeid))
				{
					$item = vB_Item_Content::create($record['package'], $record['class'], $nodeid);
					$dm = $item->getDM();
					$dm->delete();
				}
			}
			else
			{
				//Check the order. This is fairly tricky. If we do the order updates
				// out of sequence, things get scrambled and we are sure to wind up incorrect. So we
				// need to index them and do them in order.

				if (intval( vB::$vbulletin->GPC["order_$nodeid"]))
				{
					$orders[$nodeid] = vB::$vbulletin->GPC["order_$nodeid"];
				}
				else
				{
					$orders[$nodeid] = 0;
				}

				$updates = array();
				//Check the preview status
				$newpreview = vB::$vbulletin->GPC_exists["cb_preview_$nodeid"] ? 1 : 0;


				if (($newpreview == 1) AND (array_key_exists($nodeid, $existing))
					AND (intval($existing[$nodeid]['publicpreview']) != 1))
				{
					$updates[] = " publicpreview = 1";
				}
				else if (($newpreview == 0) AND (array_key_exists($nodeid, $existing))
				AND (intval($existing[$nodeid]['publicpreview']) != 0))
				{
					$updates[] = " publicpreview = 0";
				}

				//Check the published status. Remember we set published = 2;
				if (array_key_exists($nodeid, $existing) AND vB::$vbulletin->GPC_exists["published_$nodeid"]
					AND (vB::$vbulletin->GPC["published_$nodeid"] == 2) AND
					($existing[$nodeid]['setpublish'] != 1))
				{
					$updates[] = " setpublish = 1, publishdate = " . (TIMENOW  - vBCms_ContentManager::getTimeOffset(vB::$vbulletin->userinfo, false) );
				}
				else if (array_key_exists($nodeid, $existing)  AND vB::$vbulletin->GPC_exists["published_$nodeid"]
					AND (vB::$vbulletin->GPC["published_$nodeid"] == 1) AND ($existing[$nodeid]['setpublish'] != 0))
				{
					$updates[] = " setpublish = 0 " ;
				}

				if (count($updates))
				{
					$sql = "UPDATE " . TABLE_PREFIX . "cms_node set " . implode(', ' , $updates) .
						" WHERE nodeid = $nodeid";
					vB::$vbulletin->db->query_write($sql);
				}
			}
		}
		asort($orders);
		$min_sequence = 1;

		foreach ($orders as $nodeid => $order)
		{
			if ($order == 0)
			{
				vBCms_ContentManager::setDisplayOrder($this->set_fields['nodeid'], $nodeid, 0);
			}
			else
			{
				$order = max($min_sequence, intval($order));
				$min_sequence++;
				vBCms_ContentManager::setDisplayOrder($this->set_fields['nodeid'], $nodeid, $order);
			}

		}

		return parent::postSave($result, $deferred, $replace, $ignore);
	}

	/********
	 * We need to remove records from cms_category and cms_nodecategory
	 *
	 *********/
	protected function postDelete($result)
	{
		vB::$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cms_nodecategory
		WHERE categoryid in (SELECT categoryid FROM " . TABLE_PREFIX . "cms_category
		WHERE parentnode = " . $this->item->getNodeId() . ") OR nodeid = " . $this->item->getNodeId());


		vB::$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cms_category WHERE parentnode =
			" . $this->item->getNodeId() );
	}



}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 77270 $
|| ####################################################################
\*======================================================================*/