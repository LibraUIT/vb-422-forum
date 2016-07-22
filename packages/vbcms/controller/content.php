<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Main CMS Page Controller
 * Page controller with actions to view nodes, edit nodes, edit content, add and
 * delete content.
 *
 * @TODO: Generalise some of the stuff that's done in multiple actions.  This class
 * is still a rough merge of various controllers into action methods.
 *
 * @TODO: We have to abstract the overlay stuff somehow so that config views can be
 * rendered as part of a html page; and to make overlay views easier to work with.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29533 $
 * @since $Date: 2009-02-12 16:00:09 +0000 (Thu, 12 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Controller_Content extends vBCms_Controller
{
	/*Properties====================================================================*/

	/**
	 * The package that the controller belongs to.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * The class string id that identifies the controller.
	 *
	 * @var string
	 */
	protected $class = 'Content';

	/**
	 * The action definitions for the controller.
	 *
	 * @var array string => bool
	 */
	protected $actions = array(
		'View',		'EditContent',	'EditPage',
		'AddNode',	'DeleteNode',	'ConfigContent',
		'PublishNode', 'UnPublishNode', 'NodeOptions',
		'List',	'Rate'
	);

	protected $wol_info = array(
		'View' 			=> array(array('wol', 'viewing_page')),
		'AddNode' 		=> array(array('wol', 'creating_content')),
		'DeleteNode'	=> array(array('wol', 'deleting_content')),
		'Default' 		=> array(array('wol', 'managing_content'), array('action' => 'view'))
	);

	protected $precache_loaded = false;
	/*Initialization================================================================*/

	/**
	 * Initialisation.
	 * Initialises the view, templaters and all other necessary objects for
	 * successfully creating the response.
	 */
	protected function initialize()
	{
		parent::initialize();

		// Setup the templater.  Even XML output needs this for the html response
		$this->registerXHTMLTemplater();
	}


	/**
	 * Authorise the current user for the current action. If the type of view is
	 * View, Preview, or Rate, we just need canview privilege. If it's anything else
	 * then we need canedit
	 */
	protected function authorizeAction()
	{
		// Get content controller
		if (! $this->content)
		{
			$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());
		}
		vB::$vbulletin->nodeid = $this->node->getNodeId();
		vB::$vbulletin->parentnode = $this->node->getParentId();

		if (! $this->precache_loaded)
		{
			$metacache_key = 'vbcms_view_data_' . $this->node->getNodeId();
			vB_Cache::instance()->restoreCacheInfo($metacache_key);
			$this->precache_loaded = true;

		}

		if (! $this->node->canView())
		{
			throw (new vB_Exception_AccessDenied());
		}

		//If this is a view, we're done
		if ($this->action == 'View')
		{
			return;
		}

		//If this is rate, we're done
		if ($this->action == 'Rate')
		{
			return;
		}

		//If this is publish, check the publish rights
		if ($this->action == 'PublishNode' OR $this->action == 'UnPublishNode')
		{
			if (! $this->content->canPublish())
			{
				throw (new vB_Exception_AccessDenied());
			}

			return;
		}

		if ($this->action = 'AddNode' AND $this->node->canCreate())
		{
			return;
		}
		//If we got here, we need publish or edit rights.
		if (!($this->node->canEdit() OR $this->node->canPublish()) )
		{
			throw (new vB_Exception_AccessDenied());
		}
	}


	/*Actions=======================================================================*/

	/**
	 * View a node page.
	 *
	 * TODO: Widgets need to be able to alter the content config before the content
	 * is rendered.  Widgets then need to be able to query the content for meta data
	 * before rendering themselves.
	 *
	 * @return string							- The final page output
	 */
	public function actionView()
	{
		//Load cached values as appropriate
		$metacache_key = 'vbcms_view_data_' . $this->node->getNodeId();
		if (! $this->precache_loaded)
		{
			vB_Cache::instance()->restoreCacheInfo($metacache_key);
			$this->precache_loaded = true;

		}

		// Create the page view
		$view = new vB_View_Page('vbcms_page');
		$view->page_url = vB_Router::getURL();
		$this->node->invalidateCached();


		// Get content controller
		$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getNodeId());

		// add the content information to the global cms array
		vB::$vbulletin->vbcms['content_type'] = $this->content->getClass();
		vB::$vbulletin->vbcms['page_url'] = $view->page_url;

		vB::$vbulletin->options['description']  = $this->node->getDescription();
		vB::$vbulletin->options['keywords']  = $this->node->getKeywords();

		// Check this user's permissions
		$this->authorizeAction();

		($hook = vBulletinHook::fetch_hook('vbcms_nodeview_start')) ? eval($hook) : false;

		// We need the content, but if it's a save it could change the layout.
		// So we need to get the content before we get the layout where it goes
		$layout_content = $this->content->getPageView($this->parameters);
		//The object may have updated. We need to get the latest data.

		if ($this->content->getChanged())
		{
			$this->node = new vBCms_Item_Content($this->node_segment);

			// Prenotify the node item of info we will require
			$info_flags = 	vBCms_Item_Content::INFO_NODE |
							vBCms_Item_Content::INFO_PARENTS |
							vBCms_Item_Content::INFO_CONFIG;
			$this->node->requireInfo($info_flags);
		}
		// Get the layout
		$this->layout = $this->content->getLayout();

		// Create the layout view
		$layout = new vBCms_View_Layout($this->layout->getTemplate());
		$layout->content = $layout_content;

		$layout->contentcolumn = $this->layout->getContentColumn();
		$layout->contentindex = $this->layout->getContentIndex();
		// Get widget locations
		$layout->widgetlocations = $this->layout->getWidgetLocations();

		if (count($layout->widgetlocations))
		{
			// Get Widgets
			$widgetids = $this->layout->getWidgetIds();

			if (count($widgetids))
			{
				$widgets = vBCms_Widget::getWidgetCollection($this->layout->getWidgetIds(), vBCms_Item_Widget::INFO_CONFIG, $this->node->getId());
				$widgets = vBCms_Widget::getWidgetControllers($widgets, true, $this->content);
				$contenttypeid = $this->node->getContentTypeID();

				($hook = vBulletinHook::fetch_hook('vbcms_widgets_start')) ? eval($hook) : false;

				// Get the widget views
				$widget_views = array();
				foreach($widgets AS $widgetid => $widget)
				{
					($hook = vBulletinHook::fetch_hook('vbcms_process_widget_start')) ? eval($hook) : false;

					try
					{
						$widgetview = $widget->getPageView();
						if ($widgetview AND $widgetview->fetchDisplayView())
						{
							if ($widgetview->fetchDisplayView())
							{
								$widget_views[$widgetid] = $widgetview;
								$widget_views[$widgetid]->contenttypeid = $contenttypeid;
							}
						}
					}
					catch (vB_Exception $e)
					{
						if ($e->isCritical())
						{
							throw ($e);
						}

						if (vB::$vbulletin->debug)
						{
							$widget_views[$widgetid] = 'Exception: ' . $e;
						}
					}

					($hook = vBulletinHook::fetch_hook('vbcms_process_widget_complete')) ? eval($hook) : false;
				}

				// Assign the widgets to the layout view
				$layout->widgets = $widget_views;
			}

			($hook = vBulletinHook::fetch_hook('vbcms_widgets_complete')) ? eval($hook) : false;
		}

		// Assign the layout view to the page view
		$view->layout = $layout;

		// Add general page info
		$view->setBreadcrumbInfo($this->node->getBreadcrumbInfo());
		$view->setPageTitle($this->content->getTitle());
		$view->pagedescription = $this->content->getDescription();
		$view->published = $this->node->isPublished();
		$title = $this->node->getHtmlTitle();

		if (method_exists($this->content , 'getPageTitle'))
		{
				$view->html_title = $this->content->getPageTitle();
		}
		else
		{
			$view->html_title = ($title ? $title : $this->node->getTitle());
		}

		if (!$view->published)
		{
			$view->publish_phrase = new vB_Phrase('vbcms', 'page_not_published');
		}

		// Add toolbar view
		$view->toolbar = $this->getToolbarView();

		vB_Cache::instance()->saveCacheInfo($metacache_key);

		($hook = vBulletinHook::fetch_hook('vbcms_nodeview_complete')) ? eval($hook) : false;

		// Render view and return
		return $view->render(true);
	}


	/**
	 * View a list page. Currently not used, because it was replaced by the list controller
	 *
	 * @return string							- The final page output
	 */
	public function actionList()
	{
		//This is an aggregator. We can pull in three different modes as of this writing,
		// and we plan to add more. We can have passed on the url the following:
		// author=id, category=id, section=id, and format=id. "Format" should normally
		// be passed as for author only, and it defines a sectionid to be used for the format.

		// Create the page view
		$view = new vB_View_Page('vbcms_page');

		$view->page_url = vB_Router::getURL();
		$view->pagetitle = $this->node->getTitle();
		vB::$vbulletin->options['description']  = $this->node->getDescription();
		vB::$vbulletin->options['keywords']  = $this->node->getKeywords();

		// Get layout
		$this->layout = $this->node->getLayout();

		// Create the layout view
		$layout = new vBCms_View_Layout($this->layout->getTemplate());

		// Get content controller
		$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());

		// Add the node as content
		$this->content->castFrom($this->node);
		// Check this user's permissions
		$this->authorizeAction();
		// Get the content view
		$layout->content = $this->content->getPageView($this->parameters);
		$layout->contentcolumn = $this->layout->getContentColumn();
		$layout->contentindex = $this->layout->getContentIndex();

		// Get widget locations
		$layout->widgetlocations = $this->layout->getWidgetLocations();

		if (count($layout->widgetlocations))
		{
			// Get Widgets

			$widgetids = $this->layout->getWidgetIds();

			if (count($widgetids))
			{
				$widgets = vBCms_Widget::getWidgetCollection($widgetids, vBCms_Item_Widget::INFO_CONFIG, $this->node->getId());
				$widgets = vBCms_Widget::getWidgetControllers($widgets, true, $this->content);

				// Get the widget views
				$widget_views = array();
				foreach($widgets AS $widgetid => $widget)
				{
					try
					{
						$widget_views[$widgetid] = $widget->getPageView();
					}
					catch (vB_Exception $e)
					{
						if ($e->isCritical())
						{
							throw ($e);
						}

						if (vB::$vbulletin->debug)
						{
							$widget_views[$widgetid] = 'Exception: ' . $e;
						}
					}
				}

				// Assign the widgets to the layout view
				$layout->widgets = $widget_views;

			}
		}

		// Assign the layout view to the page view
		$view->layout = $layout;

		// Add general page info
		$view->setBreadcrumbInfo($this->node->getBreadcrumbInfo());
		$view->setPageTitle($this->content->getTitle());
		$view->pagedescription = $this->content->getDescription();

		// Add toolbar view
		$view->toolbar = $this->getToolbarView();

		// Render view and return
		return $view->render();
	}
	/**
	 * Views the page in edit mode
	 *
	 * @return string
	 */
	public function actionEditPage()
	{
		global $vbphrase;
		require_once DIR . '/packages/vbcms/contentmanager.php';

		// Create the page view
		if (! $this->precache_loaded)
		{
			$metacache_key = 'vbcms_view_data_' . $this->node->getNodeId();
			vB_Cache::instance()->restoreCacheInfo($metacache_key);
			$this->precache_loaded = true;

		}

		$view = new vB_View_Page('vbcms_edit_page');

		$view->page_url = vB_Router::getURL();
		$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getNodeId());

		$view->rawtitle = $this->node->getTitle();
		vB::$vbulletin->options['description']  = $this->node->getDescription();
		vB::$vbulletin->options['keywords']  = $this->node->getKeywords();

		// Get the content view
		$view->content = $this->content->getInlineEditView($this->parameters);

		//Here's some javascript we need in page content;
		$view->showscripts = vBCms_ContentManager::showJs('.');
		// Add general page info
		$view->setBreadcrumbInfo($this->node->getBreadcrumbInfo());
		$title = $this->content->getTitle();
		if (!$title)
		{
			$title = $vbphrase['new_article'];
		}
		$view->setPageTitle($title);
		$view->pagedescription = $this->content->getDescription();
		$view->published = $this->node->isPublished();

		if (!$view->published)
		{
			$view->publish_phrase = new vB_Phrase('vbcms', 'page_not_published');
		}

		// Render view and return
		$result = $view->render();
		return $result;
	}


	/**
	 * Displays and controls the AJAX edit content UI for the page content.
	 *
	 * @return string
	 */
	public function actionEditContent()
	{
		if (! $this->precache_loaded)
		{
			$metacache_key = 'vbcms_view_data_' . $this->node->getNodeId();
			vB_Cache::instance()->restoreCacheInfo($metacache_key);
			$this->precache_loaded = true;

		}

		// Get content controller
		if (! $this->content)
		{
			$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());
		}

		// Add the node as content
		$this->content->castFrom($this->node);

		// Render the content's edit view and return
		return $this->content->getEditView()->render(true);
	}


	/**
	 * Displays and controls the AJAX config UI for the page content.
	 *
	 * @return string
	 */
	public function actionConfigContent()
	{
		// Create the page view
		$view = new vB_View_Page('vbcms_edit_page');

		$view->page_url = vB_Router::getURL();

		// Get content
		if (! $this->content)
		{
			$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());
		}

		$view->rawtitle = $this->node->getTitle();
		vB::$vbulletin->options['description']  = $this->node->getDescription();
		vB::$vbulletin->options['keywords']  = $this->node->getKeywords();

		// Render the content's config view and return
		$view->content = $this->content->getConfigView();

		//Here's some javascript we need in page content;
		$view->showscripts = vBCms_ContentManager::showJs('.');
		// Add general page info
		$view->setBreadcrumbInfo($this->node->getBreadcrumbInfo());
		$view->setPageTitle($this->content->getTitle());
		$view->pagedescription = $this->content->getDescription();
		$view->published = $this->node->isPublished();

		// Render the content's config view and return
		return $view;
	}

	/**
	 * Adds item id info to a view for submitting via post.
	 *
	 * @param vB_View $view
	 */
	protected function addPostId(vB_View $view)
	{
//		$view->item_type = 'controller';
//		$view->item_class = vB_Types::instance()->getTypeKey($this->package, $this->class);
		$view->item_type = 'content';
		$view->item_class = vBCms_Types::instance()->getTypeKey($this->content->getPackage(), $this->content->getClass());
		$view->item_id = $this->content->getContentId();
		$view->nodeid = $this->content->getNodeId();

	}

	/**
	 * Handles adding a new content node.
	 *
	 * @return string
	 */
	public function actionNodeOptions()
	{
		// Create AJAX view for html replacement
		$view = new vB_View_AJAXHTML('vbcms_options_view');

		// Add location info for where the new content will reside
		$view->rawtitle = $this->node->getTitle();
		vB::$vbulletin->options['description']  = $this->node->getDescription();
		vB::$vbulletin->options['keywords']  = $this->node->getKeywords();


		vB::$vbulletin->input->clean_array_gpc('p', array(
			'do' => vB_Input::TYPE_STR,
			'style' => vB_Input::TYPE_UINT,
			'layout' => vB_Input::TYPE_UINT,
			'url' => vB_Input::TYPE_NOHTMLCOND,
			'title' => vB_Input::TYPE_NOHTML,
			'contenttype' => vB_Input::TYPE_UINT
		));

		if ((vB::$vbulletin->GPC['do'] == 'update') AND $this->verifyPostId())
		{
			// update the node
			$nodedm = $this->node->getDM();
			$nodedm->set('url', vB::$vbulletin->GPC['url']);
			$nodedm->set('userid', vB::$vbulletin->userinfo['userid']);
			$nodedm->set('title', vB::$vbulletin->GPC['title']);

			$nodedm->set('title', vB::$vbulletin->GPC['title']);

			if (vB::$vbulletin->GPC['style'])
			{
				$nodedm->set('styleid', vB::$vbulletin->GPC['style']);
			}

			if (vB::$vbulletin->GPC['layout'])
			{
				$nodedm->set('layoutid', vB::$vbulletin->GPC['layout']);
			}

			if (!$nodedm->save())
			{
				$fieldnames = array(
					'title' => new vB_Phrase('vbcms', 'title'),
					'url' => new vB_Phrase('vbcms', 'url_segment')
				);

				$view->addErrors($nodedm->getErrors(array_keys($fieldnames)), $fieldnames);

				return $this->saveError($view, 'Node DM save failed');
			}

			$finishurl = vBCms_Route_Content::getURL(array('node' => $this->node->getUrlSegment()));
			$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, $finishurl);
			$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'page_updated'));
		}
		else
		{
			// get the content controller
			$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());

			// add the node as content
			$this->content->castFrom($this->node);

			// create the form view
			$formview = new vB_View('vbcms_node_options_form');
			$formview->title = $this->node->getTitle();

			// add the available styles
			// TODO: Allow configured constraints
			$formview->styles = vB_Style::getStyles();
			$formview->style_phrase = new vB_Phrase('vbcms', 'style');
			$formview->current_style = $this->node->getStyleSetting();

			// add the available layouts
			// TODO: Allow configured constraints.
			$layout_collection = new vBCms_Collection_Layout();

			$layouts = array();
			foreach ($layout_collection AS $id => $layout)
			{
				$layouts[$id]['id'] = $id;
				$layouts[$id]['title'] = $layout->getTitle();
				$layouts[$id]['selected'] = ($id == $this->node->getLayoutSetting());
			}
			unset($layout_collection);

			$formview->layouts = $layouts;
			$formview->layout_phrase = new vB_Phrase('vbcms', 'layout');

			// some useful phrases
			$formview->url_segment_phrase = new vB_Phrase('vbcms', 'url_segment');
			$formview->title_phrase = new vB_Phrase('vbcms', 'title');
			$formview->dont_change_phrase = new vB_Phrase('vbcms', 'dont_change');
			$formview->url_segment = $this->node->getUrlTitle();

			// item id to ensure form is submitted to us
			$this->addPostId($formview);

			// add form to the html replacement output
			$view->setContent($formview);

			// send the view
			// TODO: update overlay handler to accept an empty status
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, '&nbsp;');
		}

		return $view->render(true);
	}


	/**
	 * Adds a new content node of the posted contenttype.
	 * Default content is created with a default title and url segment.  Everything
	 * else inherits from the new node's parent until changes with actionEditNode().
	 *
	 * @return string
	 */
	public function actionAddNode()
	{
			vB::$vbulletin->input->clean_array_gpc('r', array(
			'contenttypeid' => vB_Input::TYPE_UINT,
			'parentnode'    => vB_Input::TYPE_UINT,
			'item_type' => vB_Input::TYPE_STR
		));

		// Validate the content id
		if (!($contenttypeid = vBCms_Types::instance()->getContentTypeID(vB::$vbulletin->GPC['contenttypeid'])))
		{
				throw (new vB_Exception_User(new vB_Phrase('error', 'no_contenttype_selected')));
		}

		if (! vB::$vbulletin->GPC_exists['parentnode'])
		{
			vB::$vbulletin->GPC_exists['parentnode'] = true;
			vB::$vbulletin->GPC['parentnode'] = $this->node->getNodeId() ;
		}

		//Check the privileges.
		if (!$this->node->canCreate())
		{
			throw (new vB_Exception_User(new vB_Phrase('error', 'no_create_permissions')));
		}

		// Validate the postid
		if ((!vB::$vbulletin->GPC['item_type'] == 'content') AND !$contenttypeid)
		{
			throw (new vB_Exception_User());
		}

		try
		{
			// create the nodedm
			$class  = vB_Types::instance()->getContentClassFromId($contenttypeid);
			$classname = $class['package']. "_DM_" . $class['class'];

			if (class_exists($classname))
			{
				$nodedm = new $classname;
			}
			else
			{
				$nodedm = new vBCms_DM_Node();
			}

			// create content handler
			$content = vBCms_Content::create(vB_Types::instance()->getContentTypePackage($contenttypeid), vBCms_Types::instance()->getContentTypeClass($contenttypeid));
			// insert default content for the contenttype and get the new contentid
			$nodeid = $content->createDefaultContent($nodedm);
		}
		catch (vB_Exception $e)
		{
			throw (new vB_Exception_DM('Could not create default content.  Exception thrown with message: \'' . htmlspecialchars_uni($e->getMessage()) . '\''));
		}

		// Create route to redirect the user to
		$route = new vBCms_Route_Content();
		$route->node = $nodeid;
		$route->setSegments(array('action' => 'edit'));
		throw (new vB_Exception_Reroute($route));
	}


	/**
	 * Main entry point for the controller.
	 *
	 * @return string							- The final page output
	 */
	public function actionDeleteNode()
	{
		// Create AJAX view for html replacement
		$view = new vB_View_AJAXHTML('cms_delete_view');
		$view->title = new vB_Phrase('vbcms', 'deleting_content');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do' => vB_Input::TYPE_STR
		));

		if ((vB::$vbulletin->GPC['do'] == 'delete') AND $this->verifyPostId())
		{
			// get content controller
			if ($this->node->getContentId())
			{
				$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());
				$this->content->deleteContent();
			}

			$nodedm = new vBCms_DM_Node($this->node);
			$nodedm->delete(vBCms_DM_Node::MOVE_ROOT);

			$finishurl = vBCms_Route_Content::getURL(array('node' => $this->node->getParentURLSegment()));
			$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, $finishurl);
			$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'page_deleted'));
		}
		else
		{
			// get the delete view
			$deleteview = new vB_View('vbcms_delete_form');

			// add confirmation message
			$deleteview->confirmation = new vB_Phrase('vbcms', 'delete_page_confirmation_message');

			// item id to ensure form is submitted to us
			$this->addPostId($deleteview);

			// add form to the html replacement output
			$view->setContent($deleteview);

			// send the view
			// TODO: update overlay handler to accept an empty status
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, '&nbsp;');
		}

		return $view->render(true);
	}


	/**
	 * Main entry point for the controller.
	 *
	 * @return string							- The final page output
	 */
	public function actionPublishNode()
	{
		// Create AJAX view for html replacement
		$view = new vB_View_AJAXHTML('cms_publish_view');
		$view->title = new vB_Phrase('vbcms', 'publishing_page');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do' => vB_Input::TYPE_STR,
			'publishdate' => vB_Input::TYPE_UNIXTIME
		));

		if ((vB::$vbulletin->GPC['do'] == 'save') AND $this->verifyPostId())
		{
			$publishdate = vB::$vbulletin->GPC['publishdate'];

			$nodedm = new vBCms_DM_Node();
			$nodedm->setExisting($this->node);
			$nodedm->set('publishdate', vB::$vbulletin->GPC['publishdate']);

			if (!$nodedm->save())
			{
				$view->addErrors($nodedm->getErrors());
				return $this->saveError($view, 'Node DM save failed');
			}

			//We need to see if we have a content node to index.
			$contenttypeid = $this->node->getContenttypeId();
			$index_controller = vB_Search_Core::get_instance()->get_index_controller_by_id($contenttypeid);

			if (!($index_controller instanceof vb_Search_Indexcontroller_Null))
			{
				$classinfo = vB_Types::instance()->getContentClassFromId($contenttypeid);
				vB_Search_Indexcontroller_Queue::indexQueue($classinfo['package'], $classinfo['class'], 'index', $this->node->getId());
			}

			$published = ($publishdate AND ($publishdate <= TIMENOW));

			if ($published != $this->node->isPublished())
			{
				$finishurl = vBCms_Route_Content::getURL(array('node' => $this->node->getNodeId(), 'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));
				$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, $finishurl);
			}

			$status_phrase = new vB_Phrase('vbcms', $published ? 'page_published' : 'page_unpublished');
			$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, $status_phrase);
		}
		else
		{
			// get the delete view
			$publishview = new vB_View('vbcms_publish_form');

			// add datepicker for date
			$publishview->datepicker = new vB_View_DatePicker();
			$publishview->datepicker->setDate($this->node->getPublishDate());
			$publishview->datepicker->setLabel(new vB_Phrase('vbcms', 'publish_date'));
			$publishview->datepicker->setDateVar('publishdate');

			// item id to ensure form is submitted to us
			$this->addPostId($publishview);

			// add form to the html replacement output
			$view->setContent($publishview);

			// send the view
			// TODO: update overlay handler to accept an empty status
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, '&nbsp;');
		}

		return $view->render(true);
	}

	/**
	 * Rate a node (ajax only)
	 *
	 * @return string
	 */
	public function actionRate()
	{
		global $bootstrap;

		$nodeid = intval($this->node->getNodeId());

		// Load the style
		$bootstrap->force_styleid($this->node->getStyleId());
		$bootstrap->load_style();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'vote' => vB_Input::TYPE_UINT
		));
		$vote = vB::$vbulletin->GPC['vote'];

		if ($vote < 0 OR $vote > 5)
		{
			die;
		}

		$rated = intval(fetch_bbarray_cookie('cms_rate', $nodeid));

		$update = false;
		if (vB::$vbulletin->userinfo['userid'])
		{
			if ($rating = vB::$db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "cms_rate
				WHERE userid = " . vB::$vbulletin->userinfo['userid'] . "
					AND nodeid = $nodeid
			"))
			{
				if (vB::$vbulletin->options['votechange'])
				{
					if ($vote != $rating['vote'])
					{
						$rateitem = new vBCms_Item_Rate($rating['rateid']);
						$ratedm = new vBCms_DM_Rate($rateitem);
						$ratedm->set('nodeid', $nodeid);
						$ratedm->set('userid', vB::$vbulletin->userinfo['userid']);
						$ratedm->set('vote', intval($vote));
						$ratedm->save();
					}
					$update = true;
				}
			}
			else
			{
				$ratedm = new vBCms_DM_Rate();
				$ratedm->set('nodeid', $nodeid);
				$ratedm->set('userid', vB::$vbulletin->userinfo['userid']);
				$ratedm->set('vote', intval($vote));
				$ratedm->save();

				$update = true;
			}
		}
		else
		{
			// Check for cookie on user's computer for this blogid
			if ($rated AND !vB::$vbulletin->options['votechange'])
			{

			}
			else
			{
				// Check for entry in Database for this Ip Addr/blogid
				if ($rating = vB::$db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "cms_rate
					WHERE ipaddress = '" . vB::$db->escape_string(IPADDRESS) . "'
						AND nodeid = $nodeid
				"))
				{
					if (vB::$vbulletin->options['votechange'])
					{
						if ($vote != $rating['vote'])
						{
							$rateitem = new vBCms_Item_Rate($rating['rateid']);
							$ratedm = new vBCms_DM_Rate($rateitem);
							$ratedm->set('nodeid', $nodeid);
							$ratedm->set('vote', intval($vote));
							$ratedm->save();
						}
						$update = true;
					}
				}
				else
				{
					$ratedm = new vBCms_DM_Rate();
					$ratedm->set('nodeid', $nodeid);
					$ratedm->set('userid', 0);
					$ratedm->set('vote', intval($vote));
					$ratedm->save();

					$update = true;

				}
			}
		}

		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder(vB::$vbulletin, 'text/xml');
		$xml->add_group('threadrating');
		if ($update)
		{
			$node = vB::$db->query_first_slave("
				SELECT ratingtotal, ratingnum
				FROM " . TABLE_PREFIX . "cms_nodeinfo
				WHERE nodeid = $nodeid
			");

			if ($node['ratingnum'] > 0 AND $node['ratingnum'] >= vB::$vbulletin->options['showvotes'])
			{	// Show Voteavg
				$node['ratingavg'] = vb_number_format($node['ratingtotal'] / $node['ratingnum'], 2);
				$node['rating'] = intval(round($node['ratingtotal'] / $node['ratingnum']));
				$xml->add_tag('voteavg', "<img class=\"inlineimg\" src=\"" . vB_Template_Runtime::fetchStyleVar('imgdir_rating') . "/rating-15_$node[rating].png\" alt=\"" . construct_phrase($vbphrase['rating_x_votes_y_average'], $node['ratingnum'], $node['ratingavg']) . "\" border=\"0\" />");
			}
			else
			{
				$xml->add_tag('voteavg', '');
			}

			if (!function_exists('fetch_phrase'))
			{
				require_once(DIR . '/includes/functions_misc.php');
			}
			$xml->add_tag('message', fetch_phrase('redirect_article_rate_add', 'frontredirect', 'redirect_'));
		}
		else	// Already voted error...
		{
			if (!empty($rating['nodeid']))
			{
				set_bbarray_cookie('cms_rate', $rating['nodeid'], $rating['vote'], 1);
			}
			$xml->add_tag('error', fetch_error('article_rate_voted'));
		}
		$xml->close_group();
		$xml->print_xml();

	}



	/*Helpers=======================================================================*/

	/**
	 * Builds the toolbar view for managing the page.
	 *
	 * @param bool $edit_mode					- Whether the user is currently in edit mode
	 * @return vB_View
	 */
	protected function getToolbarView($edit_mode = false)
	{
		global $vbphrase;

		if (!$this->content->canCreate() AND !$this->content->canEdit() AND !$this->content->canPublish())
		{
			return;
		}

		require_once DIR . '/includes/functions_databuild.php';

		fetch_phrase_group('cpcms');
		// Create view
		$view = new vB_View('vbcms_toolbar');
		$view->edit_mode = $edit_mode;
		$view->page_url = vB_Router::getURL();
		$view->access = ($this->content->publicCanView() ?
			$vbphrase['public'] : $vbphrase['private']);

		// Setup a new route to get URLs
		$route = new vBCms_Route_Content();
		$route->node = $this->node->getURLSegment();

		$view->view_url = $route->getCurrentURL(array('action' => vB_Router::getUserAction('vBCms_Controller_Content', 'View')));

		$view->edit_url = $route->getCurrentURL(array('action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage')));
		$view->edit_label = new vB_Phrase('vbcms', 'edit_this_page');

		// New content options
		$view->add_url = $route->getCurrentURL(array('action' => vB_Router::getUserAction('vBCms_Controller_Content', 'AddNode')));
		$view->add_label = new vB_Phrase('vbcms', 'create_new');

		// Get placable contenttypes.  TODO: This should be a method of vB_Types for reuse

		// permissions are different depending on the user's usergroup and the section they are in
		$creatable_content_types_cache_key = 'vbcms_creatable_content_types.ugid_' . vB::$vbulletin->userinfo['usergroupid'] . '_nodeid_' . $this->node->getNodeId();

		if (!($view->contenttypes = vB_Cache::instance()->read($creatable_content_types_cache_key, true, true)))
		{
			$contenttype_collection = new vB_Collection_ContentType();
			$contenttype_collection->filterPlaceable(true);
			$contenttype_collection->filterNonAggregators(true);

			$contenttypes = array();
			$permissionsfrom = $this->content->getPermissionsFrom();
			foreach ($contenttype_collection AS $contenttype)
			{
				$this_type = vBCms_Content::create($contenttype->getPackageClass(), $contenttype->getClass(), 0);

				if ($this_type->canCreateHere($permissionsfrom))
				{
					$title = (string)$contenttype->getTitle();
					$contenttypes[$title] = array(
						'id'    => $contenttype->getId(),
						'title' => $title,
					);
				};
				unset($this_type);
			}
			ksort($contenttypes);
			$view->contenttypes = $contenttypes;
			unset($contenttype_collection, $contenttypes);

			$cache_events = vB_Types::instance()->getContentTypeCacheEvents();
			$cache_events[] = 'cms_permissions_change'; // expire this cache when cms permissions are changed
			vB_Cache::instance()->write($creatable_content_types_cache_key, $view->contenttypes, false, $cache_events);
		}



		// Set the publish state description
		if ($this->node->isPublished())
		{
			$view->publish_status = new vB_Phrase('vbcms', 'page_is_published');
		}
		else if ($this->node->getPendingParentId())
		{
			$pending_title = $this->node->getPendingParentTitle();
			$pending_route = vB_Route::create('vBCms_Route_Content')->getCurrentURL(array('node' => $this->node->getPendingParentId()));

			$view->publish_status = new vB_Phrase('vbcms', 'section_x_not_published', $pending_route, $pending_title);
		}
		else if ($date = $this->node->getPublishDate())
		{
			$date = vbdate(vB::$vbulletin->options['dateformat'], $date, true);
			$view->publish_status = new vB_Phrase('vbcms', 'page_will_be_published_x', $date);
		}
		else
		{
			$view->publish_status = new vB_Phrase('vbcms', 'page_not_published');
		}

		$view->can_publish = $this->content->canPublish();
		$view->can_edit = $this->content->canEdit();
		$view->can_create = $this->content->canCreate();

		// Add postid
		$this->addPostId($view);

		return $view;
	}


	/**
	 * Sets up the XHTML templater.
	 */
	protected function registerXHTMLTemplater()
	{
		// Create the standard vB templater
		$templater = new vB_Templater_vB();

		global $bootstrap;
		$bootstrap->force_styleid($this->node->getStyleId());
		$bootstrap->load_style();
		
		// Register the templater to be used for XHTML
		vB_View::registerTemplater(vB_View::OT_XHTML, $templater);
	}


	/**
	 * Sends an AJAXHTML save failed message.
	 *
	 * @param vB_View $view
	 * @param string $debug_message
	 */
	protected function saveError(vB_View_AJAXHTML $view, $debug_message)
	{
		if ($debug_message)
		{
			$view->addError($debug_message, 'debug');
		}

		$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'save_failed'));

		return $view->render(true);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 29533 $
|| ####################################################################
\*======================================================================*/
