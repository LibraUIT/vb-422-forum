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
 * vBCms_Widget_myFriends
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: myfriends.php 77408 2013-09-06 20:02:39Z pmarsden $
 * @access public
 */
class vBCms_Widget_myFriends extends vBCms_Widget
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
	protected $class = 'myFriends';

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
		$this->assertWidget();

		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('vbcms');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'days'    => vB_Input::TYPE_UINT,
			'item_id'    => vB_Input::TYPE_UINT,
			'count'    => vB_Input::TYPE_UINT,
			'rb_type'  => vB_Input::TYPE_UINT,
			'template_name'  => vB_Input::TYPE_STR,
			'contenttypeid'   => vB_Input::TYPE_ARRAY
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());

		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] =  vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] =  vB::$vbulletin->GPC['template_name'];
			}

			if ( vB::$vbulletin->GPC_exists['rb_type'] AND intval(vB::$vbulletin->GPC['rb_type']))
			{
				$config['contenttypeid'] = vB::$vbulletin->GPC['rb_type'];
				vB::$vbulletin->input->clean_array_gpc('p', array(
					'template_' .  vB::$vbulletin->GPC['rb_type'] => vB_Input::TYPE_STR));

				$config['template'] =
				(vB::$vbulletin->GPC_exists['template_' . vB::$vbulletin->GPC['rb_type']] ?
				vB::$vbulletin->GPC['template_' . vB::$vbulletin->GPC['rb_type']] :
				'vbcms_searchresult_' . vB_Types::instance()->getPackageClass(vB::$vbulletin->GPC['rb_type']) );
			}
			else
			{
				$config['contenttypeid'] = vB_Types::instance()->getContentTypeID('vBForum_Post');
				$config[ 'template'] =	'vbcms_searchresult_post';
			}

			$widgetdm = $this->widget->getDM();
			$widgetdm->set('config', $config);

			if ($this->content)
			{
				$widgetdm->setConfigNode($this->content->getNodeId());
			}

			$widgetdm->save();

			//clear the cache
			vB_Cache::instance()->event('widget_config_' . $this->widget->getId());
			vB_Cache::instance()->cleanNow();

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
			$contenttypes = array() ;
			require_once DIR . '/includes/functions_databuild.php';
			fetch_phrase_group('search');

			foreach (vB_Search_Core::get_instance()->get_indexed_types() as $type)
			{
				$contenttypes[$type['contenttypeid']] = array('name' => $type['class'],
					'contenttypeid' => $type['contenttypeid'],
					'template' => ((intval($type['contenttypeid']) == intval($config['contenttypeid'])) and
								isset($config['template'])) ?
							$config['template'] : 'vbcms_searchresult_' . strtolower($type['class']),
					'checked' => intval($type['contenttypeid']) == intval($config['contenttypeid']) ? 'checked="checked"' : '')  ;
			}

			$configview->contenttypes = $contenttypes;
			$show_checked = array();

			// Contenttype select
			$select_types = '';
			foreach (vB_Search_Core::get_instance()->get_indexed_types() as $type)
			{
				$contenttypes[$type['contenttypeid']] = array('name' => $type['class'],
					'contenttypeid' => $type['contenttypeid'],
					'template' => ((intval($type['contenttypeid']) == intval($config['contenttypeid'])) and
								isset($config['template'])) ?
							$config['template'] : 'vbcms_searchresult_' . strtolower($type['class']),
					'checked' => intval($type['contenttypeid']) == intval($config['contenttypeid']) ? 'checked="checked"' : '')  ;
			}
			$configview->contenttypes = $contenttypes;

			$configview->count = $config['count'];
			$configview->days = $config['days'];
			$configview->template_name = ($config['template_name'] ? $config['template_name'] : 'vbcms_widget_searchwidget_page');

			// add id to form
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
	 * @param bool $skip_errors					- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		$this->assertWidget();
		$config = $this->widget->getConfig();

		if (!intval(vB::$vbulletin->userinfo['userid']))
		{
			return '';
		}

		// Create view
		$view = new vB_View($config['template_name'] ? $config['template_name'] : 'vbcms_widget_searchwidget_page');
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();

		$results = $this->getResults($config);

		if (!count($results['results']))
		{
				return false;
		}
		$view->friends_html = $this->renderResult($config, $results);

		if (!$view->friends_html)
		{
			$view->setDisplayView(false);
		}

		$view->widget_title = $this->widget->getTitle();

		return $view;
	}


	/***** This function actually renders the results
	 *
	 * @param array  holds the configuration values
	 *
	 * @param array  result of sql query
	 *
	 * @return string
	 *****/
	private function renderResult($config, $results)
	{
		//until we manage to figure out how to handle this
		global $show;
		$show['inlinemod'] = false;
		if (!$results OR empty($results['results']))
		{
			return false;
		}
		//prepare types for render
		$items_by_type = array();
		foreach ($results['results'] as $item)
		{
			$typeid = $item->get_contenttype();

			if ($typeid)
			{
				$items_by_type[$typeid][] =  $item;
			}
		}

		//perform render
		$searchbits = '';
		$user = new vB_Legacy_CurrentUser();
		foreach ($results['results'] as $item)
		{
			$searchbits .= $item->render($user, $results['criteria'], $config['template']);
		}

		return $searchbits;
	}

	/**
	 * This sets up the search parameters, gets the query results,
	 * and renders them
	 *
	 * @param array $config
	 * @return string
	 */
	private function getResults($config)
	{
		include_once DIR . '/includes/functions_misc.php';
		$search_core = vB_Search_Core::get_instance();

		//first see if we can get cached results
		$hashkey = $this->getHash();
		$cache_data = vB_Cache::instance()->read($hashkey, false, false);
		if ($cache_data)
		{

			//If there are no id's, we're done.
			if (empty($cache_data['ids']))
			{
				return false;
			}

			$controller = vB_Search_Core::get_instance()->get_search_type_from_id($config['contenttypeid']);

			if (method_exists($controller, 'create_array'))
			{
				$results = $controller->create_array($cache_data['ids']);
			}
			else if(method_exists($controller, 'create_item'))
			{
				$results = array();
				foreach ($cache_data['ids'] as $resultid)
				{
					$result = $controller->create_item($resultid);
					if ($result)
					{
						$results[] = $result;
					}
				}
			}
			else
			{
				return false;
			}
			return array('results' => $results, 'criteria' => $cache_data['criteria']);
		}

		$rst = vB::$vbulletin->db->query_read("SELECT relationid FROM "
		. TABLE_PREFIX . "userlist WHERE friend='yes' AND userid = "
		. vB::$vbulletin->userinfo['userid']
		);

		if (!$rst)
		{
			return false;
		}
		$userids = array();

		while($row = vB::$vbulletin->db->fetch_row($rst))
		{
			$userids[] = $row[0];
		}

		//If there are no friends there's no friend information.
		if (! count($userids))
		{
			return '';
		}

		if ($config['contenttypeid'] == null)
		{
			$config['contenttypeid']= array();
		}
		else if (!is_array($config['contenttypeid']))
		{
			$config['contenttypeid'] = array($config['contenttypeid']);
		}

		if (!count($userids))
		{
			new vB_Phrase('global', 'your_friends_list_is_empty');
		}

		$criteria = vB_Search_Core::get_instance()->create_criteria(vB_Search_Core::SEARCH_ADVANCED);
		$criteria->add_contenttype_filter($config['contenttypeid']);
		$criteria->set_advanced_typeid($config['contenttypeid']);

		$criteria->add_userid_filter($userids, false);
		$criteria->set_grouped(vB_Search_Core::GROUP_NO);
		$timelimit = TIMENOW - (86400 * $config['days']);
		$criteria->add_date_filter(vB_Search_Core::OP_GT, $timelimit);
		$criteria->set_sort('dateline', 'desc');
		$current_user = new vB_Legacy_CurrentUser();
		$results = vB_Search_Results::create_from_cache($current_user, $criteria);

		if (!$results)
		{
			$results = vB_Search_Results::create_from_criteria($current_user, $criteria);
		}

		if (empty($results))
		{
			return false;
		}

		$page_results = $results->get_page(1, $config['count'], 1);
		//prepare types for render
		$items_by_type = array();
		foreach ($page_results as $item)
		{
			$typeid = $item->get_contenttype();

			if ($typeid)
			{
				$items_by_type[$typeid][] =  $item;
				$ids[] = $item->get_id();
			}
		}

		foreach ($items_by_type as $contenttype => $items)
		{
			$type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttype);
			$type->prepare_render($results->get_user(), $items);

		}

		vB_Cache::instance()->write($hashkey, array('ids' => $ids,
			'criteria' =>$criteria), $this->cache_ttl,
				'widget_config_' . $this->widget->getId());

		return array('results' => $page_results, 'criteria' => $criteria);
	}


	/**
	 * Returns a hash function for caching. Obviously each user must have a unique
	 * widget view.
	 *
	 * @param  mixed $widgetid - Added for PHP 5.4 strict standards compliance
	 * @param  mixed $nodeid   - Added for PHP 5.4 strict standards compliance
	 * 
	 * @return hash that will identify this widget content for this user
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_'.$this->widget->getId() , array('widgetid' => $this->widget->getId(),
			'userid' => vB::$vbulletin->userinfo['userid']));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77408 $
|| ####################################################################
\*======================================================================*/