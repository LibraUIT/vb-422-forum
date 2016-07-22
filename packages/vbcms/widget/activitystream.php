<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©
 * 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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
 * @version $Revision: 58956 $
 * @since $Date: 2012-02-10 17:31:19 -0800 (Fri, 10 Feb 2012) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_ActivityStream extends vBCms_Widget
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
	protected $class = 'ActivityStream';

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
			'do'             => vB_Input::TYPE_STR,
			'activitystream_limit'  => vB_Input::TYPE_UINT,
			'activitystream_date'   => vB_Input::TYPE_UINT,
			'activitystream_sort'   => vB_Input::TYPE_UINT,
			'activitystream_filter' => vB_Input::TYPE_UINT,
			'cache_ttl'             => vB_Input::TYPE_UINT,
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$config['activitystream_limit'] = max(min(vB::$vbulletin->GPC['activitystream_limit'], 200), 1);
			$config['activitystream_date'] = max(min(vB::$vbulletin->GPC['activitystream_date'], 3), 0);
			$config['activitystream_sort'] = max(min(vB::$vbulletin->GPC['activitystream_sort'], 1), 0);
			$config['activitystream_filter'] = max(min(vB::$vbulletin->GPC['activitystream_filter'], 5), 0);
			$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];

			$widgetdm = $this->widget->getDM();
			$widgetdm->set('config', $config);

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}

			$widgetdm->save();
			vB::$db->query_write("DELETE FROM " . TABLE_PREFIX . "cache WHERE cacheid LIKE 'widget_{$this->widget->getId()}.%'");

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

			// add the config content
			$configview->activitystream_limit = !$config['activitystream_limit'] ? 5 : $config['activitystream_limit'];
			$configview->activitystream_date_selected = array(intval($config['activitystream_date']) => 'checked="checked"');
			$configview->activitystream_sort_selected = array(intval($config['activitystream_sort']) => 'checked="checked"');
			$configview->activitystream_filter_selected = array(intval($config['activitystream_filter']) => 'checked="checked"');
			$configview->cache_ttl = intval($config['cache_ttl']);

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
		$this->config = $this->widget->getConfig();
		$this->config['activitystream_limit'] = max(min($this->config['activitystream_limit'], 200), 1);
		$this->config['activitystream_date'] = max(min($this->config['activitystream_date'], 3), 0);
		$this->config['activitystream_sort'] = max(min($this->config['activitystream_sort'], 1), 0);
		$this->config['activitystream_filter'] = max(min($this->config['activitystream_filter'], 5), 0);
		$this->config['cache_ttl'] = intval($this->config['cache_ttl']);

		// Create view
		$view = new vBCms_View_Widget('vbcms_widget_activitystream_page');

		// Add widget details
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->widget_title = $this->widget->getTitle();

		if ($this->config['cache_ttl'])
		{
			$hashkey = $this->getHash();
			$stream = vB_Cache::instance()->read($hashkey);
			if (!$stream)
			{
				$stream = $this->getStream();
				vB_Cache::instance()->write($hashkey, $stream, $this->config['cache_ttl'], 'activitystream_updated');
			}
		}
		else
		{
			$stream = $this->getStream();
		}

		if (!$stream)
		{
			$view->setDisplayView(false);
		}
		$view->stream = $stream;
		return $view;
	}

	/*
	 * Fetch the stream data!
	 */
	private function getStream()
	{
		global $vbphrase;

		fetch_phrase_group('activitystream');

		$activity = new vB_ActivityStream_View_Block($vbphrase);
		return $activity->process($this->config);
	}

	/**
	 * Gets a cache key - each user has a cache due to complicated permissions
	 * 	 
	 * @param  boolean $widgetid - Added for PHP 5.4 strict standards compliance
	 * @param  boolean $nodeid   - Added for PHP 5.4 strict standards compliance
	 *
	 * @return string
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_' . $this->widget->getId(),
			array(
				'widgetid' => $this->widget->getId(),
				'userid'   => vB::$vbulletin->userinfo['userid']
			)
		);
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 58956 $
|| ####################################################################
\*======================================================================*/
