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
 * Static HTML Widget Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 37602 $
 * @since $Date: 2010-06-18 11:37:15 -0700 (Fri, 18 Jun 2010) $
 * @copyright vBulletin Solutions Inc.
 */

class vBCms_Widget_Static extends vBCms_Widget
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'Static';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = false;



	/*Render========================================================================*/

	/**
	 * Returns the configuration view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'statichtml'    => vB_Input::TYPE_STR,
			'template_name'    => vB_Input::TYPE_STR
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$config['statichtml'] = convert_urlencoded_unicode(vB::$vbulletin->GPC['statichtml']);

			$widgetdm = $this->widget->getDM();

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}
			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}
			$widgetdm->set('config', $config);

			$widgetdm->save();

			if (!$widgetdm->hasErrors())
			{
				if ($this->content)
				{
					$segments = array('node' => $this->content->getNodeURLSegment(),
										'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
					$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, vBCms_Route_Content::getURL($segments));
				}

				$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'configuration_saved'));
			}
			else
			{
				if (vB::$vbulletin->debug)
				{
					$view->addErrors($widgetdm->getErrors());
				}

				// only send a message
				$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'configuration_failed'));
			}
		}
		else
		{
			// add the config content
			$configview = $this->createView('config');


			if (!isset($config['template_name']) OR ($config['template_name'] == '') )
			{
				$config['template_name'] = 'vbcms_widget_static_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];

			$configview->statichtml = $config['statichtml'] ? htmlspecialchars_uni($config['statichtml']) : '';

			// item id to ensure form is submitted to us
			$this->addPostId($configview);

			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}


	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		$this->assertWidget();

		$config = $this->widget->getConfig();
		// Create view
		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_static_page';
		}

		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $view->widget_title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->static_html = $config['statichtml'];

		if (empty($view->static_html))
		{
			$view->setDisplayView(false);
		}

		return $view;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 37602 $
|| ####################################################################
\*======================================================================*/