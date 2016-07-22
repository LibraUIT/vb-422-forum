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
 * @version $Id: recentcmscomments.php 77258 2013-09-03 00:14:45Z pmarsden $
 * @access public
 */
class vBCms_Widget_RecentCmsComments extends vBCms_Widget
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
	protected $class = 'RecentCmsComments';

	/*** this widget's configuration settings ****/
	protected $config;

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
			'do'    => vB_Input::TYPE_STR,
			'days'    => vB_Input::TYPE_UINT,
			'count' => vB_Input::TYPE_UINT,
			'template' => vB_Input::TYPE_STR,
			'inner_template' => vB_Input::TYPE_STR,
			'cache_ttl' => vB_Input::TYPE_UINT,
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

			if (vB::$vbulletin->GPC_exists['template'])
			{
				$config['template'] =  vB::$vbulletin->GPC['template'];
			}

			if (vB::$vbulletin->GPC_exists['inner_template'])
			{
				$config['inner_template'] =  vB::$vbulletin->GPC['inner_template'];
			}

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] =  vB::$vbulletin->GPC['cache_ttl'];
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
			$configview = $this->createView('config');
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$configview->cache_ttl = $config['cache_ttl'];
			if (!isset($config['template']) OR ($config['template'] == '') )
			{
				$config['template'] = 'vbcms_widget_recentcmscomments_page';
			}

			if (!isset($config['inner_template']) OR ($config['inner_template'] == '') )
			{
				$config['inner_template'] = 'vbcms_searchresult_newcomment';
			}

			$configview->template = $config['template'];
			$configview->inner_template = $config['inner_template'];

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

		if (!isset($config['template']) OR ($config['template'] == '') )
		{
			$config['template'] = 'vbcms_widget_recentcmscomments_page';
		}

		if (!isset($config['inner_template']) OR ($config['inner_template'] == '') )
		{
			$config['inner_template'] = 'vbcms_searchresult_newcomment';
		}

		if (!isset($config['days']) OR ($config['days'] == '') )
		{
			$config['days'] = 7;
		}

		if (!isset($config['count']) OR (intval($config['count'])> 20)
				OR (intval($config['count'])== 0 ))
		{
			$config['count'] = 5;
		}

		if (!isset($config['cache_ttl']) OR !intval($config['cache_ttl'])
			OR (intval($config['cache_ttl'])< 5 )
			OR (intval($config['cache_ttl']) > 43200 ))
		{
			$config['cache_ttl'] = 1440;
		}
		$view = new vBCms_View_Widget($config['template']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->widget_title = $this->widget->getTitle();

		if (!$results = vB_Cache::instance()->read($hash = $this->getHash($this->widget->getId()), true, false))
		{
			$results = $this->makeResults($config);
			vB_Cache::instance()->write($hash,
			   $results, $config['cache_ttl'], array('cms_comments_change'));
		}

		$view->result_html = $this->renderResult($config, $results, $criteria, $current_user);

		if (!$results OR !$view->result_html)
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
	private function makeResults($config)
	{
		//Start by generating the sql and executing it.
		//LEFT JOIN users to include Comments of unregistered users
		$sql = "SELECT post.postid, thread.threadid, node.nodeid, info.title,
		  user.username as cms_author, node.userid AS cms_authorid,
		  thread.replycount, node.url, post.userid, user.avatarrevision
			" . (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath, info.creationdate AS dateline,
			NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,
			customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "cms_node AS node
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON node.nodeid = info.nodeid
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON thread.threadid = info.associatedthreadid
			INNER JOIN " . TABLE_PREFIX . "post AS post ON post.threadid = thread.threadid
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX .
			"avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX .
			"customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE node.comments_enabled > 0 AND node.setpublish > 0 AND
			post.postid <> thread.firstpostid AND post.dateline > ".
			(TIMENOW - intval($config['days']) * 86400) . " AND " .
			vBCMS_Permissions::getPermissionString() . " AND thread.visible = 1 AND post.visible = 1
			ORDER BY post.dateline DESC LIMIT 50";
		$rst = vB::$vbulletin->db->query_read($sql);
		$blocked_threads = array();
		$results = array();
		while($record = vB::$vbulletin->db->fetch_array($rst) AND count($results) < $config['count'])
		{
			$results[]= $record;
		}

		return $results;
	}

	function addVariables($this_post)
	{
		$post = array();
		$thread = $this_post->get_thread();
		$forum = $thread->get_forum();
		$post['post_statusicon'] = 'new';
		$post['post_statustitle'] = $vbphrase['unread'];
		$post['postid'] = $this_post->get_field('postid');
		$post['postdateline'] = $this_post->get_field('dateline');
		$post['posttitle'] = htmlspecialchars_decode(vB_Search_Searchtools::stripHtmlTags($this_post->get_display_title()));
		$post['pagetext'] = nl2br($this_post->get_summary(100));
		$post['visible'] = $this_post->get_field('visible');
		$post['attach'] = $this_post->get_field('attach');

		$post['userid'] = $this_post->get_field('userid');

		if ($post['userid'] == 0)
		{
			$post['username'] = $this_post->get_field('username');
		}
		else if ($user = $this_post->get_user() AND $user->has_field('username'))
		{
			$post['username'] = $user->get_field('username');
		}

		$post['threadid'] = $thread->get_field('threadid');
		$post['threadtitle'] = $thread->get_field('title');
		$post['threadiconid'] = $thread->get_field('iconid');
		$post['replycount'] = $thread->get_field('replycount');
		$post['views'] = $thread->get_field('views') > 0 ?
			$thread->get_field('views') : $thread->get_field('replycount') + 1;
		$post['firstpostid'] = $thread->get_field('firstpostid');
		$post['prefixid'] = $thread->get_field('prefixid');
		$post['taglist'] = $thread->get_field('taglist');
		$post['pollid'] = $thread->get_field('pollid');
		$post['sticky'] = $thread->get_field('sticky');
		$post['open'] = $thread->get_field('open');
		$post['lastpost'] = $thread->get_field('lastpost');
		$post['forumid'] = $thread->get_field('forumid');
		$post['thread_visible'] = $thread->get_field('visible');

		$post['forumtitle'] = $forum->get_field('title');

		$post['posticonid'] = $this_post->get_field('iconid');
		$post['allowicons'] = $forum->allow_icons();
		$post['posticonpath'] = $this_post->get_icon_path();
		$post['posticontitle'] = $this_post->get_icon_title();
		$post['posticon'] = $post ['allowicons'] and $post ['posticonpath'];
		$show['deleted'] = false;
		$post['prefixid'] = $thread->get_field('prefixid');
		return $post;

	}

	private function renderResult($config, $results, $criteria, $current_user)
	{
		require_once DIR . "/includes/functions_user.php";
		//None of the search result renderers do this right. Instead
		// we need two templates- one for the header and one for each row
		if (count($results))
		{
			//Here we have something of a dilemma. We need to verify permissions
			// for each post. That requires that we instantiate the object, so we've got
			// two sql calls per object. We could reduce that by instantiating an array, but we
			// still make a second query to get the thread. So I guess we'll just brute-force it.
			$views =	'' ;
			$current_user = new vB_Legacy_CurrentUser();
			$count = 0;
			foreach ($results as $result)
			{
				// title comes in encoded already and gets encoded again in the view
				$result['title'] = unhtmlspecialchars($result['title']);
				$post = vB_Legacy_Post::create_from_id($result['postid']);
				if (!empty($post) AND is_object($post) AND $post->can_view($current_user))
				{
					$view = new vB_View($config['inner_template']);
					$user = vB_Legacy_User::createFromId($post->get_field('userid'));

					if (vB::$vbulletin->options['avatarenabled'])
					{
						$avatar = fetch_avatar_from_record($result, true);
					}

					$view->avatar = $avatar;
					$view->record = $result;
					$view->node_url = vB_Route::create('vBCms_Route_Content', $result['nodeid'] .
						($result['url'] != '' ? '-' . $result['url'] : '') )->getCurrentURL();
					$view->node_title = htmlspecialchars_uni($result['title']);

					// Comment url
					$join_char = strpos($view->node_url,'?') ? '&amp;' : '?';
					$view->comment_url = $view->node_url . $join_char . "postid=" . $post->get_field('postid') . "#comments_" . $post->get_field('postid');

					$view->post = $this->addVariables($post);
					$thread = $post->get_thread();
					$view->threadinfo = array('threadid' => $thread->get_field('threadid'),
						 'title' => $thread->get_field('title'));
					$view->dateformat = vB::$vbulletin->options['dateformat'];
					$view->timeformat = vB::$vbulletin->options['timeformat'];
					$view->dateline =  $post->get_field('dateline');

					$views .= $view->render();
					$count++;
					if ($count >= intval($config['count']))
					{
						break;
					}

				}
			}
			return $views;

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
			'userid' => vB::$vbulletin->userinfo['userid']));
		return strval($context);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 77258 $
|| ####################################################################
\*======================================================================*/
