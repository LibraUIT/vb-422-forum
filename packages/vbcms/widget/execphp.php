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
 * @version $Revision: 77258 $
 * @since $Date: 2013-09-02 17:14:45 -0700 (Mon, 02 Sep 2013) $
 * @copyright vBulletin Solutions Inc.
 */

class vBCms_Widget_ExecPhp extends vBCms_Widget
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
	protected $class = 'ExecPhp';

	/*** cache lifetime, minutes ****/
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

		global $vbphrase;

		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'phpcode'    => vB_Input::TYPE_STR,
			'template_name'    => vB_Input::TYPE_STR,
			'cache_ttl'    => vB_Input::TYPE_INT
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());
		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			$config['phpcode'] = convert_urlencoded_unicode(vB::$vbulletin->GPC['phpcode']);

			$widgetdm = $this->widget->getDM();

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}
			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}
			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
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

			vB_Cache::instance()->event($this->package . '_event_' . $this->class . '_' . $this->widget->getId());
			vB_Cache::instance()->cleanNow();

		}
		else
		{
			// add the config content
			$configview = $this->createView('config');


			if (!isset($config['template_name']) OR ($config['template_name'] == '') )
			{
				$config['template_name'] = 'vbcms_widget_execphp_page';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->cache_ttl = $config['cache_ttl'];
			$configview->statichtml = $config['phpcode'] ? $config['phpcode'] : '';

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
		$config = $this->widget->getConfig();
		// Create view
		if (!isset($config['template_name']) OR ($config['template_name'] == '') )
		{
			$config['template_name'] = 'vbcms_widget_execphp_page';
		}


		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $view->widget_title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		$hash = $this->getHash($this->widget->getId());
		$view->output = vB_Cache::instance()->read($hash, true, true);
		if ($view->output)
		{
			return $view;
		}

		$this->assertWidget();

		try
		{
			if (is_demo_mode())
			{
				$view->output = 'PHP Execution not allowed in Demo Mode!';
			}
			else
			{
				$content = eval($config['phpcode']);

				if ((!isset($content) OR empty($content)) AND isset($output) AND !empty($output))
				{
					$content = $output;
				}
				$view->output = $content;
				
				if (intval($config['cache_ttl']) > 0 AND !empty($content))
				{
					vB_Cache::instance()->write($hash,
					   $content, $config['cache_ttl'],
					   array($this->package . '_event_' . $this->class . '_' . $this->widget->getId()));
				}
			}

		}
		catch(Exception $e)
		{
			$view->output = '';

		}

		return $view;
	}

	/**
	 * Return the appropriate hash function. We include userid, because results
	 * will vary by user due to visibility/privilege variations.
	 *
	 * @param integer $widgetid
	 * @param  boolean $nodeid   - Added for PHP 5.4 strict standards compliance
	 * 
	 * @return hash that will identify this widget content for this user
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		if (!$widgetid)
		{
			$widgetid = $this->widget->getId();
		}
		
		$context = new vB_Context("widget_$widgetid" , array( 'widgetid' =>$widgetid,
			'userid' => vB::$vbulletin->userinfo['userid'],
			'sessionurl' => vB::$vbulletin->session->vars['sessionurl']));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/