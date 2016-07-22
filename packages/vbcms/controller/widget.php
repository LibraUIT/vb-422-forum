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
 * CMS Config Content Controller
 * Default View Page Controller for vB CMS
 *
 * @TODO: Ajax detection
 * @TODO: We probably want to roll this up with vBCms_Controller_BaseWidget
 * and make the node segment optional.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29171 $
 * @since $Date: 2009-01-19 02:05:50 +0000 (Mon, 19 Jan 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Controller_Widget extends vBCms_Controller
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
	protected $class = 'ConfigWidget';

	/**
	 * The action definitions for the controller.
	 *
	 * @var array string => bool
	 */
	protected $actions = array('Config' => true);



	/*Response======================================================================*/

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


	/**
	 * Config Widget
	 *
	 * @return string							- The final page output
	 */
	public function actionConfig($widget = false)
	{
		if (!$widget)
		{
			throw (new vB_Exception_404(new vB_Phrase('error', 'page_not_found')));
		}

		// Setup the templater for xhtml
		vB_View::registerTemplater(vB_View::OT_XHTML, new vB_Templater_vB());

		// Get the content controller
		$this->content = vBCms_Content::create($this->node->getPackage(), $this->node->getClass(), $this->node->getContentId());

		// Add the node as content
		$this->content->castFrom($this->node);

		// Get Widget that we're configuring
		$widgets = vBCms_Widget::getWidgetCollection(array($widget), vBCms_Item_Widget::INFO_CONFIG, $this->node->getId());
		$widgets = vBCms_Widget::getWidgetControllers($widgets, true, $this->content);

		if (!isset($widgets[$widget]))
		{
			throw (new vB_Exception_404());
		}

		$widget = $widgets[$widget];

		// Render the content's config view and return
		return $widget->getConfigView()->render(true);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 29171 $
|| ####################################################################
\*======================================================================*/