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
 * Article Content Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 61698 $
 * @since $Date: 2012-04-19 14:46:06 -0700 (Thu, 19 Apr 2012) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Content_PhpEval extends vBCms_Content_StaticPage
{
	/*Properties====================================================================*/

	/**
	 * A class identifier.
	 *
	 * @var string
	 */
	protected $class = 'PhpEval';

	/**
	 * A package identifier.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	protected $parent_node = false;
	/*ViewInfo======================================================================*/
	protected $data_saved = false;

	/**
	 * Info required for view types.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => 91,
		self::VIEW_PREVIEW => /* vB_Item::INFO_BASIC | vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 91,
		self::VIEW_PAGE => /* vB_Item::INFO_BASIC | vBCms_Item_Content::INFO_NODE | vBCms_Item_Content::INFO_CONTENT */ 19,
		self::VIEW_AGGREGATE => 91
	);

	protected $config = array(
		'template' => 'vbcms_content_phpeval_page',
		'previewtemplate' => 'vbcms_content_phpeval_preview',
		'previewlength' => '200',
		'cache_ttl' => '60',
		'last_update' => '0',
		'pagecontent' => '',
		'preview_image' => ''

			);

	protected $cache_ttl = 60;

	protected $editing = false;

	protected $pagelist = false;

	protected $default_template = 'vbcms_content_phpeval_page';
	protected $default_previewtemplate = 'vbcms_content_phpeval_preview';
	protected $content_start_hook = 'vbcms_phpeval_defaultcontent_start';
	protected $content_end_hook = 'vbcms_phpeval_defaultcontent_end';
	protected $startpopulatehook = 'vbcms_phpeval_populate_start';
	protected $endpopulatehook = 'vbcms_phpeval_populate_end';
	protected $savestarthook = 'vbcms_phpeval_save_start';
	protected $saveendhook = 'vbcms_phpeval_save_end';


	/**
	 * Creates a new, empty content item to add to a node.
	 *
	 * @param vBCms_DM_Node $nodedm				- The DM of the node that the content is being created for
	 * @return int | false						- The id of the new content or false if not applicable
	 */
	public function createDefaultContent(vBCms_DM_Node $nodedm)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'nodeid'        => vB_Input::TYPE_UINT,
			'parentnode'    => vB_Input::TYPE_UINT,
			'parentid'      => vB_Input::TYPE_UINT,
			'pagecontent'   => vB_Input::TYPE_STR,
			));

		//We should have a nodeid, but a parentnode is even better.
		($hook = vBulletinHook::fetch_hook($this->content_start_hook)) ? eval($hook) : false;

		if ($this->parent_node)
		{
			$parentnode = $this->parent_node;
		}
		else if (vB::$vbulletin->GPC_exists['parentnode'] AND intval(vB::$vbulletin->GPC['parentnode'] ))
		{
			$parentnode = vB::$vbulletin->GPC['parentnode'];
		}
		else if (vB::$vbulletin->GPC_exists['parentid'] AND intval(vB::$vbulletin->GPC['parentid'] ))
		{
			$parentnode = vB::$vbulletin->GPC['parentid'];
		}
		else if (vB::$vbulletin->GPC_exists['nodeid'] AND intval(vB::$vbulletin->GPC['nodeid'] )
			and $record = vB::$vbulletin->db->query_first("SELECT contenttypeid, nodeid, parentnode FROM " .
			TABLE_PREFIX . "cms_node where nodeid = " . vB::$vbulletin->GPC['nodeid'] ))
		{
			$parentnode = vB_Types::instance()->getContentTypeID("vBCms_Section") == $record['contenttypeid'] ?
				$record['nodeid'] : $record['parentnode'];
		}
		else
		{
			throw (new vB_Exception_Content('No valid parent node'));
		}
		$contenttypeid = vB_Types::instance()->getContentTypeID($this->package . '_'  . $this->class);

		//Verify Permissions
		if (!vBCMS_Permissions::canUseHtml($parentnode, $contenttypeid, vB::$vbulletin->userinfo['userid']))
		{
			throw (new vB_Exception_AccessDenied());
		}

		$this->config = array('pagetext' => $vbphrase['php_goes_here_desc'],
			'previewtext' => $vbphrase['php_preview_goes_here_desc']);
		$nodedm->set('config', $this->config);
		$nodedm->set('contenttypeid', $contenttypeid);
		$nodedm->set('parentnode', $parentnode);
		$nodedm->set('publicpreview', 1);
		$nodedm->set('comments_enabled', 1);
		$title = new vB_Phrase('vbcms', 'new_php_eval_page');
		$nodedm->set('description', $title);
		$nodedm->set('title', $title);

		if (!($contentid = $nodedm->save()))
		{
			throw (new vB_Exception_Content('Failed to create default content for contenttype ' . get_class($this)));
		}
		($hook = vBulletinHook::fetch_hook($this->content_end_hook)) ? eval($hook) : false;

		//at this point we have saved the data. We need to get the content id, which isn't easily available.
		if ($record = vB::$vbulletin->db->query_first("SELECT contentid FROM " . TABLE_PREFIX . "cms_node WHERE nodeid = $contentid"))
		{
			$nodedm->set('contentid', $record['contentid']);
		}

		return $contentid;
	}


	/*Render========================================================================*/

	/**
	 * Populates a view with the expected info from a content item.
	 *
	 * @param vB_View $view
	 * @param int $viewtype
	 */
	protected function populateViewContent(vB_View $view, $viewtype = self::VIEW_PAGE, $increment_count = true)
	{
		$view = parent::populateViewContent($view, $viewtype, $increment_count);

		if (!$this->editing)
		{
			if ((self::VIEW_PREVIEW != $viewtype) OR !$view->showpreviewonly)
			{
				$view->pagetext = $this->content->getRenderedText();
			}

			if ($view->showpreviewonly )
			{
				$view->previewtext = $this->content->getRenderedPreviewText();
			}
			else
			{
				$view->previewtext = $this->content->getRenderedText();
			}

		}

		return $view;

	}
}
