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
 * @version $Revision: 42666 $
 * @since $Date: 2011-04-05 15:17:42 -0700 (Tue, 05 Apr 2011) $
 * @copyright vBulletin Solutions Inc.
 */

class vBCms_Widget_TagCloud extends vBCms_Widget
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
	protected $class = 'TagCloud';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = false;
	
	protected $config;
	
	protected $cache_ttl = 5;



	/*Render========================================================================*/

	/**
	 * Returns the configuration view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      	=> vB_Input::TYPE_STR,
			'type'    	=> vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_INT,
			'template_name' => vB_Input::TYPE_STR
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$widgetdm = $this->widget->getDM();

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}
			
			$config['type'] = (vB::$vbulletin->GPC_exists['type'] AND (vB::$vbulletin->GPC['type'] == 'search') ) ? 'search' : 'usage';
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
				$config['template_name'] = 'vbcms_widget_tagcloud_page';
			}
			
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->type = ($config['type'] == 'search' ) ? 'search' : 'usage';
			$configview->cache_ttl = intval($config['cache_ttl']) ? $config['cache_ttl'] : 5;

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

		$this->config = $this->widget->getConfig();
		// Create view
		if (!isset($this->config['template_name']) OR ($this->config['template_name'] == '') )
		{
			$this->config['template_name'] = 'vbcms_widget_tagcloud_page';
		}

		// Create view
		$view = new vBCms_View_Widget($this->config['template_name']);
		$view->class = $this->widget->getClass();
		$view->widget_title = $this->widget->getTitle();
		$view->tagcloud = $this->getTagCloud();
		
		if (empty($view->tagcloud))
		{
				return '';
		}
		return $view;
	}
	
	/** This function creates & caches the tag cloud content.
	*
	*	@return string		tag cloud html
	**/
	protected function getTagCloud()
	{
		require_once DIR . '/includes/functions_search.php';
		$hashkey = $this->getHash();
		$raw_cloud = vB_Cache::instance()->read($hashkey, true, true);
		
		if (! $raw_cloud)
		{
			$raw_cloud = prepare_tagcloud($this->config['type']);
			vB_Cache::instance()->write($hashkey,
			   $raw_cloud, $this->config['cache_ttl']);
		}
		$cloud_links = prepare_tagcloudlinks($raw_cloud);
		return prepare_tagcloudlinks($cloud_links['links']);
		
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 42666 $
|| ####################################################################
\*======================================================================*/