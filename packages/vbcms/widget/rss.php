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
 * Test Widget Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 77258 $
 * @since $Date: 2013-09-02 17:14:45 -0700 (Mon, 02 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_Rss extends vBCms_Widget
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
	protected $class = 'Rss';

	/*** cache lifetime, minutes ****/
	protected $cache_ttl = 5;


	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'            => vB_Input::TYPE_STR,
			'url'           => vB_Input::TYPE_STR,
			'template_name' => vB_Input::TYPE_STR,
			'use_rss_title' => vB_Input::TYPE_BOOL,
			'max_items'		 => vB_Input::TYPE_INT,
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$config['url'] = convert_urlencoded_unicode(vB::$vbulletin->GPC['url']);
			$config['use_rss_title'] = vB::$vbulletin->GPC['use_rss_title'];

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['max_items'])
			{
				$config['max_items'] = vB::$vbulletin->GPC['max_items'];
			}
			$widgetdm = $this->widget->getDM();
			$widgetdm->set('config', $config);

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}

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
				$config['template_name'] = 'vbcms_widget_rss_page';
			}
			// add the config content
			$configview->use_rss_title = $config['use_rss_title'];
			$configview->template_name = $config['template_name'];
			$configview->max_items = $config['max_items'];
			$configview->url = $config['url'] ? htmlspecialchars_uni($config['url']) : $config['url'];

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
		global $vbphrase, $vbulletin;

		// Ensure the model is loaded
		$this->assertWidget();

		// Normalise widget config
		$config = $this->widget->getConfig();

		// Use fallback template name if none configured
		$config['template_name'] = (isset($config['template_name']) AND $config['template_name'])
									? $config['template_name']
									: 'vbcms_widget_rss_page';

		// Sanitize max items
		$config['max_items'] = max(min($config['max_items'], 100), 1);

		// Load RSS
		$rss = array();
		if (!($rss = vB_Cache::instance()->read($this->getHash($this->widget->getId()), false, true)))
		{
			// get feed
			require_once DIR . '/includes/class_rss_poster.php';
			$feed = new vB_RSS_Poster($vbulletin);
			$feed->fetch_xml($config['url']);

			// TODO: Add config values for encoding behaviour
			$feed->parse_xml(false, true, false, true);

			// get rss elements
			if ($rss['items'] = $feed->fetch_normalised_items())
			{
				$rss['title']		= $feed->xml_array['channel']['title'];
				$rss['description']	= $feed->xml_array['channel']['description'];
				$rss['link']		= $feed->xml_array['channel']['link'];

				// check quantity
				if (sizeof($rss['items']) > $config['max_items'])
				{
					$rss['more'] = true;
					$rss['items'] = array_slice($rss['items'], 0, $config['max_items']);
				}

				$rss['url'] = vB::$vbulletin->input->xss_clean_url($config['url']);
			}

			// write cache
			vB_Cache::instance()->write($this->getHash($this->widget->getId()), $rss, $this->cache_ttl);
		}

		// Create view
		$view = new vBCms_View_Widget($config['template_name']);

		if (!$rss['items'])
		{
			$view->setDisplayView(false);
		}

		// Add widget details
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->widget_title = $this->widget->getTitle();

		// Add rss
		$view->addArray($rss, 'rss_');

		// Phrases
		$view->no_items = empty($rss['items']) ? $vbphrase['invalid_data'] : false;

		return $view;
	}


	/**
	 * Gets a cache key
	 * @param int
	 * @param mixed  - Added for PHP 5.4 strict standards compliance 
	 *
	 * @return string
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		if (!$widgetid)
		{
			$widgetid = $this->widget->getId();
		}
		
		if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			$charset = vB::$vbulletin->userinfo['lang_charset'];
		}

		$context = new vB_Context("widget_$widgetid" , array('widgetid' => $widgetid, 'charset' => $charset));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/
