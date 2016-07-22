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
 * vBCms_Widget_Searchwidget
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: searchwidget.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_Searchwidget extends vBCms_Widget
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
	protected $class = 'Searchwidget';

	/** The list of template names by content type ***/
	protected $template_names = array();

	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView()
	{
		global $vbphrase;
		$this->assertWidget();
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		fetch_phrase_group('contenttypes');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_UINT,
			'days' => vB_Input::TYPE_UINT,
			'count' => vB_Input::TYPE_UINT,
			'rb_type' => vB_Input::TYPE_UINT,
			'username' => vB_Input::TYPE_STR,
			'friends' => vB_Input::TYPE_BOOL,
			'childforums' => vB_Input::TYPE_BOOL,
			'keywords' => vB_Input::TYPE_STR,
			'template_name'  => vB_Input::TYPE_STR,
			'contenttypeid'   => vB_Input::TYPE_UINT,
			'group_text' =>  vB_Input::TYPE_STR,
			'forumchoice' =>  vB_Input::TYPE_ARRAY,
			'cat' =>  vB_Input::TYPE_ARRAY,
			'prefixchoice' =>  vB_Input::TYPE_ARRAY,
			'srch_tag_text' => vB_Input::TYPE_STR
			));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());

		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}

			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] =  vB::$vbulletin->GPC['count'];
			}

			$config['username'] = vB::$vbulletin->GPC_exists['username']?
				convert_urlencoded_unicode(vB::$vbulletin->GPC['username']) : null;

			$config['friends'] =  vB::$vbulletin->GPC_exists['friends'];
			$config['childforums'] =  vB::$vbulletin->GPC_exists['childforums'];


			$config['keywords'] =  convert_urlencoded_unicode(vB::$vbulletin->GPC['keywords']);

			//the contenttype array gets special handling.
			$type_info = array() ;

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

			$config['group'] = vB::$vbulletin->GPC_exists['group_text']?
				convert_urlencoded_unicode(vB::$vbulletin->GPC['group_text']) : null;

			$config['forumchoice'] = vB::$vbulletin->GPC_exists['forumchoice']?
				vB::$vbulletin->GPC['forumchoice'] : null;

			$config['cat'] = vB::$vbulletin->GPC_exists['cat']?
				vB::$vbulletin->GPC['cat'] : null;

			$config['prefixchoice'] = vB::$vbulletin->GPC_exists['prefixchoice']?
				vB::$vbulletin->GPC['prefixchoice'] : null;

			$config['tag'] = vB::$vbulletin->GPC_exists['srch_tag_text']?
				convert_urlencoded_unicode(vB::$vbulletin->GPC['srch_tag_text']) : null;

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

			// Contenttype multiselect
			$contenttypes = array() ;
			require_once DIR . '/includes/functions_databuild.php';
			fetch_phrase_group('search');

			foreach (vB_Search_Core::get_instance()->get_indexed_types() as $type)
			{
				$phrasekey = 'contenttype_' . strtolower($type['package']) . '_' . strtolower($type['class']);
				$contenttypes[$type['contenttypeid']] = array('name' => $vbphrase[$phrasekey] ,
					'contenttypeid' => $type['contenttypeid'],
					'template' => ((intval($type['contenttypeid']) == intval($config['contenttypeid'])) and
								isset($config['template'])) ?
							$config['template'] : 'vbcms_searchresult_' . strtolower($type['class']),
					'checked' => intval($type['contenttypeid']) == intval($config['contenttypeid']) ? 'checked="checked"' : '')  ;
			}

			$configview->contenttypes = $contenttypes;
			$configview->cache_ttl = (isset($config['cache_ttl']) ? $config['cache_ttl'] : 5);
			$configview->days = (isset($config['days']) ? $config['days'] : 14);
			$configview->count = $config['count'];
			$configview->username = $config['username'] ? $config['username'] : '';
			$configview->friendschecked = ($config['friends'] ? 'checked="checked"' : '');
			$configview->childforumschecked = ($config['childforums'] ? 'checked="checked"' : '');
			$configview->keywords = $config['keywords'];
			$configview->template_name = ($config['template_name'] ? $config['template_name'] : 'vbcms_widget_searchwidget_page');
			$configview->group = $config['group'];
			$configview->tag = $config['tag'];
			$configview->type_select = $select_types;
			$configview->cat_select = $this->getGroupCategories($config);
			$configview->prefixchoice_select = $this->getPrefixes($config);
			$configview->forumchoice_select = $this->getForums($config);

			// item id to ensure form is submitted to us
			$this->addPostId($configview);

			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}

	/**
	 * This lists the available prefixes for the select list
	 *
	 * @param mixed $config - array of current configuration for this widget
	 * @return
	 */
	private function getPrefixes($config)
	{
		require_once DIR . '/vb/search/searchtools.php';
		return vB_Search_Searchtools::showPrefixes('prefixchoice',
			 'class="bginput"', $config['prefixchoice'], 4);
	}

	/**
	 * This lists the forums for the select list
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

	/**
	 * This list the group categories for search
	 *
	 * @param mixed $config - array of current configuration for this widget
	 * @return
	 */
	private function getGroupCategories($config)
	{
		global $vbphrase;
		require_once DIR . '/includes/functions_socialgroup.php';

		$categories = fetch_socialgroup_category_options();
		$category_options = '<option value="">' . $vbphrase['any_category'] . '</option>';

		if (! isset($config['cat']))
		{
			$config['cat'] = array();
		}
		else if (! is_array($config['cat']))
		{
			$config['cat'] = array($config['cat']);
		}

		foreach ($categories AS $key => $name)
		{
			$category_options .= "<option value=\"$key\""
				. (in_array($key, $config['cat'])  ? ' selected="selected" ' : '' )
				. " >" . $name['title'] . "</option>\n";
		}
		return $category_options;
	}

	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @param vBCms_Item_Widget					- Optional new widget to work with, or a collection of widgets
	 * @param bool $skip_errors					- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget				- The resolved view, or array of views
	 */
	public function getPageView($widget = null)
	{
		$this->assertWidget();
		$config = $this->widget->getConfig();

		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$hashkey = $this->getHash($this->widget->getId());

		//if we need to hit the database
		$results = $this->getResults($config);

		if (!$results)
		{
			return '';
		}
		$view->result_html = $this->renderResult($config, $results);
		$view->widget_title = $this->widget->getTitle();

		if (!$view->result_html)
		{
			$view->setDisplayView(false);
		}

		return $view;
	}


	/**
	 * This function returns a list of friends of the
	 * current user.
	 *
	 * @param integer $userid
	 * @return array
	 */
	private function getFriends($userid)
	{
		$userids = array();
		if ($rst = vB::$vbulletin->db->query_read("SELECT relationid FROM "
				. TABLE_PREFIX . "userlist WHERE friend='yes' AND userid = $userid"
			))
		{

			while($row = vB::$vbulletin->db->fetch_row($rst))
			{
				$userids[] = $row[0];
			}
		}
		return $userids;
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
			if (empty($cache_data['ids']))
			{
				return false;
			}
			else
			{
				return array('results' => $cache_data['results'], 'criteria' => $cache_data['criteria']);
			}
		}

		//First set the contenttype, if appropriate.
		if (!intval($config['days']))
		{
			$config['days'] = 7;
		}

		if (!intval($config['count']))
		{
			$config['count'] = 10;
		}

		// default ttl
		$config['cache_ttl'] = isset($config['cache_ttl']) ? intval($config['cache_ttl']) : 5;

		// constrain ttl to the range of 1 to 43200
		$config['cache_ttl'] = min(max($config['cache_ttl'], 1), 43200);

		if (isset($config['contenttypeid']) AND $config['contenttypeid'])
		{
			$contenttypeid = $config['contenttypeid'];
			$criteria = $search_core->create_criteria(vB_Search_Core::SEARCH_ADVANCED);
			//It's important to figure whether we need to group or not. We group for blogentries and posts
			$criteria->set_grouped( vB_Search_Core::GROUP_NO);
			$search_type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);
			$criteria->set_advanced_typeid($contenttypeid);
			$criteria->add_contenttype_filter($contenttypeid);

			//Ugly hack, but we need to do this if the content type is blogentry.
			if (vB_Types::instance()->getContentTypeID('vBBlog_BlogEntry') == $contenttypeid )
			{
				vB::$vbulletin->GPC['ignorecomments'] = true;
			}

		}
		else if ((isset($config['forumchoice']) AND count($config['forumchoice']) AND $config['forumchoice'][0])
			OR (isset($config['prefixchoice']) AND count($config['prefixchoice']) AND $config['prefixchoice'][0]))
		{
			$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Post');
			$search_type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);
			$criteria = $search_core->create_criteria($contenttypeid);
			$criteria->set_advanced_typeid($contenttypeid);
			$criteria->add_contenttype_filter($contenttypeid);
			$criteria->set_grouped(vB_Search_Core::GROUP_NO);
		}
		else if ((isset($config['group']) AND $config['group'] != '')
			OR (isset($config['cat']) AND count($config['cat']) AND $config['cat'][0]))
		{
			//We haven't gotten a specific content type, and we won't work without one,
			// so assume we're showing visitor messages.
			$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupMessage');
			$search_type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);
			$criteria = $search_core->create_criteria($contenttypeid);
			$criteria->set_advanced_typeid($contenttypeid);
			$criteria->add_contenttype_filter($contenttypeid);
		}
		else
		{
			return $vbphrase['widget_needs_configuration'];
		}

		//tag applies to several types. Let's do that next.
		if (isset($config['tag']) AND ($config['tag'] != '') )
		{
			$criteria->add_tag_filter($config['tag']);
		}

		//now set the content-type specific items.
		if ((isset($config['forumchoice']) AND count($config['forumchoice']) AND $config['forumchoice'][0])
			 AND $contenttypeid == vB_Types::instance()->getContentTypeID('vBForum_Post'))
		{
			$criteria->add_forumid_filter($config['forumchoice'], $config['childforums']);
		}
		else
		{
			if(vB::$vbulletin->options['vbcmsforumid'] > 0)
			{
				$criteria->add_excludeforumid_filter(vB::$vbulletin->options['vbcmsforumid']);
			}
		}

		if ((isset($config['prefixchoice']) AND count($config['prefixchoice']) AND $config['prefixchoice'][0])
			 AND $contenttypeid == vB_Types::instance()->getContentTypeID('vBForum_Post'))
		{
				$criteria->add_filter('prefix', vB_Search_Core::OP_EQ, $config['prefixchoice'], true);
		}

		if ((isset($config['cat']) AND count($config['cat']) AND $config['cat'][0]) AND $contenttypeid == vB_Types::instance()->getContentTypeID('vBForum_SocialGroup'))
		{
			$criteria->add_filter('sgcategory', vB_Search_Core::OP_EQ, $config['cat'], true);
		}

		if (isset($config['group']) AND ($config['group'] != '') AND $contenttypeid == vB_Types::instance()->getContentTypeID('vBForum_SocialGroupMessage'))
		{
			$criteria->add_filter('socialgroup', vB_Search_Core::OP_EQ, $config['group'], true);
		}
		else if ((isset($config['cat']) AND count($config['cat']) AND $config['cat'][0]) AND $contenttypeid == vB_Types::instance()->getContentTypeID('vBForum_SocialGroupMessage'))
		{
			$criteria->add_filter('sgcategoryid', vB_Search_Core::OP_EQ, $config['cat'], true);
		}

		$search_type->add_advanced_search_filters($criteria, vB::$vbulletin);

		$current_user = new vB_Legacy_CurrentUser();

		$timelimit = TIMENOW - (86400 * $config['days']);
		$criteria->add_date_filter(vB_Search_Core::OP_GT, $timelimit);

		if ($config['username'] AND $config['username'] != '')
		{
			$criteria->add_user_filter($config['username'], true, true);
		}
		else if ($config['friends'])
		{
			$friends = $this->getFriends($current_user->getField('userid'));

			if (count($friends))
			{
				$criteria->add_userid_filter($friends, false);
			}
			else
			{
				return '';
			}
		}

		if ($config['keywords'] AND $config['keywords'] != '')
		{
			$criteria->add_keyword_filter($config['keywords'], false);
		}

		$criteria->set_sort('dateline', 'desc');
		$results = vB_Search_Results::create_from_criteria($current_user, $criteria);

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

		vB_Cache::instance()->write(
			$hashkey,
			array('contenttypeid' => $contenttypeid, 'ids' => $ids, 'criteria' =>$criteria, 'results' => $page_results), 
			$config['cache_ttl'],
			$this->getCacheEvent()
		);

		return array('results' => $page_results, 'criteria' => $criteria);

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
		$criteria = new vB_Search_Criteria();
		//perform render
		$searchbits = '';
		$currentuser = new vB_Legacy_CurrentUser();
		foreach ($results['results'] as $item)
		{
			$searchbit = $item->render($currentuser, $results['criteria'],
				$config['template']);

			$searchbits .= $searchbit;
		}
		return $searchbits;
	}
	/**
	 * return the proper hash function for caching. We include the userid
	 * because the results may vary by user.
	 *
	 * @param integer $widgetid
	 * @param mixed  - Added for PHP 5.4 strict standards compliance 
	 * 
	 * @return hash that will identify this widget content for this user
	 */
	protected function getHash($widgetid = false, $nodeid = false)
	{
		$context = new vB_Context('widget_'.$this->widget->getId(), array( 'widgetid' =>$this->widget->getId(),
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
