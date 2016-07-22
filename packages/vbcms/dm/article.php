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
 * CMS Article Data Manager
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_DM_Article extends vBCms_DM_Node
{
	/*Properties====================================================================*/

	/**
	* Field definitions.
	* The field definitions are in the form:
	*	array(fieldname => array(VF_TYPE, VF_REQ, VF_METHOD, VF_VERIFY)).
	*
	* @var array string => array(int, int, mixed)
	*/
	protected $type_fields = array(
		'pagetext'     => array(vB_Input::TYPE_STR,		self::REQ_YES,	self::VM_CALLBACK,	array('$this', 'verifyPageText')),
		'threadid'     => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'blogid'       => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'blogpostid'   => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'postid'       => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'poststarter'  => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'post_started' => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'post_posted'  => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'posttitle'    => array(vB_Input::TYPE_STR,		self::REQ_NO,	self::VM_CALLBACK,	array('vB_Validate', 'stringLength', 1, 256)),
		'postauthor'   => array(vB_Input::TYPE_STR,		self::REQ_NO,	self::VM_CALLBACK,	array('vB_Validate', 'stringLength', 1, 100)),
		'previewimage' => array(vB_Input::TYPE_STR,		self::REQ_NO),
		'imagewidth'   => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'imageheight'  => array(vB_Input::TYPE_INT,		self::REQ_NO),
		'previewvideo' => array(vB_Input::TYPE_STR,		self::REQ_NO),
		'htmlstate'    => array(vB_Input::TYPE_STR, 	self::REQ_NO),
		'keepthread' =>  array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'allcomments' => array(vB_Input::TYPE_UINT,		self::REQ_NO),
		'movethread' => array(vB_Input::TYPE_UINT,		self::REQ_NO),
	);

	/**
	 * Map of table => field for fields that can automatically be updated with their
	 * set value.
	 *
	 * @var array (tablename => array(fieldnames))
	 */
	protected $type_table_fields = array(
		'cms_article' => array(
			'pagetext',
			'threadid',
			'blogid',
			'posttitle',
			'postauthor',
			'poststarter',
			'postid',
			'blogpostid',
			'post_started',
			'post_posted',
			'previewimage',
			'imagewidth',
			'previewvideo',
			'imageheight',
			'htmlstate',
			'keepthread',
			'allcomments',
			'movethread'
		)
	);

	/**
	 * Table name of the primary table.
	 *
	 * @var string
	 */
	protected $type_table = 'cms_article';

	/**
	 * vB_Item Class.
	 *
	 * @var string
	 */
	protected $item_class = 'vBCms_Item_Content_Article';

	/**
	 * Whether to reindex the content after an update.
	 *
	 * @var bool
	 */
	protected $index_search = true;


	/*Save==========================================================================*/

	/**
	 * Resolves the condition SQL to be used in update queries.
	 *
	 * @param string $table						- The table to get the condition for
	 * @return string							- The resolved sql
	 */
	protected function getTypeConditionSQL($table)
	{
		$this->assertItem();

		return 'contentid = ' . intval($this->item->getId());
	}


	/**
	 * Fetches the value to update the node description when content is updated.
	 *
	 * @return string
	 */
	protected function getUpdatedNodeDescription()
	{
		return $this->set_fields['title'];
	}

	/**
	 * Prepare meta description to use first 150 words of the article (if it's not set).
	 * Prepare meta keywords to use most popular 5 words of the article (if it's not set).
	 */
	protected function prepareFields()
	{
		parent::prepareFields();

		if (!empty($this->type_set_fields['pagetext']))
		{
			require_once(DIR . '/includes/functions_databuild.php');
			$wordarray = split_string(fetch_postindex_text($this->type_set_fields['pagetext']));
			$preview = split_string(strip_tags(strip_bbcode($this->type_set_fields['pagetext'])));
			$words = array_slice($preview, 0, 150, true);

			if (empty($this->set_fields['description']) OR $this->set_fields['description'] == (string) new vB_Phrase('vbcms', 'new_article'))
			{
				$this->set_fields['description'] = implode(' ',$words);
			}

			if (empty($this->set_fields['keywords']))
			{
				$scores = array();
				foreach ($wordarray AS $word)
				{
					if (!is_index_word($word))
					{
						continue;
					}
					$scores[$word]++;
				}

				arsort($scores, SORT_NUMERIC);
				$scores = array_slice($scores, 0, 5, true);
				$this->set_fields['keywords'] = implode(',',array_keys($scores));
			}

			unset($words, $scores, $wordarray);
		}
	}

	/**** This executes after a save. In our case we set the
	* tag list.
	*
	****/
	protected function postSave($result, $deferred, $replace, $ignore)
	{
		$result = parent::postSave($result, $deferred, $replace, $ignore);

		vB::$vbulletin->input->clean_array_gpc('p', array(
			'taglist'          => vB_Input::TYPE_STR
		));

		if (vB::$vbulletin->GPC_exists['taglist'] and (vB::$vbulletin->GPC['taglist'] != ''))
		{
			require_once DIR . '/includes/class_taggablecontent.php';
			$taggable = vB_Taggable_Content_Item::create(vB::$vbulletin,
				vB_Types::instance()->getContentTypeID("vBCms_Article"),
				$this->getField('contentid'));
			$taggable->add_tags_to_content(vB::$vbulletin->GPC['taglist'], array('content_limit' => 25));
		}

		if (!$this->isUpdating())
		{
			$activity = new vB_ActivityStream_Manage('cms', 'article');
			$activity->set('contentid', $this->getField('nodeid'));
			$activity->set('userid', $this->getField('userid'));
			$activity->set('dateline', $this->getField('publishdate'));
			$activity->set('action', 'create');
			$activity->save();
		}
		else if ($this->getField('publishdate') AND $this->getField('nodeid'))
		{
			$activity = new vB_ActivityStream_Manage('cms', 'article');
			$activity->set('contentid', $this->getField('nodeid'));
			$activity->set('dateline', $this->getField('publishdate'));
			$activity->update();
		}

		$result = (intval($result) ? $result : true);
		return $result;
	}

	/**
	* Additional tasks to perform before a delete.
	*
	* Return false to indicate that the entire delete process was not a success.
	*
	* @param mixed								- The result of execDelete()
	*/
	protected function preDelete($result = '')
	{
		$this->assertItem();

		require_once DIR . '/includes/class_taggablecontent.php';
		$taggable = vB_Taggable_Content_Item::create(vB::$vbulletin,
			vB_Types::instance()->getContentTypeID("vBCms_Article"),
			intval($this->item->getId()));
		$taggable->delete_tag_attachments();

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_nodecategory
			WHERE nodeid = " . intval($this->item->getNodeId())
		);

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cms_article
			WHERE contentid = " . intval($this->item->getId())
		);
		vB_Cache::instance()->event('categories_updated');

		return parent::preDelete($result);
	}

	/**
	* Additional tasks to perform after a delete
	*
	* @param mixed								- The result of execDelete()
	*/
	protected function postDelete($result)
	{
		$this->assertItem();

		$activity = new vB_ActivityStream_Manage('cms', 'article');
		$activity->set('contentid', intval($this->item->getNodeId()));
		$activity->delete();

		$contenttypeid = intval(vB_Types::instance()->getContentTypeID("vBCms_Article"));
		$attachdata =& datamanager_init('Attachment', vB::$vbulletin, ERRTYPE_SILENT, 'attachment');
		$attachdata->condition = "a.contentid = " . intval($this->item->getNodeId()) . " AND a.contenttypeid = " . $contenttypeid;
		$attachdata->delete(true, false);

		return parent::postDelete($result);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/
