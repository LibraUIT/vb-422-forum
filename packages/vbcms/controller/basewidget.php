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
 * CMS Widget Page Controller
 * Handles AJAX responses for configuring a widget instance.
 * Note: Frontend widget confirguration and editing is handled with
 * vBCms_Controller_Content.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29171 $
 * @since $Date: 2009-01-19 02:05:50 +0000 (Mon, 19 Jan 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Controller_BaseWidget extends vB_Controller
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
	protected $class = 'BaseWidget';

	/**
	 * The requested action
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * The widget that we are working with.
	 *
	 * @var vBCms_Widget
	 */
	protected $widget;

	/**
	 * The node that the widget is on.
	 *
	 * @var vBCms_Node
	 */
	protected $node;



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 * The constructor grabs the requested node segment and parameters.
	 *
	 * @param array mixed $parameters			- User requested parameters.
	 */
	public function __construct($parameters, $action = false)
	{
		parent::__construct($parameters, $action);

		// Evaluate the node that we're working with
		if (!($this->action = vB_Router::getSegment('action')))
		{
			// TODO: shouldn't throw 404's on construction, only in getResponse()
			throw (new vB_Exception_404());
		}

		if (!($this->widget = vB_Router::getSegment('widget'))
			OR !intval($this->widget))
		{
			throw (new vB_Exception_404());
		}

		$this->node = intval(vB_Router::getSegment('node'));

		$this->initialize();
	}


	/**
	 * Initialisation.
	 * Initialises the view, templaters and all other necessary objects for
	 * successfully creating the response.
	 */
	protected function initialize()
	{
		// Get Widget that we're configuring
		$widgets = vBCms_Widget::getWidgetCollection(array($this->widget), vBCms_Item_Widget::INFO_CONFIG, $this->node);
		$widgets = vBCms_Widget::getWidgetControllers($widgets, true);

		if (!isset($this->widget) OR !isset($widgets[$this->widget]))
		{
			throw (new vB_Exception_404());
		}

		$this->widget = $widgets[$this->widget];

		// Ensure node is valid
		if ($this->node)
		{
			$this->node = new vBCms_Item_Content($this->node);

			if (!$this->node->isValid())
			{
				throw (new vB_Exception_404());
			}
		}

		// Setup the templater for xhtml
		vB_View::registerTemplater(vB_View::OT_XHTML, new vB_Templater_vB());
	}


	/**
	 * Authorise the current user for the current action.
	 */
	protected function authorizeAction()
	{
		if (! isset(vB::$vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}
		
		if (!(vB::$vbulletin->userinfo['permissions']['cms']['admin']))
		{
			throw (new vB_Exception_AccessDenied());
		}
	}



	/*Response======================================================================*/

	/**
	 * Main entry point for the controller.
	 * Performs all necessary controller related tasks to evaluate, render and
	 * return the page output.
	 *
	 * @return string							- The final page output
	 */
	public function getResponse()
	{
		$this->authorizeAction();

		switch ($this->action)
		{
			case('edit'):
				return $this->widget->getEditView()->render(true);
			case('config'):
				return $this->widget->getConfigView()->render(true);
			case('configeditor'):
				return $this->actionConfigEditor();
			default:
				return '';
		}
	}



	/*Actions=======================================================================*/

	/**
	 * Gets the config editor page.
	 *
	 * @return vB_View
	 */
	public function actionConfigEditor()
	{
		// Create the page view
		$view = new vB_View_Page('vbcms_editor_page');

		$view->editor_view = $this->widget->getConfigEditorView();
		$view->setBreadcrumbInfo(array());

		vB::$vbulletin->debug = false; // can't return debug info

		// Render view and return
		return $view;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 29171 $
|| ####################################################################
\*======================================================================*/