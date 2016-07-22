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
 * The CMS controller class.
 *
 * The widget handler acts as a specific sub controller for rendering and
 * interacting with widgets.
 *
 * @TODO create a vB_ItemController class and derive both this and vB_Content
 * from it.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28996 $
 * @since $Date: 2009-01-05 08:56:52 +0000 (Mon, 05 Jan 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vBCms_Widget
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * The id of a particular widget being worked on.
	 * The widget item is lazy loaded when it is needed by a method.
	 * @see vBCms_Widget::$widget
	 *
	 * @var int
	 */
	protected $widgetid;

	/**
	 * How long to cache the page data in minutes.
	 *
	 * @var int
	 */
	protected $cache_ttl = 5 ; //minutes, not seconds

	/**
	 * The current widget item.
	 * Methods that require a widget item will default to the currently set widget if it
	 * exists; set the widget item if it is passed to the method, or lazy load the
	 * widget item if vBCms_Widget::$widgetid exists.
	 *
	 * Note that the controller can also use a vBCms_Collection_Widget, and methods
	 * will return an array or collection appropriately.
	 *
	 * @var vBCms_Item_Widget | vBCms_Collection_Widget
	 */
	protected $widget;

	/**
	 * A reference to the current content controller.
	 * @see vBCms_Widget::setContentController()
	 *
	 * @var vBCms_Content
	 */
	protected $content;

	/**
	 * Whether the content supports a special editing UI.
	 * This should not determine if the content supports an inline editing UI or if
	 * the user is allowed to edit content, but instead a specific edit UI that can
	 * be fetched with getEditView().
	 * @see vBCms_Content::getEditView()
	 *
	 * @var bool
	 */
	protected $is_editable;

	/**
	 * Whether the content supports a config UI.
	 * Determines if the content supports a config UI retreivable with getConfigView().
	 * @see vBCms_Content::getConfigView()
	 *
	 * @var bool
	 */
	protected $is_configurable;

	/*Initialisation================================================================*/

	/**
	 * Constructor.
	 *
	 * Note: Widget is optional.  Widget handlers have methods that do not require
	 * a widget to be associated with the handler in order to use them.
	 *
	 * If used, $widget may be passed as the integer id of the widget.  The widget
	 * handler is then responsible for creating the appropriate vBCms_Item_Widget
	 * based on the id when methods are called that require it.
	 *
	 * Alternatively, a vBCms_Item_Widget may be passed if it is already known.
	 *
	 * @param mixed $widget
	 */
	protected function __construct($widget = false)
	{
		if (!$this->package OR !$this->class)
		{
			throw (new vBCms_Exception_Widget('No package or contenttype class defined for widget item \'' . get_class($this) . '\''));
		}

		if ($widget)
		{
			$this->setWidgetItem($widget);
		}
	}


	/**
	 * Factory method for creating a widget.
	 *
	 * @param int $package							- The id of the widget.
	 * @param int $class							- The node that the widget
	 * @param mixed $widgetid							- The id of the widget
	 */
	public static function create($package, $class, $widget)
	{
		$class = $package . '_Widget_' . $class;
		return new $class($widget);
	}


	/**
	 * Sets the current widget item to work with.
	 *
	 * @param vBCms_Item_Widget					- The widget item to set as curren.
	 */
	public function setWidgetItem($widget)
	{
		if (($widget instanceof vBCms_Item_Widget) OR ($widget instanceof vBCms_Collection_Widget) AND ($widget !== $this->widget))
		{
			$this->widget = $widget;
			$this->widgetid = $widget->getId();
		}
		else
		{
			unset($this->widget);
			$this->widgetid = $widget;
		}
	}


	/**
	 * Associates a content controller with the widget.
	 * This is usually the content controller for the content that is being
	 * displayed at the same time as the widget.
	 *
	 * Having a reference to the content controller allows the widget to interact
	 * with the content, making config changes to the content, and basing widget
	 * output on content meta data.
	 *
	 * @param vBCms_Content $content_controller
	 */
	public function setContentController(vBCms_Content $content_controller)
	{
		$this->content = $content_controller;
	}



	/*Item==========================================================================*/

	/**
	 * Ensures the current widget item is instantiated and loaded.
	 *
	 * @param int $load_flags					- The required info needed from the content item
	 */
	protected function loadWidget($load_flags = false)
	{
		$this->assertWidget($load_flags);
		return $this->widget->isValid();
	}


	/**
	 * Ensures that the current content item is instantiated.
	 * This creates the content item without loading anything.
	 */
	protected function assertWidget()
	{
		if (!$this->widget)
		{
			if (!$this->widgetid)
			{
				throw (new vBCms_Exception_Widget('No widgetid given to widget handler \'' . get_class($this) . '\' for loading widget'));
			}

			$this->widget = vBCms_Item_Widget::create($this->package, $this->class, $this->widgetid);
		}

		if (!$this->widget->isValid())
		{
			throw (new vBCms_Exception_Widget('Could not load widget \'' . get_class($this) . '\' with id \'' . htmlspecialchars_uni($this->widgetid) . '\''));
		}
	}



	/*Render========================================================================*/

	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		$this->assertWidget();

		if (!$this->canView())
		{
			throw (new vB_Exception_AccessDenied());
		}

		// Create view
		$view = $this->createView('page');

		// Add the standard view content
		$this->populateViewContent($view);

		return $view;
	}


	/**
	 * Returns the inline edit view for the widget.
	 * Widgets do not have to support this, however if they are editable and
	 * configurable then the page view can be returned in a tool block view, which
	 * the default implementation provides.
	 *
	 * The inline edit view allows the current user to edit the contents of the
	 * widget.  This should only be allowed for users with permission and should
	 * generally be used to edit widget content rather than configuration.
	 *
	 * @return vBCms_View_Widget					- The view result
	 */
	public function getInlineEditView()
	{
		$this->assertWidget();

		// add the tool block
		$view = new vBCms_View_EditBlock('vbcms_edit_block');

		if ($this->canEdit())
		{
			$view->content = $this->getInlineEditBodyView();

			if ($this->is_editable)
			{
				$view->edit_url = vBCms_Route_Content::getURL(array('node' => $this->content->getNodeURLSegment(),
																'action' => vB_Router::getUserAction('vBCms_Controller_Widget', 'Edit')),
																array($this->widget->getId()));
			}

			if ($this->is_configurable)
			{
				$view->config_url = vBCms_Route_Content::getURL(array('node' => $this->content->getNodeURLSegment(),
																		'action' => vB_Router::getUserAction('vBCms_Controller_Widget', 'Config')),
																		array($this->widget->getId()));
			}
		}
		else
		{
			$view->content = $this->getPageView();
		}

		$view->type = new vB_Phrase('vbcms', 'widget');
		$view->typetitle = $this->widget->getTypeTitle();
		$view->title = $this->widget->getTitle();

		return $view;
	}


	/**
	 * Gets the body view for the widget inline edit view.
	 * By default, the standard page view is returned within the tool block view.
	 * Some widgets may offer inline editing in the page view.
	 *
	 * @return vBCms_View_Widget
	 */
	protected function getInlineEditBodyView()
	{
		return $this->getPageView();
	}


	/**
	 * Returns the edit view for the widget.
	 * The edit view is generally a popup form with fields to edit content related
	 * to the widget.  This is usually used where an inline edit is not appropriate.
	 *
	 * Widgets do not have to support this, however, whether they support it must be
	 * defined when the widget is registered on package installation.
	 *
	 * @return vBCms_View_EditWidget			- The view result
	 */
	public function getEditView()
	{
		throw (new vBCms_Exception_Widget('An edit view has been requested for widget \'' . $this->widget->getId() . '\' but no getEditView is defined'));
	}


	/**
	 * Returns the config view for the widget.
	 * If a widget does not support this then it must be defined in the database
	 * definition.
	 *
	 * The config view is generally responsible for displaying a form that allows
	 * the current user to configure the widget.  Whether the widget is being
	 * configured at the instance or node level is dependant on the presence of
	 * $this->nodeid.
	 *
	 * getConfigView() should always check if the user has permission to edit the
	 * config.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		throw (new vBCms_Exception_Widget('Config view requested from widget \'' . htmlspecialchars_uni($this->title) . '\' but getConfigView() is undefined'));
	}


	/**
	 * Enter description here...
	 *
	 */
	public function getConfigEditorView()
	{
		throw (new vBCms_Exception_Widget('Config Editor view requested from widget \'' . htmlspecialchars_uni($this->title) . '\' but getConfigView() is undefined'));
	}


	/**
	 * Creates a content view.
	 * The default method fetches a view based on the required result, package
	 * identifier and content class identifier.  Child classes may want to override
	 * this.  Ths method is also voluntary if the getView methods are overriden.
	 *
	 * @param string $result					- The result identifier for the view
	 * @return vB_View
	 */
	protected function createView($result, $viewtype = false)
	{
		$result = strtolower($this->package . '_widget_' . $this->class . '_' . $result);

		return new vBCms_View_Widget($result);
	}



	/**
	 * Populates the view with details about the widget.
	 *
	 * @param vB_View $view
	 */
	protected function populateViewContent(vB_View $view)
	{
		$this->loadWidget();

		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		$view->id = $this->widget->getId();
		$view->package = $this->widget->getPackage();
		$view->class = $this->widget->getClass();

		$view->page_url = vBCms_Route_Content::getURL();
	}


	/**
	 * Checks if a post request was intended for this item controller.
	 *
	 * @return bool
	 */
	public function verifyPostId()
	{
		define('ADMINHASH', md5(COOKIE_SALT . vB::$vbulletin->userinfo['userid'] . vB::$vbulletin->userinfo['salt']));

		require_once(DIR . '/includes/adminfunctions.php');
		assert_cp_sessionhash();

		vB::$vbulletin->input->clean_array_gpc('p', array(
			'item_type' => vB_Input::TYPE_NOCLEAN,
			'item_class' => vB_Input::TYPE_STR,
			'item_id' => vB_Input::TYPE_NOCLEAN,
			'adminhash' => vB_Input::TYPE_STR
		));

		return ((vB::$vbulletin->GPC['item_type'] == 'widget')
				AND (vB::$vbulletin->GPC['item_class'] == vBCms_Types::instance()->getTypeKey($this->widget->getPackage(), $this->widget->getClass()))
				AND vB::$vbulletin->GPC['item_id'] == $this->widget->getId()
				AND (!defined('ADMINHASH') OR ADMINHASH == vB::$vbulletin->GPC['adminhash'])
				AND (CP_SESSIONHASH AND (!vB::$vbulletin->options['timeoutcontrolpanel'] OR vB::$vbulletin->session->vars['loggedin'])));
	}


	/**
	 * Adds item id info to a view for submitting via post.
	 *
	 * @param vB_View $view
	 */
	protected function addPostId(vB_View $view)
	{
		$view->item_type = 'widget';
		$view->item_class = vBCms_Types::instance()->getTypeKey($this->widget->getPackage(), $this->widget->getClass());
		$view->item_id = $this->widget->getId();

		define('ADMINHASH', md5(COOKIE_SALT . vB::$vbulletin->userinfo['userid'] . vB::$vbulletin->userinfo['salt']));
		$view->adminhash = ADMINHASH;
	}


	/*Permissions===================================================================*/

	/**
	 * Determines whether the current user can view the widget.
	 *
	 * @return bool
	 */
	public function canView()
	{
		return true;
	}


	/**
	 * Determines if the current user can edit the widget.
	 *
	 * @return bool
	 */
	public function canEdit()
	{
		return true;
	}



	/*ClassInteraction================================================================*/

	/**
	 * Gets a collection of widgets.
	 *
	 * @param array int | int $widgetids		- Ids of the widgets to fetch or the nodeid that they belong to
	 * @param int $load_flags					- Any required info prenotification
	 * @param int $nodeid						- NodeId to load additional config for
	 */
	public static function getWidgetCollection($widgetids, $load_flags = false, $nodeid = false)
	{
		// Get the collection
		$widgets = new vBCms_Collection_Widget($widgetids, $load_flags);

		if ($nodeid)
		{
			$widgets->setConfigNode($nodeid);
		}

		return $widgets;
	}

	/**
	 * Gets an array of Widget controllers.
	 * If a node or layout is specified, the widget configs will be loaded.
	 *
	 * @param vBCms_Collection_Widget $widgets	- The widgets to fetch handlers for
	 * @param bool $skip_errors					- Whether to skip widgets that throw exceptions
	 * @param vBCms_Content $content			- Content handler reference to give to widgets
	 * @return array vBCms_Widget				- Array of widget controllers.
	 */
	public static function getWidgetControllers(vBCms_Collection_Widget $widgets, $skip_errors = true, vBCms_Content $content = null)
	{
		$controllers = array();

		if (!$widgets->isValid())
		{
			return $controllers;
		}

		foreach ($widgets AS $id => $widget)
		{
			try
			{
				$controller = self::create($widget->getPackage(), $widget->getClass(), $widget);

				if ($content)
				{
					$controller->setContentController($content);
				}
			}
			catch (vB_Exception $e)
			{
				if (!$skip_errors OR $e->isCritical())
				{
					throw ($e);
				}

				continue;
			}

			$controllers[$widget->getId()] = $controller;
		}

		return $controllers;
	}


	/**** This function creates the hash to store the cached information
	 *
	 * @param int
	 * @param mixed - Added for PHP 5.4 strict standards compliance 
	 *
	 * @return string
	 ****/
	protected function getHash($widgetid = false, $nodeid = false)
	{
		if (!$widgetid)
		{
			$widgetid = $this->widget->getId();
		}
		$context = new vB_Context("widget_$widgetid" , array('widgetid' => $widgetid));
		return strval($context);
	}


	/**** This creates a standard cache event string
	 *
	 * @return string
	 ****/
	protected function getCacheEvent()
	{
		$context =new vB_Context('widget_' . $this->widget->getId() , array('widgetid' => $this->widget->getId()));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28996 $
|| ####################################################################
\*======================================================================*/