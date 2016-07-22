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
 * vBCms_Widget_Poll
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: poll.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_Poll extends vBCms_Widget
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
	protected $class = 'Poll';

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

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'      => vB_Input::TYPE_STR,
			'days'    => vB_Input::TYPE_UINT,
			'ids'    => vB_Input::TYPE_STR,
			'count'    => vB_Input::TYPE_UINT,
			'forumchoice' => vB_Input::TYPE_ARRAY,
			'childforums' => vB_Input::TYPE_BOOL,
			'template_name'    => vB_Input::TYPE_STR,
			'detail_template' => vB_Input::TYPE_STR
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
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['detail_template'])
			{
				$config['detail_template'] = vB::$vbulletin->GPC['detail_template'];
			}

			if (vB::$vbulletin->GPC_exists['ids'])
			{
				$ids = array_unique(explode(',', vB::$vbulletin->GPC['ids']));
				$cleaned = array();

				foreach ($ids as $id)
				{
					if (intval($id) )
					{
						$cleaned[] = intval($id);
					}
				}
				$ids = implode(',', $cleaned);
				$config['ids'] = $ids ;
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
			//clear the cache for this widget
			vB_Cache::instance()->event('poll_widget_' . $this->widget->getId());

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
			$configview = $this->createView('config');
			require_once DIR . '/includes/functions_databuild.php';
			fetch_phrase_group('search');

			if (!isset($config['template_name']) OR ($config['template_name'] == '') )
			{
				$config['template_name'] = 'vbcms_widget_poll_page';
			}

			if (!isset($config['detail_template']) OR ($config['detail_template'] == '') )
			{
				$config['detail_template'] = 'vbcms_widget_poll_resultdetail';
			}
			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->detail_template = $config['detail_template'];
			$configview->forumchoice_select = $this->getForums($config);
			$configview->childforumschecked = ($config['childforums'] ? 'checked="checked"' : '');
			$configview->count = $config['count'];
			$configview->days = $config['days'];
			$configview->ids = $config['ids'];

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

		// Create view

		$view = $this->makeResults($config);
		$view->widget_title = $this->widget->getTitle();
		return $view;
	}


	/**
	 * This does the actual work of creating the navigation elements. T
	 * We use the existing search functionality. It's already all there, we just need
	 * to
	 *
	 * @return string;
	 */
	private function makeResults($config)
	{
		include_once DIR . '/includes/functions_misc.php';
		$search_core = vB_Search_Core::get_instance();

		$hashkey = $this->getHash($this->widget->getId());
		$data = vB_Cache::instance()->read($hashkey, true, true);
		if (!$data)
		{
			$criteria = $search_core->create_criteria($search_core->get_search_type('vBForum',
				'Post'));
			$contenttypeid = $search_core->get_contenttypeid('vBForum',	'Post');
			$criteria->add_contenttype_filter($contenttypeid);
			$criteria->set_advanced_typeid($contenttypeid);

			if ($config['ids'])
			{
				$criteria->add_group_filter( explode(",", $config['ids']));
				$criteria->set_grouped(vB_Search_Core::GROUP_YES);

			}
			else
			{
				if ($config['forumchoice'])
				{
					$criteria->add_forumid_filter($config['forumchoice'], $config['childforums']);
				}

				if ($config['days'])
				{
					$timelimit = TIMENOW - (86400 * $config['days']);
					$criteria->add_date_filter(vB_Search_Core::OP_GT, $timelimit);
				}

				$criteria->add_filter('pollid', vB_Search_Core::OP_GT, 1, true);

				$search_type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);
				$search_type->add_advanced_search_filters($criteria, vB::$vbulletin);
				$criteria->set_grouped(vB_Search_Core::GROUP_YES);

			}

			//Set the configuration parameters
			if (! intval($config['count']))
			{
				$config['count'] = 5;
			}

			if (intval($config['count']) > 12)
			{
				$config['count'] = 5;
			}

			if (!isset($config['template_name']) OR ($config['template_name'] == ''))
			{
				$config['template_name'] = 'vbcms_widget_poll_page';
			}

			if (!isset($config['detail_template']) OR ($config['detail_template'] == ''))
			{
				$config['detail_template'] = 'vbcms_widget_poll_resultdetail';
			}


			$criteria->set_sort('dateline', 'desc');
			$current_user = new vB_Legacy_CurrentUser();
			$results = vB_Search_Results::create_from_cache($current_user, $criteria);

			if (!$results)
			{
				$results = vB_Search_Results::create_from_criteria($current_user, $criteria);
			}

			$page = $results->get_page(1, $config['count'], 0);

			if (count($page))
			{
				$threads = array();
				foreach ($page as $result)
				{
					$threads[] = $result->get_thread()->get_field('threadid');
				}
				$where = implode(', ', $threads);
				$sql = "SELECT p.pollid, p.question, p.options, p.multiple, p.active, p.voters,  p.votes, p.dateline, p.timeout, t.threadid, t.open, t.forumid, t.title
						FROM " . TABLE_PREFIX . "poll p 
						INNER JOIN " . TABLE_PREFIX . "thread t ON t.pollid = p.pollid 
					 	WHERE t.threadid IN ( " . $where . ");";

				if ($rst = vB::$vbulletin->db->query_read($sql))
				{
					$data = array();
					require_once(DIR . '/includes/class_bbcode_alt.php');
					while ($row = vB::$vbulletin->db->fetch_array($rst))
					{
						$this_item = array();
						$options = explode('|||', $row['options'] );
						$votes = explode('|||', $row['votes'] );
						$totalvotes = 0;
						$canvote = (vB::$vbulletin->userinfo['userid'] == 0 OR !$row['active'] OR !$row['open'] OR 
									!(vB::$vbulletin->userinfo['forumpermissions'][$row['forumid']] & vB::$vbulletin->bf_ugp_forumpermissions['canvote']) OR 
									($row['dateline'] + ($row['timeout'] * 86400) < TIMENOW AND $row['timeout'] != 0)) ? 0 : 1;
						$uservoted = 0;					
						if ($canvote)
						{
							$uservoted = intval(fetch_bbarray_cookie('poll_voted', $row['pollid']));
							if (!$uservoted)
							{
								$pollvotes = vB::$vbulletin->db->query_read_slave("
									SELECT voteoption
									FROM " . TABLE_PREFIX . "pollvote
									WHERE userid = " . vB::$vbulletin->userinfo['userid'] . " AND pollid = $row[pollid]
								");
								if (vB::$vbulletin->db->num_rows($pollvotes) > 0)
								{
									$uservoted = 1;
								}
							}
						}
						for($i = 0; $i < count($votes); $i++)
						{
							$totalvotes += $votes[$i];
						}
						$detail = array();
						$parser = new vB_BbCodeParser_Wysiwyg(vB::$vbulletin, fetch_tag_list('', true), true);
						for ($i = 0; $i < count($options); $i++)
						{
							$this_option = $parser->do_parse($options[$i]);
							if ($votes[$i] <= 0)
							{
								$percent = 0;
							}
							else if ($row['multiple'])
							{
								$percent = ($votes[$i] < $row['voters']) ? $votes[$i] / $row['voters'] * 100 : 100;
							}
							else
							{
								$percent = ($votes[$i] < $totalvotes) ? $votes[$i] / $totalvotes * 100 : 100;
							}

							$detail[] = array(
								'number'		=> $i+1,
								'option'        => $this_option,
								'votes'         => $votes[$i],
								'percent'       => vb_number_format($percent, 2),
								'percentraw'    => $percent,
								'number'        => $i + 1,
								'graphicnumber' => (($i + 1) % 6) + 1,
							);
						}
						$detailview = new vBCms_View_Widget($config['detail_template']);
						$detailview->resultdetail = $detail;
						$canvote = ($canvote AND !$uservoted ? 1 : 0);
						$detailview->canvote	= $canvote;
						$detailview->multiple	= $row['multiple'];
						$detailview->pollid 	= $row['pollid'];
						$this_item['resultdetail'] = $detailview->render();
						$this_item['threadid'] = $row['threadid'];
						$this_item['pollid']   = $row['pollid'];
						$this_item['question'] = $row['question'];
						$this_item['hashkey']  = $hashkey;
						$this_item['canvote']  = $canvote;
						$this_item['title']	   = $row['title'];
						$this_item['totalvotes'] = $row['voters'];
						$data[$row['threadid']] = $this_item;
					}

					if (!isset($config['template_name']) OR ($config['template_name'] == ''))
					{
						$config['template_name'] = 'vbcms_widget_poll_page';
					}
				}
			}

			vB_Cache::instance()->write($hashkey,
			   $data, $this->cache_ttl);
		}
		// Create view
		$view = new vBCms_View_Widget($config['template_name']);
		$view->poll_data = $data;
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		if (empty($view->poll_data))
		{
			$view->setDisplayView(false);
		}

		return $view;
	}


	/**
	 * vBCms_Widget_Poll::getForums()
	 *
	 * @param mixed $config	- array of current configuration for this widget
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
					render_option_template(new vB_Phrase('search', 'search_all_open_forums'), '',
						$haveforum ? '' : 'selected="selected"') .
					render_option_template(new vB_Phrase('search', 'search_subscribed_forums'), 'subscribed') .
					$options .
				 	"</select>\r";
		return $select;

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
