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
 * vBCms_Widget_Recent
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: recent.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_Recent extends vBCms_Widget
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
	protected $class = 'Recent';

	/*** this widget's configuration settings ****/
	protected $config;

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
		global $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');

		$this->assertWidget();

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'days'    => vB_Input::TYPE_UINT,
			'recent_type'    => vB_Input::TYPE_STR,
			'count'    => vB_Input::TYPE_UINT,
			'forumchoice' => vB_Input::TYPE_ARRAY,
			'template_name' => vB_Input::TYPE_STR,
			'min_replies'   => vB_Input::TYPE_UINT,
			'main_template' => vB_Input::TYPE_STR,
			'childforums' => vB_Input::TYPE_BOOL
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

			if (vB::$vbulletin->GPC_exists['min_replies'])
			{
				$config['min_replies'] =  vB::$vbulletin->GPC['min_replies'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] =  vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['recent_type'])
			{
				$config['recent_type'] =  vB::$vbulletin->GPC['recent_type'];
			}

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] =  vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['main_template'])
			{
				$config['main_template'] =  vB::$vbulletin->GPC['main_template'];
			}

			if (vB::$vbulletin->GPC_exists['forumchoice'])
			{
				$config['forumchoice'] =  vB::$vbulletin->GPC['forumchoice'];
			}

			$config['childforums'] =  vB::$vbulletin->GPC_exists['childforums'];

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
				vB_Cache::instance()->event($this->getCacheEvent());
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
			$configview = $this->createView('config');
			require_once DIR . '/includes/functions_databuild.php';
			fetch_phrase_group('search');

			$configview->forumchoice_select = $this->getForums($config);
			$configview->childforumschecked = ($config['childforums'] ? 'checked="checked"' : '');
			$configview->count = $config['count'];
			$configview->template_name = (isset($config['template_name']) ? $config['template_name'] : 'vbcms_searchresult_thread');
			$configview->main_template = (isset($config['main_template']) ? $config['main_template'] : 'vbcms_widget_recent_page');
			$configview->min_replies = $config['min_replies'];
			$configview->days = $config['days'];
			$typeselected = array();
			$recent_typeselected[0]= ($config['recent_type'] == 'active' ? 'checked="checked"' : '');
			$recent_typeselected[1]= ($config['recent_type'] == 'recent' ? 'checked="checked"' : '');
			$recent_typeselected[2]= ($config['recent_type'] == 'viewed' ? 'checked="checked"' : '');
			$recent_typeselected[3]= ($config['recent_type'] == 'mostrated' ? 'checked="checked"' : '');
			$recent_typeselected[4]= ($config['recent_type'] == 'bestrated' ? 'checked="checked"' : '');
			$configview->recent_typeselected = $recent_typeselected;

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
	 * @param bool $skip_errors					- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView()
	{
		include_once DIR . '/includes/functions_search.php';
		$this->assertWidget();
		$config = $this->widget->getConfig();


		if (!isset($config['main_template']) OR ($config['main_template'] == '') )
		{
			$config['main_template'] = 'vbcms_widget_recent_page';
		}

		$view = new vBCms_View_Widget($config['main_template']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->widget_title = $this->widget->getTitle();

		$view->result_html = $this->makeResults($config);

		if (!$view->result_html)
		{
			$view->setDisplayView(false);
		}


		return $view;
	}

	/**
	 * This does the actual work of creating the navigation elements. This needs some
	 * styling, but we'll do that later.
	 * We use the existing search functionality. It's already all there, we just need
	 * to
	 *
	 * @return string;
	 */
	protected function makeResults($config)
	{
		include_once DIR . '/includes/functions_misc.php';
		$search_core = vB_Search_Core::get_instance();

		if (! intval($config['days']))
		{
			$config['days'] = 1;
		}

		$timelimit = TIMENOW - (86400 * $config['days']);

		if ($config['recent_type'] == 'mostrated')
		{
			vB::$vbulletin->GPC_exists['votenum'] = true;
			vB::$vbulletin->GPC['votenum'] = 1;
		}
		else if ($config['recent_type'] == 'bestrated')
		{
			vB::$vbulletin->GPC_exists['votetotal'] = true;
			vB::$vbulletin->GPC['votetotal'] = 1;
		}

		$hashkey = $this->getHash($this->widget->getId());
		$page = vB_Cache::instance()->read($hashkey, true, true);

		$current_user = new vB_Legacy_CurrentUser();
		$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_ADVANCED);
		$contenttypeid = $search_core->get_contenttypeid('vBForum',	'Post');
		$criteria->add_contenttype_filter($contenttypeid);
		$criteria->set_advanced_typeid($contenttypeid);

		if (intval($config['min_replies']) > 0)
		{
			$criteria->add_filter('replycount', vB_Search_Core::OP_GT, $config['min_replies'], true);
		}

		if ($config['forumchoice'])
		{
			$criteria->add_forumid_filter($config['forumchoice'], $config['childforums']);
		}
		$criteria->set_grouped(vB_Search_Core::GROUP_YES);

		if (! $page)
		{
			$search_type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);
			$search_type->add_advanced_search_filters($criteria, vB::$vbulletin);

			$config['count'] = min(max(intval($config['count']),1), 20);

			switch($config['recent_type'])
				//We'll make 'active' the default.
			{
				case 'recent' :
					$criteria->set_sort('lastpost', 'desc');
					$criteria->add_filter('lastpost', vB_Search_Core::OP_GT, $timelimit, true );
					break;
				case 'viewed' :
					$criteria->set_sort('views', 'desc');
					$criteria->add_filter('lastpost', vB_Search_Core::OP_GT, $timelimit, true );
					break;
				case 'most' :
					$criteria->set_sort('votenum', 'desc');
					$criteria->add_filter('votenum', vB_Search_Core::OP_GT, 1, true );
					break;
				case 'best' :
					$criteria->set_sort('votetotal', 'desc');
					$criteria->add_filter('votetotal', vB_Search_Core::OP_GT, 1, true );
					break;
				default :
					$criteria->set_sort('replycount', 'desc');
					$criteria->add_date_filter(vB_Search_Core::OP_GT, $timelimit);
					break;
			} // switch

			$results = vB_Search_Results::create_from_cache($current_user, $criteria);

			if (!$results)
			{
				$results = vB_Search_Results::create_from_criteria($current_user, $criteria);
			}
			$page = $results->get_page(1, $config['count'], 0);
			vB_Cache::instance()->write($hashkey,
			   $page, $this->cache_ttl, $this->getCacheEvent());
		}

		return $this->renderResult($config, $page, $criteria, $current_user);
	}

	/**
	 * This function makes a select list of forums
	 *
	 @param mixed $config - array of current configuration for this widget
	 * @return
	 */
	private function getForums($config, $name = 'forumchoice')
	{
		global $vbulletin, $vbphrase, $show;
		require_once DIR . '/includes/functions_search.php';

		//this will fill out $searchforumids as well as set the depth param in $vbulletin->forumcache
		global $searchforumids;
		fetch_search_forumids_array();


		$options = "";
		foreach ($searchforumids AS $forumid)
		{
			$forum = $vbulletin->forumcache["$forumid"];

			if (trim($forum['link']))
			{
				continue;
			}

			$optionvalue = $forumid;
			$optiontitle = "$forum[depthmark] $forum[title_clean]";

			if (!($vbulletin->userinfo['forumpermissions'][$forumid] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$optiontitle .= '*';
				$show['cantsearchposts'] = true;
			}

			$optionselected = '';

			if ($config['forumchoice'] AND in_array($forumid, $config['forumchoice']))
			{
				$optionselected = 'selected="selected"';
				$haveforum = true;
			}

			require_once DIR . '/includes/adminfunctions.php';
			$options .= render_option_template(construct_depth_mark($forum['depth'], '--') . ' ' . $optiontitle, $forumid, $optionselected);
		}

		$select = "<select name=\"" .$name."[]\" multiple=\"multiple\" size=\"6\" $style_string>\n" .
					render_option_template($vbphrase['search_all_open_forums'], '',
						$haveforum ? '' : 'selected="selected"') .
					render_option_template($vbphrase['search_subscribed_forums'], 'subscribed') .
					$options .
				 	"</select>\r";
		return $select;

	}

	/***** This function actually renders the results
	 *
	 * @param array  holds the configuration values
	 *
	 * @param array  result of sql query
	 *
	 * @return string
	 *****/
	private function renderResult($config, $page, $criteria, $current_user)
	{
		//None of the search result renderers do this right. Instead
		// we need two templates- one for the header and one for each row
		if (count($page))
		{
			if (!$config['template_name'] OR ($config['template_name'] == ''))
			{
				$config['template_name'] = 'vbcms_searchresult_thread';
			}

			$result_html = '';
			foreach ($page as $item)
			{
				$result_html .= $item->render($current_user, $criteria, $config['template_name']);
			}
			return $result_html;

		}
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
			'usergroup' => vB::$vbulletin->userinfo['usergroupid'],
			'membergroupids' => vB::$vbulletin->userinfo['membergroupids']));

		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/
